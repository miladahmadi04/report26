<?php
// kb_install.php - نصب ماژول پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// بررسی دسترسی مدیر
requireAdmin();

$message = '';

// اگر دکمه نصب زده شد
if (isset($_POST['install'])) {
    try {
        // غیرفعال کردن بررسی کلیدهای خارجی
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // جدول دسته‌بندی‌های پایگاه دانش
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NULL DEFAULT NULL,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            icon VARCHAR(50) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES kb_categories(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول مقالات پایگاه دانش
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            excerpt TEXT NULL,
            status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
            is_featured BOOLEAN DEFAULT FALSE,
            is_public BOOLEAN DEFAULT FALSE,
            views_count INT DEFAULT 0,
            created_by INT NOT NULL,
            updated_by INT NULL,
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY (company_id, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول رابطه مقالات و دسته‌بندی‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_article_categories (
            article_id INT NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (article_id, category_id),
            FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول پیوست‌های مقالات
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            download_count INT DEFAULT 0,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول برچسب‌های مقالات
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            UNIQUE KEY (company_id, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول رابطه مقالات و برچسب‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_article_tags (
            article_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (article_id, tag_id),
            FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES kb_tags(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول مشاهدات مقالات
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            user_id INT NULL,
            personnel_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول امتیازدهی به مقالات
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            user_id INT NULL,
            personnel_id INT NULL,
            rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
            feedback TEXT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول نظرات مقالات
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            parent_id INT NULL DEFAULT NULL,
            user_id INT NULL,
            personnel_id INT NULL,
            author_name VARCHAR(100) NULL,
            comment TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES kb_comments(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول دسترسی‌های پایگاه دانش
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            role_id INT NULL,
            permission_type ENUM('view', 'create', 'edit', 'delete', 'manage') NOT NULL,
            resource_type ENUM('all', 'category', 'article') NOT NULL,
            resource_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // جدول جستجوهای کاربران
        $pdo->exec("CREATE TABLE IF NOT EXISTS kb_search_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            user_id INT NULL,
            personnel_id INT NULL,
            search_query VARCHAR(255) NOT NULL,
            result_count INT NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL,
            search_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
        // اضافه کردن دسترسی‌های پیش‌فرض
        $defaultPermissions = [
            ['view_kb', 'مشاهده پایگاه دانش', 'دسترسی به مشاهده پایگاه دانش و مقالات عمومی'],
            ['edit_kb', 'ویرایش پایگاه دانش', 'دسترسی به ایجاد و ویرایش مقالات پایگاه دانش'],
            ['manage_kb', 'مدیریت پایگاه دانش', 'دسترسی کامل به پایگاه دانش شامل دسته‌بندی‌ها و تنظیمات']
        ];
        
        foreach ($defaultPermissions as $permission) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE code = ?");
            $stmt->execute([$permission[0]]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
                $stmt->execute($permission);
            }
        }
        
        // اضافه کردن منوی پایگاه دانش به جدول منوها (اگر وجود دارد)
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'menu_items'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE url = 'kb_dashboard.php'");
                $stmt->execute();
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO menu_items (parent_id, title, icon, url, module, sort_order) 
                                        VALUES (0, 'پایگاه دانش', 'fas fa-book', 'kb_dashboard.php', 'kb', 50)");
                    $stmt->execute();
                }
            }
        } catch (PDOException $e) {
            // جدول منو احتمالاً وجود ندارد - نادیده می‌گیریم
        }
        
        // ایجاد پوشه‌های مورد نیاز
        $uploadDirs = [
            'uploads',
            'uploads/kb_attachments'
        ];
        
        foreach ($uploadDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // فعال کردن مجدد بررسی کلیدهای خارجی
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $message = '<div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ماژول پایگاه دانش با موفقیت نصب شد.
                        <br>اکنون می‌توانید به <a href="kb_dashboard.php" class="alert-link">پایگاه دانش</a> دسترسی داشته باشید.
                    </div>';
        
        // ایجاد ردایرکت به پایگاه دانش
        redirect('kb_dashboard.php');
        
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> خطا در نصب ماژول پایگاه دانش: ' . $e->getMessage() . 
                    '</div>';
    }
}

// بررسی نصب بودن ماژول پایگاه دانش
$isInstalled = false;
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'kb_articles'");
    $stmt->execute();
    $isInstalled = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    // خطا در بررسی وضعیت نصب - نادیده می‌گیریم
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>نصب ماژول پایگاه دانش</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> بازگشت به صفحه اصلی
    </a>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body">
        <?php if ($isInstalled): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> ماژول پایگاه دانش قبلاً نصب شده است.
                <br>می‌توانید به <a href="kb_dashboard.php" class="alert-link">پایگاه دانش</a> دسترسی داشته باشید.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> قبل از نصب، لطفاً از پایگاه داده خود پشتیبان تهیه کنید.
            </div>
            
            <h5>ویژگی‌های ماژول پایگاه دانش:</h5>
            <ul>
                <li>مدیریت مقالات و محتوای آموزشی</li>
                <li>دسته‌بندی و برچسب‌گذاری محتوا</li>
                <li>جستجوی پیشرفته</li>
                <li>امکان پیوست فایل به مقالات</li>
                <li>نظرات و امتیازدهی کاربران</li>
                <li>آمار بازدید و گزارش‌گیری</li>
                <li>تنظیمات دسترسی کاربران</li>
            </ul>
            
            <p>برای نصب ماژول پایگاه دانش، دکمه زیر را کلیک کنید:</p>
            
            <form method="POST" action="">
                <button type="submit" name="install" class="btn btn-primary">
                    <i class="fas fa-download"></i> نصب ماژول پایگاه دانش
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>