<?php
// kb_article.php - نمایش مقاله پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// دریافت شناسه مقاله
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$articleId) {
    // دریافت مقاله با اسلاگ
    $slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';
    if ($slug) {
        $article = kb_getArticleBySlug($slug);
        if ($article) {
            $articleId = $article['id'];
        }
    }
}

if (!$articleId) {
    $_SESSION['error_message'] = 'مقاله مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// دریافت اطلاعات مقاله
$article = kb_getArticle($articleId);
if (!$article) {
    $_SESSION['error_message'] = 'مقاله مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// بررسی دسترسی به مقاله
$canView = false;

// اگر مقاله عمومی است، همه می‌توانند آن را ببینند
if ($article['is_public']) {
    $canView = true;
}
// اگر کاربر ادمین است، می‌تواند همه مقالات را ببیند
else if (isAdmin()) {
    $canView = true;
}
// اگر مقاله منتشر شده است و کاربر دسترسی مشاهده دارد
else if ($article['status'] === 'published' && kb_hasPermission('view', 'article', $articleId)) {
    $canView = true;
}
// اگر کاربر ایجاد کننده یا ویرایش کننده مقاله است
else if ($article['created_by'] === $userId || $article['updated_by'] === $userId) {
    $canView = true;
}

if (!$canView) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مشاهده این مقاله را ندارید.';
    redirect('kb_dashboard.php');
}

// دریافت دسته‌بندی‌های مقاله
$categories = kb_getArticleCategories($articleId);

// دریافت برچسب‌های مقاله
$tags = kb_getArticleTags($articleId);

// دریافت پیوست‌های مقاله
$attachments = kb_getArticleAttachments($articleId);

// دریافت نظرات مقاله
$comments = kb_getArticleComments($articleId);

// دریافت مقالات مرتبط
$relatedArticles = kb_getRelatedArticles($articleId);

// افزودن بازدید جدید
kb_addArticleView($articleId);

// دریافت امتیاز کاربر فعلی به مقاله (اگر قبلاً امتیاز داده باشد)
$userRating = kb_getUserRating($articleId);

// ثبت امتیاز جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $feedback = isset($_POST['feedback']) ? clean($_POST['feedback']) : '';
    
    if ($rating >= 1 && $rating <= 5) {
        if (kb_rateArticle($articleId, $rating, $feedback)) {
            $message = showSuccess('امتیاز شما با موفقیت ثبت شد.');
            // بروزرسانی امتیاز کاربر
            $userRating = kb_getUserRating($articleId);
            // بروزرسانی اطلاعات مقاله
            $article = kb_getArticle($articleId);
        } else {
            $message = showError('خطا در ثبت امتیاز.');
        }
    } else {
        $message = showError('لطفاً یک امتیاز معتبر (بین 1 تا 5) انتخاب کنید.');
    }
}

// ثبت نظر جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $comment = isset($_POST['comment']) ? clean($_POST['comment']) : '';
    $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if (!empty($comment)) {
        if (kb_addComment($articleId, $comment, $parentId)) {
            $message = showSuccess('نظر شما با موفقیت ثبت شد و پس از تأیید نمایش داده خواهد شد.');
            // بروزرسانی نظرات
            $comments = kb_getArticleComments($articleId);
        } else {
            $message = showError('خطا در ثبت نظر.');
        }
    } else {
        $message = showError('لطفاً متن نظر را وارد کنید.');
    }
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="kb_dashboard.php">پایگاه دانش</a></li>
            <?php if (!empty($categories)): ?>
                <?php 
                // نمایش دسته‌بندی اول به عنوان بردکرامب
                $firstCategory = $categories[0];
                $breadcrumb = kb_getCategoryBreadcrumb($firstCategory['id']);
                foreach ($breadcrumb as $crumb): 
                ?>
                <li class="breadcrumb-item">
                    <a href="kb_category.php?id=<?php echo $crumb['id']; ?>"><?php echo $crumb['name']; ?></a>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $article['title']; ?></li>
        </ol>
    </nav>
    <div>
        <?php if (kb_hasPermission('edit', 'article', $articleId)): ?>
        <a href="kb_article_form.php?id=<?php echo $articleId; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> ویرایش مقاله
        </a>
        <?php endif; ?>
    </div>
</div>

<?php echo $message; ?>

