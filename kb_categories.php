<?php
// kb_categories.php - مدیریت دسته‌بندی‌های پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت دسته‌بندی‌ها
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مدیریت دسته‌بندی‌های پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

$message = '';
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

// حذف دسته‌بندی
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $categoryId = (int)$_GET['delete'];
    
    try {
        // بررسی وجود زیرمجموعه
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_categories WHERE parent_id = ?");
        $stmt->execute([$categoryId]);
        $subcategoryCount = $stmt->fetchColumn();
        
        if ($subcategoryCount > 0) {
            $message = showError('این دسته‌بندی دارای زیرمجموعه است و نمی‌توان آن را حذف کرد.');
        } else {
            // بررسی استفاده از دسته‌بندی در مقالات
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_article_categories WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $articleCount = $stmt->fetchColumn();
            
            if ($articleCount > 0) {
                $message = showError('این دسته‌بندی در مقالات استفاده شده است و نمی‌توان آن را حذف کرد.');
            } else {
                // حذف دسته‌بندی
                $stmt = $pdo->prepare("DELETE FROM kb_categories WHERE id = ? AND company_id = ?");
                $stmt->execute([$categoryId, $companyId]);
                
                $message = showSuccess('دسته‌بندی با موفقیت حذف شد.');
            }
        }
    } catch (PDOException $e) {
        $message = showError('خطا در حذف دسته‌بندی: ' . $e->getMessage());
    }
}

// فعال/غیرفعال کردن دسته‌بندی
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $categoryId = (int)$_GET['toggle'];
    
    try {
        // دریافت وضعیت فعلی
        $stmt = $pdo->prepare("SELECT is_active FROM kb_categories WHERE id = ? AND company_id = ?");
        $stmt->execute([$categoryId, $companyId]);
        $isActive = $stmt->fetchColumn();
        
        // تغییر وضعیت
        $newStatus = $isActive ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE kb_categories SET is_active = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$newStatus, $categoryId, $companyId]);
        
        $message = showSuccess('وضعیت دسته‌بندی با موفقیت تغییر کرد.');
    } catch (PDOException $e) {
        $message = showError('خطا در تغییر وضعیت دسته‌بندی: ' . $e->getMessage());
    }
}

// افزودن دسته‌بندی جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    $icon = clean($_POST['icon']);
    $parentId = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $sortOrder = isset($_POST['sort_order']) && is_numeric($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    
    if (empty($name)) {
        $message = showError('لطفاً نام دسته‌بندی را وارد کنید.');
    } else {
        try {
            // بررسی تکراری نبودن نام در همان سطح
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_categories 
                                  WHERE name = ? AND company_id = ? 
                                  AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
            $stmt->execute([$name, $companyId, $parentId, $parentId]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $message = showError('دسته‌بندی با این نام در این سطح قبلاً ایجاد شده است.');
            } else {
                // ایجاد دسته‌بندی جدید
                $stmt = $pdo->prepare("INSERT INTO kb_categories (parent_id, company_id, name, description, icon, sort_order, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$parentId, $companyId, $name, $description, $icon, $sortOrder, $userId]);
                
                $message = showSuccess('دسته‌بندی جدید با موفقیت ایجاد شد.');
            }
        } catch (PDOException $e) {
            $message = showError('خطا در ایجاد دسته‌بندی: ' . $e->getMessage());
        }
    }
}

// ویرایش دسته‌بندی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $categoryId = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    $icon = clean($_POST['icon']);
    $parentId = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $sortOrder = isset($_POST['sort_order']) && is_numeric($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    
    if (empty($name) || $categoryId === 0) {
        $message = showError('لطفاً نام دسته‌بندی را وارد کنید.');
    } else if ($categoryId === $parentId) {
        $message = showError('دسته‌بندی نمی‌تواند والد خودش باشد.');
    } else {
        try {
            // بررسی معتبر بودن والد (برای جلوگیری از حلقه در ساختار درختی)
            if ($parentId !== null) {
                // دریافت تمام والدین دسته‌بندی انتخاب شده به عنوان والد
                $ancestors = [];
                $currentParent = $parentId;
                
                while ($currentParent !== null) {
                    // اگر والد فعلی همان دسته‌بندی در حال ویرایش باشد، حلقه وجود دارد
                    if ($currentParent === $categoryId) {
                        $message = showError('ایجاد حلقه در ساختار دسته‌بندی‌ها مجاز نیست.');
                        break;
                    }
                    
                    $ancestors[] = $currentParent;
                    
                    // دریافت والد بعدی
                    $stmt = $pdo->prepare("SELECT parent_id FROM kb_categories WHERE id = ?");
                    $stmt->execute([$currentParent]);
                    $currentParent = $stmt->fetchColumn();
                }
            }
            
            if (empty($message)) {
                // بررسی تکراری نبودن نام در همان سطح
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_categories 
                                      WHERE name = ? AND company_id = ? AND id != ? 
                                      AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))");
                $stmt->execute([$name, $companyId, $categoryId, $parentId, $parentId]);
                $exists = $stmt->fetchColumn() > 0;
                
                if ($exists) {
                    $message = showError('دسته‌بندی با این نام در این سطح قبلاً ایجاد شده است.');
                } else {
                    // به‌روزرسانی دسته‌بندی
                    $stmt = $pdo->prepare("UPDATE kb_categories 
                                          SET parent_id = ?, name = ?, description = ?, icon = ?, sort_order = ? 
                                          WHERE id = ? AND company_id = ?");
                    $stmt->execute([$parentId, $name, $description, $icon, $sortOrder, $categoryId, $companyId]);
                    
                    $message = showSuccess('دسته‌بندی با موفقیت به‌روزرسانی شد.');
                }
            }
        } catch (PDOException $e) {
            $message = showError('خطا در به‌روزرسانی دسته‌بندی: ' . $e->getMessage());
        }
    }
}

