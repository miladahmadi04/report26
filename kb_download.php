<?php
// kb_download.php - دانلود پیوست‌های مقالات پایگاه دانش
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';
require_once 'kb_functions.php';

// بررسی دسترسی کاربر
requireLogin();

// دریافت شناسه پیوست
$attachmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$attachmentId) {
    $_SESSION['error_message'] = 'پیوست مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// دریافت اطلاعات پیوست
$stmt = $pdo->prepare("SELECT a.*, ar.company_id, ar.status 
                      FROM kb_attachments a
                      JOIN kb_articles ar ON a.article_id = ar.id
                      WHERE a.id = ?");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    $_SESSION['error_message'] = 'پیوست مورد نظر یافت نشد.';
    redirect('kb_dashboard.php');
}

// بررسی دسترسی به مقاله پیوست
$companyId = $_SESSION['company_id'];

if ($attachment['company_id'] != $companyId) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای دانلود این پیوست را ندارید.';
    redirect('kb_dashboard.php');
}

// بررسی دسترسی به مشاهده پایگاه دانش
if (!kb_hasPermission('view')) {
    $_SESSION['error_message'] = 'شما دسترسی لازم برای مشاهده پایگاه دانش را ندارید.';
    redirect('index.php');
}

// بررسی وضعیت مقاله
if ($attachment['status'] !== 'published' && !isAdmin() && !kb_hasPermission('edit', 'article', $attachment['article_id'])) {
    $_SESSION['error_message'] = 'این مقاله منتشر نشده است.';
    redirect('kb_dashboard.php');
}

// بررسی وجود فایل
if (!file_exists($attachment['file_path'])) {
    $_SESSION['error_message'] = 'فایل مورد نظر در سرور یافت نشد.';
    redirect('kb_article.php?id=' . $attachment['article_id']);
}

try {
    // افزایش شمارنده دانلود
    $stmt = $pdo->prepare("UPDATE kb_attachments SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$attachmentId]);
    
    // نوع MIME فایل
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $attachment['file_path']);
    finfo_close($finfo);
    
    // نام فایل برای دانلود
    $filename = $attachment['file_name'];
    
    // تنظیم هدرهای HTTP برای دانلود
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($attachment['file_path']));
    
    // ارسال محتوای فایل
    readfile($attachment['file_path']);
    exit;
} catch (Exception $e) {
    $_SESSION['error_message'] = 'خطا در دانلود فایل: ' . $e->getMessage();
    redirect('kb_article.php?id=' . $attachment['article_id']);
}