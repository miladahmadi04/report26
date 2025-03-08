<?php
// kpi_models.php - Manage KPI calculation models
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Check admin access
requireAdmin();

$message = '';

// Add new KPI model
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_model'])) {
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    $modelType = clean($_POST['model_type']);
    
    if (empty($name) || empty($modelType)) {
        $message = showError('لطفا نام و نوع مدل KPI را وارد کنید.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO kpi_models (name, description, model_type) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $modelType]);
            $message = showSuccess('مدل KPI با موفقیت اضافه شد.');
        } catch (PDOException $e) {
            $message = showError('خطا در ثبت مدل KPI: ' . $e->getMessage());
        }
    }
}

// Edit KPI model
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_model'])) {
    $modelId = clean($_POST['model_id']);
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    $modelType = clean($_POST['model_type']);
    
    if (empty($name) || empty($modelType)) {
        $message = showError('لطفا نام و نوع مدل KPI را وارد کنید.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE kpi_models SET name = ?, description = ?, model_type = ? WHERE id = ?");
            $stmt->execute([$name, $description, $modelType, $modelId]);
            $message = showSuccess('مدل KPI با موفقیت ویرایش شد.');
        } catch (PDOException $e) {
            $message = showError('خطا در ویرایش مدل KPI: ' . $e->getMessage());
        }
    }
}

// Delete KPI model
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $modelId = $_GET['delete'];
    
    // Check if model is being used in any page KPIs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM page_kpis WHERE kpi_model_id = ?");
    $stmt->execute([$modelId]);
    $usageCount = $stmt->fetch()['count'];
    
    if ($usageCount > 0) {
        $message = showError('این مدل KPI قابل حذف نیست زیرا در ' . $usageCount . ' KPI صفحه استفاده شده است.');
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM kpi_models WHERE id = ?");
            $stmt->execute([$modelId]);
            $message = showSuccess('مدل KPI با موفقیت حذف شد.');
        } catch (PDOException $e) {
            $message = showError('خطا در حذف مدل KPI: ' . $e->getMessage());
        }
    }
}

// Get all KPI models with usage count
$stmt = $pdo->query("SELECT m.*, 
                   (SELECT COUNT(*) FROM page_kpis WHERE kpi_model_id = m.id) as usage_count 
                   FROM kpi_models m ORDER BY m.id");
$models = $stmt->fetchAll();

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت مدل‌های KPI</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModelModal">
        <i class="fas fa-plus"></i> افزودن مدل جدید
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body">
        <?php if (count($models) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام مدل</th>
                            <th>توضیحات</th>
                            <th>نوع مدل</th>
                            <th>تعداد استفاده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($models as $model): ?>
                            <tr>
                                <td><?php echo $model['id']; ?></td>
                                <td><?php echo $model['name']; ?></td>
                                <td><?php echo $model['description']; ?></td>
                                <td>
                                    <?php if ($model['model_type'] == 'growth_over_time'): ?>
                                        <span class="badge bg-info">رشد زمانی</span>
                                    <?php elseif ($model['model_type'] == 'percentage_of_field'): ?>
                                        <span class="badge bg-primary">درصدی از فیلد دیگر</span>
                                    <?php else: ?>
                                        <?php echo $model['model_type']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $model['usage_count']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModelModal" 
                                            data-id="<?php echo $model['id']; ?>"
                                            data-name="<?php echo $model['name']; ?>"
                                            data-description="<?php echo $model['description']; ?>"
                                            data-type="<?php echo $model['model_type']; ?>">
                                        <i class="fas fa-edit"></i> ویرایش
                                    </button>
                                    
                                    <?php if ($model['usage_count'] == 0): ?>
                                        <a href="?delete=<?php echo $model['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('آیا از حذف این مدل KPI اطمینان دارید؟')">
                                            <i class="fas fa-trash"></i> حذف
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center">هیچ مدل KPI یافت نشد.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add Model Modal -->
<div class="modal fade" id="addModelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن مدل KPI جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">نام مدل</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">توضیحات</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="model_type" class="form-label">نوع مدل</label>
                        <select class="form-select" id="model_type" name="model_type" required>
                            <option value="growth_over_time">رشد زمانی</option>
                            <option value="percentage_of_field">درصدی از فیلد دیگر</option>
                        </select>
                        <div class="form-text mt-2">
                            <strong>رشد زمانی:</strong> انتظار دارم فیلد X هر Y روز به مقدار N رشد کند.<br>
                            <strong>درصدی از فیلد دیگر:</strong> انتظار دارم فیلد X به مقدار N درصد از فیلد دیگر باشد.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_model" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Model Modal -->
<div class="modal fade" id="editModelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ویرایش مدل KPI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_model_id" name="model_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">نام مدل</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">توضیحات</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_model_type" class="form-label">نوع مدل</label>
                        <select class="form-select" id="edit_model_type" name="model_type" required>
                            <option value="growth_over_time">رشد زمانی</option>
                            <option value="percentage_of_field">درصدی از فیلد دیگر</option>
                        </select>
                        <div class="form-text mt-2">
                            <strong>رشد زمانی:</strong> انتظار دارم فیلد X هر Y روز به مقدار N رشد کند.<br>
                            <strong>درصدی از فیلد دیگر:</strong> انتظار دارم فیلد X به مقدار N درصد از فیلد دیگر باشد.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="edit_model" class="btn btn-primary">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit model modal
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editModelModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const description = button.getAttribute('data-description');
            const type = button.getAttribute('data-type');
            
            document.getElementById('edit_model_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_model_type').value = type;
        });
    }
});
</script>

<?php include 'footer.php'; ?>