<?php
// kb_tags.php - مدیریت برچسب‌های پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت برچسب‌ها
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مدیریت برچسب‌های پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// حذف برچسب
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tagId = (int)$_GET['delete'];
    
    try {
        // بررسی استفاده از برچسب در مقالات
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_article_tags WHERE tag_id = ?");
        $stmt->execute([$tagId]);
        $articleCount = $stmt->fetchColumn();
        
        if ($articleCount > 0) {
            $message = showError("این برچسب در $articleCount مقاله استفاده شده است و نمی‌توان آن را حذف کرد.");
        } else {
            // حذف برچسب
            $stmt = $pdo->prepare("DELETE FROM kb_tags WHERE id = ? AND company_id = ?");
            $stmt->execute([$tagId, $companyId]);
            
            $message = showSuccess('برچسب با موفقیت حذف شد.');
        }
    } catch (PDOException $e) {
        $message = showError('خطا در حذف برچسب: ' . $e->getMessage());
    }
}

// افزودن برچسب جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    $name = clean($_POST['name']);
    
    if (empty($name)) {
        $message = showError('لطفاً نام برچسب را وارد کنید.');
    } else {
        try {
            // بررسی تکراری نبودن نام
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_tags WHERE name = ? AND company_id = ?");
            $stmt->execute([$name, $companyId]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $message = showError('برچسب با این نام قبلاً ایجاد شده است.');
            } else {
                // ایجاد برچسب جدید
                $stmt = $pdo->prepare("INSERT INTO kb_tags (company_id, name) VALUES (?, ?)");
                $stmt->execute([$companyId, $name]);
                
                $message = showSuccess('برچسب جدید با موفقیت ایجاد شد.');
            }
        } catch (PDOException $e) {
            $message = showError('خطا در ایجاد برچسب: ' . $e->getMessage());
        }
    }
}

// ویرایش برچسب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tag'])) {
    $tagId = isset($_POST['tag_id']) && is_numeric($_POST['tag_id']) ? (int)$_POST['tag_id'] : 0;
    $name = clean($_POST['name']);
    
    if (empty($name) || $tagId === 0) {
        $message = showError('لطفاً نام برچسب را وارد کنید.');
    } else {
        try {
            // بررسی تکراری نبودن نام
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_tags WHERE name = ? AND company_id = ? AND id != ?");
            $stmt->execute([$name, $companyId, $tagId]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $message = showError('برچسب با این نام قبلاً ایجاد شده است.');
            } else {
                // به‌روزرسانی برچسب
                $stmt = $pdo->prepare("UPDATE kb_tags SET name = ? WHERE id = ? AND company_id = ?");
                $stmt->execute([$name, $tagId, $companyId]);
                
                $message = showSuccess('برچسب با موفقیت به‌روزرسانی شد.');
            }
        } catch (PDOException $e) {
            $message = showError('خطا در به‌روزرسانی برچسب: ' . $e->getMessage());
        }
    }
}

// آدغام برچسب‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge_tags'])) {
    $sourceTagIds = isset($_POST['source_tags']) ? $_POST['source_tags'] : [];
    $targetTagId = isset($_POST['target_tag']) && is_numeric($_POST['target_tag']) ? (int)$_POST['target_tag'] : 0;
    
    if (empty($sourceTagIds) || $targetTagId === 0) {
        $message = showError('لطفاً برچسب‌های منبع و برچسب هدف را انتخاب کنید.');
    } else if (in_array($targetTagId, $sourceTagIds)) {
        $message = showError('برچسب هدف نمی‌تواند یکی از برچسب‌های منبع باشد.');
    } else {
        try {
            // شروع تراکنش
            $pdo->beginTransaction();
            
            // بررسی وجود برچسب هدف
            $stmt = $pdo->prepare("SELECT id FROM kb_tags WHERE id = ? AND company_id = ?");
            $stmt->execute([$targetTagId, $companyId]);
            
            if (!$stmt->fetch()) {
                throw new Exception('برچسب هدف یافت نشد.');
            }
            
            foreach ($sourceTagIds as $sourceTagId) {
                // به‌روزرسانی ارتباط‌های مقاله-برچسب
                $stmt = $pdo->prepare("INSERT IGNORE INTO kb_article_tags (article_id, tag_id) 
                                      SELECT article_id, ? FROM kb_article_tags WHERE tag_id = ?");
                $stmt->execute([$targetTagId, $sourceTagId]);
                
                // حذف برچسب منبع
                $stmt = $pdo->prepare("DELETE FROM kb_article_tags WHERE tag_id = ?");
                $stmt->execute([$sourceTagId]);
                
                $stmt = $pdo->prepare("DELETE FROM kb_tags WHERE id = ? AND company_id = ?");
                $stmt->execute([$sourceTagId, $companyId]);
            }
            
            // پایان تراکنش
            $pdo->commit();
            
            $message = showSuccess('برچسب‌ها با موفقیت ادغام شدند.');
        } catch (Exception $e) {
            // برگرداندن تراکنش در صورت بروز خطا
            $pdo->rollBack();
            $message = showError('خطا در ادغام برچسب‌ها: ' . $e->getMessage());
        }
    }
}

