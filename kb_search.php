<?php
// kb_search.php - جستجو در پایگاه دانش
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

// دریافت عبارت جستجو
$searchQuery = isset($_GET['q']) ? clean($_GET['q']) : '';

// فیلترهای جستجو
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$tagFilter = isset($_GET['tag']) ? (int)$_GET['tag'] : 0;

// صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// نتایج جستجو
$searchResults = [];
$totalResults = 0;

if (!empty($searchQuery) || $categoryFilter > 0 || $tagFilter > 0) {
    // ساخت کوئری جستجو با فیلترها
    $query = "SELECT a.*, 
              CONCAT(p.first_name, ' ', p.last_name) as creator_name,
              COALESCE(
                  (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                  0
              ) as average_rating,
              (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count,
              MATCH(a.title, a.content, a.excerpt) AGAINST (? IN BOOLEAN MODE) as relevance
              FROM kb_articles a
              LEFT JOIN users u ON a.created_by = u.id
              LEFT JOIN personnel p ON u.id = p.user_id";
    
    $countQuery = "SELECT COUNT(*) FROM kb_articles a";
    
    $params = [];
    $countParams = [];
    
    // اگر فیلتر دسته‌بندی انتخاب شده است
    if ($categoryFilter > 0) {
        $query .= " JOIN kb_article_categories ac ON a.id = ac.article_id";
        $countQuery .= " JOIN kb_article_categories ac ON a.id = ac.article_id";
    }
    
    // اگر فیلتر برچسب انتخاب شده است
    if ($tagFilter > 0) {
        $query .= " JOIN kb_article_tags at ON a.id = at.article_id";
        $countQuery .= " JOIN kb_article_tags at ON a.id = at.article_id";
    }
    
    // بخش شرایط کوئری
    $query .= " WHERE a.company_id = ? AND a.status = 'published'";
    $countQuery .= " WHERE a.company_id = ? AND a.status = 'published'";
    
    $params[] = $companyId;
    $countParams[] = $companyId;
    
    // افزودن شرط جستجو
    if (!empty($searchQuery)) {
        $query .= " AND (
                      MATCH(a.title, a.content, a.excerpt) AGAINST (? IN BOOLEAN MODE)
                      OR a.title LIKE ?
                      OR a.content LIKE ?
                      OR a.excerpt LIKE ?
                  )";
        $countQuery .= " AND (
                          MATCH(a.title, a.content, a.excerpt) AGAINST (? IN BOOLEAN MODE)
                          OR a.title LIKE ?
                          OR a.content LIKE ?
                          OR a.excerpt LIKE ?
                      )";
        
        $likeQuery = "%$searchQuery%";
        $params = array_merge($params, [$searchQuery, $likeQuery, $likeQuery, $likeQuery]);
        $countParams = array_merge($countParams, [$searchQuery, $likeQuery, $likeQuery, $likeQuery]);
    }
    
    // افزودن فیلتر دسته‌بندی
    if ($categoryFilter > 0) {
        $query .= " AND ac.category_id = ?";
        $countQuery .= " AND ac.category_id = ?";
        $params[] = $categoryFilter;
        $countParams[] = $categoryFilter;
    }
    
    // افزودن فیلتر برچسب
    if ($tagFilter > 0) {
        $query .= " AND at.tag_id = ?";
        $countQuery .= " AND at.tag_id = ?";
        $params[] = $tagFilter;
        $countParams[] = $tagFilter;
    }
    
    // مرتب‌سازی و حد نتایج
    if (!empty($searchQuery)) {
        $query .= " ORDER BY relevance DESC, a.views_count DESC, a.published_at DESC";
    } else {
        $query .= " ORDER BY a.is_featured DESC, a.published_at DESC";
    }
    
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    // اجرای کوئری شمارش نتایج
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $totalResults = $stmt->fetchColumn();
    
    // اجرای کوئری جستجو
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll();
    
    // دریافت برچسب‌ها و دسته‌بندی‌های هر مقاله
    foreach ($searchResults as &$article) {
        // دریافت دسته‌بندی‌های مقاله
        $stmt = $pdo->prepare("SELECT c.id, c.name 
                              FROM kb_categories c
                              JOIN kb_article_categories ac ON c.id = ac.category_id
                              WHERE ac.article_id = ?
                              ORDER BY c.name");
        $stmt->execute([$article['id']]);
        $article['categories'] = $stmt->fetchAll();
        
        // دریافت برچسب‌های مقاله
        $stmt = $pdo->prepare("SELECT t.id, t.name 
                              FROM kb_tags t
                              JOIN kb_article_tags at ON t.id = at.tag_id
                              WHERE at.article_id = ?
                              ORDER BY t.name");
        $stmt->execute([$article['id']]);
        $article['tags'] = $stmt->fetchAll();
    }
}

// محاسبه تعداد صفحات
$totalPages = ceil($totalResults / $perPage);

// دریافت دسته‌بندی‌های فعال
$stmt = $pdo->prepare("SELECT c.*, 
                      (SELECT COUNT(*) FROM kb_article_categories ac 
                       JOIN kb_articles a ON ac.article_id = a.id 
                       WHERE ac.category_id = c.id AND a.status = 'published') as article_count
                      FROM kb_categories c
                      WHERE c.company_id = ? AND c.is_active = 1 AND c.parent_id IS NULL
                      HAVING article_count > 0
                      ORDER BY c.sort_order, c.name");
$stmt->execute([$companyId]);
$categories = $stmt->fetchAll();

// دریافت برچسب‌های پرکاربرد
$tags = kb_getAllTags();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>جستجو در پایگاه دانش</h1>
    <a href="kb_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<!-- فرم جستجو -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="kb_search.php">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control form-control-lg" name="q" placeholder="جستجو در پایگاه دانش..." value="<?php echo $searchQuery; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> جستجو
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <select class="form-select" name="category" onchange="this.form.submit()">
                        <option value="0">همه دسته‌بندی‌ها</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $categoryFilter) ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?> (<?php echo number_format($category['article_count']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <select class="form-select" name="tag" onchange="this.form.submit()">
                        <option value="0">همه برچسب‌ها</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo $tag['id']; ?>" <?php echo ($tag['id'] == $tagFilter) ? 'selected' : ''; ?>>
                            <?php echo $tag['name']; ?> (<?php echo number_format($tag['article_count']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- نتایج جستجو -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">نتایج جستجو (<?php echo number_format($totalResults); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($searchResults)): ?>
        <div class="search-results mb-4">
            <?php foreach ($searchResults as $article): ?>
            <div class="search-result mb-4 pb-3 border-bottom">
                <h3 class="h5 mb-2">
                    <a href="kb_article.php?id=<?php echo $article['id']; ?>" class="text-decoration-none">
                        <?php echo $article['title']; ?>
                    </a>
                    <?php if ($article['is_featured']): ?>
                    <span class="badge bg-warning ms-1">ویژه</span>
                    <?php endif; ?>
                </h3>
                
                <div class="search-result-meta mb-2 d-flex flex-wrap">
                    <small class="text-muted me-3">
                        <i class="fas fa-user me-1"></i> <?php echo $article['creator_name']; ?>
                    </small>
                    <small class="text-muted me-3">
                        <i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($article['published_at']); ?>
                    </small>
                    <small class="text-muted me-3">
                        <i class="fas fa-eye me-1"></i> <?php echo number_format($article['views_count']); ?> بازدید
                    </small>
                    <?php if ($article['comment_count'] > 0): ?>
                    <small class="text-muted me-3">
                        <i class="fas fa-comments me-1"></i> <?php echo number_format($article['comment_count']); ?> نظر
                    </small>
                    <?php endif; ?>
                    
                    <?php if ($article['average_rating'] > 0): ?>
                    <small class="me-3">
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
                    </small>
                    <?php endif; ?>
                </div>
                
                <p class="search-result-excerpt mb-2">
                    <?php 
                    // اگر عبارت جستجو موجود باشد، متن حاوی آن را نمایش دهیم
                    if (!empty($searchQuery) && !empty($article['content'])) {
                        $content = strip_tags($article['content']);
                        
                        // یافتن موقعیت عبارت جستجو
                        $pos = mb_stripos($content, $searchQuery);
                        
                        if ($pos !== false) {
                            // نمایش بخشی از متن حاوی عبارت جستجو
                            $start = max(0, $pos - 50);
                            $length = 200;
                            $excerpt = mb_substr($content, $start, $length);
                            
                            // افزودن ... در صورت برش متن
                            if ($start > 0) {
                                $excerpt = '...' . $excerpt;
                            }
                            
                            if ($start + $length < mb_strlen($content)) {
                                $excerpt .= '...';
                            }
                            
                            // هایلایت کردن عبارت جستجو
                            echo preg_replace('/(' . preg_quote($searchQuery, '/') . ')/i', '<mark>$1</mark>', $excerpt);
                        } else {
                            // اگر عبارت جستجو در متن اصلی یافت نشد، از خلاصه استفاده کنیم
                            echo $article['excerpt'] ? $article['excerpt'] : mb_substr($content, 0, 200) . '...';
                        }
                    } else {
                        // اگر عبارت جستجو وجود ندارد، خلاصه معمولی نمایش داده شود
                        echo $article['excerpt'] ? $article['excerpt'] : mb_substr(strip_tags($article['content']), 0, 200) . '...';
                    }
                    ?>
                </p>
                
                <div class="search-result-meta d-flex flex-wrap">
                    <?php if (!empty($article['categories'])): ?>
                    <div class="me-3">
                        <i class="fas fa-folder me-1 text-muted"></i>
                        <?php foreach ($article['categories'] as $index => $category): ?>
                            <a href="kb_category.php?id=<?php echo $category['id']; ?>" class="badge bg-secondary text-decoration-none">
                                <?php echo $category['name']; ?>
                            </a>
                            <?php echo ($index < count($article['categories']) - 1) ? ' ' : ''; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($article['tags'])): ?>
                    <div>
                        <i class="fas fa-tags me-1 text-muted"></i>
                        <?php foreach ($article['tags'] as $index => $tag): ?>
                            <a href="kb_tag.php?id=<?php echo $tag['id']; ?>" class="badge bg-info text-decoration-none">
                                <?php echo $tag['name']; ?>
                            </a>
                            <?php echo ($index < count($article['tags']) - 1) ? ' ' : ''; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&tag=<?php echo $tagFilter; ?>&page=1">ابتدا</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&tag=<?php echo $tagFilter; ?>&page=<?php echo $page - 1; ?>">قبلی</a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&tag=<?php echo $tagFilter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&tag=<?php echo $tagFilter; ?>&page=<?php echo $page + 1; ?>">بعدی</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&category=<?php echo $categoryFilter; ?>&tag=<?php echo $tagFilter; ?>&page=<?php echo $totalPages; ?>">انتها</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php elseif (!empty($searchQuery) || $categoryFilter > 0 || $tagFilter > 0): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> هیچ نتیجه‌ای یافت نشد.
            <hr>
            <p class="mb-0">پیشنهادات:</p>
            <ul class="mb-0 mt-2">
                <li>املای کلمات را بررسی کنید.</li>
                <li>از کلمات کلیدی دیگری استفاده کنید.</li>
                <li>فیلترهای دسته‌بندی و برچسب را حذف کنید.</li>
                <li>عبارت جستجوی کوتاه‌تری وارد کنید.</li>
            </ul>
            
            <div class="mt-3">
                <a href="kb_dashboard.php" class="btn btn-outline-primary">بازگشت به صفحه اصلی</a>
                <a href="kb_search.php" class="btn btn-outline-secondary ms-2">پاک کردن فیلترها</a>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-search me-2"></i> لطفاً عبارت جستجو را وارد کنید یا از فیلترهای بالا استفاده کنید.
        </div>
        
        <!-- راهنمای جستجو -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">راهنمای جستجو</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="mb-0">
                            <li>کلمات کلیدی اصلی را وارد کنید.</li>
                            <li>جستجو در عنوان، خلاصه و محتوای مقالات انجام می‌شود.</li>
                            <li>می‌توانید از منوی دسته‌بندی‌ها برای محدود کردن نتایج استفاده کنید.</li>
                            <li>برای جستجوی دقیق‌تر، می‌توانید از برچسب‌ها استفاده کنید.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>دسته‌بندی‌های اصلی:</h6>
                        <div class="d-flex flex-wrap">
                            <?php foreach ($categories as $category): ?>
                            <a href="kb_category.php?id=<?php echo $category['id']; ?>" class="badge bg-secondary me-2 mb-2 text-decoration-none">
                                <?php echo $category['name']; ?> (<?php echo number_format($category['article_count']); ?>)
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.search-result mark {
    background-color: #ffeb3b;
    padding: 0;
}
</style>

<?php include 'footer.php'; ?>