<?php
// personnel.php - Manage personnel
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Check admin access
requireAdmin();

$message = '';
$filterCompany = isset($_GET['company']) && is_numeric($_GET['company']) ? $_GET['company'] : null;

// Add new personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_personnel'])) {
    // دریافت اطلاعات فرم
    $company_ids = isset($_POST['company_ids']) ? $_POST['company_ids'] : [];
    $primary_company_id = isset($_POST['primary_company_id']) ? clean($_POST['primary_company_id']) : null;
    $role_id = clean($_POST['role_id']);
    $first_name = clean($_POST['first_name']);
    $last_name = clean($_POST['last_name']);
    $gender = clean($_POST['gender']);
    $email = clean($_POST['email']);
    $mobile = clean($_POST['mobile']);
    
    // بررسی معتبر بودن داده‌ها
    if (empty($company_ids) || empty($role_id) || empty($first_name) || empty($last_name) || 
        empty($gender) || empty($email) || empty($mobile) || empty($primary_company_id)) {
        $message = showError('لطفا تمام فیلدهای ضروری را پر کنید.');
    } else if (!in_array($primary_company_id, $company_ids)) {
        $message = showError('شرکت اصلی باید یکی از شرکت‌های انتخاب شده باشد.');
    } else {
        try {
            // Generate username and password
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . $last_name)) . rand(100, 999);
            $password = generateRandomPassword();
            $hashed_password = generateHash($password);
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Create user first
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, user_type) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$username, $hashed_password, $email]);
            $userId = $pdo->lastInsertId();
            
            // Create personnel
            $stmt = $pdo->prepare("INSERT INTO personnel (user_id, role_id, first_name, last_name, gender, email, mobile, username, password) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $role_id, $first_name, $last_name, $gender, $email, $mobile, $username, $hashed_password]);
            $personnelId = $pdo->lastInsertId();
            
            // ثبت رابطه کاربر با شرکت‌ها
            $insertStmt = $pdo->prepare("INSERT INTO personnel_companies (personnel_id, company_id, is_primary) VALUES (?, ?, ?)");
            
            foreach ($company_ids as $company_id) {
                $is_primary = ($company_id == $primary_company_id) ? 1 : 0;
                $insertStmt->execute([$personnelId, $company_id, $is_primary]);
            }
            
            $pdo->commit();
            $message = showSuccess("پرسنل با موفقیت اضافه شد. نام کاربری: $username | رمز عبور: $password");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = showError('خطا در ثبت پرسنل: ' . $e->getMessage());
        }
    }
}

// Edit personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_personnel'])) {
    $personnel_id = clean($_POST['personnel_id']);
    $company_ids = isset($_POST['company_ids']) ? $_POST['company_ids'] : [];
    $primary_company_id = isset($_POST['primary_company_id']) ? clean($_POST['primary_company_id']) : null;
    $role_id = clean($_POST['role_id']);
    $first_name = clean($_POST['first_name']);
    $last_name = clean($_POST['last_name']);
    $gender = clean($_POST['gender']);
    $email = clean($_POST['email']);
    $mobile = clean($_POST['mobile']);
    
    if (empty($personnel_id) || empty($company_ids) || empty($role_id) || empty($first_name) || 
        empty($last_name) || empty($gender) || empty($email) || empty($mobile) || empty($primary_company_id)) {
        $message = showError('لطفا تمام فیلدهای ضروری را پر کنید.');
    } else if (!in_array($primary_company_id, $company_ids)) {
        $message = showError('شرکت اصلی باید یکی از شرکت‌های انتخاب شده باشد.');
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Get user_id from personnel
            $stmt = $pdo->prepare("SELECT user_id FROM personnel WHERE id = ?");
            $stmt->execute([$personnel_id]);
            $user_id = $stmt->fetchColumn();
            
            if (!$user_id) {
                throw new Exception('پرسنل مورد نظر یافت نشد.');
            }
            
            // Update user email
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
            
            // Update personnel
            $stmt = $pdo->prepare("UPDATE personnel SET role_id = ?, first_name = ?, last_name = ?, 
                                 gender = ?, email = ?, mobile = ? WHERE id = ?");
            $stmt->execute([$role_id, $first_name, $last_name, $gender, $email, $mobile, $personnel_id]);
            
            // حذف تمام روابط قبلی شرکت‌ها
            $stmt = $pdo->prepare("DELETE FROM personnel_companies WHERE personnel_id = ?");
            $stmt->execute([$personnel_id]);
            
            // ثبت روابط جدید شرکت‌ها
            $insertStmt = $pdo->prepare("INSERT INTO personnel_companies (personnel_id, company_id, is_primary) VALUES (?, ?, ?)");
            
            foreach ($company_ids as $company_id) {
                $is_primary = ($company_id == $primary_company_id) ? 1 : 0;
                $insertStmt->execute([$personnel_id, $company_id, $is_primary]);
            }
            
            $pdo->commit();
            $message = showSuccess('اطلاعات پرسنل با موفقیت به‌روزرسانی شد.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = showError('خطا در ویرایش پرسنل: ' . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = showError($e->getMessage());
        }
    }
}