// دریافت برچسب برای ویرایش
$editTag = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $tagId = (int)$_GET['edit'];
    
    $stmt = $pdo->prepare("SELECT * FROM kb_tags WHERE id = ? AND company_id = ?");
    $stmt->execute([$tagId, $companyId]);
    $editTag = $stmt->fetch();
    
    if (!$editTag) {
        $message = showError('برچسب مورد نظر یافت نشد.');
    }
}

// دریافت تمام برچسب‌ها با تعداد مقالات
$stmt = $pdo->prepare("SELECT t.*, 
                      (SELECT COUNT(*) FROM kb_article_tags WHERE tag_id = t.id) as article_count
                      FROM kb_tags t
                      WHERE t.company_id = ?
                      ORDER BY t.name");
$stmt->execute([$companyId]);
$tags = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت برچسب‌های پایگاه دانش</h1>
    <a href="kb_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-4">
        <!-- فرم افزودن/ویرایش برچسب -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $editTag ? 'ویرایش برچسب' : 'افزودن برچسب جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editTag): ?>
                    <input type="hidden" name="tag_id" value="<?php echo $editTag['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">نام برچسب <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               value="<?php echo $editTag ? $editTag['name'] : ''; ?>">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="<?php echo $editTag ? 'edit_tag' : 'add_tag'; ?>" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editTag ? 'به‌روزرسانی برچسب' : 'افزودن برچسب'; ?>
                        </button>
                        <?php if ($editTag): ?>
                        <a href="kb_tags.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> انصراف
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- فرم ادغام برچسب‌ها -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">ادغام برچسب‌ها</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">برچسب‌های منبع <span class="text-danger">*</span></label>
                        <select class="form-select" name="source_tags[]" multiple required size="6">
                            <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>">
                                <?php echo $tag['name']; ?> (<?php echo number_format($tag['article_count']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">برچسب‌هایی که می‌خواهید حذف شوند و به برچسب هدف منتقل شوند را انتخاب کنید.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">برچسب هدف <span class="text-danger">*</span></label>
                        <select class="form-select" name="target_tag" required>
                            <option value="">انتخاب کنید...</option>
                            <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>">
                                <?php echo $tag['name']; ?> (<?php echo number_format($tag['article_count']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">برچسبی که می‌خواهید برچسب‌های منبع با آن ادغام شوند.</div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        توجه: ادغام برچسب‌ها یک عملیات غیرقابل بازگشت است. برچسب‌های منبع حذف خواهند شد و تمام مقالات آن‌ها به برچسب هدف منتقل می‌شوند.
                    </div>
                    
                    <button type="submit" name="merge_tags" class="btn btn-warning" onclick="return confirm('آیا از ادغام این برچسب‌ها اطمینان دارید؟ این عملیات غیرقابل بازگشت است.')">
                        <i class="fas fa-object-group"></i> ادغام برچسب‌ها
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- لیست برچسب‌ها -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">لیست برچسب‌ها</h5>
                <input type="text" id="tagSearch" class="form-control form-control-sm w-auto" placeholder="جستجو...">
            </div>
            <div class="card-body">
                <?php if (!empty($tags)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="tagsTable">
                        <thead>
                            <tr>
                                <th>نام برچسب</th>
                                <th>تعداد مقالات</th>
                                <th>تاریخ ایجاد</th>
                                <th style="width: 150px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tags as $tag): ?>
                            <tr>
                                <td><a href="kb_tag.php?id=<?php echo $tag['id']; ?>" target="_blank"><?php echo $tag['name']; ?></a></td>
                                <td><?php echo number_format($tag['article_count']); ?></td>
                                <td><?php echo formatDate($tag['created_at']); ?></td>
                                <td>
                                    <a href="kb_tag.php?id=<?php echo $tag['id']; ?>" class="btn btn-sm btn-info" title="مشاهده مقالات" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?edit=<?php echo $tag['id']; ?>" class="btn btn-sm btn-primary" title="ویرایش">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($tag['article_count'] == 0): ?>
                                    <a href="?delete=<?php echo $tag['id']; ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این برچسب اطمینان دارید؟')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="این برچسب در مقالات استفاده شده است و قابل حذف نیست">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // جستجو در جدول برچسب‌ها
    const tagSearch = document.getElementById('tagSearch');
    const tagsTable = document.getElementById('tagsTable');
    
    if (tagSearch && tagsTable) {
        tagSearch.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = tagsTable.querySelectorAll('tbody tr');
            
            rows.forEach(function(row) {
                const tagName = row.querySelector('td:first-child').textContent.toLowerCase();
                
                if (tagName.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>