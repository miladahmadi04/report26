<?php
// kb_tag.php - نمایش مقالات یک برچسب
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

// دریافت شناسه برچسب
$tagId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tagId) {
    $_SESSION['error_message'] = 'برچسب مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// دریافت اطلاعات برچسب
$stmt = $pdo->prepare("SELECT t.*, 
                      (SELECT COUNT(*) FROM kb_article_tags at 
                       JOIN kb_articles a ON at.article_id = a.id 
                       WHERE at.tag_id = t.id AND a.status = 'published') as article_count
                      FROM kb_tags t
                      WHERE t.id = ? AND t.company_id = ?");
$stmt->execute([$tagId, $companyId]);
$tag = $stmt->fetch();

if (!$tag) {
    $_SESSION['error_message'] = 'برچسب مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// صفحه‌بندی
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// دریافت مقالات برچسب
$articles = kb_getTagArticles($tagId, $perPage, $offset);

// دریافت تعداد کل مقالات
$totalArticles = $tag['article_count'];

// محاسبه تعداد صفحات
$totalPages = ceil($totalArticles / $perPage);

// دریافت برچسب‌های مرتبط
$stmt = $pdo->prepare("SELECT t.id, t.name, COUNT(at1.article_id) as common_count
                      FROM kb_tags t
                      JOIN kb_article_tags at1 ON t.id = at1.tag_id
                      JOIN kb_article_tags at2 ON at1.article_id = at2.article_id
                      WHERE at2.tag_id = ? AND t.id != ?
                      GROUP BY t.id
                      ORDER BY common_count DESC
                      LIMIT 10");
$stmt->execute([$tagId, $tagId]);
$relatedTags = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="kb_dashboard.php">پایگاه دانش</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <i class="fas fa-tag me-1"></i> <?php echo $tag['name']; ?>
            </li>
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

<!-- اطلاعات برچسب -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-1 text-center">
                <div class="display-4 text-muted">
                    <i class="fas fa-tag"></i>
                </div>
            </div>
            <div class="col-md-11">
                <h1 class="card-title h2">برچسب: <?php echo $tag['name']; ?></h1>
                <div class="d-flex">
                    <span class="badge bg-primary">
                        <i class="fas fa-file-alt me-1"></i> <?php echo number_format($tag['article_count']); ?> مقاله
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- برچسب‌های مرتبط -->
<?php if (!empty($relatedTags)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tags me-1"></i> برچسب‌های مرتبط</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            <?php foreach ($relatedTags as $relatedTag): ?>
            <a href="kb_tag.php?id=<?php echo $relatedTag['id']; ?>" class="btn btn-outline-secondary me-2 mb-2">
                <?php echo $relatedTag['name']; ?> 
                <span class="badge bg-secondary"><?php echo number_format($relatedTag['common_count']); ?></span>
            </a>
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
                    <a class="page-link" href="?id=<?php echo $tagId; ?>&page=1">ابتدا</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $tagId; ?>&page=<?php echo $page - 1; ?>">قبلی</a>
                </li>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?id=<?php echo $tagId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $tagId; ?>&page=<?php echo $page + 1; ?>">بعدی</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?id=<?php echo $tagId; ?>&page=<?php echo $totalPages; ?>">انتها</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="alert alert-info mb-0">
            هیچ مقاله‌ای با این برچسب یافت نشد.
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