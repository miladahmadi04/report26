<?php
// تنظیمات اتصال به پایگاه داده
$host = 'localhost';
$dbname = 'company_management10';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

try {
    // ایجاد اتصال PDO
    $dsn = "mysql:host=$host;charset=$charset";
    $pdo = new PDO($dsn, $username, $password);
    
    // تنظیم حالت خطایابی
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ایجاد دیتابیس اگر وجود نداشته باشد
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
    $pdo->exec("USE `$dbname`");
    
    // غیرفعال کردن بررسی کلیدهای خارجی
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // جدول کاربران
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        user_type ENUM('admin', 'user') NOT NULL DEFAULT 'user',
        is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
    // جدول شرکت‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
    // جدول نقش‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT NULL,
        is_ceo BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        
    // جدول دسترسی‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
        code VARCHAR(100) NOT NULL UNIQUE,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول پرسنل
    $pdo->exec("CREATE TABLE IF NOT EXISTS personnel (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        company_id INT NOT NULL,
        role_id INT NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        gender ENUM('male', 'female') NOT NULL DEFAULT 'male',
        email VARCHAR(100) NOT NULL UNIQUE,
        mobile VARCHAR(20) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        position VARCHAR(100) NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
    
    // جدول رابطه نقش‌ها و دسترسی‌ها
        $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول انواع محتوا
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        is_default BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول محتوا
    $pdo->exec("CREATE TABLE IF NOT EXISTS contents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        scenario TEXT,
        description TEXT,
        production_status_id INT NOT NULL,
        publish_status_id INT NOT NULL,
        created_by INT NOT NULL,
        publish_date DATE NOT NULL,
        publish_time TIME DEFAULT '10:00:00',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (production_status_id) REFERENCES content_production_statuses(id) ON DELETE RESTRICT,
        FOREIGN KEY (publish_status_id) REFERENCES content_publish_statuses(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول رابطه محتوا و نوع محتوا
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_type_relations (
        content_id INT NOT NULL,
        type_id INT NOT NULL,
        PRIMARY KEY (content_id, type_id),
        FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE,
        FOREIGN KEY (type_id) REFERENCES content_types(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول وضعیت‌های تولید محتوا
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_production_statuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // جدول وضعیت‌های انتشار محتوا
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_publish_statuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

    // فعال کردن مجدد بررسی کلیدهای خارجی
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // ایجاد کاربر مدیر سیستم پیش‌فرض اگر وجود نداشته باشد
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, email, user_type) 
                    VALUES ('admin', '$adminPassword', 'admin@example.com', 'admin')");
    }

    // ایجاد نقش مدیر سیستم اگر وجود نداشته باشد
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles WHERE name = 'مدیر سیستم'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO roles (name, description, is_ceo) 
                    VALUES ('مدیر سیستم', 'دسترسی کامل به تمام بخش‌های سیستم', 1)");
        
        // دریافت شناسه نقش مدیر سیستم
        $adminRoleId = $pdo->lastInsertId();
        
        // ایجاد دسترسی‌های پیش‌فرض
        $defaultPermissions = [
            // داشبورد
            ['مشاهده داشبورد', 'view_dashboard', 'دسترسی به صفحه داشبورد'],
            
            // شرکت‌ها
            ['مشاهده شرکت‌ها', 'view_companies', 'مشاهده لیست شرکت‌ها'],
            ['افزودن شرکت', 'add_company', 'افزودن شرکت جدید'],
            ['ویرایش شرکت', 'edit_company', 'ویرایش اطلاعات شرکت'],
            ['حذف شرکت', 'delete_company', 'حذف شرکت'],
            ['تغییر وضعیت شرکت', 'toggle_company', 'فعال/غیرفعال کردن شرکت'],
            
            // پرسنل
            ['مشاهده پرسنل', 'view_personnel', 'مشاهده لیست پرسنل'],
            ['افزودن پرسنل', 'add_personnel', 'افزودن پرسنل جدید'],
            ['ویرایش پرسنل', 'edit_personnel', 'ویرایش اطلاعات پرسنل'],
            ['حذف پرسنل', 'delete_personnel', 'حذف پرسنل'],
            ['تغییر وضعیت پرسنل', 'toggle_personnel', 'فعال/غیرفعال کردن پرسنل'],
            ['بازنشانی رمز عبور', 'reset_password', 'بازنشانی رمز عبور پرسنل'],
            
            // نقش‌ها
            ['مشاهده نقش‌ها', 'view_roles', 'مشاهده لیست نقش‌ها'],
            ['افزودن نقش', 'add_role', 'افزودن نقش جدید'],
            ['ویرایش نقش', 'edit_role', 'ویرایش اطلاعات نقش'],
            ['حذف نقش', 'delete_role', 'حذف نقش'],
            ['مدیریت دسترسی‌ها', 'manage_permissions', 'تنظیم دسترسی‌های هر نقش'],
            
            // دسته‌بندی‌ها
            ['مشاهده دسته‌بندی‌ها', 'view_categories', 'مشاهده لیست دسته‌بندی‌ها'],
            ['افزودن دسته‌بندی', 'add_category', 'افزودن دسته‌بندی جدید'],
            ['ویرایش دسته‌بندی', 'edit_category', 'ویرایش اطلاعات دسته‌بندی'],
            ['حذف دسته‌بندی', 'delete_category', 'حذف دسته‌بندی'],
            
            // گزارش‌های روزانه
            ['مشاهده گزارش‌های روزانه', 'view_daily_reports', 'مشاهده لیست گزارش‌های روزانه'],
            ['افزودن گزارش روزانه', 'add_daily_report', 'ثبت گزارش روزانه جدید'],
            ['ویرایش گزارش روزانه', 'edit_daily_report', 'ویرایش گزارش روزانه'],
            ['حذف گزارش روزانه', 'delete_daily_report', 'حذف گزارش روزانه'],
            
            // گزارش‌های ماهانه
            ['مشاهده گزارش‌های ماهانه', 'view_monthly_reports', 'مشاهده لیست گزارش‌های ماهانه'],
            ['افزودن گزارش ماهانه', 'add_monthly_report', 'ثبت گزارش ماهانه جدید'],
            ['ویرایش گزارش ماهانه', 'edit_monthly_report', 'ویرایش گزارش ماهانه'],
            ['حذف گزارش ماهانه', 'delete_monthly_report', 'حذف گزارش ماهانه'],
            
            // گزارش‌های کوچ
            ['مشاهده گزارش‌های کوچ', 'view_coach_reports', 'مشاهده لیست گزارش‌های کوچ'],
            ['افزودن گزارش کوچ', 'add_coach_report', 'ثبت گزارش کوچ جدید'],
            ['ویرایش گزارش کوچ', 'edit_coach_report', 'ویرایش گزارش کوچ'],
            ['حذف گزارش کوچ', 'delete_coach_report', 'حذف گزارش کوچ'],
            
            // شبکه‌های اجتماعی
            ['مشاهده شبکه‌های اجتماعی', 'view_social_networks', 'مشاهده لیست شبکه‌های اجتماعی'],
            ['افزودن شبکه اجتماعی', 'add_social_network', 'افزودن شبکه اجتماعی جدید'],
            ['ویرایش شبکه اجتماعی', 'edit_social_network', 'ویرایش اطلاعات شبکه اجتماعی'],
            ['حذف شبکه اجتماعی', 'delete_social_network', 'حذف شبکه اجتماعی'],
            
            // صفحات اجتماعی
            ['مشاهده صفحات اجتماعی', 'view_social_pages', 'مشاهده لیست صفحات اجتماعی'],
            ['افزودن صفحه اجتماعی', 'add_social_page', 'افزودن صفحه اجتماعی جدید'],
            ['ویرایش صفحه اجتماعی', 'edit_social_page', 'ویرایش اطلاعات صفحه اجتماعی'],
            ['حذف صفحه اجتماعی', 'delete_social_page', 'حذف صفحه اجتماعی'],
            
            // مدیریت محتوا
            ['مشاهده محتواها', 'view_contents', 'مشاهده لیست محتواها'],
            ['افزودن محتوا', 'add_content', 'افزودن محتوای جدید'],
            ['ویرایش محتوا', 'edit_content', 'ویرایش محتوا'],
            ['حذف محتوا', 'delete_content', 'حذف محتوا'],
            ['مشاهده تقویم محتوا', 'view_content_calendar', 'مشاهده تقویم محتوایی'],
            ['مدیریت قالب‌های محتوا', 'manage_content_templates', 'مدیریت قالب‌های محتوایی'],
            
            // KPI و عملکرد
            ['مشاهده KPI', 'view_kpis', 'مشاهده شاخص‌های کلیدی عملکرد'],
            ['افزودن KPI', 'add_kpi', 'افزودن شاخص جدید'],
            ['ویرایش KPI', 'edit_kpi', 'ویرایش شاخص'],
            ['حذف KPI', 'delete_kpi', 'حذف شاخص'],
            ['مشاهده عملکرد', 'view_performance', 'مشاهده گزارش‌های عملکرد'],
            ['ثبت عملکرد', 'add_performance', 'ثبت عملکرد جدید'],
            ['ویرایش عملکرد', 'edit_performance', 'ویرایش عملکرد'],
            
            // تنظیمات
            ['مشاهده تنظیمات', 'view_settings', 'مشاهده تنظیمات سیستم'],
            ['ویرایش تنظیمات', 'edit_settings', 'ویرایش تنظیمات سیستم']
        ];
        
        // درج دسترسی‌های پیش‌فرض
        foreach ($defaultPermissions as $permission) {
            $stmt = $pdo->prepare("INSERT INTO permissions (name, code, description) VALUES (?, ?, ?)");
            $stmt->execute($permission);
            
            // دریافت شناسه دسترسی
            $permissionId = $pdo->lastInsertId();
            
            // اختصاص دسترسی به نقش مدیر سیستم
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$adminRoleId, $permissionId]);
        }
    }
	// ایجاد جداول مربوط به گزارش کوچ
$pdo->exec("CREATE TABLE IF NOT EXISTS coach_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    personnel_id INT NOT NULL,
    company_id INT NOT NULL,
    report_date DATE DEFAULT CURRENT_DATE,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    general_comments TEXT NULL,
    receiver_feedback TEXT NULL,
    feedback_date DATETIME NULL,
    coach_comment TEXT NULL,
    coach_score DECIMAL(3,1) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coach_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS coach_report_receivers (
    coach_report_id INT NOT NULL,
    receiver_id INT NOT NULL,
    PRIMARY KEY (coach_report_id, receiver_id),
    FOREIGN KEY (coach_report_id) REFERENCES coach_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS coach_report_personnel (
    coach_report_id INT NOT NULL,
    personnel_id INT NOT NULL,
    coach_comment TEXT NULL,
    coach_score DECIMAL(3,1) NULL,
    statistics_json JSON NULL,
    PRIMARY KEY (coach_report_id, personnel_id),
    FOREIGN KEY (coach_report_id) REFERENCES coach_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS coach_report_social_reports (
    coach_report_id INT NOT NULL,
    social_report_id INT NOT NULL,
    PRIMARY KEY (coach_report_id, social_report_id),
    FOREIGN KEY (coach_report_id) REFERENCES coach_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (social_report_id) REFERENCES monthly_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS coach_report_access (
    company_id INT NOT NULL,
    personnel_id INT NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (company_id, personnel_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
    
    // خط زیر حذف شده است:
    // echo "دیتابیس و جداول با موفقیت ایجاد شدند.\n";
    
} catch(PDOException $e) {
    die("خطا در ایجاد دیتابیس: " . $e->getMessage());
}