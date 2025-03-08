<?php
// admin_profile.php - Admin profile and password management
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Check admin access
requireAdmin();

$message = '';
$adminId = $_SESSION['user_id'];

// Get admin info
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Update admin password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = clean($_POST['current_password']);
    $newPassword = clean($_POST['new_password']);
    $confirmPassword = clean($_POST['confirm_password']);
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = showError('لطفا تمام فیلدها را پر کنید.');
    } 
    else if ($newPassword !== $confirmPassword) {
        $message = showError('رمز عبور جدید با تکرار آن مطابقت ندارد.');
    }
    else if (strlen($newPassword) < 6) {
        $message = showError('رمز عبور جدید باید حداقل 6 کاراکتر باشد.');
    }
    else if (!verifyPassword($currentPassword, $admin['password'])) {
        $message = showError('رمز عبور فعلی اشتباه است.');
    }
    else {
        try {
            $hashedPassword = generateHash($newPassword);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $adminId]);
            $message = showSuccess('رمز عبور با موفقیت به‌روزرسانی شد.');
        } catch (PDOException $e) {
            $message = showError('خطا در به‌روزرسانی رمز عبور: ' . $e->getMessage());
        }
    }
}

// Update admin username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {
    $newUsername = clean($_POST['new_username']);
    $password = clean($_POST['password_confirm']);
    
    if (empty($newUsername) || empty($password)) {
        $message = showError('لطفا تمام فیلدها را پر کنید.');
    }
    else if (!verifyPassword($password, $admin['password'])) {
        $message = showError('رمز عبور اشتباه است.');
    }
    else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_users WHERE username = ? AND id != ?");
            $stmt->execute([$newUsername, $adminId]);
            $exists = $stmt->fetch()['count'] > 0;
            
            if ($exists) {
                $message = showError('این نام کاربری قبلاً استفاده شده است.');
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
                $stmt->execute([$newUsername, $adminId]);
                
                // Update session
                $_SESSION['username'] = $newUsername;
                
                $message = showSuccess('نام کاربری با موفقیت به‌روزرسانی شد.');
            }
        } catch (PDOException $e) {
            $message = showError('خطا در به‌روزرسانی نام کاربری: ' . $e->getMessage());
        }
    }
}

// Update admin personal info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal_info'])) {
    $firstName = clean($_POST['first_name']);
    $lastName = clean($_POST['last_name']);
    $email = clean($_POST['email']);
    $mobile = clean($_POST['mobile']);
    $password = clean($_POST['personal_info_password']);
    
    if (empty($password)) {
        $message = showError('لطفا رمز عبور را برای تأیید هویت وارد کنید.');
    }
    else if (!verifyPassword($password, $admin['password'])) {
        $message = showError('رمز عبور اشتباه است.');
    }
    else {
        try {
            // Check if email exists in admin_users table structure
            $columnsQuery = $pdo->prepare("SHOW COLUMNS FROM admin_users");
            $columnsQuery->execute();
            $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
            
            // If columns don't exist, add them
            $needToAddColumns = false;
            $columnsToAdd = [];
            
            if (!in_array('first_name', $columns)) {
                $columnsToAdd[] = "ADD COLUMN `first_name` VARCHAR(50) NULL";
                $needToAddColumns = true;
            }
            
            if (!in_array('last_name', $columns)) {
                $columnsToAdd[] = "ADD COLUMN `last_name` VARCHAR(50) NULL";
                $needToAddColumns = true;
            }
            
            if (!in_array('email', $columns)) {
                $columnsToAdd[] = "ADD COLUMN `email` VARCHAR(100) NULL";
                $needToAddColumns = true;
            }
            
            if (!in_array('mobile', $columns)) {
                $columnsToAdd[] = "ADD COLUMN `mobile` VARCHAR(20) NULL";
                $needToAddColumns = true;
            }
            
            if ($needToAddColumns) {
                $alterQuery = "ALTER TABLE admin_users " . implode(", ", $columnsToAdd);
                $pdo->exec($alterQuery);
            }
            
            // Update admin info
            $stmt = $pdo->prepare("UPDATE admin_users SET first_name = ?, last_name = ?, email = ?, mobile = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $email, $mobile, $adminId]);
            
            $message = showSuccess('اطلاعات شخصی با موفقیت به‌روزرسانی شد.');
            
            // Refresh admin data
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message = showError('خطا در به‌روزرسانی اطلاعات شخصی: ' . $e->getMessage());
        }
    }
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>پروفایل مدیر سیستم</h1>
    <a href="admin_dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
    </a>
</div>

<?php echo $message; ?>

<div class="row">
    <!-- New section for personal information -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">اطلاعات شخصی</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">نام</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo isset($admin['first_name']) ? $admin['first_name'] : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">نام خانوادگی</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo isset($admin['last_name']) ? $admin['last_name'] : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">ایمیل</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($admin['email']) ? $admin['email'] : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="mobile" class="form-label">موبایل</label>
                        <input type="text" class="form-control" id="mobile" name="mobile" 
                               value="<?php echo isset($admin['mobile']) ? $admin['mobile'] : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="personal_info_password" class="form-label">تأیید رمز عبور</label>
                        <input type="password" class="form-control" id="personal_info_password" name="personal_info_password" required>
                        <div class="form-text">برای تأیید هویت، رمز عبور فعلی خود را وارد کنید.</div>
                    </div>
                    <button type="submit" name="update_personal_info" class="btn btn-info text-white">
                        <i class="fas fa-save"></i> به‌روزرسانی اطلاعات شخصی
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">تغییر نام کاربری</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_username" class="form-label">نام کاربری فعلی</label>
                        <input type="text" class="form-control" id="current_username" value="<?php echo $admin['username']; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="new_username" class="form-label">نام کاربری جدید</label>
                        <input type="text" class="form-control" id="new_username" name="new_username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">تأیید رمز عبور</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                        <div class="form-text">برای تأیید هویت، رمز عبور فعلی خود را وارد کنید.</div>
                    </div>
                    <button type="submit" name="update_username" class="btn btn-primary">
                        <i class="fas fa-save"></i> به‌روزرسانی نام کاربری
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">تغییر رمز عبور</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">رمز عبور فعلی</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">رمز عبور جدید</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">رمز عبور باید حداقل 6 کاراکتر باشد.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تکرار رمز عبور جدید</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="update_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> به‌روزرسانی رمز عبور
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">اطلاعات حساب کاربری</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>نام کاربری:</strong> <?php echo $admin['username']; ?></p>
                        <p><strong>نوع کاربر:</strong> <span class="badge bg-danger">مدیر سیستم</span></p>
                        <?php if (isset($admin['first_name']) && !empty($admin['first_name'])): ?>
                            <p><strong>نام و نام خانوادگی:</strong> <?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></p>
                        <?php endif; ?>
                        <?php if (isset($admin['email']) && !empty($admin['email'])): ?>
                            <p><strong>ایمیل:</strong> <?php echo $admin['email']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>تاریخ ایجاد حساب:</strong> <?php echo $admin['created_at']; ?></p>
                        <?php if (isset($admin['mobile']) && !empty($admin['mobile'])): ?>
                            <p><strong>شماره موبایل:</strong> <?php echo $admin['mobile']; ?></p>
                        <?php endif; ?>
                        <p><strong>آخرین ورود:</strong> 
                            <?php 
                                // If you want to track last login, add this field to the database and display it here
                                echo "اطلاعات موجود نیست"; 
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> 
                    لطفاً از رمز عبور قوی استفاده کنید و آن را به صورت منظم تغییر دهید.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>