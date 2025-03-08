<?php
// kb_comments_batch.php - پردازش دسته‌ای نظرات پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// بررسی دسترسی به مدیریت نظرات
if (!kb_hasPermission('manage')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مدیریت نظرات پایگاه دانش را ندارید.';
    redirect('kb_dashboard.php');
}

// بررسی درخواست POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('kb_comments.php');
}

$companyId = $_SESSION['company_id'];
$action = isset($_POST['action']) ? clean($_POST['action']) : '';
$commentIds = isset($_POST['comment_ids']) ? $_POST['comment_ids'] : '';

// تبدیل رشته شناسه‌ها به آرایه
if (!empty($commentIds)) {
    $commentIds = explode(',', $commentIds);
    $commentIds = array_map('intval', $commentIds);
} else {
    $commentIds = [];
}

if (empty($commentIds)) {
    $_SESSION['error_message'] = 'هیچ نظری برای پردازش انتخاب نشده است.';
    redirect('kb_comments.php');
}

try {
    $pdo->beginTransaction();
    
    $successCount = 0;
    $errorCount = 0;
    
    switch ($action) {
        case 'approve_all':
            // تأیید همه نظرات انتخاب شده
            $placeholders = str_repeat('?,', count($commentIds) - 1) . '?';
            $params = array_merge([$companyId], $commentIds);
            
            $stmt = $pdo->prepare("UPDATE kb_comments c
                                  JOIN kb_articles a ON c.article_id = a.id
                                  SET c.status = 'approved'
                                  WHERE a.company_id = ? AND c.id IN ($placeholders)");
            $stmt->execute($params);
            
            $successCount = $stmt->rowCount();
            $_SESSION['success_message'] = "$successCount نظر با موفقیت تأیید شد.";
            break;
            
        case 'reject_all':
            // رد همه نظرات انتخاب شده
            $placeholders = str_repeat('?,', count($commentIds) - 1) . '?';
            $params = array_merge([$companyId], $commentIds);
            
            $stmt = $pdo->prepare("UPDATE kb_comments c
                                  JOIN kb_articles a ON c.article_id = a.id
                                  SET c.status = 'rejected'
                                  WHERE a.company_id = ? AND c.id IN ($placeholders)");
            $stmt->execute($params);
            
            $successCount = $stmt->rowCount();
            $_SESSION['success_message'] = "$successCount نظر با موفقیت رد شد.";
            break;
            
        case 'delete_all':
            // ابتدا حذف پاسخ‌های نظرات انتخاب شده
            $placeholders = str_repeat('?,', count($commentIds) - 1) . '?';
            $params = array_merge([$companyId], $commentIds);
            
            $stmt = $pdo->prepare("DELETE c FROM kb_comments c
                                  JOIN kb_articles a ON c.article_id = a.id
                                  WHERE a.company_id = ? AND c.parent_id IN ($placeholders)");
            $stmt->execute($params);
            
            // سپس حذف خود نظرات
            $stmt = $pdo->prepare("DELETE c FROM kb_comments c
                                  JOIN kb_articles a ON c.article_id = a.id
                                  WHERE a.company_id = ? AND c.id IN ($placeholders)");
            $stmt->execute($params);
            
            $successCount = $stmt->rowCount();
            $_SESSION['success_message'] = "$successCount نظر با موفقیت حذف شد.";
            break;
            
        default:
            $_SESSION['error_message'] = 'عملیات نامعتبر است.';
            $pdo->rollBack();
            redirect('kb_comments.php');
    }
    
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'خطا در پردازش دسته‌ای نظرات: ' . $e->getMessage();
}

// بازگشت به صفحه مدیریت نظرات
redirect('kb_comments.php');