<?php
// kb_articles.php - مدیریت مقالات پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت مقالات
if (!kb_hasPermission('edit')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مدیریت مقالات پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// فیلترهای جستجو
$searchQuery = isset($_GET['q']) ? clean($_GET['q']) : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$statusFilter = isset($_GET['status']) ? clean($_GET['status']) : 'all';

// صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// حذف مقاله
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $articleId = (int)$_GET['delete'];
    
    // بررسی دسترسی به حذف مقاله
    if (kb_hasPermission('delete', 'article', $articleId)) {
        try {
            // شروع تراکنش
            $pdo->beginTransaction();
            
            // حذف رابطه‌های مقاله با دسته‌بندی‌ها
            $stmt = $pdo->prepare("DELETE FROM kb_article_categories WHERE article_id = ?");
            $stmt->execute([$articleId]);
            
            // حذف رابطه‌های مقاله با برچسب‌ها
            $stmt = $pdo->prepare("DELETE FROM kb_article_tags WHERE article_id = ?");
            $stmt->execute([$articleId]);
            
            // حذف نظرات مقاله
            $stmt = $pdo->prepare("DELETE FROM kb_comments WHERE article_id = ?");
            $stmt->execute([$articleId]);
            
            // حذف امتیازدهی‌های مقاله
            $stmt = $pdo->prepare("DELETE FROM kb_ratings WHERE article_id = ?");
            $stmt->execute([$articleId]);
            
            // حذف بازدیدهای مقاله
            $stmt = $pdo->prepare("DELETE FROM kb_views WHERE article_id = ?");
            $stmt->execute([$articleId]);
            
            // حذف پیوست‌های مقاله
            $stmt = $pdo->prepare("SELECT file_path FROM kb_attachments WHERE article_id = ?");
            $stmt->execute([$articleId]);
            $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($attachments as $attachment) {
                // حذف فایل از سرور
                if (file_exists($attachment)) {
                    unlink($attachment);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM kb_attachments WHERE article_id = ?");
            $stmt->execute([$articleId]);
            
            // حذف مقاله
            $stmt = $pdo->prepare("DELETE FROM kb_articles WHERE id = ? AND company_id = ?");
            $stmt->execute([$articleId, $companyId]);
            
            // پایان تراکنش
            $pdo->commit();
            
            $message = showSuccess('مقاله با موفقیت حذف شد.');
        } catch (PDOException $e) {
            // برگرداندن تراکنش در صورت بروز خطا
            $pdo->rollBack();
            $message = showError('خطا در حذف مقاله: ' . $e->getMessage());
        }
    } else {
        $message = showError('شما دسترسی لازم برای حذف این مقاله را ندارید.');
    }
}

// تغییر وضعیت مقاله
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $articleId = (int)$_GET['toggle'];
    
    // بررسی دسترسی به ویرایش مقاله
    if (kb_hasPermission('edit', 'article', $articleId)) {
        try {
            // دریافت وضعیت فعلی مقاله
            $stmt = $pdo->prepare("SELECT status FROM kb_articles WHERE id = ? AND company_id = ?");
            $stmt->execute([$articleId, $companyId]);
            $currentStatus = $stmt->fetchColumn();
            
            // تعیین وضعیت جدید
            $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';
            $publishedAt = ($newStatus === 'published') ? date('Y-m-d H:i:s') : null;
            
            // به‌روزرسانی وضعیت مقاله
            $stmt = $pdo->prepare("UPDATE kb_articles 
                                  SET status = ?, published_at = ?, updated_by = ? 
                                  WHERE id = ? AND company_id = ?");
            $stmt->execute([$newStatus, $publishedAt, $userId, $articleId, $companyId]);
            
            $message = showSuccess('وضعیت مقاله با موفقیت تغییر کرد.');
        } catch (PDOException $e) {
            $message = showError('خطا در تغییر وضعیت مقاله: ' . $e->getMessage());
        }
    } else {
        $message = showError('شما دسترسی لازم برای تغییر وضعیت این مقاله را ندارید.');
    }
}

// تغییر وضعیت ویژه بودن مقاله
if (isset($_GET['feature']) && is_numeric($_GET['feature'])) {
    $articleId = (int)$_GET['feature'];
    
    // بررسی دسترسی به ویرایش مقاله
    if (kb_hasPermission('edit', 'article', $articleId)) {
        try {
            // دریافت وضعیت فعلی ویژه بودن مقاله
            $stmt = $pdo->prepare("SELECT is_featured FROM kb_articles WHERE id = ? AND company_id = ?");
            $stmt->execute([$articleId, $companyId]);
            $isFeatured = $stmt->fetchColumn();
            
            // تغییر وضعیت ویژه بودن
            $newStatus = $isFeatured ? 0 : 1;
            
            // به‌روزرسانی وضعیت ویژه بودن مقاله
            $stmt = $pdo->prepare("UPDATE kb_articles 
                                  SET is_featured = ?, updated_by = ? 
                                  WHERE id = ? AND company_id = ?");
            $stmt->execute([$newStatus, $userId, $articleId, $companyId]);
            
            $message = showSuccess('وضعیت ویژه بودن مقاله با موفقیت تغییر کرد.');
        } catch (PDOException $e) {
            $message = showError('خطا در تغییر وضعیت ویژه بودن مقاله: ' . $e->getMessage());
        }
    } else {
        $message = showError('شما دسترسی لازم برای تغییر وضعیت ویژه بودن این مقاله را ندارید.');
    }
}

// تغییر وضعیت عمومی بودن مقاله
if (isset($_GET['public']) && is_numeric($_GET['public'])) {
    $articleId = (int)$_GET['public'];
    
    // بررسی دسترسی به ویرایش مقاله
    if (kb_hasPermission('edit', 'article', $articleId)) {
        try {
            // دریافت وضعیت فعلی عمومی بودن مقاله
            $stmt = $pdo->prepare("SELECT is_public FROM kb_articles WHERE id = ? AND company_id = ?");
            $stmt->execute([$articleId, $companyId]);
            $isPublic = $stmt->fetchColumn();
            
            // تغییر وضعیت عمومی بودن
            $newStatus = $isPublic ? 0 : 1;
            
            // به‌روزرسانی وضعیت عمومی بودن مقاله
            $stmt = $pdo->prepare("UPDATE kb_articles 
                                  SET is_public = ?, updated_by = ? 
                                  WHERE id = ? AND company_id = ?");
            $stmt->execute([$newStatus, $userId, $articleId, $companyId]);
            
            $message = showSuccess('وضعیت عمومی بودن مقاله با موفقیت تغییر کرد.');
        } catch (PDOException $e) {
            $message = showError('خطا در تغییر وضعیت عمومی بودن مقاله: ' . $e->getMessage());
        }
    } else {
        $message = showError('شما دسترسی لازم برای تغییر وضعیت عمومی بودن این مقاله را ندارید.');
    }
}

// دریافت تعداد کل مقالات
$countQuery = "SELECT COUNT(*) FROM kb_articles a WHERE a.company_id = ?";
$countParams = [$companyId];

// اعمال فیلترهای جستجو در کوئری شمارش
if (!empty($searchQuery)) {
    $countQuery .= " AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)";
    $searchLike = "%$searchQuery%";
    $countParams = array_merge($countParams, [$searchLike, $searchLike, $searchLike]);
}

if ($categoryFilter > 0) {
    $countQuery .= " AND EXISTS (SELECT 1 FROM kb_article_categories ac WHERE ac.article_id = a.id AND ac.category_id = ?)";
    $countParams[] = $categoryFilter;
}

if ($statusFilter !== 'all') {
    $countQuery .= " AND a.status = ?";
    $countParams[] = $statusFilter;
}

$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$totalArticles = $stmt->fetchColumn();

// محاسبه تعداد صفحات
$totalPages = ceil($totalArticles / $perPage);

// دریافت لیست مقالات
$query = "SELECT a.*, 
          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
          COALESCE(
              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
              0
          ) as average_rating,
          (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id) as comment_count,
          (
            SELECT GROUP_CONCAT(c.name SEPARATOR ', ')
            FROM kb_categories c
            JOIN kb_article_categories ac ON c.id = ac.category_id
            WHERE ac.article_id = a.id
          ) as categories
          FROM kb_articles a
          LEFT JOIN users u ON a.created_by = u.id
          LEFT JOIN personnel p ON u.id = p.user_id
          WHERE a.company_id = ?";

$params = [$companyId];

// اعمال فیلترهای جستجو
if (!empty($searchQuery)) {
    $query .= " AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)";
    $searchLike = "%$searchQuery%";
    $params = array_merge($params, [$searchLike, $searchLike, $searchLike]);
}

if ($categoryFilter > 0) {
    $query .= " AND EXISTS (SELECT 1 FROM kb_article_categories ac WHERE ac.article_id = a.id AND ac.category_id = ?)";
    $params[] = $categoryFilter;
}

if ($statusFilter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$perPage, $offset]);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// دریافت لیست دسته‌بندی‌ها برای فیلتر
$stmt = $pdo->prepare("SELECT id, name FROM kb_categories WHERE company_id = ? ORDER BY name");
$stmt->execute([$companyId]);
$categories = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت مقالات پایگاه دانش</h1>
    <div>
        <a href="kb_dashboard.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
        </a>
        <?php if (kb_hasPermission('create')): ?>
        <a href="kb_article_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> افزودن مقاله جدید
        </a>
        <?php endif; ?>
    </div>
</div>

<?php echo $message; ?>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="q" class="form-label">جستجو</label>
                    <input type="text" class="form-control" id="q" name="q" 
                           value="<?php echo $searchQuery; ?>" placeholder="عنوان، خلاصه یا محتوا...">
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label for="category" class="form-label">دسته‌بندی</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">همه دسته‌بندی‌ها</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $categoryFilter) ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3">
                    <label for="status" class="form-label">وضعیت</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>همه وضعیت‌ها</option>
                        <option value="published" <?php echo ($statusFilter === 'published') ? 'selected' : ''; ?>>منتشر شده</option>
                        <option value="draft" <?php echo ($statusFilter === 'draft') ? 'selected' : ''; ?>>پیش‌نویس</option>
                        <option value="archived" <?php echo ($statusFilter === 'archived') ? 'selected' : ''; ?>>آرشیو شده</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="mb-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> جستجو
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- لیست مقالات -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($articles)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>عنوان</th>
                        <th>دسته‌بندی‌ها</th>
                        <th>وضعیت</th>
                        <th>بازدید</th>
                        <th>امتیاز</th>
                        <th>نظرات</th>
                        <th>ایجاد کننده</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $index => $article): ?>
                    <tr>
                        <td><?php echo $offset + $index + 1; ?></td>
                        <td>
                            <a href="kb_article.php?id=<?php echo $article['id']; ?>" target="_blank">
                                <?php echo $article['title']; ?>
                            </a>
                            <?php if ($article['is_featured']): ?>
                            <span class="badge bg-warning ms-1">ویژه</span>
                            <?php endif; ?>
                            <?php if ($article['is_public']): ?>
                            <span class="badge bg-info ms-1">عمومی</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $article['categories']; ?></td>
                        <td>
                            <?php 
                            $statusClass = 'secondary';
                            $statusText = 'نامشخص';
                            
                            switch ($article['status']) {
                                case 'published':
                                    $statusClass = 'success';
                                    $statusText = 'منتشر شده';
                                    break;
                                case 'draft':
                                    $statusClass = 'warning';
                                    $statusText = 'پیش‌نویس';
                                    break;
                                case 'archived':
                                    $statusClass = 'danger';
                                    $statusText = 'آرشیو شده';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td><?php echo number_format($article['views_count']); ?></td>
                        <td>
                            <?php 
                            $rating = number_format($article['average_rating'], 1);
                            $ratingClass = 'text-warning';
                            if ($rating >= 4) $ratingClass = 'text-success';
                            elseif ($rating < 3) $ratingClass = 'text-danger';
                            if ($rating > 0): 
                            ?>
                            <span class="<?php echo $ratingClass; ?>">
                                <?php echo $rating; ?> / 5
                            </span>
                            <?php else: ?>
                            <span class="text-muted">بدون امتیاز</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($article['comment_count']); ?></td>
                        <td><?php echo $article['creator_name']; ?></td>
                        <td><?php echo formatDate($article['created_at']); ?></td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $article['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    عملیات
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $article['id']; ?>">
                                    <li>
                                        <a class="dropdown-item" href="kb_article.php?id=<?php echo $article['id']; ?>" target="_blank">
                                            <i class="fas fa-eye"></i> مشاهده
                                        </a>
                                    </li>
                                    <?php if (kb_hasPermission('edit', 'article', $article['id'])): ?>
                                    <li>
                                        <a class="dropdown-item" href="kb_article_form.php?id=<?php echo $article['id']; ?>">
                                            <i class="fas fa-edit"></i> ویرایش
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?toggle=<?php echo $article['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                                            <i class="fas fa-sync-alt"></i> 
                                            <?php echo ($article['status'] === 'published') ? 'تبدیل به پیش‌نویس' : 'انتشار'; ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?feature=<?php echo $article['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                                            <i class="fas fa-star"></i> 
                                            <?php echo $article['is_featured'] ? 'حذف از ویژه‌ها' : 'افزودن به ویژه‌ها'; ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?public=<?php echo $article['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                                            <i class="fas fa-globe"></i> 
                                            <?php echo $article['is_public'] ? 'خصوصی کردن' : 'عمومی کردن'; ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (kb_hasPermission('delete', 'article', $article['id'])): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="?delete=<?php echo $article['id']; ?>&status=<?php echo $statusFilter; ?>&page=<?php echo $page; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>" onclick="return confirm('آیا از حذف این مقاله اطمینان دارید؟ این عملیات قابل بازگشت نیست.')">
                                            <i class="fas fa-trash"></i> حذف
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
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
                    <a class="page-link" href="?page=1&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                        ابتدا
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                        قبلی
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                        بعدی
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $totalPages; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>">
                        انتها
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="alert alert-info">
            هیچ مقاله‌ای یافت نشد.
            <?php if (!empty($searchQuery) || $categoryFilter > 0 || $statusFilter !== 'all'): ?>
            <p class="mb-0 mt-2">
                <a href="kb_articles.php" class="btn btn-outline-primary">حذف فیلترها</a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>