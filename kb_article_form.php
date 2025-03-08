<?php
// kb_article_form.php - فرم افزودن/ویرایش مقاله پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به افزودن/ویرایش مقاله
if (!kb_hasPermission('edit')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای ایجاد یا ویرایش مقالات پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
// اصلاح شده - بررسی وجود company_id در session
$companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

// اگر company_id تنظیم نشده است و کاربر به چند شرکت دسترسی دارد، از اولین شرکت استفاده کنیم
if ($companyId === null && isset($_SESSION['companies']) && !empty($_SESSION['companies'])) {
    $companyId = $_SESSION['companies'][0]['company_id'];
    // تنظیم شرکت فعال در session
    $_SESSION['company_id'] = $companyId;
    $_SESSION['company_name'] = $_SESSION['companies'][0]['company_name'];
    
    // اگر تابع همگام‌سازی شرکت فعال وجود دارد، آن را فراخوانی کنیم
    if (function_exists('syncActiveCompany')) {
        syncActiveCompany();
    }
}

// اگر همچنان company_id تنظیم نشده، خطا نمایش دهیم
if ($companyId === null) {
    $_SESSION['error_message'] = 'لطفاً ابتدا یک شرکت فعال انتخاب کنید.';
    redirect('kb_dashboard.php');
}

$userId = $_SESSION['user_id'];

// دریافت شناسه مقاله برای ویرایش
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditing = ($articleId > 0);

// اگر در حال ویرایش هستیم، بررسی دسترسی
if ($isEditing && !kb_hasPermission('edit', 'article', $articleId)) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای ویرایش این مقاله را ندارید.';
    redirect('kb_articles.php');
}

// مقدار پیش‌فرض مقاله
$article = [
    'id' => 0,
    'company_id' => $companyId,
    'title' => '',
    'slug' => '',
    'content' => '',
    'excerpt' => '',
    'status' => 'draft',
    'is_featured' => 0,
    'is_public' => 0,
    'created_by' => $userId,
    'updated_by' => null,
    'published_at' => null,
    'created_at' => date('Y-m-d H:i:s')
];

// لیست دسته‌بندی‌های انتخاب شده
$selectedCategories = [];

// لیست برچسب‌های انتخاب شده
$selectedTags = [];

// لیست پیوست‌های مقاله
$attachments = [];

// اگر در حال ویرایش هستیم، اطلاعات مقاله را دریافت کن
if ($isEditing) {
    // دریافت اطلاعات مقاله
    $stmt = $pdo->prepare("SELECT * FROM kb_articles WHERE id = ? AND company_id = ?");
    $stmt->execute([$articleId, $companyId]);
    $articleData = $stmt->fetch();
    
    if (!$articleData) {
        $_SESSION['error_message'] = 'مقاله مورد نظر یافت نشد.';
        redirect('kb_articles.php');
    }
    
    $article = array_merge($article, $articleData);
    
    // دریافت دسته‌بندی‌های مقاله
    $stmt = $pdo->prepare("SELECT category_id FROM kb_article_categories WHERE article_id = ?");
    $stmt->execute([$articleId]);
    $selectedCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // دریافت برچسب‌های مقاله
    $stmt = $pdo->prepare("SELECT tag_id FROM kb_article_tags WHERE article_id = ?");
    $stmt->execute([$articleId]);
    $selectedTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // دریافت پیوست‌های مقاله
    $attachments = kb_getArticleAttachments($articleId);
}

