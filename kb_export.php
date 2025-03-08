<?php
// kb_export.php - خروجی گرفتن از پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت پایگاه دانش
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای خروجی گرفتن از پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// نوع خروجی
$exportType = isset($_GET['type']) ? clean($_GET['type']) : 'articles';

// دوره زمانی برای گزارش آماری
$dateFrom = isset($_GET['date_from']) ? clean($_GET['date_from']) : date('Y-m-01'); // اول ماه جاری
$dateTo = isset($_GET['date_to']) ? clean($_GET['date_to']) : date('Y-m-d'); // امروز

// تنظیم هدرهای HTTP برای دانلود فایل اکسل
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="kb_export_' . $exportType . '_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// تابع خروجی اکسل
function outputExcelHeader() {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<Worksheet ss:Name="Sheet1">' . "\n";
    echo '<Table>' . "\n";
}

function outputExcelFooter() {
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
}

function outputExcelRow($data) {
    echo '<Row>' . "\n";
    foreach ($data as $value) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($value) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
}

// شروع خروجی اکسل
outputExcelHeader();

// بر اساس نوع خروجی
switch ($exportType) {
    case 'stats':
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
        
        // عنوان‌های اکسل
        outputExcelRow(['آمار کلی پایگاه دانش', 'مقدار']);
        
        // داده‌های آمار کلی
        outputExcelRow(['تعداد کل مقالات', $generalStats['total_articles']]);
        outputExcelRow(['مقالات منتشر شده', $generalStats['published_articles']]);
        outputExcelRow(['پیش‌نویس‌ها', $generalStats['draft_articles']]);
        outputExcelRow(['مقالات آرشیو شده', $generalStats['archived_articles']]);
        outputExcelRow(['تعداد دسته‌بندی‌ها', $generalStats['total_categories']]);
        outputExcelRow(['تعداد برچسب‌ها', $generalStats['total_tags']]);
        outputExcelRow(['تعداد کل نظرات', $generalStats['total_comments']]);
        outputExcelRow(['نظرات تأیید شده', $generalStats['approved_comments']]);
        outputExcelRow(['نظرات در انتظار تأیید', $generalStats['pending_comments']]);
        outputExcelRow(['تعداد پیوست‌ها', $generalStats['total_attachments']]);
        outputExcelRow(['تعداد کل دانلودها', $generalStats['total_downloads']]);
        outputExcelRow(['تعداد کل بازدیدها', $generalStats['total_views']]);
        
        // خط خالی
        outputExcelRow(['', '']);
        
        // بخش آمار بازدید روزانه
        outputExcelRow(['آمار بازدید روزانه از تاریخ ' . $dateFrom . ' تا تاریخ ' . $dateTo, '']);
        outputExcelRow(['تاریخ', 'تعداد بازدید']);
        
        foreach ($dailyViews as $view) {
            outputExcelRow([$view['view_date'], $view['view_count']]);
        }
        
        // آمار محبوب‌ترین مقالات
        $stmt = $pdo->prepare("SELECT a.title, a.views_count, 
                             (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count,
                             (SELECT COALESCE(AVG(rating), 0) FROM kb_ratings WHERE article_id = a.id) as avg_rating,
                             (SELECT COUNT(*) FROM kb_ratings WHERE article_id = a.id) as rating_count
                             FROM kb_articles a 
                             WHERE a.company_id = ? AND a.status = 'published' 
                             ORDER BY a.views_count DESC 
                             LIMIT 20");
        $stmt->execute([$companyId]);
        $popularArticles = $stmt->fetchAll();
        
        // خط خالی
        outputExcelRow(['', '']);
        
        // بخش محبوب‌ترین مقالات
        outputExcelRow(['20 مقاله محبوب', '', '', '']);
        outputExcelRow(['عنوان مقاله', 'تعداد بازدید', 'میانگین امتیاز', 'تعداد نظرات']);
        
        foreach ($popularArticles as $article) {
            outputExcelRow([
                $article['title'], 
                $article['views_count'], 
                number_format($article['avg_rating'], 1), 
                $article['comment_count']
            ]);
        }
        
        break;
        
    case 'articles':
        // خروجی تمام مقالات
        $stmt = $pdo->prepare("SELECT a.id, a.title, a.slug, a.status, a.is_featured, a.is_public, 
                             a.excerpt, a.content, a.views_count, DATE(a.created_at) as created_date, 
                             DATE(a.published_at) as published_date, 
                             CONCAT(p.first_name, ' ', p.last_name) as creator_name
                             FROM kb_articles a
                             LEFT JOIN personnel p ON a.created_by = p.id
                             WHERE a.company_id = ?
                             ORDER BY a.id");
        $stmt->execute([$companyId]);
        $articles = $stmt->fetchAll();
        
        // عنوان‌های اکسل
        outputExcelRow([
            'شناسه', 
            'عنوان', 
            'اسلاگ', 
            'وضعیت', 
            'ویژه', 
            'عمومی', 
            'خلاصه', 
            'محتوا', 
            'بازدید', 
            'تاریخ ایجاد', 
            'تاریخ انتشار', 
            'ایجاد کننده',
            'دسته‌بندی‌ها',
            'برچسب‌ها'
        ]);
        
        foreach ($articles as $article) {
            // دریافت دسته‌بندی‌های مقاله
            $stmt = $pdo->prepare("SELECT c.name 
                                  FROM kb_categories c
                                  JOIN kb_article_categories ac ON c.id = ac.category_id
                                  WHERE ac.article_id = ?
                                  ORDER BY c.name");
            $stmt->execute([$article['id']]);
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $categoryList = implode(', ', $categories);
            
            // دریافت برچسب‌های مقاله
            $stmt = $pdo->prepare("SELECT t.name 
                                  FROM kb_tags t
                                  JOIN kb_article_tags at ON t.id = at.tag_id
                                  WHERE at.article_id = ?
                                  ORDER BY t.name");
            $stmt->execute([$article['id']]);
            $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $tagList = implode(', ', $tags);
            
            outputExcelRow([
                $article['id'],
                $article['title'],
                $article['slug'],
                $article['status'],
                $article['is_featured'] ? 'بله' : 'خیر',
                $article['is_public'] ? 'بله' : 'خیر',
                $article['excerpt'],
                strip_tags($article['content']), // حذف تگ‌های HTML از محتوا
                $article['views_count'],
                $article['created_date'],
                $article['published_date'],
                $article['creator_name'],
                $categoryList,
                $tagList
            ]);
        }
        
        break;
        
    case 'comments':
        // خروجی نظرات
        $stmt = $pdo->prepare("SELECT c.id, c.comment, c.status, c.author_name, c.ip_address, 
                             DATE(c.created_at) as comment_date, a.title as article_title
                             FROM kb_comments c
                             JOIN kb_articles a ON c.article_id = a.id
                             WHERE a.company_id = ?
                             ORDER BY c.created_at DESC");
        $stmt->execute([$companyId]);
        $comments = $stmt->fetchAll();
        
        // عنوان‌های اکسل
        outputExcelRow([
            'شناسه', 
            'نویسنده', 
            'متن نظر', 
            'وضعیت', 
            'تاریخ ارسال', 
            'عنوان مقاله', 
            'آی‌پی'
        ]);
        
        foreach ($comments as $comment) {
            outputExcelRow([
                $comment['id'],
                $comment['author_name'],
                $comment['comment'],
                $comment['status'],
                $comment['comment_date'],
                $comment['article_title'],
                $comment['ip_address']
            ]);
        }
        
        break;
        
    case 'categories':
        // خروجی دسته‌بندی‌ها
        $stmt = $pdo->prepare("SELECT c.id, c.name, c.description, c.icon, c.sort_order, 
                             pc.name as parent_name, 
                             (SELECT COUNT(*) FROM kb_article_categories ac 
                              JOIN kb_articles a ON ac.article_id = a.id 
                              WHERE ac.category_id = c.id AND a.status = 'published') as article_count
                             FROM kb_categories c
                             LEFT JOIN kb_categories pc ON c.parent_id = pc.id
                             WHERE c.company_id = ?
                             ORDER BY c.sort_order, c.name");
        $stmt->execute([$companyId]);
        $categories = $stmt->fetchAll();
        
        // عنوان‌های اکسل
        outputExcelRow([
            'شناسه', 
            'نام دسته‌بندی', 
            'دسته‌بندی والد', 
            'توضیحات', 
            'آیکون', 
            'ترتیب نمایش', 
            'تعداد مقالات'
        ]);
        
        foreach ($categories as $category) {
            outputExcelRow([
                $category['id'],
                $category['name'],
                $category['parent_name'] ?: '-',
                $category['description'],
                $category['icon'],
                $category['sort_order'],
                $category['article_count']
            ]);
        }
        
        break;
        
    case 'tags':
        // خروجی برچسب‌ها
        $stmt = $pdo->prepare("SELECT t.id, t.name, DATE(t.created_at) as created_date,
                             (SELECT COUNT(*) FROM kb_article_tags at 
                              JOIN kb_articles a ON at.article_id = a.id 
                              WHERE at.tag_id = t.id AND a.status = 'published') as article_count
                             FROM kb_tags t
                             WHERE t.company_id = ?
                             ORDER BY t.name");
        $stmt->execute([$companyId]);
        $tags = $stmt->fetchAll();
        
        // عنوان‌های اکسل
        outputExcelRow([
            'شناسه', 
            'نام برچسب', 
            'تاریخ ایجاد', 
            'تعداد مقالات'
        ]);
        
        foreach ($tags as $tag) {
            outputExcelRow([
                $tag['id'],
                $tag['name'],
                $tag['created_date'],
                $tag['article_count']
            ]);
        }
        
        break;

    case 'single_article':
        // خروجی یک مقاله خاص
        $articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$articleId) {
            outputExcelRow(['خطا: شناسه مقاله نامعتبر است.']);
            break;
        }
        
        // بررسی دسترسی به مقاله
        $stmt = $pdo->prepare("SELECT a.id, a.title, a.slug, a.status, a.is_featured, a.is_public, 
                             a.excerpt, a.content, a.views_count, DATE(a.created_at) as created_date, 
                             DATE(a.published_at) as published_date, 
                             CONCAT(p.first_name, ' ', p.last_name) as creator_name
                             FROM kb_articles a
                             LEFT JOIN personnel p ON a.created_by = p.id
                             WHERE a.id = ? AND a.company_id = ?");
        $stmt->execute([$articleId, $companyId]);
        $article = $stmt->fetch();
        
        if (!$article) {
            outputExcelRow(['خطا: مقاله مورد نظر یافت نشد یا شما دسترسی لازم را ندارید.']);
            break;
        }
        
        // اطلاعات اصلی مقاله
        outputExcelRow(['اطلاعات مقاله', $article['title']]);
        outputExcelRow(['شناسه', $article['id']]);
        outputExcelRow(['عنوان', $article['title']]);
        outputExcelRow(['اسلاگ', $article['slug']]);
        outputExcelRow(['وضعیت', $article['status']]);
        outputExcelRow(['ویژه', $article['is_featured'] ? 'بله' : 'خیر']);
        outputExcelRow(['عمومی', $article['is_public'] ? 'بله' : 'خیر']);
        outputExcelRow(['خلاصه', $article['excerpt']]);
        outputExcelRow(['بازدید', $article['views_count']]);
        outputExcelRow(['تاریخ ایجاد', $article['created_date']]);
        outputExcelRow(['تاریخ انتشار', $article['published_date']]);
        outputExcelRow(['ایجاد کننده', $article['creator_name']]);
        
        // دریافت دسته‌بندی‌های مقاله
        $stmt = $pdo->prepare("SELECT c.name 
                              FROM kb_categories c
                              JOIN kb_article_categories ac ON c.id = ac.category_id
                              WHERE ac.article_id = ?
                              ORDER BY c.name");
        $stmt->execute([$articleId]);
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $categoryList = implode(', ', $categories);
        
        outputExcelRow(['دسته‌بندی‌ها', $categoryList]);
        
        // دریافت برچسب‌های مقاله
        $stmt = $pdo->prepare("SELECT t.name 
                              FROM kb_tags t
                              JOIN kb_article_tags at ON t.id = at.tag_id
                              WHERE at.article_id = ?
                              ORDER BY t.name");
        $stmt->execute([$articleId]);
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $tagList = implode(', ', $tags);
        
        outputExcelRow(['برچسب‌ها', $tagList]);
        
        // خط خالی
        outputExcelRow(['', '']);
        
        // محتوای مقاله
        outputExcelRow(['محتوای مقاله', '']);
        outputExcelRow([strip_tags($article['content'])]);
        
        // خط خالی
        outputExcelRow(['', '']);
        
        // نظرات مقاله
        $stmt = $pdo->prepare("SELECT c.author_name, c.comment, c.status, DATE(c.created_at) as comment_date 
                              FROM kb_comments c
                              WHERE c.article_id = ?
                              ORDER BY c.created_at DESC");
        $stmt->execute([$articleId]);
        $comments = $stmt->fetchAll();
        
        outputExcelRow(['نظرات مقاله', '']);
        outputExcelRow(['نویسنده', 'متن نظر', 'وضعیت', 'تاریخ']);
        
        foreach ($comments as $comment) {
            outputExcelRow([
                $comment['author_name'],
                $comment['comment'],
                $comment['status'],
                $comment['comment_date']
            ]);
        }
        
        break;
        
    default:
        outputExcelRow(['خطا: نوع خروجی نامعتبر است.']);
        break;
}

// پایان خروجی اکسل
outputExcelFooter();