<?php
// kb_category.php - نمایش مقالات یک دسته‌بندی
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مشاهده پایگاه دانش
if (!kb_hasPermission('view')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مشاهده پایگاه دانش را ندارید.';
    redirect('index.php');
}

$message = '';
$companyId = $_SESSION['company_id'];

// دریافت شناسه دسته‌بندی
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$categoryId) {
    $_SESSION['error_message'] = 'دسته‌بندی مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// دریافت اطلاعات دسته‌بندی
$category = kb_getCategory($categoryId);

if (!$category) {
    $_SESSION['error_message'] = 'دسته‌بندی مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// بررسی فعال بودن دسته‌بندی
if (!$category['is_active'] && !isAdmin()) {
    $_SESSION['error_message'] = 'این دسته‌بندی غیرفعال است.';
    redirect('kb_dashboard.php');
}

// دریافت زیردسته‌های دسته‌بندی فعلی
$subcategories = kb_getCategories($categoryId);

// دریافت مسیر دسته‌بندی (بردکرامب)
$breadcrumb = kb_getCategoryBreadcrumb($categoryId);

// صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// دریافت مقالات دسته‌بندی
$articles = kb_getCategoryArticles($categoryId, $perPage, $offset);

// دریافت تعداد کل مقالات دسته‌بندی
$totalArticles = kb_getCategoryArticleCount($categoryId);

// محاسبه تعداد صفحات
$totalPages = ceil($totalArticles / $perPage);

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="kb_dashboard.php">پایگاه دانش</a></li>
            <?php foreach ($breadcrumb as $index => $crumb): ?>
                <?php if ($index < count($breadcrumb) - 1): ?>
                <li class="breadcrumb-item">
                    <a href="kb_category.php?id=<?php echo $crumb['id']; ?>"><?php echo $crumb['name']; ?></a>
                </li>
                <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $crumb['name']; ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
    
    <form action="kb_search.php" method="GET" class="d-flex">
        <input type="text" name="q" class="form-control me-2" placeholder="جستجو در پایگاه دانش...">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<?php echo $message; ?>

<!-- اطلاعات دسته‌بندی -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-1 text-center">
                <div class="display-4 text-muted">
                    <i class="<?php echo !empty($category['icon']) ? $category['icon'] : 'fas fa-folder'; ?>"></i>
                </div>
            </div>
            <div class="col-md-11">
                <h1 class="card-title h2"><?php echo $category['name']; ?></h1>
                <?php if (!empty($category['description'])): ?>
                <p class="card-text"><?php echo $category['description']; ?></p>
                <?php endif; ?>
                <div class="d-flex">
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-file-alt me-1"></i> <?php echo number_format($category['article_count']); ?> مقاله
                    </span>
                    <?php if ($category['subcategory_count'] > 0): ?>
                    <span class="badge bg-secondary">
                        <i class="fas fa-folder me-1"></i> <?php echo number_format($category['subcategory_count']); ?> زیرمجموعه
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- زیردسته‌ها -->
<?php if (!empty($subcategories)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-folder-open me-1"></i> زیرمجموعه‌ها</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($subcategories as $subcategory): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2">
                                <i class="<?php echo !empty($subcategory['icon']) ? $subcategory['icon'] : 'fas fa-folder'; ?> fa-2x text-muted"></i>
                            </div>
                            <h5 class="card-title mb-0">
                                <a href="kb_category.php?id=<?php echo $subcategory['id']; ?>" class="text-decoration-none">
                                    <?php echo $subcategory['name']; ?>
                                </a>
                            </h5>
                        </div>
                        <?php if (!empty($subcategory['description'])): ?>
                        <p class="card-text small text-muted">
                            <?php echo mb_substr($subcategory['description'], 0, 100) . (mb_strlen($subcategory['description']) > 100 ? '...' : ''); ?>
                        </p>
                        <?php endif; ?>
                        <div class="text-end">
                            <span class="badge bg-primary">
                                <?php echo number_format($subcategory['article_count']); ?> مقاله
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- مقالات -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-alt me-1"></i> مقالات (<?php echo number_format($totalArticles); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($articles)): ?>
        <div class="list-group mb-4">
            <?php foreach ($articles as $article): ?>
            <a href="kb_article.php?id=<?php echo $article['id']; ?>" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">
                        <?php echo $article['title']; ?>
                        <?php if ($article['is_featured']): ?>
                        <span class="badge bg-warning ms-1">ویژه</span>
                        <?php endif; ?>
                    </h5>
                    <small>
                        <i class="fas fa-eye me-1"></i> <?php echo number_format($article['views_count']); ?>
                    </small>
                </div>
                <p class="mb-1">
                    <?php echo $article['excerpt'] ? $article['excerpt'] : mb_substr(strip_tags($article['content']), 0, 150) . '...'; ?>
                </p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i> <?php echo $article['creator_name']; ?> | 
                        <i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($article['published_at']); ?>
                        
                        <?php if ($article['comment_count'] > 0): ?>
                        | <i class="fas fa-comments me-1"></i> <?php echo number_format($article['comment_count']); ?> نظر
                        <?php endif; ?>
                    </small>
                    <?php if ($article['average_rating'] > 0): ?>
                    <div>
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
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=1">ابتدا</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $page - 1; ?>">قبلی</a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $page + 1; ?>">بعدی</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $categoryId; ?>&page=<?php echo $totalPages; ?>">انتها</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="alert alert-info mb-0">
            هیچ مقاله‌ای در این دسته‌بندی یافت نشد.
            <?php if (kb_hasPermission('create')): ?>
            <div class="mt-3">
                <a href="kb_article_form.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> افزودن مقاله جدید
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>