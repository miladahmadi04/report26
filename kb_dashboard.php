<?php
// kb_dashboard.php - داشبورد پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به پایگاه دانش
if (!kb_hasPermission('view')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مشاهده پایگاه دانش را ندارید.';
    redirect('index.php');
}

// دریافت آمار پایگاه دانش
$stats = kb_getStatistics();

// دریافت دسته‌بندی‌های ریشه
$rootCategories = kb_getCategories();

// دریافت مقالات ویژه
$featuredArticles = kb_getFeaturedArticles();

// دریافت مقالات محبوب
$popularArticles = kb_getPopularArticles();

// دریافت برچسب‌های کلیدی
$tags = kb_getAllTags();

// دریافت کوئری جستجو
$searchQuery = isset($_GET['q']) ? clean($_GET['q']) : '';
$searchResults = [];

// اگر جستجو انجام شده، نتایج را دریافت کن
if (!empty($searchQuery)) {
    $searchResults = kb_searchArticles($searchQuery);
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>پایگاه دانش</h1>
    <?php if (kb_hasPermission('create')): ?>
    <a href="kb_article_form.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> افزودن مقاله جدید
    </a>
    <?php endif; ?>
</div>

<!-- جستجو -->
<div class="card mb-4">
    <div class="card-body">
        <form action="kb_search.php" method="GET" class="mb-0">
            <div class="input-group">
                <input type="text" name="q" class="form-control form-control-lg" 
                      placeholder="جستجو در پایگاه دانش..." value="<?php echo $searchQuery; ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> جستجو
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($searchQuery) && !empty($searchResults)): ?>
<!-- نتایج جستجو -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">نتایج جستجو برای: <?php echo $searchQuery; ?></h5>
    </div>
    <div class="card-body">
        <div class="list-group">
            <?php foreach ($searchResults as $article): ?>
            <a href="kb_article.php?id=<?php echo $article['id']; ?>" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1"><?php echo $article['title']; ?></h5>
                    <small>
                        <?php echo number_format($article['views_count']); ?> بازدید
                    </small>
                </div>
                <p class="mb-1"><?php echo $article['excerpt'] ? $article['excerpt'] : mb_substr(strip_tags($article['content']), 0, 150) . '...'; ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-user"></i> <?php echo $article['creator_name']; ?> | 
                        <i class="far fa-calendar-alt"></i> <?php echo formatDate($article['published_at']); ?>
                    </small>
                    <div>
                        <?php 
                        $rating = $article['average_rating'];
                        $ratingClass = 'text-warning';
                        if ($rating >= 4) $ratingClass = 'text-success';
                        elseif ($rating < 3) $ratingClass = 'text-danger';
                        if ($rating > 0): 
                        ?>
                        <span class="<?php echo $ratingClass; ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?php echo ($i <= round($rating)) ? 's' : 'r'; ?> fa-star"></i>
                            <?php endfor; ?>
                            (<?php echo number_format($rating, 1); ?>)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-3">
            <a href="kb_search.php?q=<?php echo urlencode($searchQuery); ?>" class="btn btn-outline-primary">
                مشاهده همه نتایج
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- آمار سریع -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            تعداد مقالات
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_articles']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
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
                            تعداد دسته‌بندی‌ها
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_categories']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            تعداد بازدیدها
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_views']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-eye fa-2x text-gray-300"></i>
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
                            تعداد نظرات
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['total_comments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-comments fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- دسته‌بندی‌ها -->
    <div class="col-md-4">
        <div class="card mb-4 h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">دسته‌بندی‌ها</h5>
                <?php if (kb_hasPermission('manage')): ?>
                <a href="kb_categories.php" class="btn btn-sm btn-outline-primary">مدیریت</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($rootCategories)): ?>
                <div class="list-group">
                    <?php foreach ($rootCategories as $category): ?>
                    <a href="kb_category.php?id=<?php echo $category['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <?php if (!empty($category['icon'])): ?>
                            <i class="<?php echo $category['icon']; ?> me-2"></i>
                            <?php endif; ?>
                            <?php echo $category['name']; ?>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?php echo $category['article_count']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    هیچ دسته‌بندی‌ای یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- مقالات ویژه -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">مقالات ویژه</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($featuredArticles)): ?>
                <div class="row">
                    <?php foreach ($featuredArticles as $article): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="kb_article.php?id=<?php echo $article['id']; ?>"><?php echo $article['title']; ?></a>
                                </h5>
                                <p class="card-text small">
                                    <?php echo $article['excerpt'] ? $article['excerpt'] : mb_substr(strip_tags($article['content']), 0, 100) . '...'; ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-eye"></i> <?php echo number_format($article['views_count']); ?> بازدید
                                    </small>
                                    <?php 
                                    $rating = $article['average_rating'];
                                    $ratingClass = 'text-warning';
                                    if ($rating >= 4) $ratingClass = 'text-success';
                                    elseif ($rating < 3) $ratingClass = 'text-danger';
                                    if ($rating > 0): 
                                    ?>
                                    <span class="<?php echo $ratingClass; ?>">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa<?php echo ($i <= round($rating)) ? 's' : 'r'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    هیچ مقاله ویژه‌ای یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- مقالات محبوب -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">مقالات محبوب</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($popularArticles)): ?>
                <div class="list-group">
                    <?php foreach ($popularArticles as $article): ?>
                    <a href="kb_article.php?id=<?php echo $article['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <?php echo $article['title']; ?>
                            <span class="badge bg-secondary ms-2"><?php echo number_format($article['views_count']); ?> بازدید</span>
                        </div>
                        <?php 
                        $rating = $article['average_rating'];
                        $ratingClass = 'text-warning';
                        if ($rating >= 4) $ratingClass = 'text-success';
                        elseif ($rating < 3) $ratingClass = 'text-danger';
                        if ($rating > 0): 
                        ?>
                        <span class="<?php echo $ratingClass; ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?php echo ($i <= round($rating)) ? 's' : 'r'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    هیچ مقاله‌ای یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- برچسب‌های کلیدی -->
<?php if (!empty($tags)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">برچسب‌های کلیدی</h5>
    </div>
    <div class="card-body">
        <?php foreach ($tags as $tag): ?>
        <a href="kb_tag.php?id=<?php echo $tag['id']; ?>" class="btn btn-sm btn-outline-secondary mb-2 me-2">
            <?php echo $tag['name']; ?> 
            <span class="badge bg-secondary"><?php echo $tag['article_count']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- اگر کاربر دسترسی مدیریت دارد، نمایش لینک‌های مدیریتی -->
<?php if (kb_hasPermission('manage')): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">ابزارهای مدیریت</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <a href="kb_categories.php" class="btn btn-primary btn-block">
                    <i class="fas fa-folder-open"></i> مدیریت دسته‌بندی‌ها
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="kb_articles.php" class="btn btn-success btn-block">
                    <i class="fas fa-file-alt"></i> مدیریت مقالات
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="kb_comments.php" class="btn btn-info btn-block">
                    <i class="fas fa-comments"></i> مدیریت نظرات
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="kb_tags.php" class="btn btn-warning btn-block">
                    <i class="fas fa-tags"></i> مدیریت برچسب‌ها
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>