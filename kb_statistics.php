<?php
// kb_statistics.php - آمار و گزارشات پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت پایگاه دانش
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مشاهده آمار پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// دوره زمانی برای گزارش
$dateFrom = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01'); // اول ماه جاری
$dateTo = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d'); // امروز

// آمار کلی پایگاه دانش
$stmt = $pdo->prepare("SELECT
                      (SELECT COUNT(*) FROM kb_articles WHERE company_id = ?) as total_articles,
                      (SELECT COUNT(*) FROM kb_articles WHERE company_id = ? AND status = 'published') as published_articles,
                      (SELECT COUNT(*) FROM kb_articles WHERE company_id = ? AND status = 'draft') as draft_articles,
                      (SELECT COUNT(*) FROM kb_articles WHERE company_id = ? AND status = 'archived') as archived_articles,
                      (SELECT COUNT(*) FROM kb_categories WHERE company_id = ?) as total_categories,
                      (SELECT COUNT(*) FROM kb_tags WHERE company_id = ?) as total_tags,
                      (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ?) as total_comments,
                      (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.status = 'approved') as approved_comments,
                      (SELECT COUNT(*) FROM kb_comments c JOIN kb_articles a ON c.article_id = a.id WHERE a.company_id = ? AND c.status = 'pending') as pending_comments,
                      (SELECT COUNT(*) FROM kb_attachments at JOIN kb_articles a ON at.article_id = a.id WHERE a.company_id = ?) as total_attachments,
                      (SELECT SUM(download_count) FROM kb_attachments at JOIN kb_articles a ON at.article_id = a.id WHERE a.company_id = ?) as total_downloads,
                      (SELECT SUM(views_count) FROM kb_articles WHERE company_id = ?) as total_views");
$stmt->execute([$companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId]);
$generalStats = $stmt->fetch();

// آمار بازدید روزانه در دوره زمانی انتخاب شده
$stmt = $pdo->prepare("SELECT DATE(viewed_at) as view_date, COUNT(*) as view_count 
                      FROM kb_views v 
                      JOIN kb_articles a ON v.article_id = a.id 
                      WHERE a.company_id = ? AND DATE(viewed_at) BETWEEN ? AND ? 
                      GROUP BY view_date 
                      ORDER BY view_date");
$stmt->execute([$companyId, $dateFrom, $dateTo]);
$dailyViews = $stmt->fetchAll();

// آمار بازدید ماهانه
$stmt = $pdo->prepare("SELECT DATE_FORMAT(viewed_at, '%Y-%m') as view_month, COUNT(*) as view_count 
                      FROM kb_views v 
                      JOIN kb_articles a ON v.article_id = a.id 
                      WHERE a.company_id = ? 
                      GROUP BY view_month 
                      ORDER BY view_month DESC 
                      LIMIT 12");
$stmt->execute([$companyId]);
$monthlyViews = $stmt->fetchAll();

// آمار محبوب‌ترین مقالات
$stmt = $pdo->prepare("SELECT a.id, a.title, a.views_count, 
                     (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count,
                     (SELECT COALESCE(AVG(rating), 0) FROM kb_ratings WHERE article_id = a.id) as avg_rating,
                     (SELECT COUNT(*) FROM kb_ratings WHERE article_id = a.id) as rating_count
                     FROM kb_articles a 
                     WHERE a.company_id = ? AND a.status = 'published' 
                     ORDER BY a.views_count DESC 
                     LIMIT 10");
$stmt->execute([$companyId]);
$popularArticles = $stmt->fetchAll();

// آمار مقالات با بیشترین امتیاز
$stmt = $pdo->prepare("SELECT a.id, a.title, a.views_count, 
                     (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count,
                     (SELECT COALESCE(AVG(rating), 0) FROM kb_ratings WHERE article_id = a.id) as avg_rating,
                     (SELECT COUNT(*) FROM kb_ratings WHERE article_id = a.id) as rating_count
                     FROM kb_articles a 
                     WHERE a.company_id = ? AND a.status = 'published' 
                     AND EXISTS (SELECT 1 FROM kb_ratings WHERE article_id = a.id)
                     ORDER BY avg_rating DESC, rating_count DESC 
                     LIMIT 10");
$stmt->execute([$companyId]);
$topRatedArticles = $stmt->fetchAll();

// آمار محبوب‌ترین دسته‌بندی‌ها
$stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(ac.article_id) as article_count,
                     SUM(a.views_count) as total_views 
                     FROM kb_categories c
                     LEFT JOIN kb_article_categories ac ON c.id = ac.category_id
                     LEFT JOIN kb_articles a ON ac.article_id = a.id AND a.status = 'published'
                     WHERE c.company_id = ?
                     GROUP BY c.id
                     ORDER BY total_views DESC
                     LIMIT 10");
$stmt->execute([$companyId]);
$popularCategories = $stmt->fetchAll();

// آمار محبوب‌ترین برچسب‌ها
$stmt = $pdo->prepare("SELECT t.id, t.name, COUNT(at.article_id) as article_count,
                     SUM(a.views_count) as total_views 
                     FROM kb_tags t
                     LEFT JOIN kb_article_tags at ON t.id = at.tag_id
                     LEFT JOIN kb_articles a ON at.article_id = a.id AND a.status = 'published'
                     WHERE t.company_id = ?
                     GROUP BY t.id
                     ORDER BY total_views DESC
                     LIMIT 10");
$stmt->execute([$companyId]);
$popularTags = $stmt->fetchAll();

// آمار کاربران فعال در ایجاد محتوا
$stmt = $pdo->prepare("SELECT a.created_by, CONCAT(p.first_name, ' ', p.last_name) as author_name,
                     COUNT(a.id) as article_count,
                     SUM(a.views_count) as total_views,
                     MAX(a.created_at) as last_activity
                     FROM kb_articles a
                     LEFT JOIN personnel p ON a.created_by = p.id
                     WHERE a.company_id = ?
                     GROUP BY a.created_by
                     ORDER BY article_count DESC
                     LIMIT 10");
$stmt->execute([$companyId]);
$activeUsers = $stmt->fetchAll();

// آمار عبارات جستجو شده توسط کاربران
$stmt = $pdo->prepare("SELECT search_query, COUNT(*) as search_count, 
                     MAX(search_time) as last_search 
                     FROM kb_search_logs 
                     WHERE company_id = ? 
                     GROUP BY search_query 
                     ORDER BY search_count DESC 
                     LIMIT 20");
$stmt->execute([$companyId]);
$searchTerms = $stmt->fetchAll();

// تبدیل داده‌ها به فرمت‌های مورد نیاز نمودارها
$dailyViewsData = [];
$dailyViewsDates = [];
$dailyViewsCounts = [];

foreach ($dailyViews as $view) {
    $dailyViewsDates[] = $view['view_date'];
    $dailyViewsCounts[] = $view['view_count'];
    $dailyViewsData[] = [
        'date' => $view['view_date'],
        'count' => $view['view_count']
    ];
}

$monthlyViewsData = [];
$monthlyViewsLabels = [];
$monthlyViewsCounts = [];

foreach ($monthlyViews as $view) {
    $date = new DateTime($view['view_month'] . '-01');
    $label = $date->format('F Y');
    $monthlyViewsLabels[] = $label;
    $monthlyViewsCounts[] = $view['view_count'];
    $monthlyViewsData[] = [
        'month' => $label,
        'count' => $view['view_count']
    ];
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>آمار و گزارشات پایگاه دانش</h1>
    <a href="kb_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<!-- فیلتر دوره زمانی -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row">
            <div class="col-md-4 mb-3">
                <label for="date_from" class="form-label">از تاریخ</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label for="date_to" class="form-label">تا تاریخ</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label d-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> اعمال فیلتر
                </button>
            </div>
        </form>
    </div>
</div>

<!-- آمار کلی -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            کل مقالات
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['total_articles']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            کل بازدیدها
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['total_views']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-eye fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            کل نظرات
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['total_comments']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-comments fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            دانلود پیوست‌ها
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['total_downloads']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-download fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            مقالات منتشر شده
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['published_articles']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            پیش‌نویس‌ها
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['draft_articles']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-edit fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            مقالات آرشیو شده
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['archived_articles']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-archive fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            تعداد دسته‌بندی‌ها
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($generalStats['total_categories']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نمودار بازدید روزانه -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">آمار بازدید روزانه</h6>
            </div>
            <div class="card-body">
                <div id="daily-views-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- نمودار بازدید ماهانه -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">آمار بازدید ماهانه</h6>
            </div>
            <div class="card-body">
                <div id="monthly-views-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- محبوب‌ترین مقالات -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">محبوب‌ترین مقالات</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($popularArticles)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>عنوان مقاله</th>
                                <th>بازدید</th>
                                <th>امتیاز</th>
                                <th>نظرات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularArticles as $article): ?>
                            <tr>
                                <td>
                                    <a href="kb_article.php?id=<?php echo $article['id']; ?>" target="_blank">
                                        <?php echo mb_substr($article['title'], 0, 40) . (mb_strlen($article['title']) > 40 ? '...' : ''); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($article['views_count']); ?></td>
                                <td>
                                    <?php 
                                    $rating = $article['avg_rating'];
                                    if ($rating > 0): 
                                    ?>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa<?php echo ($i <= round($rating)) ? 's' : 'r'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                        (<?php echo number_format($rating, 1); ?>)
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">بدون امتیاز</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($article['comment_count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    هیچ مقاله‌ای یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- مقالات با بیشترین امتیاز -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">مقالات با بیشترین امتیاز</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topRatedArticles)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>عنوان مقاله</th>
                                <th>امتیاز</th>
                                <th>تعداد رأی</th>
                                <th>بازدید</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topRatedArticles as $article): ?>
                            <tr>
                                <td>
                                    <a href="kb_article.php?id=<?php echo $article['id']; ?>" target="_blank">
                                        <?php echo mb_substr($article['title'], 0, 40) . (mb_strlen($article['title']) > 40 ? '...' : ''); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $rating = $article['avg_rating'];
                                    if ($rating > 0): 
                                    ?>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa<?php echo ($i <= round($rating)) ? 's' : 'r'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                        (<?php echo number_format($rating, 1); ?>)
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">بدون امتیاز</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($article['rating_count']); ?></td>
                                <td><?php echo number_format($article['views_count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    هیچ مقاله‌ای با امتیاز یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- محبوب‌ترین دسته‌بندی‌ها -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">محبوب‌ترین دسته‌بندی‌ها</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($popularCategories)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>نام دسته‌بندی</th>
                                <th>تعداد مقالات</th>
                                <th>کل بازدید</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularCategories as $category): ?>
                            <tr>
                                <td>
                                    <a href="kb_category.php?id=<?php echo $category['id']; ?>" target="_blank">
                                        <?php echo $category['name']; ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($category['article_count']); ?></td>
                                <td><?php echo number_format($category['total_views']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    هیچ دسته‌بندی‌ای یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- محبوب‌ترین برچسب‌ها -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">محبوب‌ترین برچسب‌ها</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($popularTags)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>نام برچسب</th>
                                <th>تعداد مقالات</th>
                                <th>کل بازدید</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popularTags as $tag): ?>
                            <tr>
                                <td>
                                    <a href="kb_tag.php?id=<?php echo $tag['id']; ?>" target="_blank">
                                        <?php echo $tag['name']; ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($tag['article_count']); ?></td>
                                <td><?php echo number_format($tag['total_views']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    هیچ برچسبی یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- کاربران فعال در ایجاد محتوا -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">کاربران فعال در ایجاد محتوا</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($activeUsers)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>نام کاربر</th>
                                <th>تعداد مقالات</th>
                                <th>کل بازدید</th>
                                <th>آخرین فعالیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['author_name']; ?></td>
                                <td><?php echo number_format($user['article_count']); ?></td>
                                <td><?php echo number_format($user['total_views']); ?></td>
                                <td><?php echo formatDate($user['last_activity']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    هیچ کاربر فعالی یافت نشد.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- عبارات جستجو شده -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">عبارات جستجو شده توسط کاربران</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($searchTerms)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>عبارت جستجو</th>
                                <th>تعداد جستجو</th>
                                <th>آخرین جستجو</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchTerms as $term): ?>
                            <tr>
                                <td>
                                    <a href="kb_search.php?q=<?php echo urlencode($term['search_query']); ?>" target="_blank">
                                        <?php echo $term['search_query']; ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($term['search_count']); ?></td>
                                <td><?php echo formatDate($term['last_search']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    هیچ جستجویی ثبت نشده است.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ابزارهای گزارش‌گیری -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">ابزارهای گزارش‌گیری</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="kb_export.php?type=stats&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>" class="btn btn-primary btn-block">
                    <i class="fas fa-file-export"></i> دریافت گزارش آماری (Excel)
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="kb_export.php?type=articles" class="btn btn-success btn-block">
                    <i class="fas fa-file-excel"></i> خروجی لیست مقالات
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="kb_export.php?type=comments" class="btn btn-info btn-block">
                    <i class="fas fa-comments"></i> خروجی نظرات کاربران
                </a>
            </div>
        </div>
    </div>
</div>

<!-- کتابخانه ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمودار بازدید روزانه
    const dailyViewsOptions = {
        series: [{
            name: 'تعداد بازدید',
            data: <?php echo json_encode($dailyViewsCounts); ?>
        }],
        chart: {
            height: 300,
            type: 'area',
            fontFamily: 'Tahoma, Arial, sans-serif',
            dir: 'rtl',
            toolbar: {
                show: true
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth'
        },
        xaxis: {
            type: 'datetime',
            categories: <?php echo json_encode($dailyViewsDates); ?>
        },
        yaxis: {
            title: {
                text: 'تعداد بازدید'
            }
        },
        tooltip: {
            x: {
                format: 'yyyy-MM-dd'
            }
        },
        colors: ['#4e73df']
    };
    
    const dailyViewsChart = new ApexCharts(document.querySelector("#daily-views-chart"), dailyViewsOptions);
    dailyViewsChart.render();
    
    // نمودار بازدید ماهانه
    const monthlyViewsOptions = {
        series: [{
            name: 'تعداد بازدید',
            data: <?php echo json_encode($monthlyViewsCounts); ?>
        }],
        chart: {
            height: 300,
            type: 'bar',
            fontFamily: 'Tahoma, Arial, sans-serif',
            dir: 'rtl',
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                dataLabels: {
                    position: 'top'
                }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val.toLocaleString();
            },
            offsetY: -20,
            style: {
                fontSize: '12px',
                colors: ["#304758"]
            }
        },
        xaxis: {
            categories: <?php echo json_encode($monthlyViewsLabels); ?>,
            position: 'bottom',
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            title: {
                text: 'تعداد بازدید'
            }
        },
        colors: ['#36b9cc']
    };
    
    const monthlyViewsChart = new ApexCharts(document.querySelector("#monthly-views-chart"), monthlyViewsOptions);
    monthlyViewsChart.render();
});
</script>

<?php include 'footer.php'; ?>