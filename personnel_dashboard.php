<?php
// personnel_dashboard.php - Personnel dashboard
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

requirePersonnel();

$personnelId = $_SESSION['user_id'];

// نمایش پیام خطا در صورت وجود
$message = '';
if (isset($_SESSION['error_message'])) {
    $message = showError($_SESSION['error_message']);
    unset($_SESSION['error_message']);
}

// بررسی آیا کاربر فعلی دریافت کننده گزارش کوچ است
function isCoachReportRecipient() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    // بررسی جدول جدید new_coach_reports
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_coach_report_recipients WHERE personnel_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
    } catch (PDOException $e) {
        // اگر جدول وجود نداشته باشد یا خطای دیگری رخ دهد، ادامه می‌دهیم
    }
    
    // بررسی ساختار جدول قدیمی coach_reports اگر وجود داشته باشد
    try {
        $tableInfoQuery = "DESCRIBE coach_reports";
        $tableInfoStmt = $pdo->query($tableInfoQuery);
        $tableColumns = $tableInfoStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // اولویت با ستون receiver_id است
        if (in_array('receiver_id', $tableColumns)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coach_reports WHERE receiver_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetchColumn() > 0;
        }
        // اگر ستون recipients وجود دارد
        elseif (in_array('recipients', $tableColumns)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coach_reports WHERE FIND_IN_SET(?, recipients) > 0");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetchColumn() > 0;
        }
        // اگر ستون ceo_id وجود دارد
        elseif (in_array('ceo_id', $tableColumns)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coach_reports WHERE ceo_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetchColumn() > 0;
        }
        // اگر ستون personnel_id وجود دارد
        elseif (in_array('personnel_id', $tableColumns)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coach_reports WHERE personnel_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetchColumn() > 0;
        }
    } catch (PDOException $e) {
        // اگر جدول coach_reports وجود نداشته باشد، خطا را نادیده می‌گیریم
    }
    
    return false;
}

// تنظیم متغیر دسترسی به گزارش کوچ برای استفاده در header.php
$_SESSION['can_view_coach_reports'] = hasPermission('view_coach_reports') || isCoachReportRecipient();