// پردازش فرم ارسالی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_article'])) {
    $title = clean($_POST['title']);
    $content = $_POST['content']; // از clean استفاده نمی‌کنیم چون محتوا HTML است
    $excerpt = clean($_POST['excerpt']);
    $status = clean($_POST['status']);
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $categoryIds = isset($_POST['categories']) ? $_POST['categories'] : [];
    $tagNames = isset($_POST['tags']) ? explode(',', clean($_POST['tags'])) : [];
    
    // بررسی فیلدهای اجباری
    if (empty($title) || empty($content)) {
        $message = showError('لطفاً عنوان و محتوای مقاله را وارد کنید.');
    } else {
        try {
            // شروع تراکنش
            $pdo->beginTransaction();
            
            // اگر اسلاگ خالی است یا در حال ایجاد مقاله جدید هستیم، اسلاگ جدید ایجاد کن
            $slug = !empty($article['slug']) && $isEditing ? $article['slug'] : kb_generateSlug($title);
            $slug = kb_ensureUniqueSlug($slug, $isEditing ? $articleId : null);
            
            // تنظیم زمان انتشار
            $publishedAt = null;
            if ($status === 'published') {
                $publishedAt = $isEditing && $article['published_at'] ? $article['published_at'] : date('Y-m-d H:i:s');
            }
            
            if ($isEditing) {
                // به‌روزرسانی مقاله موجود
                $stmt = $pdo->prepare("UPDATE kb_articles 
                                      SET title = ?, slug = ?, content = ?, excerpt = ?, 
                                          status = ?, is_featured = ?, is_public = ?, 
                                          updated_by = ?, published_at = ? 
                                      WHERE id = ? AND company_id = ?");
                $stmt->execute([
                    $title, $slug, $content, $excerpt, 
                    $status, $isFeatured, $isPublic, 
                    $userId, $publishedAt, 
                    $articleId, $companyId
                ]);
                
                // حذف روابط دسته‌بندی‌های قبلی
                $stmt = $pdo->prepare("DELETE FROM kb_article_categories WHERE article_id = ?");
                $stmt->execute([$articleId]);
                
                // حذف روابط برچسب‌های قبلی
                $stmt = $pdo->prepare("DELETE FROM kb_article_tags WHERE article_id = ?");
                $stmt->execute([$articleId]);
            } else {
                // ایجاد مقاله جدید
                $stmt = $pdo->prepare("INSERT INTO kb_articles 
                                      (company_id, title, slug, content, excerpt, 
                                       status, is_featured, is_public, 
                                       created_by, published_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $companyId, $title, $slug, $content, $excerpt, 
                    $status, $isFeatured, $isPublic, 
                    $userId, $publishedAt
                ]);
                
                // دریافت شناسه مقاله جدید
                $articleId = $pdo->lastInsertId();
            }
            
            // افزودن روابط دسته‌بندی‌ها
            if (!empty($categoryIds)) {
                $insertCategoryStmt = $pdo->prepare("INSERT INTO kb_article_categories 
                                                   (article_id, category_id) VALUES (?, ?)");
                foreach ($categoryIds as $categoryId) {
                    $insertCategoryStmt->execute([$articleId, $categoryId]);
                }
            }
            
            // افزودن یا دریافت برچسب‌ها و ایجاد روابط
            if (!empty($tagNames)) {
                $insertTagStmt = $pdo->prepare("INSERT INTO kb_article_tags 
                                              (article_id, tag_id) VALUES (?, ?)");
                
                foreach ($tagNames as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;
                    
                    // بررسی آیا برچسب قبلاً وجود دارد
                    $stmt = $pdo->prepare("SELECT id FROM kb_tags 
                                          WHERE company_id = ? AND name = ?");
                    $stmt->execute([$companyId, $tagName]);
                    $tagId = $stmt->fetchColumn();
                    
                    if (!$tagId) {
                        // اگر برچسب وجود ندارد، ایجاد کن
                        $stmt = $pdo->prepare("INSERT INTO kb_tags 
                                              (company_id, name) VALUES (?, ?)");
                        $stmt->execute([$companyId, $tagName]);
                        $tagId = $pdo->lastInsertId();
                    }
                    
                    // ایجاد رابطه مقاله-برچسب
                    $insertTagStmt->execute([$articleId, $tagId]);
                }
            }
            
            // پردازش پیوست‌های آپلود شده
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $uploadDir = 'uploads/kb_attachments/';
                
                // اطمینان از وجود دایرکتوری آپلود
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $insertAttachmentStmt = $pdo->prepare("INSERT INTO kb_attachments 
                                                     (article_id, file_name, file_path, file_size, file_type, uploaded_by) 
                                                     VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($_FILES['attachments']['name'] as $key => $fileName) {
                    if (empty($fileName)) continue;
                    
                    $fileTmpName = $_FILES['attachments']['tmp_name'][$key];
                    $fileSize = $_FILES['attachments']['size'][$key];
                    $fileType = $_FILES['attachments']['type'][$key];
                    $fileError = $_FILES['attachments']['error'][$key];
                    
                    if ($fileError === UPLOAD_ERR_OK) {
                        // ایجاد نام فایل یکتا
                        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                        $newFileName = uniqid('kb_') . '-' . $articleId . '.' . $fileExt;
                        $filePath = $uploadDir . $newFileName;
                        
                        // آپلود فایل
                        if (move_uploaded_file($fileTmpName, $filePath)) {
                            // ثبت اطلاعات پیوست در دیتابیس
                            $insertAttachmentStmt->execute([
                                $articleId, $fileName, $filePath, $fileSize, $fileType, $userId
                            ]);
                        }
                    }
                }
            }
            
            // پایان تراکنش
            $pdo->commit();
            
            $actionText = $isEditing ? 'ویرایش' : 'ایجاد';
            $message = showSuccess("مقاله با موفقیت $actionText شد.");
            
            // اگر مقاله جدید ایجاد شده، فرم را خالی کن
            if (!$isEditing) {
                // هدایت به صفحه ویرایش مقاله جدید
                redirect("kb_article_form.php?id=$articleId&success=1");
            } else if (isset($_GET['success'])) {
                $message = showSuccess("مقاله با موفقیت ایجاد شد.");
            }
            
            // بروزرسانی اطلاعات مقاله
            $stmt = $pdo->prepare("SELECT * FROM kb_articles WHERE id = ?");
            $stmt->execute([$articleId]);
            $article = $stmt->fetch();
            
            // دریافت مجدد دسته‌بندی‌های مقاله
            $stmt = $pdo->prepare("SELECT category_id FROM kb_article_categories WHERE article_id = ?");
            $stmt->execute([$articleId]);
            $selectedCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // دریافت مجدد برچسب‌های مقاله
            $stmt = $pdo->prepare("SELECT t.id, t.name 
                                  FROM kb_tags t
                                  JOIN kb_article_tags at ON t.id = at.tag_id
                                  WHERE at.article_id = ?");
            $stmt->execute([$articleId]);
            $tagRows = $stmt->fetchAll();
            $selectedTags = array_column($tagRows, 'id');
            $tagNames = array_column($tagRows, 'name');
            
            // دریافت مجدد پیوست‌های مقاله
            $attachments = kb_getArticleAttachments($articleId);
            
            // حالت ویرایش را فعال کن
            $isEditing = true;
        } catch (PDOException $e) {
            // برگرداندن تراکنش در صورت بروز خطا
            $pdo->rollBack();
            $message = showError('خطا در ذخیره مقاله: ' . $e->getMessage());
        }
    }
}

// حذف پیوست
if (isset($_GET['delete_attachment']) && is_numeric($_GET['delete_attachment']) && $isEditing) {
    $attachmentId = (int)$_GET['delete_attachment'];
    
    try {
        // دریافت اطلاعات پیوست
        $stmt = $pdo->prepare("SELECT * FROM kb_attachments 
                              WHERE id = ? AND article_id = ?");
        $stmt->execute([$attachmentId, $articleId]);
        $attachment = $stmt->fetch();
        
        if ($attachment) {
            // حذف فایل از سرور
            if (file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }
            
            // حذف رکورد از دیتابیس
            $stmt = $pdo->prepare("DELETE FROM kb_attachments WHERE id = ?");
            $stmt->execute([$attachmentId]);
            
            $message = showSuccess('پیوست با موفقیت حذف شد.');
            
            // بروزرسانی لیست پیوست‌ها
            $attachments = kb_getArticleAttachments($articleId);
        } else {
            $message = showError('پیوست مورد نظر یافت نشد.');
        }
    } catch (PDOException $e) {
        $message = showError('خطا در حذف پیوست: ' . $e->getMessage());
    }
}

// دریافت تمام دسته‌بندی‌ها
$stmt = $pdo->prepare("SELECT * FROM kb_categories WHERE company_id = ? ORDER BY name");
$stmt->execute([$companyId]);
$categories = $stmt->fetchAll();

// ساخت ساختار درختی دسته‌بندی‌ها
$categoryTree = [];
$categoryMap = [];

foreach ($categories as $category) {
    $categoryMap[$category['id']] = $category;
    $categoryMap[$category['id']]['children'] = [];
    
    if ($category['parent_id'] === null) {
        $categoryTree[$category['id']] = &$categoryMap[$category['id']];
    } else {
        if (isset($categoryMap[$category['parent_id']])) {
            $categoryMap[$category['parent_id']]['children'][$category['id']] = &$categoryMap[$category['id']];
        }
    }
}

// دریافت برچسب‌های پرکاربرد
$stmt = $pdo->prepare("SELECT t.*, 
                      (SELECT COUNT(*) FROM kb_article_tags WHERE tag_id = t.id) as usage_count
                      FROM kb_tags t
                      WHERE t.company_id = ?
                      ORDER BY usage_count DESC
                      LIMIT 20");
$stmt->execute([$companyId]);
$popularTags = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $isEditing ? 'ویرایش مقاله' : 'افزودن مقاله جدید'; ?></h1>
    <div>
        <?php if ($isEditing): ?>
        <a href="kb_article.php?id=<?php echo $articleId; ?>" class="btn btn-info me-2" target="_blank">
            <i class="fas fa-eye"></i> مشاهده مقاله
        </a>
        <?php endif; ?>
        <a href="kb_articles.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> بازگشت به لیست مقالات
        </a>
    </div>
</div>

<?php echo $message; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-8">
            <!-- اطلاعات اصلی مقاله -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">اطلاعات مقاله</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">عنوان مقاله <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required 
                               value="<?php echo $article['title']; ?>">
                    </div>
                    
                    <?php if ($isEditing): ?>
                    <div class="mb-3">
                        <label for="slug" class="form-label">اسلاگ (نامک) مقاله</label>
                        <input type="text" class="form-control" id="slug" disabled 
                               value="<?php echo $article['slug']; ?>">
                        <div class="form-text">اسلاگ به صورت خودکار ایجاد می‌شود.</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="excerpt" class="form-label">خلاصه مقاله</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo $article['excerpt']; ?></textarea>
                        <div class="form-text">خلاصه کوتاهی از مقاله که در لیست نمایش داده می‌شود (اختیاری).</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">محتوای مقاله <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="15" required><?php echo $article['content']; ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- پیوست‌ها -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">پیوست‌ها</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($attachments)): ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>نام فایل</th>
                                    <th>اندازه</th>
                                    <th>نوع</th>
                                    <th>تاریخ آپلود</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attachments as $attachment): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo $attachment['file_path']; ?>" target="_blank">
                                            <?php echo $attachment['file_name']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo formatFileSize($attachment['file_size']); ?></td>
                                    <td><?php echo $attachment['file_type']; ?></td>
                                    <td><?php echo formatDate($attachment['created_at']); ?></td>
                                    <td>
                                        <a href="?id=<?php echo $articleId; ?>&delete_attachment=<?php echo $attachment['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('آیا از حذف این پیوست اطمینان دارید؟')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="attachments" class="form-label">افزودن پیوست‌های جدید</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                        <div class="form-text">می‌توانید چندین فایل را انتخاب کنید.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- وضعیت و تنظیمات مقاله -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">تنظیمات انتشار</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">وضعیت مقاله</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo ($article['status'] === 'draft') ? 'selected' : ''; ?>>پیش‌نویس</option>
                            <option value="published" <?php echo ($article['status'] === 'published') ? 'selected' : ''; ?>>منتشر شده</option>
                            <option value="archived" <?php echo ($article['status'] === 'archived') ? 'selected' : ''; ?>>آرشیو شده</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" 
                                  <?php echo ($article['is_featured']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">مقاله ویژه</label>
                        </div>
                        <div class="form-text">مقالات ویژه در صفحه اصلی پایگاه دانش نمایش داده می‌شوند.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_public" name="is_public" 
                                  <?php echo ($article['is_public']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public">مقاله عمومی</label>
                        </div>
                        <div class="form-text">مقالات عمومی توسط همه کاربران قابل مشاهده هستند.</div>
                    </div>
                    
                    <?php if ($isEditing): ?>
                    <div class="mb-3">
                        <label class="form-label">آمار مقاله</label>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                تعداد بازدید
                                <span class="badge bg-primary rounded-pill"><?php echo number_format($article['views_count']); ?></span>
                            </li>
                            <?php
                            // دریافت تعداد نظرات
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_comments WHERE article_id = ?");
                            $stmt->execute([$articleId]);
                            $commentCount = $stmt->fetchColumn();
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                تعداد نظرات
                                <span class="badge bg-primary rounded-pill"><?php echo number_format($commentCount); ?></span>
                            </li>
                            <?php
                            // دریافت میانگین امتیازات
                            $stmt = $pdo->prepare("SELECT AVG(rating) FROM kb_ratings WHERE article_id = ?");
                            $stmt->execute([$articleId]);
                            $avgRating = $stmt->fetchColumn();
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                میانگین امتیاز
                                <span class="badge bg-primary rounded-pill"><?php echo $avgRating ? number_format($avgRating, 1) : 'بدون امتیاز'; ?></span>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="save_article" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> ذخیره مقاله
                    </button>
                </div>
            </div>
            
            <!-- دسته‌بندی‌ها -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">دسته‌بندی‌ها</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                    <div class="alert alert-warning">
                        هیچ دسته‌بندی‌ای یافت نشد. ابتدا یک دسته‌بندی ایجاد کنید.
                    </div>
                    <?php else: ?>
                    <div class="mb-3" style="max-height: 300px; overflow-y: auto;">
                        <?php
                        // تابع بازگشتی برای نمایش دسته‌بندی‌ها به صورت درختی
                        function displayCategoryTree($categories, $selectedCategories, $level = 0) {
                            $html = '';
                            foreach ($categories as $category) {
                                $indent = str_repeat('&nbsp;&nbsp;', $level);
                                $isChecked = in_array($category['id'], $selectedCategories) ? 'checked' : '';
                                
                                $html .= '<div class="form-check">';
                                $html .= "<input class='form-check-input' type='checkbox' name='categories[]' value='{$category['id']}' id='category_{$category['id']}' $isChecked>";
                                $html .= "<label class='form-check-label' for='category_{$category['id']}'>";
                                $html .= $indent;
                                if ($level > 0) {
                                    $html .= '└─ ';
                                }
                                $html .= $category['name'];
                                $html .= '</label>';
                                $html .= '</div>';
                                
                                if (!empty($category['children'])) {
                                    $html .= displayCategoryTree($category['children'], $selectedCategories, $level + 1);
                                }
                            }
                            return $html;
                        }
                        
                        echo displayCategoryTree($categoryTree, $selectedCategories);
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (kb_hasPermission('manage')): ?>
                    <div class="text-center mt-2">
                        <a href="kb_categories.php" class="btn btn-sm btn-outline-primary">مدیریت دسته‌بندی‌ها</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- برچسب‌ها -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">برچسب‌ها</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="tags" class="form-label">برچسب‌ها</label>
                        <input type="text" class="form-control" id="tags" name="tags" 
                               value="<?php echo isset($tagNames) ? implode(',', $tagNames) : ''; ?>">
                        <div class="form-text">برچسب‌ها را با کاما (،) از هم جدا کنید.</div>
                    </div>
                    
                    <?php if (!empty($popularTags)): ?>
                    <div class="mb-3">
                        <label class="form-label">برچسب‌های پرکاربرد</label>
                        <div>
                            <?php foreach ($popularTags as $tag): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-1 me-1 tag-suggestion">
                                <?php echo $tag['name']; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- راه‌اندازی ادیتور TinyMCE -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // راه‌اندازی TinyMCE
    tinymce.init({
        selector: '#content',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste help wordcount',
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        language: 'fa',
        directionality: 'rtl',
        height: 500,
        menubar: true
    });
    
    // افزودن برچسب‌های پیشنهادی به ورودی برچسب‌ها
    const tagInput = document.getElementById('tags');
    const tagSuggestions = document.querySelectorAll('.tag-suggestion');
    
    tagSuggestions.forEach(function(button) {
        button.addEventListener('click', function() {
            const tagName = this.textContent.trim();
            const currentTags = tagInput.value.split(',').map(tag => tag.trim()).filter(tag => tag !== '');
            
            if (!currentTags.includes(tagName)) {
                if (currentTags.length > 0 && currentTags[0] !== '') {
                    tagInput.value = currentTags.join(',') + ',' + tagName;
                } else {
                    tagInput.value = tagName;
                }
            }
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