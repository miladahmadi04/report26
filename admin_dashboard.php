<?php
// executive_dashboard.php - داشبورد مدیریتی جامع
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// بررسی دسترسی کاربر
if (!isAdmin() && !isCEO()) {
    redirect('index.php');
}

// تنظیم تاریخ‌های پیش‌فرض برای فیلترها
$currentDate = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');
$firstDayOfLastMonth = date('Y-m-01', strtotime('-1 month'));
$lastDayOfLastMonth = date('Y-m-t', strtotime('-1 month'));

// دریافت تاریخ‌های فیلتر
$dateFrom = isset($_GET['date_from']) ? clean($_GET['date_from']) : $firstDayOfMonth;
$dateTo = isset($_GET['date_to']) ? clean($_GET['date_to']) : $lastDayOfMonth;

// انتخاب شرکت (برای مدیر سیستم)
$selectedCompany = null;
if (isAdmin()) {
    $selectedCompany = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    
    if (!$selectedCompany) {
        // گرفتن اولین شرکت به صورت پیش‌فرض
        $stmt = $pdo->query("SELECT id FROM companies WHERE is_active = 1 ORDER BY id LIMIT 1");
        $company = $stmt->fetch();
        $selectedCompany = $company ? $company['id'] : null;
    }
} else {
    // برای مدیرعامل، گرفتن شرکت مربوطه
    $stmt = $pdo->prepare("SELECT company_id FROM personnel WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $selectedCompany = $user ? $user['company_id'] : null;
}

// دریافت لیست شرکت‌ها برای فیلتر
$companies = [];
if (isAdmin()) {
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");
    $companies = $stmt->fetchAll();
}

// آمار گزارش‌های کوچ
$coachReports = [];
if ($selectedCompany) {
    $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total_reports,
                              SUM(CASE WHEN DATE(cr.report_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as current_period,
                              SUM(CASE WHEN DATE(cr.report_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as previous_period,
                              AVG(crp.coach_score) as avg_score
                          FROM coach_reports cr
                          LEFT JOIN coach_report_personnel crp ON cr.id = crp.coach_report_id
                          WHERE cr.company_id = ?");
    $stmt->execute([$dateFrom, $dateTo, $firstDayOfLastMonth, $lastDayOfLastMonth, $selectedCompany]);
    $coachReports = $stmt->fetch();
}

// آمار گزارش‌های روزانه
$dailyReports = [];
if ($selectedCompany) {
    $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total_reports,
                              COUNT(DISTINCT personnel_id) as total_personnel,
                              SUM(CASE WHEN DATE(report_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as current_period
                          FROM reports r
                          JOIN personnel p ON r.personnel_id = p.id
                          WHERE p.company_id = ?");
    $stmt->execute([$dateFrom, $dateTo, $selectedCompany]);
    $dailyReports = $stmt->fetch();
}

// آمار شبکه‌های اجتماعی
$socialStats = [];
if ($selectedCompany) {
    $stmt = $pdo->prepare("SELECT 
                              COUNT(DISTINCT sp.id) as total_pages,
                              COUNT(mr.id) as total_reports,
                              SUM(CASE WHEN DATE(mr.report_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as current_period,
                              SUM(CASE WHEN DATE(mr.report_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as previous_period
                          FROM social_pages sp
                          LEFT JOIN monthly_reports mr ON sp.id = mr.page_id
                          WHERE sp.company_id = ?");
    $stmt->execute([$dateFrom, $dateTo, $firstDayOfLastMonth, $lastDayOfLastMonth, $selectedCompany]);
    $socialStats = $stmt->fetch();
    
    // دریافت میانگین امتیاز KPI
    $stmt = $pdo->prepare("SELECT 
                              AVG(rs.score) as avg_score
                          FROM report_scores rs
                          JOIN monthly_reports mr ON rs.report_id = mr.id
                          JOIN social_pages sp ON mr.page_id = sp.id
                          WHERE sp.company_id = ? AND DATE(mr.report_date) BETWEEN ? AND ?");
    $stmt->execute([$selectedCompany, $dateFrom, $dateTo]);
    $kpiScores = $stmt->fetch();
    $socialStats['avg_score'] = $kpiScores ? $kpiScores['avg_score'] : 0;
}

// آمار پرسنل
$personnelStats = [];
if ($selectedCompany) {
    $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total_personnel,
                              SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_personnel,
                              COUNT(DISTINCT role_id) as total_roles
                          FROM personnel
                          WHERE company_id = ?");
    $stmt->execute([$selectedCompany]);
    $personnelStats = $stmt->fetch();
    
    // پرسنل فعال‌ترین
    $stmt = $pdo->prepare("SELECT 
                              p.id,
                              CONCAT(p.first_name, ' ', p.last_name) as full_name,
                              COUNT(r.id) as report_count
                          FROM personnel p
                          LEFT JOIN reports r ON p.id = r.personnel_id AND DATE(r.report_date) BETWEEN ? AND ?
                          WHERE p.company_id = ? AND p.is_active = 1
                          GROUP BY p.id
                          ORDER BY report_count DESC
                          LIMIT 5");
    $stmt->execute([$dateFrom, $dateTo, $selectedCompany]);
    $topPersonnel = $stmt->fetchAll();
}

// برنامه محتوایی آینده
$upcomingContent = [];
if ($selectedCompany) {
    $stmt = $pdo->prepare("SELECT 
                              c.id,
                              c.title,
                              c.publish_date,
                              ct.name as content_type,
                              ps.name as publish_status,
                              CONCAT(p.first_name, ' ', p.last_name) as creator_name
                          FROM contents c
                          LEFT JOIN content_type_relations ctr ON c.id = ctr.content_id
                          LEFT JOIN content_types ct ON ctr.type_id = ct.id
                          LEFT JOIN content_publish_statuses ps ON c.publish_status_id = ps.id
                          LEFT JOIN personnel p ON c.created_by = p.id
                          WHERE c.company_id = ? AND c.publish_date >= CURRENT_DATE
                          ORDER BY c.publish_date ASC
                          LIMIT 5");
    $stmt->execute([$selectedCompany]);
    $upcomingContent = $stmt->fetchAll();
}

// عملکرد شبکه‌های اجتماعی
$socialPerformance = [];
if ($selectedCompany) {
    $stmt = $pdo->prepare("SELECT 
                              sp.id,
                              sp.page_name,
                              sn.name as network_name,
                              sn.icon as network_icon,
                              COUNT(mr.id) as report_count,
                              AVG(rs.score) as avg_score
                          FROM social_pages sp
                          JOIN social_networks sn ON sp.social_network_id = sn.id
                          LEFT JOIN monthly_reports mr ON sp.id = mr.page_id AND DATE(mr.report_date) BETWEEN ? AND ?
                          LEFT JOIN report_scores rs ON mr.id = rs.report_id
                          WHERE sp.company_id = ?
                          GROUP BY sp.id
                          ORDER BY avg_score DESC
                          LIMIT 5");
    $stmt->execute([$dateFrom, $dateTo, $selectedCompany]);
    $socialPerformance = $stmt->fetchAll();
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>داشبورد مدیریتی</h1>
    <div>
        <?php if (!empty($selectedCompany)): ?>
            <a href="coach_report_list.php" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> گزارشات کوچ
            </a>
            <a href="social_pages.php" class="btn btn-info">
                <i class="fas fa-share-alt"></i> صفحات اجتماعی
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- فیلترها -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">فیلترها</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <?php if (isAdmin() && !empty($companies)): ?>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="company_id" class="form-label">شرکت</label>
                        <select class="form-select" id="company_id" name="company_id">
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" <?php echo ($selectedCompany == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo $company['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="date_from" class="form-label">از تاریخ</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="date_to" class="form-label">تا تاریخ</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">اعمال فیلتر</button>
                    </div>
                </div>
                
                <div class="col-md-12">
                    <div class="btn-group w-100">
                        <a href="?<?php echo isAdmin() ? "company_id=$selectedCompany&" : ''; ?>date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">امروز</a>
                        <a href="?<?php echo isAdmin() ? "company_id=$selectedCompany&" : ''; ?>date_from=<?php echo date('Y-m-d', strtotime('this week')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">هفته جاری</a>
                        <a href="?<?php echo isAdmin() ? "company_id=$selectedCompany&" : ''; ?>date_from=<?php echo $firstDayOfMonth; ?>&date_to=<?php echo $lastDayOfMonth; ?>" class="btn btn-outline-secondary">ماه جاری</a>
                        <a href="?<?php echo isAdmin() ? "company_id=$selectedCompany&" : ''; ?>date_from=<?php echo $firstDayOfLastMonth; ?>&date_to=<?php echo $lastDayOfLastMonth; ?>" class="btn btn-outline-secondary">ماه گذشته</a>
                        <a href="?<?php echo isAdmin() ? "company_id=$selectedCompany&" : ''; ?>date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-12-31'); ?>" class="btn btn-outline-secondary">سال جاری</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($selectedCompany)): ?>
<!-- کارت‌های آماری -->
<div class="row mb-4">
    <!-- عملکرد کلی -->
    <div class="col-md-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            امتیاز عملکرد کلی (شبکه اجتماعی)
                        </div>
                        <?php 
                        $avgScore = isset($socialStats['avg_score']) ? $socialStats['avg_score'] : 0;
                        $performanceLevel = getPerformanceLevel($avgScore);
                        ?>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <span class="text-<?php echo $performanceLevel['class']; ?>">
                                <?php echo number_format($avgScore, 1); ?> از 7
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- عملکرد کوچینگ -->
    <div class="col-md-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            امتیاز متوسط کوچینگ
                        </div>
                        <?php 
                        $coachScore = isset($coachReports['avg_score']) ? $coachReports['avg_score'] : 0;
                        $coachLevel = getPerformanceLevel($coachScore);
                        ?>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <span class="text-<?php echo $coachLevel['class']; ?>">
                                <?php echo number_format($coachScore, 1); ?> از 10
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گزارشات روزانه -->
    <div class="col-md-3">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            تعداد گزارشات روزانه (دوره جاری)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format(isset($dailyReports['current_period']) ? $dailyReports['current_period'] : 0); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- تعداد پرسنل -->
    <div class="col-md-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            تعداد پرسنل فعال
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format(isset($personnelStats['active_personnel']) ? $personnelStats['active_personnel'] : 0); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ردیف آمار تحلیلی -->
<div class="row mb-4">
    <!-- صفحات اجتماعی برتر -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">بهترین صفحات اجتماعی</h6>
                <a href="social_pages.php" class="btn btn-sm btn-primary">مشاهده همه</a>
            </div>
            <div class="card-body">
                <?php if (!empty($socialPerformance)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>نام صفحه</th>
                                    <th>شبکه</th>
                                    <th>امتیاز</th>
                                    <th>عملکرد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($socialPerformance as $page): ?>
                                    <tr>
                                        <td>
                                            <a href="view_social_report.php?page=<?php echo $page['id']; ?>">
                                                <?php echo $page['page_name']; ?>
                                            </a>
                                        </td>
                                        <td><i class="<?php echo $page['network_icon']; ?>"></i> <?php echo $page['network_name']; ?></td>
                                        <td>
                                            <?php 
                                                $level = getPerformanceLevel($page['avg_score']);
                                            ?>
                                            <span class="badge bg-<?php echo $level['class']; ?>">
                                                <?php echo number_format($page['avg_score'], 1); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $level['level']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        هیچ اطلاعات عملکردی برای صفحات اجتماعی در این بازه زمانی وجود ندارد.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- پرسنل فعال‌ترین -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-success">فعال‌ترین پرسنل</h6>
                <a href="personnel.php" class="btn btn-sm btn-success">مشاهده همه</a>
            </div>
            <div class="card-body">
                <?php if (!empty($topPersonnel)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>نام پرسنل</th>
                                    <th>تعداد گزارش</th>
                                    <th>میزان فعالیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $maxReports = $topPersonnel[0]['report_count']; // بیشترین تعداد گزارش
                                foreach ($topPersonnel as $person): 
                                    $activityPercent = $maxReports > 0 ? ($person['report_count'] / $maxReports) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo $person['full_name']; ?></td>
                                        <td><?php echo $person['report_count']; ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $activityPercent; ?>%" 
                                                     aria-valuenow="<?php echo $activityPercent; ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo number_format($activityPercent, 0); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        هیچ اطلاعاتی برای پرسنل در این بازه زمانی وجود ندارد.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ردیف پایین -->
<div class="row">
    <!-- محتواهای آینده -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-info">محتواهای آینده</h6>
                <a href="content_calendar.php" class="btn btn-sm btn-info">تقویم محتوایی</a>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingContent)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>عنوان</th>
                                    <th>تاریخ انتشار</th>
                                    <th>نوع محتوا</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingContent as $content): ?>
                                    <tr>
                                        <td><?php echo $content['title']; ?></td>
                                        <td><?php echo $content['publish_date']; ?></td>
                                        <td><?php echo $content['content_type']; ?></td>
                                        <td><?php echo $content['publish_status']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        هیچ محتوای برنامه‌ریزی شده‌ای برای آینده وجود ندارد.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- نمودار فعالیت‌ها -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">آمار فعالیت‌ها</h6>
            </div>
            <div class="card-body">
                <div id="activity-chart" style="height: 320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- نمودار مقایسه‌ای -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">مقایسه عملکرد ماه جاری با ماه قبل</h6>
            </div>
            <div class="card-body">
                <div id="comparison-chart" style="height: 350px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- اسکریپت‌های نمودار -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمودار فعالیت‌ها
    const activityOptions = {
        series: [{
            name: 'گزارشات روزانه',
            data: [<?php echo isset($dailyReports['current_period']) ? $dailyReports['current_period'] : 0; ?>]
        }, {
            name: 'گزارشات کوچ',
            data: [<?php echo isset($coachReports['current_period']) ? $coachReports['current_period'] : 0; ?>]
        }, {
            name: 'گزارشات شبکه اجتماعی',
            data: [<?php echo isset($socialStats['current_period']) ? $socialStats['current_period'] : 0; ?>]
        }],
        chart: {
            type: 'bar',
            height: 320,
            stacked: true,
            fontFamily: 'Tahoma, Arial, sans-serif',
            dir: 'rtl',
            toolbar: {
                show: false
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 5,
                dataLabels: {
                    position: 'center'
                }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val;
            },
            style: {
                fontSize: '12px',
                colors: ['#fff']
            }
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: ['دوره فعلی']
        },
        yaxis: {
            title: {
                text: 'تعداد'
            }
        },
        fill: {
            opacity: 1
        },
        colors: ['#4e73df', '#1cc88a', '#36b9cc'],
        legend: {
            position: 'top',
            horizontalAlign: 'center'
        }
    };
    
    const activityChart = new ApexCharts(document.querySelector("#activity-chart"), activityOptions);
    activityChart.render();
    
    // نمودار مقایسه‌ای
    const comparisonOptions = {
        series: [{
            name: 'دوره جاری',
            data: [
                <?php echo isset($dailyReports['current_period']) ? $dailyReports['current_period'] : 0; ?>,
                <?php echo isset($coachReports['current_period']) ? $coachReports['current_period'] : 0; ?>,
                <?php echo isset($socialStats['current_period']) ? $socialStats['current_period'] : 0; ?>
            ]
        }, {
            name: 'دوره قبلی',
            data: [
                <?php echo isset($dailyReports['current_period']) ? $dailyReports['current_period'] : 0; ?>, // دوره قبلی برای گزارشات روزانه در دسترس نیست
                <?php echo isset($coachReports['previous_period']) ? $coachReports['previous_period'] : 0; ?>,
                <?php echo isset($socialStats['previous_period']) ? $socialStats['previous_period'] : 0; ?>
            ]
        }],
        chart: {
            type: 'bar',
            height: 350,
            fontFamily: 'Tahoma, Arial, sans-serif',
            dir: 'rtl',
            toolbar: {
                show: false
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 5,
                dataLabels: {
                    position: 'top'
                }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val;
            },
            offsetY: -20,
            style: {
                fontSize: '12px',
                colors: ['#304758']
            }
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: ['گزارشات روزانه', 'گزارشات کوچ', 'گزارشات شبکه اجتماعی'],
            labels: {
                style: {
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            title: {
                text: 'تعداد'
            }
        },
        fill: {
            opacity: 1
        },
        colors: ['#4e73df', '#e74a3b'],
        legend: {
            position: 'top',
            horizontalAlign: 'center'
        }
    };
    
    const comparisonChart = new ApexCharts(document.querySelector("#comparison-chart"), comparisonOptions);
    comparisonChart.render();
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>