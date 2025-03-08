<?php
// kb_schema.php - ساختار جداول پایگاه دانش
require_once 'database.php';

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
    
    // فعال کردن مجدد بررسی کلیدهای خارجی
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // اضافه کردن آیتم منو در جدول مربوطه (اگر وجود دارد)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE module = 'kb'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO menu_items (parent_id, title, icon, url, module, sort_order) 
                                  VALUES (0, 'پایگاه دانش', 'fas fa-book', 'kb_dashboard.php', 'kb', 50)");
            $stmt->execute();
        }
    } catch (PDOException $e) {
        // احتمالاً جدول menu_items وجود ندارد - نادیده می‌گیریم
    }
    
    echo "<div class='alert alert-success'>جداول پایگاه دانش با موفقیت ایجاد شدند.</div>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>خطا در ایجاد جداول پایگاه دانش: " . $e->getMessage() . "</div>";
}
?>