// Delete personnel
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $personnelId = $_GET['delete'];
    
    // Check if this is not the last admin user
    if (isLastAdmin($personnelId)) {
        $message = showError('این کاربر آخرین مدیر سیستم است و قابل حذف نیست.');
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Get user_id from personnel
            $stmt = $pdo->prepare("SELECT user_id FROM personnel WHERE id = ?");
            $stmt->execute([$personnelId]);
            $userId = $stmt->fetchColumn();
            
            if (!$userId) {
                throw new Exception('پرسنل مورد نظر یافت نشد.');
            }
            
            // حذف روابط شرکت‌ها
            $stmt = $pdo->prepare("DELETE FROM personnel_companies WHERE personnel_id = ?");
            $stmt->execute([$personnelId]);
            
            // Delete personnel first (due to foreign key constraint)
            $stmt = $pdo->prepare("DELETE FROM personnel WHERE id = ?");
            $stmt->execute([$personnelId]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            $message = showSuccess('پرسنل با موفقیت حذف شد.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = showError('خطا در حذف پرسنل: ' . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = showError($e->getMessage());
        }
    }
}

// Toggle personnel status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $personnelId = $_GET['toggle'];
    
    // Get current status
    $stmt = $pdo->prepare("SELECT p.is_active, p.user_id, u.user_type FROM personnel p 
                         JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$personnelId]);
    $personnel = $stmt->fetch();
    
    if ($personnel) {
        // Check if this is the last active admin user
        if ($personnel['user_type'] === 'admin' && $personnel['is_active'] === 1 && isLastAdmin($personnelId)) {
            $message = showError('این کاربر آخرین مدیر سیستم فعال است و نمی‌تواند غیرفعال شود.');
        } else {
            $newStatus = $personnel['is_active'] ? 0 : 1;
            
            try {
                $pdo->beginTransaction();
                
                // Update personnel status
                $stmt = $pdo->prepare("UPDATE personnel SET is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $personnelId]);
                
                // Update user status
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $personnel['user_id']]);
                
                $pdo->commit();
                $message = showSuccess('وضعیت پرسنل با موفقیت تغییر کرد.');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = showError('خطا در تغییر وضعیت پرسنل: ' . $e->getMessage());
            }
        }
    }
}