// Get personnel information with error handling
try {
    $stmt = $pdo->prepare("SELECT p.*, c.name as company_name, r.name as role_name 
                        FROM personnel p 
                        LEFT JOIN companies c ON p.company_id = c.id 
                        LEFT JOIN roles r ON p.role_id = r.id 
                        WHERE p.id = ?");
    $stmt->execute([$personnelId]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // اگر اطلاعات پرسنل یافت نشد، مقادیر پیش‌فرض تنظیم شود
    if (!$personnel) {
        // دریافت اطلاعات از جدول users
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        $personnel = [
            'id' => $_SESSION['user_id'],
            'first_name' => isset($_SESSION['username']) ? $_SESSION['username'] : 'کاربر',
            'last_name' => '',
            'company_name' => isset($_SESSION['company_name']) ? $_SESSION['company_name'] : '',
            'role_name' => 'کاربر سیستم',
            'email' => $user ? $user['email'] : '',
            'mobile' => '',
        ];
    }
} catch (Exception $e) {
    // در صورت هر گونه خطا، مقادیر پیش‌فرض تنظیم شود
    $personnel = [
        'id' => $_SESSION['user_id'],
        'first_name' => isset($_SESSION['username']) ? $_SESSION['username'] : 'کاربر',
        'last_name' => '',
        'company_name' => isset($_SESSION['company_name']) ? $_SESSION['company_name'] : '',
        'role_name' => 'کاربر سیستم',
        'email' => '',
        'mobile' => '',
    ];
}

// مطمئن شویم که تمام فیلدهای مورد نیاز وجود دارند
$personnel['first_name'] = isset($personnel['first_name']) ? $personnel['first_name'] : 'کاربر';
$personnel['last_name'] = isset($personnel['last_name']) ? $personnel['last_name'] : '';
$personnel['role_name'] = isset($personnel['role_name']) ? $personnel['role_name'] : 'کاربر';
$personnel['email'] = isset($personnel['email']) ? $personnel['email'] : '';
$personnel['mobile'] = isset($personnel['mobile']) ? $personnel['mobile'] : '';

// مطمئن شویم که اطلاعات شرکت فعلی به‌روز است
if (isset($_SESSION['company_id']) && isset($_SESSION['companies'])) {
    foreach ($_SESSION['companies'] as $company) {
        if ($company['company_id'] == $_SESSION['company_id']) {
            $personnel['company_name'] = $company['company_name'];
            break;
        }
    }
}

// Save user's full name to session for use in welcome message
$_SESSION['full_name'] = $personnel['first_name'] . ' ' . $personnel['last_name'];

// If this is a CEO, show different dashboard
if (isCEO()) {
    // Count personnel in company
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM personnel WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['company_id']]);
    $totalPersonnel = $stmt->fetch()['count'];
    
    // Count total reports in company
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reports r 
                          JOIN personnel p ON r.personnel_id = p.id 
                          WHERE p.company_id = ?");
    $stmt->execute([$_SESSION['company_id']]);
    $totalReports = $stmt->fetch()['count'];
    
    // Get report count by date for company
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(r.report_date, '%Y-%m') as month, COUNT(*) as count 
                          FROM reports r
                          JOIN personnel p ON r.personnel_id = p.id
                          WHERE p.company_id = ?
                          GROUP BY month 
                          ORDER BY month DESC 
                          LIMIT 6");
    $stmt->execute([$_SESSION['company_id']]);
    $reportsByMonth = $stmt->fetchAll();
    
    // Get recent reports from company
    $stmt = $pdo->prepare("SELECT r.id, r.report_date, CONCAT(p.first_name, ' ', p.last_name) AS full_name,
                          (SELECT COUNT(*) FROM report_items WHERE report_id = r.id) as item_count
                          FROM reports r 
                          JOIN personnel p ON r.personnel_id = p.id 
                          WHERE p.company_id = ? 
                          ORDER BY r.report_date DESC, r.created_at DESC
                          LIMIT 10");
    $stmt->execute([$_SESSION['company_id']]);
    $recentReports = $stmt->fetchAll();
    
    // Get unread coach reports (if user is a recipient)
    $newCoachReports = [];
    if ($_SESSION['can_view_coach_reports']) {
        try {
            // بررسی جدول new_coach_reports
            $stmt = $pdo->prepare("SELECT cr.id, cr.report_date, cr.description,
                                CONCAT(p.first_name, ' ', p.last_name) as creator_name
                                FROM new_coach_reports cr
                                JOIN new_coach_report_recipients crr ON cr.id = crr.coach_report_id
                                JOIN personnel p ON cr.created_by = p.id
                                WHERE crr.personnel_id = ?
                                ORDER BY cr.report_date DESC
                                LIMIT 5");
            $stmt->execute([$_SESSION['user_id']]);
            $newCoachReports = $stmt->fetchAll();
        } catch (PDOException $e) {
            // اگر جدول وجود نداشته باشد، خطا را نادیده می‌گیریم
        }
    }
    
} else {
    // Regular personnel dashboard
    
    // Count total reports by personnel
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reports WHERE personnel_id = ?");
    $stmt->execute([$personnelId]);
    $totalReports = $stmt->fetch()['count'];
    
    // Get report count by date
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(report_date, '%Y-%m') as month, COUNT(*) as count 
                          FROM reports 
                          WHERE personnel_id = ? 
                          GROUP BY month 
                          ORDER BY month DESC 
                          LIMIT 6");
    $stmt->execute([$personnelId]);
    $reportsByMonth = $stmt->fetchAll();
    
    // Get report count by category
    $stmt = $pdo->prepare("SELECT c.name, COUNT(DISTINCT ri.report_id) as count 
                          FROM report_item_categories ric 
                          JOIN categories c ON ric.category_id = c.id 
                          JOIN report_items ri ON ric.item_id = ri.id
                          JOIN reports r ON ri.report_id = r.id
                          WHERE r.personnel_id = ? 
                          GROUP BY c.id 
                          ORDER BY count DESC 
                          LIMIT 5");
    $stmt->execute([$personnelId]);
    $reportsByCategory = $stmt->fetchAll();
    
    // Get recent reports
    $stmt = $pdo->prepare("SELECT r.id, r.report_date, 
                          (SELECT COUNT(*) FROM report_items WHERE report_id = r.id) as item_count
                          FROM reports r 
                          WHERE r.personnel_id = ? 
                          ORDER BY r.report_date DESC, r.created_at DESC
                          LIMIT 5");
    $stmt->execute([$personnelId]);
    $recentReports = $stmt->fetchAll();
    
    // Get coach reports (if user is a recipient)
    $newCoachReports = [];
    if ($_SESSION['can_view_coach_reports']) {
        try {
            // بررسی جدول new_coach_reports
            $stmt = $pdo->prepare("SELECT cr.id, cr.report_date, cr.description,
                                CONCAT(p.first_name, ' ', p.last_name) as creator_name
                                FROM new_coach_reports cr
                                JOIN new_coach_report_recipients crr ON cr.id = crr.coach_report_id
                                JOIN personnel p ON cr.created_by = p.id
                                WHERE crr.personnel_id = ?
                                ORDER BY cr.report_date DESC
                                LIMIT 5");
            $stmt->execute([$_SESSION['user_id']]);
            $newCoachReports = $stmt->fetchAll();
        } catch (PDOException $e) {
            // اگر جدول وجود نداشته باشد، خطا را نادیده می‌گیریم
        }
    }
}

// Try to get active slider items - only for regular personnel and CEOs, not for admin
$sliderItems = [];
$slideDuration = 5; // Default: 5 seconds
$transitionEffect = 'slide'; // Default: slide

if (!isAdmin()) { // Only load slider items if the user is not an administrator
    try {
        $stmt = $pdo->query("SELECT * FROM slider_items WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC");
        $sliderItems = $stmt->fetchAll();
        
        // Get slider settings
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('slide_duration', 'slide_transition')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (isset($settings['slide_duration'])) {
            $slideDuration = $settings['slide_duration'];
        }
        
        if (isset($settings['slide_transition'])) {
            $transitionEffect = $settings['slide_transition'];
        }
    } catch (PDOException $e) {
        // If table doesn't exist or other error, ignore it
    }
}

include 'header.php';
?>

<h1 class="mb-4">داشبورد <?php echo isCEO() ? 'مدیر عامل' : 'پرسنل'; ?></h1>

<?php echo $message; ?>

<!-- Slider Section -->
<?php if (count($sliderItems) > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div id="mainSlider" class="carousel <?php echo $transitionEffect == 'fade' ? 'carousel-fade' : 'slide'; ?>" data-bs-ride="carousel" data-bs-interval="<?php echo $slideDuration * 1000; ?>">
                    <div class="carousel-indicators">
                        <?php foreach ($sliderItems as $index => $slide): ?>
                            <button type="button" data-bs-target="#mainSlider" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active"' : ''; ?>></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="carousel-inner">
                        <?php foreach ($sliderItems as $index => $slide): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>" style="background-color: #000;">
                                <?php if (!empty($slide['link_url'])): ?>
                                    <a href="<?php echo $slide['link_url']; ?>" target="_blank">
                                <?php endif; ?>
                                
                                <div style="background-image: url('<?php echo $slide['image_path']; ?>'); position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-size: cover; background-position: center; filter: blur(15px); opacity: 0.7; transform: scale(1.1); z-index: 1;"></div>
                                <img src="<?php echo $slide['image_path']; ?>" class="d-block" alt="<?php echo $slide['title']; ?>" style="position: relative; z-index: 2; margin: 0 auto;">
                                
                                <?php /* Title removed as requested */ ?>
                                
                                <?php if (!empty($slide['link_url'])): ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#mainSlider" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">قبلی</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#mainSlider" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">بعدی</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">اطلاعات <?php echo isCEO() ? 'مدیر عامل' : 'پرسنلی'; ?></h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>نام و نام خانوادگی:</strong> 
                        <?php echo $personnel['first_name'] . ' ' . $personnel['last_name']; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>شرکت فعال:</strong> 
                        <?php echo $personnel['company_name']; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>نقش:</strong> 
                        <?php echo $personnel['role_name']; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>ایمیل:</strong> 
                        <?php echo $personnel['email']; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>موبایل:</strong> 
                        <?php echo $personnel['mobile']; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <!-- Quick Action Buttons -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">عملیات سریع</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <a href="<?php echo !isCEO() ? 'reports.php' : 'view_reports.php'; ?>" class="btn btn-primary w-100">
                            <i class="fas fa-clipboard-list"></i> 
                            <?php echo !isCEO() ? 'ثبت گزارش روزانه' : 'مشاهده گزارش‌های روزانه'; ?>
                        </a>
                    </div>
                    <?php if ($_SESSION['can_view_coach_reports']): ?>
                    <div class="col-md-4 mb-2">
                        <a href="new_coach_report_list.php" class="btn btn-info w-100">
                            <i class="fas fa-chart-line"></i> گزارشات کوچ
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (hasPermission('view_social_pages')): ?>
                    <div class="col-md-4 mb-2">
                        <a href="social_pages.php" class="btn btn-success w-100">
                            <i class="fas fa-share-alt"></i> صفحات اجتماعی
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['companies'])): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">انتخاب شرکت</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($_SESSION['companies'] as $company): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card <?php echo ($_SESSION['company_id'] == $company['company_id']) ? 'bg-primary text-white' : ''; ?>">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $company['company_name']; ?></h5>
                                    <?php if (isset($company['is_primary']) && $company['is_primary']): ?>
                                        <span class="badge bg-warning text-dark">شرکت اصلی</span>
                                    <?php endif; ?>
                                    <?php if ($_SESSION['company_id'] != $company['company_id']): ?>
                                        <div class="mt-2">
                                            <a href="switch_company.php?company_id=<?php echo $company['company_id']; ?>" class="btn btn-sm btn-light">انتخاب</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-<?php echo !empty($newCoachReports) ? '6' : '12'; ?>">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">گزارش‌های روزانه اخیر</h5>
            </div>
            <div class="card-body">
                <?php if (isset($recentReports) && count($recentReports) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <?php if (isCEO()): ?>
                                    <th>نام پرسنل</th>
                                    <?php endif; ?>
                                    <th>تعداد آیتم</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $report): ?>
                                    <tr>
                                        <td><?php echo $report['report_date']; ?></td>
                                        <?php if (isCEO()): ?>
                                        <td><?php echo $report['full_name']; ?></td>
                                        <?php endif; ?>
                                        <td><?php echo $report['item_count']; ?></td>
                                        <td>
                                            <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">مشاهده</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="view_reports.php" class="btn btn-primary">مشاهده همه گزارش‌ها</a>
                    </div>
                <?php else: ?>
                    <p class="text-center">هیچ گزارشی یافت نشد.</p>
                    <?php if (!isCEO()): ?>
                    <div class="text-center">
                        <a href="reports.php" class="btn btn-primary">ثبت گزارش جدید</a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($newCoachReports)): ?>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">گزارش‌های کوچ اخیر</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>تهیه کننده</th>
                                <th>توضیحات</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newCoachReports as $report): ?>
                                <tr>
                                    <td><?php echo $report['report_date']; ?></td>
                                    <td><?php echo $report['creator_name']; ?></td>
                                    <td>
                                        <?php 
                                        // نمایش بخشی از توضیحات
                                        echo mb_strlen($report['description']) > 30 
                                             ? mb_substr($report['description'], 0, 30) . '...' 
                                             : $report['description']; 
                                        ?>
                                    </td>
                                    <td>
                                        <a href="new_coach_report_view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">مشاهده</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-center">
                    <a href="new_coach_report_list.php" class="btn btn-primary">مشاهده همه گزارش‌ها</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>