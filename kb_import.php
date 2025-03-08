<?php
// kb_import.php - وارد کردن مقالات به پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت پایگاه دانش
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای وارد کردن مقالات به پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// فرمت‌های مجاز برای وارد کردن
$allowedFormats = ['csv', 'xlsx', 'json', 'html'];
$importResults = [];

// پردازش فایل آپلود شده
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_articles'])) {
    $importFormat = isset($_POST['import_format']) ? clean($_POST['import_format']) : '';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $defaultStatus = isset($_POST['default_status']) ? clean($_POST['default_status']) : 'draft';
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] == 1;
    
    // بررسی فرمت انتخاب شده
    if (!in_array($importFormat, $allowedFormats)) {
        $message = showError('لطفاً یک فرمت معتبر برای وارد کردن انتخاب کنید.');
    }
    // بررسی آپلود فایل
    else if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $message = showError('خطا در آپلود فایل. لطفاً دوباره تلاش کنید.');
    } else {
        $file = $_FILES['import_file'];
        $fileTmpPath = $file['tmp_name'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        
        // بررسی پسوند فایل
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        if (($importFormat === 'csv' && $fileExtension !== 'csv') ||
            ($importFormat === 'xlsx' && $fileExtension !== 'xlsx' && $fileExtension !== 'xls') ||
            ($importFormat === 'json' && $fileExtension !== 'json') ||
            ($importFormat === 'html' && $fileExtension !== 'html' && $fileExtension !== 'htm')) {
            $message = showError('پسوند فایل با فرمت انتخاب شده مطابقت ندارد.');
        } else {
            try {
                // شروع تراکنش
                $pdo->beginTransaction();
                
                $importedCount = 0;
                $skippedCount = 0;
                $errorCount = 0;
                $importResults = [];
                
                // پردازش فایل بر اساس فرمت
                switch ($importFormat) {
                    case 'csv':
                        $importResults = importFromCSV($fileTmpPath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite);
                        break;
                        
                    case 'xlsx':
                        $importResults = importFromExcel($fileTmpPath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite);
                        break;
                        
                    case 'json':
                        $importResults = importFromJSON($fileTmpPath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite);
                        break;
                        
                    case 'html':
                        $importResults = importFromHTML($fileTmpPath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite);
                        break;
                }
                
                // پایان تراکنش
                $pdo->commit();
                
                // نمایش نتایج
                $importedCount = $importResults['imported'];
                $skippedCount = $importResults['skipped'];
                $errorCount = $importResults['errors'];
                
                $message = showSuccess("وارد کردن مقالات با موفقیت انجام شد. تعداد مقالات وارد شده: $importedCount، رد شده: $skippedCount، خطا: $errorCount");
            } catch (Exception $e) {
                // برگرداندن تراکنش در صورت بروز خطا
                $pdo->rollBack();
                $message = showError('خطا در وارد کردن مقالات: ' . $e->getMessage());
            }
        }
    }
}