// دریافت دسته‌بندی برای ویرایش
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $categoryId = (int)$_GET['edit'];
    
    $stmt = $pdo->prepare("SELECT * FROM kb_categories WHERE id = ? AND company_id = ?");
    $stmt->execute([$categoryId, $companyId]);
    $editCategory = $stmt->fetch();
    
    if (!$editCategory) {
        $message = showError('دسته‌بندی مورد نظر یافت نشد.');
    }
}

// دریافت تمام دسته‌بندی‌ها
$allCategories = kb_getCategories(null);

// ساخت ساختار درختی دسته‌بندی‌ها
$categoryTree = [];
$categoryMap = [];

foreach ($allCategories as $category) {
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

// آیکون‌های پیش‌فرض فونت آوسام
$fontAwesomeIcons = [
    'fas fa-folder', 'fas fa-book', 'fas fa-file-alt', 'fas fa-clipboard', 'fas fa-list',
    'fas fa-question-circle', 'fas fa-info-circle', 'fas fa-lightbulb', 'fas fa-tools',
    'fas fa-cog', 'fas fa-users', 'fas fa-user', 'fas fa-building', 'fas fa-home',
    'fas fa-briefcase', 'fas fa-graduation-cap', 'fas fa-code', 'fas fa-bug',
    'fas fa-database', 'fas fa-server', 'fas fa-desktop', 'fas fa-mobile-alt',
    'fas fa-envelope', 'fas fa-phone', 'fas fa-lock', 'fas fa-shield-alt'
];

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت دسته‌بندی‌های پایگاه دانش</h1>
    <a href="kb_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-4">
        <!-- فرم افزودن/ویرایش دسته‌بندی -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $editCategory ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?php echo $editCategory['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">نام دسته‌بندی <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               value="<?php echo $editCategory ? $editCategory['name'] : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">دسته‌بندی والد</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">بدون والد (دسته‌بندی اصلی)</option>
                            <?php
                            // تابع بازگشتی برای نمایش دسته‌بندی‌ها به صورت درختی
                            function displayCategoryOptions($categories, $selectedId = null, $currentEditId = null, $level = 0) {
                                $html = '';
                                foreach ($categories as $category) {
                                    // دسته‌بندی در حال ویرایش نباید بتواند خودش را به عنوان والد انتخاب کند
                                    if ($category['id'] == $currentEditId) {
                                        continue;
                                    }
                                    
                                    $indent = str_repeat('&nbsp;&nbsp;', $level);
                                    $selected = ($category['id'] == $selectedId) ? 'selected' : '';
                                    
                                    $html .= "<option value='{$category['id']}' $selected>";
                                    $html .= $indent;
                                    if ($level > 0) {
                                        $html .= '└─ ';
                                    }
                                    $html .= $category['name'];
                                    $html .= '</option>';
                                    
                                    if (!empty($category['children'])) {
                                        $html .= displayCategoryOptions($category['children'], $selectedId, $currentEditId, $level + 1);
                                    }
                                }
                                return $html;
                            }
                            
                            echo displayCategoryOptions(
                                $categoryTree, 
                                $editCategory ? $editCategory['parent_id'] : null,
                                $editCategory ? $editCategory['id'] : null
                            );
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">توضیحات</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $editCategory ? $editCategory['description'] : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="icon" class="form-label">آیکون</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="icon" name="icon" 
                                   value="<?php echo $editCategory ? $editCategory['icon'] : ''; ?>" placeholder="fas fa-folder">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#iconModal">
                                <i class="fas fa-icons"></i> انتخاب
                            </button>
                        </div>
                        <div class="form-text">کلاس آیکون Font Awesome را وارد کنید. مثال: fas fa-folder</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sort_order" class="form-label">ترتیب نمایش</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" min="0" 
                               value="<?php echo $editCategory ? $editCategory['sort_order'] : '0'; ?>">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="<?php echo $editCategory ? 'edit_category' : 'add_category'; ?>" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editCategory ? 'به‌روزرسانی دسته‌بندی' : 'افزودن دسته‌بندی'; ?>
                        </button>
                        <?php if ($editCategory): ?>
                        <a href="kb_categories.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> انصراف
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- لیست دسته‌بندی‌ها -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">لیست دسته‌بندی‌ها</h5>
            </div>
            <div class="card-body">
                <?php if (empty($allCategories)): ?>
                <div class="alert alert-info">
                    هیچ دسته‌بندی‌ای یافت نشد.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th style="width: 50px;">آیکون</th>
                                <th>نام</th>
                                <th>توضیحات</th>
                                <th>تعداد مقالات</th>
                                <th>وضعیت</th>
                                <th style="width: 180px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // تابع بازگشتی برای نمایش سلسله‌مراتبی دسته‌بندی‌ها
                            function displayCategoryRows($categories, $level = 0) {
                                foreach ($categories as $category) {
                                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if (!empty($category['icon'])): ?>
                                            <i class="<?php echo $category['icon']; ?> fa-lg"></i>
                                            <?php else: ?>
                                            <i class="fas fa-folder fa-lg text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $indent; ?>
                                            <?php if ($level > 0): ?>
                                            <i class="fas fa-level-down-alt fa-rotate-90 me-1 text-muted"></i>
                                            <?php endif; ?>
                                            <?php echo $category['name']; ?>
                                        </td>
                                        <td><?php echo mb_substr($category['description'], 0, 50) . (mb_strlen($category['description']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo number_format($category['article_count']); ?></td>
                                        <td>
                                            <?php if ($category['is_active']): ?>
                                            <span class="badge bg-success">فعال</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">غیرفعال</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="kb_category.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-info" title="مشاهده مقالات">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-primary" title="ویرایش">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?toggle=<?php echo $category['id']; ?>" class="btn btn-sm <?php echo $category['is_active'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $category['is_active'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>">
                                                <i class="fas fa-<?php echo $category['is_active'] ? 'times' : 'check'; ?>"></i>
                                            </a>
                                            <?php if ($category['article_count'] == 0 && empty($category['children'])): ?>
                                            <a href="?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('آیا از حذف این دسته‌بندی اطمینان دارید؟')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled title="این دسته‌بندی دارای مقاله یا زیرمجموعه است و قابل حذف نیست">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                    if (!empty($category['children'])) {
                                        displayCategoryRows($category['children'], $level + 1);
                                    }
                                }
                            }
                            
                            displayCategoryRows($categoryTree);
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- مودال انتخاب آیکون -->
<div class="modal fade" id="iconModal" tabindex="-1" aria-labelledby="iconModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="iconModalLabel">انتخاب آیکون</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php foreach ($fontAwesomeIcons as $icon): ?>
                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-outline-secondary w-100 icon-select" data-icon="<?php echo $icon; ?>">
                            <i class="<?php echo $icon; ?> fa-lg me-2"></i> <?php echo $icon; ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // انتخاب آیکون
    const iconButtons = document.querySelectorAll('.icon-select');
    const iconInput = document.getElementById('icon');
    
    iconButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const icon = this.getAttribute('data-icon');
            iconInput.value = icon;
            
            // بستن مودال
            var modal = bootstrap.Modal.getInstance(document.getElementById('iconModal'));
            modal.hide();
        });
    });
});
</script>

<?php include 'footer.php'; ?>