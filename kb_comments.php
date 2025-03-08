<?php
// kb_comments.php - مدیریت نظرات پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت نظرات
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مدیریت نظرات پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// تغییر وضعیت نظر
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $commentId = (int)$_GET['approve'];
    
    try {
        // تأیید نظر
        $stmt = $pdo->prepare("UPDATE kb_comments c
                              JOIN kb_articles a ON c.article_id = a.id
                              SET c.status = 'approved'
                              WHERE c.id = ? AND a.company_id = ?");
        $stmt->execute([$commentId, $companyId]);
        
        $message = showSuccess('نظر با موفقیت تأیید شد.');
    } catch (PDOException $e) {
        $message = showError('خطا در تأیید نظر: ' . $e->getMessage());
    }
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $commentId = (int)$_GET['reject'];
    
    try {
        // رد نظر
        $stmt = $pdo->prepare("UPDATE kb_comments c
                              JOIN kb_articles a ON c.article_id = a.id
                              SET c.status = 'rejected'
                              WHERE c.id = ? AND a.company_id = ?");
        $stmt->execute([$commentId, $companyId]);
        
        $message = showSuccess('نظر با موفقیت رد شد.');
    } catch (PDOException $e) {
        $message = showError('خطا در رد نظر: ' . $e->getMessage());
    }
}

// حذف نظر
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $commentId = (int)$_GET['delete'];
    
    try {
        // حذف پاسخ‌های نظر ابتدا
        $stmt = $pdo->prepare("DELETE c FROM kb_comments c
                              JOIN kb_articles a ON c.article_id = a.id
                              WHERE c.parent_id = ? AND a.company_id = ?");
        $stmt->execute([$commentId, $companyId]);
        
        // سپس حذف خود نظر
        $stmt = $pdo->prepare("DELETE c FROM kb_comments c
                              JOIN kb_articles a ON c.article_id = a.id
                              WHERE c.id = ? AND a.company_id = ?");
        $stmt->execute([$commentId, $companyId]);
        
        $message = showSuccess('نظر و پاسخ‌های آن با موفقیت حذف شدند.');
    } catch (PDOException $e) {
        $message = showError('خطا در حذف نظر: ' . $e->getMessage());
    }
}

// فیلترهای نظرات
$statusFilter = isset($_GET['status']) ? clean($_GET['status']) : 'all';
$searchQuery = isset($_GET['q']) ? clean($_GET['q']) : '';
$articleFilter = isset($_GET['article']) ? (int)$_GET['article'] : 0;

// صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// دریافت تعداد کل نظرات
$countQuery = "SELECT COUNT(c.id) 
               FROM kb_comments c
               JOIN kb_articles a ON c.article_id = a.id
               WHERE a.company_id = ?";
$countParams = [$companyId];

// اعمال فیلترها در کوئری شمارش
if ($statusFilter !== 'all') {
    $countQuery .= " AND c.status = ?";
    $countParams[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $countQuery .= " AND (c.comment LIKE ? OR c.author_name LIKE ?)";
    $searchLike = "%$searchQuery%";
    $countParams = array_merge($countParams, [$searchLike, $searchLike]);
}

if ($articleFilter > 0) {
    $countQuery .= " AND c.article_id = ?";
    $countParams[] = $articleFilter;
}

$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$totalComments = $stmt->fetchColumn();

// محاسبه تعداد صفحات
$totalPages = ceil($totalComments / $perPage);

// دریافت لیست نظرات
$query = "SELECT c.*, a.title as article_title, a.id as article_id,
          (SELECT COUNT(*) FROM kb_comments WHERE parent_id = c.id) as reply_count
          FROM kb_comments c
          JOIN kb_articles a ON c.article_id = a.id
          WHERE a.company_id = ?";
$params = [$companyId];

// اعمال فیلترها
if ($statusFilter !== 'all') {
    $query .= " AND c.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (c.comment LIKE ? OR c.author_name LIKE ?)";
    $searchLike = "%$searchQuery%";
    $params = array_merge($params, [$searchLike, $searchLike]);
}

if ($articleFilter > 0) {
    $query .= " AND c.article_id = ?";
    $params[] = $articleFilter;
}

// فقط نظرات اصلی را نمایش دهیم (نه پاسخ‌ها)
$query .= " AND c.parent_id IS NULL";

$query .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$perPage, $offset]);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$comments = $stmt->fetchAll();

// دریافت آمار نظرات
$stmt = $pdo->prepare("SELECT 
                     (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.parent_id IS NULL) as total_comments,
                     (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.status = 'pending' AND c.parent_id IS NULL) as pending_comments,
                     (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.status = 'approved' AND c.parent_id IS NULL) as approved_comments,
                     (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.status = 'rejected' AND c.parent_id IS NULL) as rejected_comments,
                     (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.parent_id IS NOT NULL) as reply_comments");
$stmt->execute([$companyId, $companyId, $companyId, $companyId, $companyId]);
$commentStats = $stmt->fetch();

// دریافت لیست مقالات برای فیلتر
$stmt = $pdo->prepare("SELECT a.id, a.title, COUNT(c.id) as comment_count
                      FROM kb_articles a
                      LEFT JOIN kb_comments c ON a.id = c.article_id
                      WHERE a.company_id = ? AND c.id IS NOT NULL
                      GROUP BY a.id
                      ORDER BY comment_count DESC, a.title
                      LIMIT 50");
$stmt->execute([$companyId]);
$articles = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت نظرات پایگاه دانش</h1>
    <a href="kb_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<!-- آمار نظرات -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            کل نظرات
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($commentStats['total_comments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-comments fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            در انتظار تأیید
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($commentStats['pending_comments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            تأیید شده
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($commentStats['approved_comments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            رد شده
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($commentStats['rejected_comments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row">
            <div class="col-md-3 mb-3">
                <label for="status" class="form-label">وضعیت</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>همه وضعیت‌ها</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>در انتظار تأیید</option>
                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>تأیید شده</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                </select>
            </div>
            
            <div class="col-md-3 mb-3">
                <label for="article" class="form-label">مقاله</label>
                <select class="form-select" id="article" name="article">
                    <option value="0">همه مقالات</option>
                    <?php foreach ($articles as $article): ?>
                    <option value="<?php echo $article['id']; ?>" <?php echo $article['id'] == $articleFilter ? 'selected' : ''; ?>>
                        <?php echo mb_substr($article['title'], 0, 40) . (mb_strlen($article['title']) > 40 ? '...' : ''); ?> (<?php echo number_format($article['comment_count']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4 mb-3">
                <label for="q" class="form-label">جستجو</label>
                <input type="text" class="form-control" id="q" name="q" value="<?php echo $searchQuery; ?>" placeholder="جستجو در متن یا نام نویسنده...">
            </div>
            
            <div class="col-md-2 mb-3">
                <label class="form-label d-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> اعمال فیلتر
                </button>
            </div>
        </form>
    </div>
</div>

<!-- لیست نظرات -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">لیست نظرات (<?php echo number_format($totalComments); ?>)</h5>
        <?php if ($statusFilter === 'pending' && !empty($comments)): ?>
        <a href="#" class="btn btn-success btn-sm" id="approveAllBtn">
            <i class="fas fa-check"></i> تأیید همه نظرات در این صفحه
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($comments)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 200px;">نویسنده</th>
                        <th>متن نظر</th>
                        <th style="width: 200px;">مقاله</th>
                        <th style="width: 150px;">تاریخ</th>
                        <th style="width: 100px;">وضعیت</th>
                        <th style="width: 150px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comment): ?>
                    <tr class="<?php echo $comment['status'] === 'pending' ? 'table-warning' : ''; ?>">
                        <td>
                            <strong><?php echo $comment['author_name']; ?></strong>
                            <?php if ($comment['reply_count'] > 0): ?>
                            <span class="badge bg-info ms-1">
                                <?php echo number_format($comment['reply_count']); ?> پاسخ
                            </span>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted"><?php echo $comment['ip_address']; ?></small>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars(mb_substr($comment['comment'], 0, 150) . (mb_strlen($comment['comment']) > 150 ? '...' : ''))); ?></td>
                        <td>
                            <a href="kb_article.php?id=<?php echo $comment['article_id']; ?>" target="_blank">
                                <?php echo mb_substr($comment['article_title'], 0, 30) . (mb_strlen($comment['article_title']) > 30 ? '...' : ''); ?>
                            </a>
                        </td>
                        <td><?php echo formatDate($comment['created_at']); ?></td>
                        <td>
                            <?php 
                            $statusClass = 'secondary';
                            $statusText = 'نامشخص';
                            
                            switch ($comment['status']) {
                                case 'pending':
                                    $statusClass = 'warning';
                                    $statusText = 'در انتظار تأیید';
                                    break;
                                case 'approved':
                                    $statusClass = 'success';
                                    $statusText = 'تأیید شده';
                                    break;
                                case 'rejected':
                                    $statusClass = 'danger';
                                    $statusText = 'رد شده';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?approve=<?php echo $comment['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>" class="btn btn-success" title="تأیید نظر">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="?reject=<?php echo $comment['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>" class="btn btn-warning" title="رد نظر">
                                    <i class="fas fa-times"></i>
                                </a>
                                <a href="#" class="btn btn-info view-comment" data-bs-toggle="modal" data-bs-target="#viewCommentModal" data-comment-id="<?php echo $comment['id']; ?>" data-comment-text="<?php echo htmlspecialchars($comment['comment']); ?>" data-comment-author="<?php echo $comment['author_name']; ?>" data-comment-date="<?php echo formatDate($comment['created_at']); ?>" title="مشاهده کامل">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?delete=<?php echo $comment['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>" class="btn btn-danger" title="حذف" onclick="return confirm('آیا از حذف این نظر و تمام پاسخ‌های آن اطمینان دارید؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>&page=1">ابتدا</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>&page=<?php echo $page - 1; ?>">قبلی</a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>&page=<?php echo $page + 1; ?>">بعدی</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>&page=<?php echo $totalPages; ?>">انتها</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="alert alert-info">
            هیچ نظری یافت نشد.
            <?php if (!empty($searchQuery) || $statusFilter !== 'all' || $articleFilter > 0): ?>
            <p class="mb-0 mt-2">
                <a href="kb_comments.php" class="btn btn-outline-primary">حذف فیلترها</a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- مودال مشاهده کامل نظر -->
<div class="modal fade" id="viewCommentModal" tabindex="-1" aria-labelledby="viewCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCommentModalLabel">مشاهده نظر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="comment-header mb-3">
                    <strong>نویسنده:</strong> <span id="commentAuthor"></span>
                    <br>
                    <strong>تاریخ:</strong> <span id="commentDate"></span>
                </div>
                <div class="comment-content p-3 bg-light rounded">
                    <div id="commentText"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <a href="#" class="btn btn-success" id="approveBtn">
                    <i class="fas fa-check"></i> تأیید نظر
                </a>
                <a href="#" class="btn btn-warning" id="rejectBtn">
                    <i class="fas fa-times"></i> رد نظر
                </a>
                <a href="#" class="btn btn-danger" id="deleteBtn" onclick="return confirm('آیا از حذف این نظر و تمام پاسخ‌های آن اطمینان دارید؟')">
                    <i class="fas fa-trash"></i> حذف نظر
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // مشاهده کامل نظر
    const viewCommentBtns = document.querySelectorAll('.view-comment');
    const commentAuthor = document.getElementById('commentAuthor');
    const commentDate = document.getElementById('commentDate');
    const commentText = document.getElementById('commentText');
    const approveBtn = document.getElementById('approveBtn');
    const rejectBtn = document.getElementById('rejectBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    
    viewCommentBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const commentId = this.getAttribute('data-comment-id');
            const commentContent = this.getAttribute('data-comment-text');
            const author = this.getAttribute('data-comment-author');
            const date = this.getAttribute('data-comment-date');
            
            commentAuthor.textContent = author;
            commentDate.textContent = date;
            commentText.innerHTML = commentContent.replace(/\n/g, '<br>');
            
            // تنظیم لینک‌های عملیات
            approveBtn.href = `?approve=${commentId}&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>`;
            rejectBtn.href = `?reject=${commentId}&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>`;
            deleteBtn.href = `?delete=${commentId}&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&article=<?php echo $articleFilter; ?>`;
        });
    });
    
    // تأیید همه نظرات
    const approveAllBtn = document.getElementById('approveAllBtn');
    if (approveAllBtn) {
        approveAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (confirm('آیا از تأیید تمام نظرات در این صفحه اطمینان دارید؟')) {
                // ایجاد لیست شناسه‌های نظرات
                const commentIds = [];
                viewCommentBtns.forEach(function(btn) {
                    commentIds.push(btn.getAttribute('data-comment-id'));
                });
                
                // ارسال درخواست به سرور برای تأیید همه نظرات
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'kb_comments_batch.php';
                
                const action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'action';
                action.value = 'approve_all';
                form.appendChild(action);
                
                const ids = document.createElement('input');
                ids.type = 'hidden';
                ids.name = 'comment_ids';
                ids.value = commentIds.join(',');
                form.appendChild(ids);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>