// وارد کردن از فایل CSV
function importFromCSV($filePath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite) {
    global $pdo;
    
    // بررسی وجود فایل
    if (!file_exists($filePath)) {
        throw new Exception('فایل مورد نظر یافت نشد.');
    }
    
    // خواندن فایل CSV
    $csvFile = fopen($filePath, 'r');
    if (!$csvFile) {
        throw new Exception('خطا در باز کردن فایل CSV.');
    }
    
    // خواندن سطر اول (هدرها)
    $headers = fgetcsv($csvFile);
    if (!$headers) {
        fclose($csvFile);
        throw new Exception('فایل CSV خالی یا نامعتبر است.');
    }
    
    // تبدیل هدرها به ایندکس برای دسترسی آسان‌تر
    $headerIndexes = [];
    foreach ($headers as $index => $header) {
        $headerIndexes[trim(strtolower($header))] = $index;
    }
    
    // بررسی وجود ستون‌های اصلی
    $requiredColumns = ['title', 'content'];
    foreach ($requiredColumns as $column) {
        if (!isset($headerIndexes[$column])) {
            fclose($csvFile);
            throw new Exception("ستون اجباری '$column' در فایل CSV یافت نشد.");
        }
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $errorMessages = [];
    
    // آماده‌سازی استیتمنت‌های SQL
    $checkArticleStmt = $pdo->prepare("SELECT id FROM kb_articles WHERE title = ? AND company_id = ?");
    $insertArticleStmt = $pdo->prepare("INSERT INTO kb_articles 
                                       (company_id, title, slug, content, excerpt, status, 
                                        is_featured, is_public, created_by, published_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertCategoryStmt = $pdo->prepare("INSERT INTO kb_article_categories 
                                        (article_id, category_id) VALUES (?, ?)");
    $insertTagStmt = $pdo->prepare("INSERT INTO kb_article_tags 
                                   (article_id, tag_id) VALUES (?, ?)");
    $checkTagStmt = $pdo->prepare("SELECT id FROM kb_tags WHERE name = ? AND company_id = ?");
    $createTagStmt = $pdo->prepare("INSERT INTO kb_tags (company_id, name) VALUES (?, ?)");
    
    // پردازش سطرهای داده
    while (($row = fgetcsv($csvFile)) !== false) {
        try {
            // استخراج داده‌های اصلی
            $title = isset($headerIndexes['title']) && isset($row[$headerIndexes['title']]) ? 
                    trim($row[$headerIndexes['title']]) : '';
            $content = isset($headerIndexes['content']) && isset($row[$headerIndexes['content']]) ? 
                       trim($row[$headerIndexes['content']]) : '';
            
            // اگر عنوان یا محتوا خالی باشد، رد کن
            if (empty($title) || empty($content)) {
                $skipped++;
                continue;
            }
            
            // بررسی وجود مقاله با همین عنوان
            $checkArticleStmt->execute([$title, $companyId]);
            $existingArticle = $checkArticleStmt->fetch();
            
            if ($existingArticle && !$overwrite) {
                $skipped++;
                continue;
            }
            
            // استخراج سایر داده‌ها
            $excerpt = isset($headerIndexes['excerpt']) && isset($row[$headerIndexes['excerpt']]) ? 
                       trim($row[$headerIndexes['excerpt']]) : '';
            $status = isset($headerIndexes['status']) && isset($row[$headerIndexes['status']]) ? 
                      trim($row[$headerIndexes['status']]) : $defaultStatus;
            $isFeatured = isset($headerIndexes['is_featured']) && isset($row[$headerIndexes['is_featured']]) ? 
                          (strtolower(trim($row[$headerIndexes['is_featured']])) === 'yes' || 
                           strtolower(trim($row[$headerIndexes['is_featured']])) === 'true' || 
                           trim($row[$headerIndexes['is_featured']]) === '1') : false;
            $isPublic = isset($headerIndexes['is_public']) && isset($row[$headerIndexes['is_public']]) ? 
                        (strtolower(trim($row[$headerIndexes['is_public']])) === 'yes' || 
                         strtolower(trim($row[$headerIndexes['is_public']])) === 'true' || 
                         trim($row[$headerIndexes['is_public']]) === '1') : false;
            $tagsString = isset($headerIndexes['tags']) && isset($row[$headerIndexes['tags']]) ? 
                          trim($row[$headerIndexes['tags']]) : '';
            
            // ساخت اسلاگ
            $slug = kb_generateSlug($title);
            $slug = kb_ensureUniqueSlug($slug);
            
            // تنظیم تاریخ انتشار
            $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            
            // درج مقاله
            $insertArticleStmt->execute([
                $companyId, $title, $slug, $content, $excerpt, $status,
                $isFeatured ? 1 : 0, $isPublic ? 1 : 0, $userId, $publishedAt
            ]);
            
            $articleId = $pdo->lastInsertId();
            
            // افزودن دسته‌بندی
            if ($categoryId > 0) {
                $insertCategoryStmt->execute([$articleId, $categoryId]);
            }
            
            // پردازش برچسب‌ها
            if (!empty($tagsString)) {
                $tagNames = explode(',', $tagsString);
                
                foreach ($tagNames as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;
                    
                    // بررسی وجود برچسب
                    $checkTagStmt->execute([$tagName, $companyId]);
                    $tag = $checkTagStmt->fetch();
                    
                    if ($tag) {
                        $tagId = $tag['id'];
                    } else {
                        // ایجاد برچسب جدید
                        $createTagStmt->execute([$companyId, $tagName]);
                        $tagId = $pdo->lastInsertId();
                    }
                    
                    // ارتباط برچسب با مقاله
                    $insertTagStmt->execute([$articleId, $tagId]);
                }
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors++;
            $errorMessages[] = "خطا در پردازش مقاله: " . $e->getMessage();
        }
    }
    
    fclose($csvFile);
    
    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_messages' => $errorMessages
    ];
}

// وارد کردن از فایل اکسل
function importFromExcel($filePath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite) {
    // نیاز به کتابخانه PHPExcel یا PhpSpreadsheet دارد
    // در اینجا یک پیاده‌سازی ساده بدون استفاده از کتابخانه‌ها ارائه می‌شود
    
    return [
        'imported' => 0,
        'skipped' => 0,
        'errors' => 1,
        'error_messages' => ['وارد کردن فایل اکسل نیاز به نصب کتابخانه PhpSpreadsheet دارد.']
    ];
}

// وارد کردن از فایل JSON
function importFromJSON($filePath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite) {
    global $pdo;
    
    // بررسی وجود فایل
    if (!file_exists($filePath)) {
        throw new Exception('فایل مورد نظر یافت نشد.');
    }
    
    // خواندن محتوای فایل JSON
    $jsonContent = file_get_contents($filePath);
    if (!$jsonContent) {
        throw new Exception('خطا در خواندن فایل JSON.');
    }
    
    // تبدیل محتوا به آرایه
    $articles = json_decode($jsonContent, true);
    if (!$articles || !is_array($articles)) {
        throw new Exception('فرمت JSON نامعتبر است.');
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $errorMessages = [];
    
    // آماده‌سازی استیتمنت‌های SQL
    $checkArticleStmt = $pdo->prepare("SELECT id FROM kb_articles WHERE title = ? AND company_id = ?");
    $insertArticleStmt = $pdo->prepare("INSERT INTO kb_articles 
                                       (company_id, title, slug, content, excerpt, status, 
                                        is_featured, is_public, created_by, published_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertCategoryStmt = $pdo->prepare("INSERT INTO kb_article_categories 
                                        (article_id, category_id) VALUES (?, ?)");
    $insertTagStmt = $pdo->prepare("INSERT INTO kb_article_tags 
                                   (article_id, tag_id) VALUES (?, ?)");
    $checkTagStmt = $pdo->prepare("SELECT id FROM kb_tags WHERE name = ? AND company_id = ?");
    $createTagStmt = $pdo->prepare("INSERT INTO kb_tags (company_id, name) VALUES (?, ?)");
    
    // پردازش مقالات
    foreach ($articles as $article) {
        try {
            // بررسی وجود فیلدهای اجباری
            if (!isset($article['title']) || !isset($article['content']) || 
                empty($article['title']) || empty($article['content'])) {
                $skipped++;
                continue;
            }
            
            $title = trim($article['title']);
            $content = trim($article['content']);
            
            // بررسی وجود مقاله با همین عنوان
            $checkArticleStmt->execute([$title, $companyId]);
            $existingArticle = $checkArticleStmt->fetch();
            
            if ($existingArticle && !$overwrite) {
                $skipped++;
                continue;
            }
            
            // استخراج سایر داده‌ها
            $excerpt = isset($article['excerpt']) ? trim($article['excerpt']) : '';
            $status = isset($article['status']) ? trim($article['status']) : $defaultStatus;
            $isFeatured = isset($article['is_featured']) ? (bool)$article['is_featured'] : false;
            $isPublic = isset($article['is_public']) ? (bool)$article['is_public'] : false;
            $tags = isset($article['tags']) && is_array($article['tags']) ? $article['tags'] : [];
            
            // ساخت اسلاگ
            $slug = isset($article['slug']) && !empty($article['slug']) ? 
                   kb_generateSlug($article['slug']) : kb_generateSlug($title);
            $slug = kb_ensureUniqueSlug($slug);
            
            // تنظیم تاریخ انتشار
            $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            
            // درج مقاله
            $insertArticleStmt->execute([
                $companyId, $title, $slug, $content, $excerpt, $status,
                $isFeatured ? 1 : 0, $isPublic ? 1 : 0, $userId, $publishedAt
            ]);
            
            $articleId = $pdo->lastInsertId();
            
            // افزودن دسته‌بندی
            if ($categoryId > 0) {
                $insertCategoryStmt->execute([$articleId, $categoryId]);
            }
            
            // افزودن دسته‌بندی‌های اضافی اگر در JSON وجود داشته باشند
            if (isset($article['categories']) && is_array($article['categories'])) {
                foreach ($article['categories'] as $cat) {
                    if (is_numeric($cat)) {
                        $insertCategoryStmt->execute([$articleId, (int)$cat]);
                    }
                }
            }
            
            // پردازش برچسب‌ها
            foreach ($tags as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;
                
                // بررسی وجود برچسب
                $checkTagStmt->execute([$tagName, $companyId]);
                $tag = $checkTagStmt->fetch();
                
                if ($tag) {
                    $tagId = $tag['id'];
                } else {
                    // ایجاد برچسب جدید
                    $createTagStmt->execute([$companyId, $tagName]);
                    $tagId = $pdo->lastInsertId();
                }
                
                // ارتباط برچسب با مقاله
                $insertTagStmt->execute([$articleId, $tagId]);
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors++;
            $errorMessages[] = "خطا در پردازش مقاله: " . $e->getMessage();
        }
    }
    
    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_messages' => $errorMessages
    ];
}

// وارد کردن از فایل HTML
function importFromHTML($filePath, $companyId, $userId, $categoryId, $defaultStatus, $overwrite) {
    global $pdo;
    
    // بررسی وجود فایل
    if (!file_exists($filePath)) {
        throw new Exception('فایل مورد نظر یافت نشد.');
    }
    
    // خواندن محتوای فایل HTML
    $htmlContent = file_get_contents($filePath);
    if (!$htmlContent) {
        throw new Exception('خطا در خواندن فایل HTML.');
    }
    
    // بارگذاری HTML با DOMDocument
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
    
    // جستجو برای عناصر مقاله
    $articles = [];
    
    // روش 1: جستجو برای عناصر مقاله با کلاس یا شناسه خاص
    $articleNodes = $dom->getElementsByTagName('article');
    foreach ($articleNodes as $articleNode) {
        $titleNode = null;
        $contentNode = null;
        
        // یافتن عنوان
        $h1Tags = $articleNode->getElementsByTagName('h1');
        if ($h1Tags->length > 0) {
            $titleNode = $h1Tags->item(0);
        } else {
            $h2Tags = $articleNode->getElementsByTagName('h2');
            if ($h2Tags->length > 0) {
                $titleNode = $h2Tags->item(0);
            }
        }
        
        // یافتن محتوا
        $divTags = $articleNode->getElementsByTagName('div');
        foreach ($divTags as $div) {
            if ($div->hasAttribute('class') && 
                (strpos($div->getAttribute('class'), 'content') !== false || 
                 strpos($div->getAttribute('class'), 'body') !== false)) {
                $contentNode = $div;
                break;
            }
        }
        
        if (!$contentNode) {
            $pTags = $articleNode->getElementsByTagName('p');
            if ($pTags->length > 0) {
                $contentHTML = '';
                for ($i = 0; $i < $pTags->length; $i++) {
                    $contentHTML .= $dom->saveHTML($pTags->item($i));
                }
                
                if (!empty($contentHTML)) {
                    $contentNode = $dom->createTextNode($contentHTML);
                }
            }
        }
        
        if ($titleNode && $contentNode) {
            $articles[] = [
                'title' => $titleNode->textContent,
                'content' => $dom->saveHTML($contentNode)
            ];
        }
    }
    
    // روش 2: اگر روش 1 نتیجه‌ای نداد، ساختار کلی را بررسی کن
    if (empty($articles)) {
        // فرض بر این است که فایل HTML یک مقاله واحد است
        $titleTags = $dom->getElementsByTagName('title');
        $title = ($titleTags->length > 0) ? $titleTags->item(0)->textContent : 'مقاله وارد شده';
        
        $bodyTags = $dom->getElementsByTagName('body');
        if ($bodyTags->length > 0) {
            $articles[] = [
                'title' => $title,
                'content' => $dom->saveHTML($bodyTags->item(0))
            ];
        }
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $errorMessages = [];
    
    // آماده‌سازی استیتمنت‌های SQL
    $checkArticleStmt = $pdo->prepare("SELECT id FROM kb_articles WHERE title = ? AND company_id = ?");
    $insertArticleStmt = $pdo->prepare("INSERT INTO kb_articles 
                                       (company_id, title, slug, content, excerpt, status, 
                                        is_featured, is_public, created_by, published_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertCategoryStmt = $pdo->prepare("INSERT INTO kb_article_categories 
                                        (article_id, category_id) VALUES (?, ?)");
    
    // پردازش مقالات
    foreach ($articles as $article) {
        try {
            $title = trim($article['title']);
            $content = $article['content'];
            
            // بررسی وجود مقاله با همین عنوان
            $checkArticleStmt->execute([$title, $companyId]);
            $existingArticle = $checkArticleStmt->fetch();
            
            if ($existingArticle && !$overwrite) {
                $skipped++;
                continue;
            }
            
            // ساخت اسلاگ
            $slug = kb_generateSlug($title);
            $slug = kb_ensureUniqueSlug($slug);
            
            // تنظیم تاریخ انتشار
            $publishedAt = ($defaultStatus === 'published') ? date('Y-m-d H:i:s') : null;
            
            // درج مقاله
            $insertArticleStmt->execute([
                $companyId, $title, $slug, $content, '', $defaultStatus,
                0, 0, $userId, $publishedAt
            ]);
            
            $articleId = $pdo->lastInsertId();
            
            // افزودن دسته‌بندی
            if ($categoryId > 0) {
                $insertCategoryStmt->execute([$articleId, $categoryId]);
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors++;
            $errorMessages[] = "خطا در پردازش مقاله: " . $e->getMessage();
        }
    }
    
    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_messages' => $errorMessages
    ];
}

// دریافت دسته‌بندی‌ها برای نمایش در فرم
$stmt = $pdo->prepare("SELECT * FROM kb_categories WHERE company_id = ? ORDER BY name");
$stmt->execute([$companyId]);
$categories = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>وارد کردن مقالات به پایگاه دانش</h1>
    <a href="kb_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<!-- فرم وارد کردن -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">آپلود فایل</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="import_format" class="form-label">فرمت فایل</label>
                    <select class="form-select" id="import_format" name="import_format" required>
                        <option value="">انتخاب کنید...</option>
                        <option value="csv">CSV (مقادیر جدا شده با کاما)</option>
                        <option value="xlsx">Excel (XLSX)</option>
                        <option value="json">JSON</option>
                        <option value="html">HTML</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="category_id" class="form-label">دسته‌بندی پیش‌فرض</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="0">بدون دسته‌بندی</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="default_status" class="form-label">وضعیت پیش‌فرض</label>
                    <select class="form-select" id="default_status" name="default_status">
                        <option value="draft">پیش‌نویس</option>
                        <option value="published">منتشر شده</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="overwrite" class="form-label">بازنویسی مقالات تکراری</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="overwrite" id="overwrite_yes" value="1">
                            <label class="form-check-label" for="overwrite_yes">بله</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="overwrite" id="overwrite_no" value="0" checked>
                            <label class="form-check-label" for="overwrite_no">خیر</label>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label for="import_file" class="form-label">انتخاب فایل</label>
                    <input type="file" class="form-control" id="import_file" name="import_file" required>
                </div>
                
                <div class="col-md-12">
                    <button type="submit" name="import_articles" class="btn btn-primary">
                        <i class="fas fa-file-import"></i> شروع وارد کردن مقالات
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- راهنمای وارد کردن -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">راهنمای وارد کردن مقالات</h5>
    </div>
    <div class="card-body">
        <h6>راهنمای فرمت CSV</h6>
        <p>فایل CSV باید دارای سطر هدر با ستون‌های زیر باشد:</p>
        <ul>
            <li><strong>title</strong> (اجباری): عنوان مقاله</li>
            <li><strong>content</strong> (اجباری): محتوای مقاله (می‌تواند شامل HTML باشد)</li>
            <li><strong>excerpt</strong> (اختیاری): خلاصه مقاله</li>
            <li><strong>status</strong> (اختیاری): وضعیت مقاله (draft, published, archived)</li>
            <li><strong>is_featured</strong> (اختیاری): آیا مقاله ویژه است (Yes/No یا True/False یا 1/0)</li>
            <li><strong>is_public</strong> (اختیاری): آیا مقاله عمومی است (Yes/No یا True/False یا 1/0)</li>
            <li><strong>tags</strong> (اختیاری): برچسب‌ها با کاما جدا شده</li>
        </ul>
        
        <h6 class="mt-4">راهنمای فرمت JSON</h6>
        <p>فایل JSON باید آرایه‌ای از اشیاء با کلیدهای زیر باشد:</p>
        <pre class="bg-light p-3 rounded">
[
  {
    "title": "عنوان مقاله",
    "content": "محتوای مقاله (می‌تواند شامل HTML باشد)",
    "excerpt": "خلاصه مقاله (اختیاری)",
    "status": "وضعیت مقاله: draft, published, archived (اختیاری)",
    "is_featured": true/false (اختیاری),
    "is_public": true/false (اختیاری),
    "tags": ["برچسب۱", "برچسب۲"] (اختیاری),
    "categories": [1, 2, 3] (شناسه‌های دسته‌بندی - اختیاری)
  },
  ...
]
</pre>
        
        <h6 class="mt-4">راهنمای فرمت HTML</h6>
        <p>برای وارد کردن از HTML، سیستم تلاش می‌کند عناصر &lt;article&gt; را پیدا کند و از آنها مقاله ایجاد کند. هر عنصر &lt;article&gt; باید شامل &lt;h1&gt; یا &lt;h2&gt; برای عنوان و &lt;div class="content"&gt; یا چندین &lt;p&gt; برای محتوا باشد.</p>
        
        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle me-2"></i>
            توجه: در تمام فرمت‌ها، فیلدهای عنوان و محتوا اجباری هستند. مقالات بدون این فیلدها رد خواهند شد.
        </div>
        
        <div class="alert alert-warning mt-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            هشدار: وارد کردن فایل‌های بزرگ ممکن است زمان‌بر باشد. لطفاً تا پایان پردازش صبر کنید.
        </div>
    </div>
</div>

<?php
// نمایش نتایج وارد کردن
if (!empty($importResults) && isset($importResults['error_messages']) && !empty($importResults['error_messages'])):
?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">خطاهای وارد کردن</h5>
    </div>
    <div class="card-body">
        <ul class="text-danger">
            <?php foreach ($importResults['error_messages'] as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // بررسی فرمت فایل آپلود شده
    const importFormat = document.getElementById('import_format');
    const importFile = document.getElementById('import_file');
    
    importFile.addEventListener('change', function() {
        const fileName = this.value.toLowerCase();
        const selectedFormat = importFormat.value;
        
        let validExtension = false;
        
        switch (selectedFormat) {
            case 'csv':
                validExtension = fileName.endsWith('.csv');
                break;
            case 'xlsx':
                validExtension = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
                break;
            case 'json':
                validExtension = fileName.endsWith('.json');
                break;
            case 'html':
                validExtension = fileName.endsWith('.html') || fileName.endsWith('.htm');
                break;
        }
        
        if (selectedFormat && !validExtension) {
            alert('فرمت فایل با نوع انتخاب شده مطابقت ندارد.');
            this.value = ''; // پاک کردن انتخاب فایل
        }
    });
});
</script>

<?php include 'footer.php'; ?>