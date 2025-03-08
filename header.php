<?php
// header.php - Header template for all pages
require_once 'auth.php';
// همگام‌سازی شرکت فعال در جدول personnel (برای سازگاری با new_coach_report.php)
if (function_exists('syncActiveCompany')) {
    syncActiveCompany();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت شرکت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="fontstyle.css" rel="stylesheet">
    
<style>
    body {
        font-family: Tahoma, Arial, sans-serif;
        background-color: #f0f0f1;
        color: #3c434a;
        margin: 0;
    }
    
    .dashboard-layout {
        display: flex;
        min-height: 100vh;
    }
    
    .admin-menu {
        background-color: #1d2327;
        color: #fff;
        width: 260px;
        position: fixed;
        top: 0;
        bottom: 0;
        right: 0;
        overflow-y: auto;
        z-index: 9990;
    }
    
    .admin-content {
        margin-right: 260px;
        padding: 20px;
        width: calc(100% - 260px);
    }
    
    .menu-header {
        padding: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 10px;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        background-color: #2271b1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 10px;
    }
    
    .user-info-details {
        flex-grow: 1;
    }
    
    .user-full-name {
        font-weight: bold;
        margin-bottom: 4px;
    }
    
    .user-role {
        font-size: 0.8rem;
        opacity: 0.8;
    }
    
    .company-switcher-container {
        margin-bottom: 15px;
    }
    
    .company-current {
        padding: 8px 10px;
        background-color: #2c3338;
        border-radius: 4px;
        color: #fff;
    }
    
    .wp-menu-item {
        position: relative;
    }
    
    .wp-menu-item > a {
        padding: 10px 15px;
        display: flex;
        align-items: center;
        color: #c3c4c7;
        text-decoration: none;
    }
    
    .wp-menu-item > a:hover {
        color: #fff;
        background-color: #2c3338;
    }
    
    .wp-menu-item > a.active {
        color: #fff;
        background-color: #2271b1;
        font-weight: 600;
    }
    
    .wp-menu-item i.menu-icon {
        width: 20px;
        margin-left: 8px;
        font-size: 16px;
        text-align: center;
    }
    
    .wp-submenu {
        background-color: #2c3338;
        padding: 8px 0;
        display: none;
    }
    
    .wp-menu-item:hover > .wp-submenu {
        display: block;
    }
    
    .wp-submenu-item a {
        padding: 6px 12px 6px 34px;
        color: #c3c4c7;
        display: block;
        text-decoration: none;
    }
    
    .wp-submenu-item a:hover {
        color: #fff;
        background-color: rgba(240, 246, 252, 0.04);
    }
    
    .wp-submenu-item a.active {
        color: #fff;
        font-weight: 600;
        background-color: rgba(240, 246, 252, 0.04);
    }
    
    .menu-toggle-indicator {
        margin-right: auto;
    }
    
    .wp-badge {
        display: inline-block;
        padding: 2px 6px;
        margin-right: 5px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .wp-badge-admin {
        background-color: #d63638;
        color: #fff;
    }
    
    .wp-badge-ceo {
        background-color: #fd7e14;
        color: #fff;
    }
    
    .menu-footer {
        padding: 10px 15px;
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: 20px;
    }
    
    .logout-button {
        display: flex;
        align-items: center;
        color: #c3c4c7;
        padding: 8px;
        text-decoration: none;
    }
    
    .logout-button:hover {
        background-color: #d63638;
        color: white;
        border-radius: 4px;
        text-decoration: none;
    }
    
    .logout-button i {
        margin-left: 8px;
    }
    
    /* Mobile styles */
    .mobile-toggle {
        position: fixed;
        top: 10px;
        right: 10px;
        background-color: #1d2327;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 9999;
        border: none;
    }
    
    @media (max-width: 768px) {
        .mobile-toggle {
            display: flex;
        }
        
        .admin-menu {
            display: none;
        }
        
        .admin-menu.show {
            display: block;
        }
        
        .admin-content {
            margin-right: 0;
            width: 100%;
        }
    }
</style>

</head>
<body>

<!-- دکمه منوی موبایل - واقعا ساده -->
<button class="mobile-toggle" onclick="toggleMenu()">
    <i class="fas fa-bars"></i>
</button>

<div class="dashboard-layout">
    <!-- منوی کناری -->
    <div class="admin-menu" id="adminMenu">
        <!-- بخش اطلاعات کاربر -->
        <div class="menu-header">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info-details">
                    <div class="user-full-name">
                        <?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username']; ?>
                        <?php if (isAdmin()): ?>
                            <span class="wp-badge wp-badge-admin">مدیر سیستم</span>
                        <?php elseif (isCEO()): ?>
                            <span class="wp-badge wp-badge-ceo">مدیر عامل</span>
                        <?php endif; ?>
                    </div>
                    <div class="user-role">
                        <?php 
                            if (isAdmin()) {
                                echo 'مدیر سیستم';
                            } elseif (isCEO()) {
                                echo 'مدیر عامل';
                            } else {
                                echo 'کاربر سیستم';
                            }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- نمایش شرکت فعلی -->
            <?php if (!isAdmin() && isset($_SESSION['company_name'])): ?>
            <div class="company-switcher-container">
                <div class="company-current">
                    <i class="fas fa-building me-1"></i> <?php echo $_SESSION['company_name']; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- منوی اصلی -->
        <?php if (isAdmin()): ?>
            <!-- Admin Menu -->
            <div class="wp-menu-item">
                <a href="admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt menu-icon"></i>
                    <span class="menu-text">داشبورد</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="companies.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'companies.php' ? 'active' : ''; ?>">
                    <i class="fas fa-building menu-icon"></i>
                    <span class="menu-text">مدیریت شرکت‌ها</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="personnel.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'personnel.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users menu-icon"></i>
                    <span class="menu-text">مدیریت پرسنل</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="roles.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tag menu-icon"></i>
                    <span class="menu-text">نقش‌های کاربری</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags menu-icon"></i>
                    <span class="menu-text">دسته‌بندی‌ها</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="content_management.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['content_management.php', 'content_add.php', 'content_edit.php', 'content_list.php', 'content_view.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt menu-icon"></i>
                    <span class="menu-text">مدیریت محتوا</span>
                </a>
            </div>
            
            <!-- منوی پایگاه دانش - اضافه شده -->
            <div class="wp-menu-item">
                <a href="kb_dashboard.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['kb_dashboard.php', 'kb_article.php', 'kb_article_form.php', 'kb_articles.php', 'kb_category.php', 'kb_categories.php', 'kb_tag.php', 'kb_tags.php', 'kb_search.php', 'kb_statistics.php', 'kb_comments.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-book menu-icon"></i>
                    <span class="menu-text">پایگاه دانش</span>
                    <i class="fas fa-chevron-down menu-toggle-indicator"></i>
                </a>
                <div class="wp-submenu">
                    <div class="wp-submenu-item">
                        <a href="kb_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kb_dashboard.php' ? 'active' : ''; ?>">
                            داشبورد پایگاه دانش
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="kb_articles.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kb_articles.php' ? 'active' : ''; ?>">
                            مدیریت مقالات
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="kb_categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kb_categories.php' ? 'active' : ''; ?>">
                            دسته‌بندی‌ها
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="kb_tags.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kb_tags.php' ? 'active' : ''; ?>">
                            برچسب‌ها
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="kb_comments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kb_comments.php' ? 'active' : ''; ?>">
                            نظرات
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="kb_statistics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kb_statistics.php' ? 'active' : ''; ?>">
                            آمار و گزارشات
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- گزارشات - Reports -->
            <div class="wp-menu-item">
                <a href="view_reports.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['view_reports.php', 'view_report.php', 'report_management.php', 'report_categories.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list menu-icon"></i>
                    <span class="menu-text">مدیریت گزارشات</span>
                    <i class="fas fa-chevron-down menu-toggle-indicator"></i>
                </a>
                <div class="wp-submenu">
                    <div class="wp-submenu-item">
                        <a href="view_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_reports.php' ? 'active' : ''; ?>">
                            لیست گزارشات
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="report_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'report_management.php' ? 'active' : ''; ?>">
                            آمار و تحلیل گزارشات
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- شبکه های اجتماعی - Social Networks -->
            <div class="wp-menu-item">
                <a href="social_networks.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['social_networks.php', 'social_network_fields.php', 'social_pages.php', 'kpi_models.php', 'page_kpi.php', 'social_report.php', 'view_social_report.php', 'expected_performance.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-share-alt menu-icon"></i>
                    <span class="menu-text">شبکه‌های اجتماعی</span>
                    <i class="fas fa-chevron-down menu-toggle-indicator"></i>
                </a>
                <div class="wp-submenu">
                    <div class="wp-submenu-item">
                        <a href="social_networks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'social_networks.php' ? 'active' : ''; ?>">
                            مدیریت شبکه‌ها
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="social_network_fields.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'social_network_fields.php' ? 'active' : ''; ?>">
                            فیلدهای شبکه‌ها
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="social_pages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'social_pages.php' ? 'active' : ''; ?>">
                            صفحات اجتماعی
                        </a>
                    </div>
                    <div class="wp-submenu-item">
                        <a href="kpi_models.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kpi_models.php' ? 'active' : ''; ?>">
                            مدل‌های KPI
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Coach Reports Menu -->
            <?php if (hasPermission('view_coach_reports')): ?>
            <div class="wp-menu-item">
                <a href="new_coach_report_list.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['new_coach_report.php', 'new_coach_report_list.php', 'new_coach_report_view.php', 'new_coach_report_edit.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line menu-icon"></i>
                    <span class="menu-text">گزارش کوچ</span>
                    <i class="fas fa-chevron-down menu-toggle-indicator"></i>
                </a>
                <div class="wp-submenu">
                    <div class="wp-submenu-item">
                        <a href="new_coach_report_list.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'new_coach_report_list.php' ? 'active' : ''; ?>">
                            لیست گزارش‌ها
                        </a>
                    </div>
                    <?php if (hasPermission('add_coach_report')): ?>
                    <div class="wp-submenu-item">
                        <a href="new_coach_report.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'new_coach_report.php' ? 'active' : ''; ?>">
                            گزارش جدید
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="wp-menu-item">
                <a href="admin_profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog menu-icon"></i>
                    <span class="menu-text">پروفایل مدیر</span>
                </a>
            </div>
            
        <?php elseif (isCEO()): ?>
            <!-- CEO Menu -->
            <div class="wp-menu-item">
                <a href="personnel_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'personnel_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt menu-icon"></i>
                    <span class="menu-text">داشبورد</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="view_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_reports.php' || basename($_SERVER['PHP_SELF']) == 'view_report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard menu-icon"></i>
                    <span class="menu-text">گزارش‌های روزانه</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="content_management.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['content_management.php', 'content_add.php', 'content_edit.php', 'content_list.php', 'content_view.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt menu-icon"></i>
                    <span class="menu-text">مدیریت محتوا</span>
                </a>
            </div>
            
            <!-- منوی پایگاه دانش برای مدیرعامل - اضافه شده -->
            <div class="wp-menu-item">
                <a href="kb_dashboard.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['kb_dashboard.php', 'kb_article.php', 'kb_category.php', 'kb_search.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-book menu-icon"></i>
                    <span class="menu-text">پایگاه دانش</span>
                </a>
            </div>
            
            <!-- Coach Reports Menu for CEO -->
            <?php if (isset($_SESSION['can_view_coach_reports']) && $_SESSION['can_view_coach_reports']): ?>
            <div class="wp-menu-item">
                <a href="new_coach_report_list.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['new_coach_report_list.php', 'new_coach_report_view.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line menu-icon"></i>
                    <span class="menu-text">گزارشات کوچ</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Social media section for CEO -->
            <div class="wp-menu-item">
                <a href="social_pages.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['social_pages.php', 'page_kpi.php', 'social_report.php', 'view_social_report.php', 'expected_performance.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-share-alt menu-icon"></i>
                    <span class="menu-text">شبکه‌های اجتماعی</span>
                    <i class="fas fa-chevron-down menu-toggle-indicator"></i>
                </a>
                <div class="wp-submenu">
                    <div class="wp-submenu-item">
                        <a href="social_pages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'social_pages.php' ? 'active' : ''; ?>">
                            صفحات اجتماعی
                        </a>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Personnel Menu -->
            <div class="wp-menu-item">
                <a href="personnel_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'personnel_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt menu-icon"></i>
                    <span class="menu-text">داشبورد</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard menu-icon"></i>
                    <span class="menu-text">ثبت گزارش روزانه</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="view_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_reports.php' || basename($_SERVER['PHP_SELF']) == 'view_report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list menu-icon"></i>
                    <span class="menu-text">مشاهده گزارش‌ها</span>
                </a>
            </div>
            
            <div class="wp-menu-item">
                <a href="content_management.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['content_management.php', 'content_add.php', 'content_edit.php', 'content_list.php', 'content_view.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt menu-icon"></i>
                    <span class="menu-text">مدیریت محتوا</span>
                </a>
            </div>
            
            <!-- منوی پایگاه دانش برای پرسنل - اضافه شده -->
            <div class="wp-menu-item">
                <a href="kb_dashboard.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['kb_dashboard.php', 'kb_article.php', 'kb_category.php', 'kb_search.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-book menu-icon"></i>
                    <span class="menu-text">پایگاه دانش</span>
                </a>
            </div>
            
            <!-- Coach Reports Menu for Personnel -->
            <?php if (isset($_SESSION['can_view_coach_reports']) && $_SESSION['can_view_coach_reports']): ?>
            <div class="wp-menu-item">
                <a href="new_coach_report_list.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['new_coach_report_list.php', 'new_coach_report_view.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line menu-icon"></i>
                    <span class="menu-text">گزارشات کوچ</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Social media reporting for regular personnel -->
            <div class="wp-menu-item">
                <a href="social_pages.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['social_pages.php', 'page_kpi.php', 'social_report.php', 'view_social_report.php', 'expected_performance.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-share-alt menu-icon"></i>
                    <span class="menu-text">شبکه‌های اجتماعی</span>
                    <i class="fas fa-chevron-down menu-toggle-indicator"></i>
                </a>
                <div class="wp-submenu">
                    <div class="wp-submenu-item">
                        <a href="social_pages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'social_pages.php' ? 'active' : ''; ?>">
                            صفحات اجتماعی
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- دکمه خروج -->
        <div class="menu-footer">
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">خروج از سیستم</span>
            </a>
        </div>
    </div>

    <!-- محتوا -->
    <div class="admin-content">

<script>
// ساده‌ترین کد ممکن برای تغییر وضعیت منو در موبایل
function toggleMenu() {
    document.getElementById('adminMenu').classList.toggle('show');
}
</script>