// Reset password
if (isset($_GET['reset']) && is_numeric($_GET['reset'])) {
    $personnelId = $_GET['reset'];
    
    // Generate new password
    $newPassword = generateRandomPassword();
    $hashedPassword = generateHash($newPassword);
    
    try {
        $pdo->beginTransaction();
        
        // Get user_id from personnel
        $stmt = $pdo->prepare("SELECT user_id FROM personnel WHERE id = ?");
        $stmt->execute([$personnelId]);
        $userId = $stmt->fetchColumn();
        
        if (!$userId) {
            throw new Exception('پرسنل مورد نظر یافت نشد.');
        }
        
        // Update personnel password
        $stmt = $pdo->prepare("UPDATE personnel SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $personnelId]);
        
        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        $pdo->commit();
        $message = showSuccess("رمز عبور با موفقیت بازنشانی شد. رمز عبور جدید: $newPassword");
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = showError('خطا در بازنشانی رمز عبور: ' . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = showError($e->getMessage());
    }
}

// Function to check if a personnel is the last admin
function isLastAdmin($personnelId) {
    global $pdo;
    
    // Get count of active admin users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel p 
                         JOIN users u ON p.user_id = u.id 
                         WHERE u.user_type = 'admin' AND u.is_active = 1");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    
    // Check if current personnel is an admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel p 
                         JOIN users u ON p.user_id = u.id 
                         WHERE p.id = ? AND u.user_type = 'admin' AND u.is_active = 1");
    $stmt->execute([$personnelId]);
    $isAdmin = $stmt->fetchColumn() > 0;
    
    // If there's only one admin left and current personnel is admin, return true
    return ($adminCount <= 1 && $isAdmin);
}

// Get all companies for the form
$stmt = $pdo->query("SELECT * FROM companies WHERE is_active = 1 ORDER BY name");
$companies = $stmt->fetchAll();

// Get all roles for the form
$stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
$roles = $stmt->fetchAll();

// Get personnel with company information
$query = "SELECT p.*, r.name as role_name, u.user_type, GROUP_CONCAT(c.name SEPARATOR ', ') as company_names,
         (SELECT c2.name FROM personnel_companies pc2 
          JOIN companies c2 ON pc2.company_id = c2.id 
          WHERE pc2.personnel_id = p.id AND pc2.is_primary = 1 
          LIMIT 1) as primary_company_name
         FROM personnel p 
         JOIN roles r ON p.role_id = r.id
         JOIN users u ON p.user_id = u.id
         LEFT JOIN personnel_companies pc ON p.id = pc.personnel_id
         LEFT JOIN companies c ON pc.company_id = c.id";

if ($filterCompany) {
    $query .= " WHERE pc.company_id = $filterCompany";
}

$query .= " GROUP BY p.id ORDER BY p.first_name, p.last_name";
$stmt = $pdo->query($query);
$personnelList = $stmt->fetchAll();

// Get personnel details for edit form
$editPersonnel = null;
$personnelCompanies = [];
$primaryCompanyId = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = $_GET['edit'];
    
    // دریافت اطلاعات پرسنل
    $stmt = $pdo->prepare("SELECT * FROM personnel WHERE id = ?");
    $stmt->execute([$editId]);
    $editPersonnel = $stmt->fetch();
    
    if ($editPersonnel) {
        // دریافت شرکت‌های پرسنل
        $stmt = $pdo->prepare("SELECT pc.*, c.name as company_name 
                              FROM personnel_companies pc 
                              JOIN companies c ON pc.company_id = c.id 
                              WHERE pc.personnel_id = ?");
        $stmt->execute([$editId]);
        $personnelCompanies = $stmt->fetchAll();
        
        // یافتن شرکت اصلی
        foreach ($personnelCompanies as $company) {
            if ($company['is_primary']) {
                $primaryCompanyId = $company['company_id'];
                break;
            }
        }
    }
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>مدیریت پرسنل</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPersonnelModal">
        <i class="fas fa-plus"></i> افزودن پرسنل جدید
    </button>
</div>

<?php echo $message; ?>

<?php if ($filterCompany): ?>
    <?php 
        $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
        $stmt->execute([$filterCompany]);
        $companyName = $stmt->fetch()['name'];
    ?>
    <div class="alert alert-info">
        در حال نمایش پرسنل شرکت: <?php echo $companyName; ?>
        <a href="personnel.php" class="btn btn-sm btn-outline-primary ms-2">نمایش همه</a>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (count($personnelList) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام و نام خانوادگی</th>
                            <th>شرکت‌ها</th>
                            <th>شرکت اصلی</th>
                            <th>نقش</th>
                            <th>جنسیت</th>
                            <th>ایمیل</th>
                            <th>موبایل</th>
                            <th>نام کاربری</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personnelList as $index => $person): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo $person['first_name'] . ' ' . $person['last_name']; ?></td>
                                <td><?php echo $person['company_names']; ?></td>
                                <td><?php echo $person['primary_company_name']; ?></td>
                                <td>
                                    <?php echo $person['role_name']; ?>
                                    <?php if ($person['user_type'] === 'admin'): ?>
                                        <span class="badge bg-danger">مدیر سیستم</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $person['gender'] == 'male' ? 'مرد' : 'زن'; ?></td>
                                <td><?php echo $person['email']; ?></td>
                                <td><?php echo $person['mobile']; ?></td>
                                <td><?php echo $person['username']; ?></td>
                                <td>
                                    <?php if ($person['is_active']): ?>
                                        <span class="badge bg-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?toggle=<?php echo $person['id']; ?>" class="btn 
                                            <?php echo $person['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                            <?php echo $person['is_active'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>
                                        </a>
                                        <a href="?reset=<?php echo $person['id']; ?>" class="btn btn-info"
                                           onclick="return confirm('آیا از بازنشانی رمز عبور اطمینان دارید؟')">
                                            بازنشانی رمز
                                        </a>
                                        <a href="?edit=<?php echo $person['id']; ?>" class="btn btn-primary">
                                            ویرایش
                                        </a>
                                        <a href="?delete=<?php echo $person['id']; ?>" class="btn btn-danger"
                                           onclick="return confirm('آیا از حذف این پرسنل اطمینان دارید؟ این عمل غیرقابل بازگشت است.')">
                                            حذف
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center">هیچ پرسنلی یافت نشد.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add Personnel Modal -->
<div class="modal fade" id="addPersonnelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن پرسنل جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">شرکت‌ها</label>
                        <div class="col-md-9">
                            <div class="alert alert-info">
                                لطفاً شرکت‌هایی که کاربر به آنها دسترسی خواهد داشت را انتخاب کنید.
                            </div>
                            <div class="row">
                                <?php foreach ($companies as $company): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input company-checkbox" type="checkbox" name="company_ids[]" 
                                                id="company_<?php echo $company['id']; ?>" value="<?php echo $company['id']; ?>">
                                            <label class="form-check-label" for="company_<?php echo $company['id']; ?>">
                                                <?php echo $company['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">شرکت اصلی</label>
                        <div class="col-md-9">
                            <select class="form-select" name="primary_company_id" id="primary_company_id" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" disabled data-company-id="<?php echo $company['id']; ?>">
                                        <?php echo $company['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">شرکت اصلی، شرکتی است که کاربر پس از ورود به سیستم به صورت پیش‌فرض با آن کار خواهد کرد.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">نقش</label>
                        <div class="col-md-9">
                            <select class="form-select" name="role_id" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="first_name" class="col-md-3 col-form-label">نام</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="last_name" class="col-md-3 col-form-label">نام خانوادگی</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">جنسیت</label>
                        <div class="col-md-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male" required>
                                <label class="form-check-label" for="gender_male">مرد</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female">
                                <label class="form-check-label" for="gender_female">زن</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="email" class="col-md-3 col-form-label">ایمیل</label>
                        <div class="col-md-9">
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="mobile" class="col-md-3 col-form-label">موبایل</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="mobile" name="mobile" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>نام کاربری و رمز عبور به صورت خودکار تولید و نمایش داده خواهد شد.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_personnel" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Personnel Modal -->
<?php if ($editPersonnel): ?>
<div class="modal fade" id="editPersonnelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ویرایش پرسنل</h5>
                <a href="personnel.php" class="btn-close" aria-label="Close"></a>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="personnel_id" value="<?php echo $editPersonnel['id']; ?>">
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">شرکت‌ها</label>
                        <div class="col-md-9">
                            <div class="alert alert-info">
                                لطفاً شرکت‌هایی که کاربر به آنها دسترسی خواهد داشت را انتخاب کنید.
                            </div>
                            <div class="row">
                                <?php 
                                $userCompanyIds = array_map(function($c) { return $c['company_id']; }, $personnelCompanies);
                                foreach ($companies as $company): 
                                ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input company-checkbox-edit" type="checkbox" name="company_ids[]" 
                                                id="edit_company_<?php echo $company['id']; ?>" value="<?php echo $company['id']; ?>"
                                                <?php echo in_array($company['id'], $userCompanyIds) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="edit_company_<?php echo $company['id']; ?>">
                                                <?php echo $company['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">شرکت اصلی</label>
                        <div class="col-md-9">
                            <select class="form-select" name="primary_company_id" id="edit_primary_company_id" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" 
                                        <?php echo $primaryCompanyId == $company['id'] ? 'selected' : ''; ?> 
                                        <?php echo !in_array($company['id'], $userCompanyIds) ? 'disabled' : ''; ?>
                                        data-company-id="<?php echo $company['id']; ?>">
                                        <?php echo $company['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">شرکت اصلی، شرکتی است که کاربر پس از ورود به سیستم به صورت پیش‌فرض با آن کار خواهد کرد.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">نقش</label>
                        <div class="col-md-9">
                            <select class="form-select" name="role_id" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo ($editPersonnel['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo $role['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="edit_first_name" class="col-md-3 col-form-label">نام</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" value="<?php echo $editPersonnel['first_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="edit_last_name" class="col-md-3 col-form-label">نام خانوادگی</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" value="<?php echo $editPersonnel['last_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">جنسیت</label>
                        <div class="col-md-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="edit_gender_male" value="male" <?php echo ($editPersonnel['gender'] == 'male') ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="edit_gender_male">مرد</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="edit_gender_female" value="female" <?php echo ($editPersonnel['gender'] == 'female') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_gender_female">زن</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="edit_email" class="col-md-3 col-form-label">ایمیل</label>
                        <div class="col-md-9">
                            <input type="email" class="form-control" id="edit_email" name="email" value="<?php echo $editPersonnel['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="edit_mobile" class="col-md-3 col-form-label">موبایل</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="edit_mobile" name="mobile" value="<?php echo $editPersonnel['mobile']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-3 col-form-label">نام کاربری</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" value="<?php echo $editPersonnel['username']; ?>" disabled>
                            <div class="form-text">نام کاربری قابل تغییر نیست. برای تغییر رمز عبور از دکمه بازنشانی رمز در لیست پرسنل استفاده کنید.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="personnel.php" class="btn btn-secondary">انصراف</a>
                    <button type="submit" name="edit_personnel" class="btn btn-primary">به‌روزرسانی</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = new bootstrap.Modal(document.getElementById('editPersonnelModal'));
        editModal.show();
        
        // کد JavaScript برای مدیریت انتخاب شرکت‌ها و شرکت اصلی در فرم ویرایش
        const companyCheckboxesEdit = document.querySelectorAll('.company-checkbox-edit');
        const primaryCompanySelectEdit = document.getElementById('edit_primary_company_id');
        
        companyCheckboxesEdit.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const companyId = this.value;
                const option = primaryCompanySelectEdit.querySelector(`option[data-company-id="${companyId}"]`);
                
                if (this.checked) {
                    option.disabled = false;
                } else {
                    option.disabled = true;
                    if (option.selected) {
                        primaryCompanySelectEdit.value = '';
                    }
                }
            });
        });
    });
</script>
<?php endif; ?>

<!-- کد JavaScript برای فرم افزودن پرسنل -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // کد JavaScript برای مدیریت انتخاب شرکت‌ها و شرکت اصلی در فرم افزودن
        const companyCheckboxes = document.querySelectorAll('.company-checkbox');
        const primaryCompanySelect = document.getElementById('primary_company_id');
        
        companyCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const companyId = this.value;
                const option = primaryCompanySelect.querySelector(`option[data-company-id="${companyId}"]`);
                
                if (this.checked) {
                    option.disabled = false;
                } else {
                    option.disabled = true;
                    if (option.selected) {
                        primaryCompanySelect.value = '';
                    }
                }
            });
        });
    });
</script>

<?php include 'footer.php'; ?>