<!-- محتوای اصلی مقاله -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h1 class="article-title mb-3"><?php echo $article['title']; ?></h1>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="text-muted">
                            <i class="fas fa-user me-1"></i> <?php echo $article['creator_name']; ?>
                        </span>
                        <span class="text-muted ms-3">
                            <i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($article['published_at'] ? $article['published_at'] : $article['created_at']); ?>
                        </span>
                        <span class="text-muted ms-3">
                            <i class="fas fa-eye me-1"></i> <?php echo number_format($article['views_count'] + 1); ?> بازدید
                        </span>
                    </div>
                    
                    <?php if ($article['average_rating'] > 0): ?>
                    <div class="article-rating">
                        <?php
                        $rating = $article['average_rating'];
                        $ratingClass = 'text-warning';
                        if ($rating >= 4) $ratingClass = 'text-success';
                        elseif ($rating < 3) $ratingClass = 'text-danger';
                        ?>
                        <span class="<?php echo $ratingClass; ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?php echo ($i <= round($rating)) ? 's' : 'r'; ?> fa-star"></i>
                            <?php endfor; ?>
                            (<?php echo number_format($rating, 1); ?>)
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($article['excerpt'])): ?>
                <div class="alert alert-light mb-4">
                    <?php echo $article['excerpt']; ?>
                </div>
                <?php endif; ?>
                
                <div class="article-content">
                    <?php echo $article['content']; ?>
                </div>
                
                <?php if (!empty($tags)): ?>
                <div class="article-tags mt-4">
                    <strong><i class="fas fa-tags me-1"></i> برچسب‌ها:</strong>
                    <?php foreach ($tags as $tag): ?>
                    <a href="kb_tag.php?id=<?php echo $tag['id']; ?>" class="badge bg-secondary text-decoration-none ms-1">
                        <?php echo $tag['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- پیوست‌ها -->
        <?php if (!empty($attachments)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-paperclip me-1"></i> پیوست‌ها</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>نام فایل</th>
                                <th>اندازه</th>
                                <th>تعداد دانلود</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachments as $attachment): ?>
                            <tr>
                                <td><?php echo $attachment['file_name']; ?></td>
                                <td><?php echo formatFileSize($attachment['file_size']); ?></td>
                                <td><?php echo number_format($attachment['download_count']); ?></td>
                                <td>
                                    <a href="kb_download.php?id=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-download me-1"></i> دانلود
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- امتیازدهی -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-star me-1"></i> امتیازدهی</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">امتیاز شما به این مقاله</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" 
                                          <?php echo (isset($userRating['rating']) && $userRating['rating'] == $i) ? 'checked' : ''; ?>>
                                    <label for="rating<?php echo $i; ?>">&#9733;</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="feedback" class="form-label">نظر شما (اختیاری)</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="3"><?php echo isset($userRating['feedback']) ? $userRating['feedback'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="submit_rating" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> ثبت امتیاز
                    </button>
                </form>
            </div>
        </div>
        
        <!-- نظرات -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-comments me-1"></i> نظرات (<?php echo count($comments); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($comments)): ?>
                <div class="comments-section mb-4">
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><?php echo $comment['author_name']; ?></strong>
                            <small class="text-muted"><?php echo formatDate($comment['created_at']); ?></small>
                        </div>
                        <p class="mb-2"><?php echo nl2br($comment['comment']); ?></p>
                        <button type="button" class="btn btn-sm btn-outline-primary reply-button" data-comment-id="<?php echo $comment['id']; ?>">
                            <i class="fas fa-reply me-1"></i> پاسخ
                        </button>
                        
                        <!-- فرم پاسخ به نظر -->
                        <div class="reply-form mt-2" id="reply-form-<?php echo $comment['id']; ?>" style="display: none;">
                            <form method="POST" action="">
                                <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                <div class="mb-3">
                                    <textarea class="form-control" name="comment" rows="2" required></textarea>
                                </div>
                                <button type="submit" name="submit_comment" class="btn btn-sm btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> ارسال پاسخ
                                </button>
                            </form>
                        </div>
                        
                        <?php if (!empty($comment['replies'])): ?>
                        <div class="replies mt-3 ms-4">
                            <?php foreach ($comment['replies'] as $reply): ?>
                            <div class="reply p-2 border-start border-primary ps-3 mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong><?php echo $reply['author_name']; ?></strong>
                                    <small class="text-muted"><?php echo formatDate($reply['created_at']); ?></small>
                                </div>
                                <p class="mb-0"><?php echo nl2br($reply['comment']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- فرم ارسال نظر جدید -->
                <div class="new-comment-form">
                    <h5 class="mb-3">ارسال نظر جدید</h5>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <textarea class="form-control" name="comment" rows="4" required placeholder="نظر خود را بنویسید..."></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> ارسال نظر
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- اطلاعات مقاله -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-1"></i> اطلاعات مقاله</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user me-1"></i> نویسنده</span>
                        <span><?php echo $article['creator_name']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="far fa-calendar-alt me-1"></i> تاریخ انتشار</span>
                        <span><?php echo formatDate($article['published_at'] ? $article['published_at'] : $article['created_at']); ?></span>
                    </li>
                    <?php if ($article['updated_by']): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-edit me-1"></i> آخرین ویرایش</span>
                        <span><?php echo formatDate($article['updated_at']); ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-eye me-1"></i> تعداد بازدید</span>
                        <span><?php echo number_format($article['views_count'] + 1); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-comments me-1"></i> تعداد نظرات</span>
                        <span><?php echo number_format($article['comment_count']); ?></span>
                    </li>
                    <?php if ($article['average_rating'] > 0): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-star me-1"></i> میانگین امتیاز</span>
                        <span>
                            <?php 
                            $rating = $article['average_rating'];
                            $ratingClass = 'text-warning';
                            if ($rating >= 4) $ratingClass = 'text-success';
                            elseif ($rating < 3) $ratingClass = 'text-danger';
                            ?>
                            <span class="<?php echo $ratingClass; ?>">
                                <?php echo number_format($rating, 1); ?> از 5
                            </span>
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- دسته‌بندی‌ها -->
        <?php if (!empty($categories)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-folder me-1"></i> دسته‌بندی‌ها</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap">
                    <?php foreach ($categories as $category): ?>
                    <a href="kb_category.php?id=<?php echo $category['id']; ?>" class="btn btn-outline-secondary me-2 mb-2">
                        <?php if (!empty($category['icon'])): ?>
                        <i class="<?php echo $category['icon']; ?> me-1"></i>
                        <?php endif; ?>
                        <?php echo $category['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- مقالات مرتبط -->
        <?php if (!empty($relatedArticles)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link me-1"></i> مقالات مرتبط</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($relatedArticles as $relatedArticle): ?>
                    <a href="kb_article.php?id=<?php echo $relatedArticle['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo $relatedArticle['title']; ?></h6>
                            <small class="text-muted"><?php echo number_format($relatedArticle['views_count']); ?> بازدید</small>
                        </div>
                        <?php if (!empty($relatedArticle['excerpt'])): ?>
                        <small class="text-muted"><?php echo mb_substr($relatedArticle['excerpt'], 0, 100) . '...'; ?></small>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- جستجو -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-search me-1"></i> جستجو</h5>
            </div>
            <div class="card-body">
                <form action="kb_search.php" method="GET">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="جستجو در پایگاه دانش...">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* استایل امتیازدهی */
.rating {
    display: flex;
    flex-direction: row-reverse;
    font-size: 1.5rem;
    justify-content: flex-end;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    color: #ccc;
    margin: 0;
    padding: 0 2px;
}

.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label {
    color: #f8ce0b;
}

.rating input:checked + label:hover,
.rating input:checked ~ label:hover,
.rating input:checked ~ label:hover ~ label,
.rating label:hover ~ input:checked ~ label {
    color: #f8ce0b;
}

/* استایل محتوای مقاله */
.article-content {
    line-height: 1.8;
}

.article-content img {
    max-width: 100%;
    height: auto;
}

.article-content table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.article-content table, 
.article-content th, 
.article-content td {
    border: 1px solid #dee2e6;
}

.article-content th, 
.article-content td {
    padding: 0.5rem;
}

.article-content blockquote {
    border-left: 4px solid #6c757d;
    margin-left: 0;
    padding-left: 1rem;
    font-style: italic;
}

.article-content code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-family: monospace;
}

.article-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.25rem;
    overflow-x: auto;
}

.article-content pre code {
    background-color: transparent;
    padding: 0;
}
</style>

<script>
// نمایش/مخفی کردن فرم پاسخ به نظر
document.addEventListener('DOMContentLoaded', function() {
    const replyButtons = document.querySelectorAll('.reply-button');
    
    replyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const commentId = this.getAttribute('data-comment-id');
            const replyForm = document.getElementById('reply-form-' + commentId);
            
            // مخفی کردن تمام فرم‌های پاسخ
            document.querySelectorAll('.reply-form').forEach(function(form) {
                form.style.display = 'none';
            });
            
            // نمایش فرم پاسخ مربوطه
            replyForm.style.display = 'block';
        });
    });
});

// تابع محاسبه حجم فایل
function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else if (bytes > 1) {
        return bytes + ' bytes';
    } else if (bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
</script>

<?php include 'footer.php'; ?>