<?php
// kb_functions.php - توابع اختصاصی پایگاه دانش
require_once 'functions.php';

/**
 * بررسی دسترسی کاربر به پایگاه دانش
 * @param string $permissionType نوع دسترسی (view, create, edit, delete, manage)
 * @param string $resourceType نوع منبع (all, category, article)
 * @param int|null $resourceId شناسه منبع (اختیاری)
 * @return bool آیا کاربر دسترسی دارد یا خیر
 */
function kb_hasPermission($permissionType, $resourceType = 'all', $resourceId = null) {
    global $pdo;
    
    // مدیر سیستم همه دسترسی‌ها را دارد
    if (isAdmin()) {
        return true;
    }
    
    // برای دسترسی مشاهده مقالات عمومی نیازی به بررسی بیشتر نیست
    if ($permissionType === 'view' && $resourceType === 'article') {
        $stmt = $pdo->prepare("SELECT is_public FROM kb_articles WHERE id = ?");
        $stmt->execute([$resourceId]);
        $isPublic = $stmt->fetchColumn();
        
        if ($isPublic) {
            return true;
        }
    }
    
    // بررسی دسترسی بر اساس نقش
    if (isset($_SESSION['role_id']) && isset($_SESSION['company_id'])) {
        $roleId = $_SESSION['role_id'];
        $companyId = $_SESSION['company_id'];
        
        // اول: بررسی دسترسی در جدول kb_permissions
        $query = "SELECT COUNT(*) FROM kb_permissions 
                 WHERE company_id = ? AND role_id = ? 
                 AND permission_type = ? AND resource_type = ?";
        $params = [$companyId, $roleId, $permissionType, $resourceType];
        
        if ($resourceId !== null && $resourceType !== 'all') {
            $query .= " AND (resource_id = ? OR resource_id IS NULL)";
            $params[] = $resourceId;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // دوم: بررسی دسترسی از جدول permissions اصلی سیستم
        switch ($permissionType) {
            case 'view':
                return hasPermission('view_kb');
            case 'create':
            case 'edit':
                return hasPermission('edit_kb');
            case 'delete':
            case 'manage':
                return hasPermission('manage_kb');
            default:
                return false;
        }
    }
    
    return false;
}

/**
 * دریافت دسته‌بندی‌های پایگاه دانش
 * @param int|null $parentId شناسه دسته‌بندی والد (اختیاری)
 * @return array آرایه‌ای از دسته‌بندی‌ها
 */
function kb_getCategories($parentId = null) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $query = "SELECT c.*, 
             (SELECT COUNT(*) FROM kb_article_categories ac 
              JOIN kb_articles a ON ac.article_id = a.id 
              WHERE ac.category_id = c.id AND a.status = 'published') as article_count,
             CONCAT(p.first_name, ' ', p.last_name) as creator_name
             FROM kb_categories c
             LEFT JOIN users u ON c.created_by = u.id
             LEFT JOIN personnel p ON u.id = p.user_id
             WHERE c.company_id = ?";
    
    $params = [$companyId];
    
    if ($parentId === null) {
        $query .= " AND c.parent_id IS NULL";
    } else {
        $query .= " AND c.parent_id = ?";
        $params[] = $parentId;
    }
    
    $query .= " ORDER BY c.sort_order, c.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * دریافت یک دسته‌بندی با شناسه
 * @param int $categoryId شناسه دسته‌بندی
 * @return array|null اطلاعات دسته‌بندی
 */
function kb_getCategory($categoryId) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT c.*, 
                          (SELECT COUNT(*) FROM kb_categories WHERE parent_id = c.id) as subcategory_count,
                          (SELECT COUNT(*) FROM kb_article_categories ac 
                           JOIN kb_articles a ON ac.article_id = a.id 
                           WHERE ac.category_id = c.id AND a.status = 'published') as article_count,
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name
                          FROM kb_categories c
                          LEFT JOIN users u ON c.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE c.id = ? AND c.company_id = ?");
    $stmt->execute([$categoryId, $companyId]);
    
    return $stmt->fetch();
}

/**
 * دریافت مقالات یک دسته‌بندی
 * @param int $categoryId شناسه دسته‌بندی
 * @param int $limit محدودیت تعداد (اختیاری)
 * @param int $offset شروع از (اختیاری)
 * @return array آرایه‌ای از مقالات
 */
function kb_getCategoryArticles($categoryId, $limit = 10, $offset = 0) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating,
                          (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count
                          FROM kb_articles a
                          JOIN kb_article_categories ac ON a.id = ac.article_id
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE ac.category_id = ? AND a.company_id = ? AND a.status = 'published'
                          ORDER BY a.is_featured DESC, a.published_at DESC
                          LIMIT ? OFFSET ?");
    $stmt->execute([$categoryId, $companyId, $limit, $offset]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت تعداد کل مقالات یک دسته‌بندی
 * @param int $categoryId شناسه دسته‌بندی
 * @return int تعداد کل مقالات
 */
function kb_getCategoryArticleCount($categoryId) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) 
                          FROM kb_articles a
                          JOIN kb_article_categories ac ON a.id = ac.article_id
                          WHERE ac.category_id = ? AND a.company_id = ? AND a.status = 'published'");
    $stmt->execute([$categoryId, $companyId]);
    
    return $stmt->fetchColumn();
}

/**
 * دریافت مقاله با شناسه
 * @param int $articleId شناسه مقاله
 * @return array|null اطلاعات مقاله
 */
function kb_getArticle($articleId) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          CONCAT(p2.first_name, ' ', p2.last_name) as updater_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating,
                          (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          LEFT JOIN users u2 ON a.updated_by = u2.id
                          LEFT JOIN personnel p2 ON u2.id = p2.user_id
                          WHERE a.id = ? AND a.company_id = ?");
    $stmt->execute([$articleId, $companyId]);
    
    return $stmt->fetch();
}

/**
 * دریافت مقاله با اسلاگ
 * @param string $slug اسلاگ مقاله
 * @return array|null اطلاعات مقاله
 */
function kb_getArticleBySlug($slug) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          CONCAT(p2.first_name, ' ', p2.last_name) as updater_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating,
                          (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          LEFT JOIN users u2 ON a.updated_by = u2.id
                          LEFT JOIN personnel p2 ON u2.id = p2.user_id
                          WHERE a.slug = ? AND a.company_id = ?");
    $stmt->execute([$slug, $companyId]);
    
    return $stmt->fetch();
}

/**
 * دریافت دسته‌بندی‌های یک مقاله
 * @param int $articleId شناسه مقاله
 * @return array آرایه‌ای از دسته‌بندی‌ها
 */
function kb_getArticleCategories($articleId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT c.* 
                          FROM kb_categories c
                          JOIN kb_article_categories ac ON c.id = ac.category_id
                          WHERE ac.article_id = ?
                          ORDER BY c.name");
    $stmt->execute([$articleId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت برچسب‌های یک مقاله
 * @param int $articleId شناسه مقاله
 * @return array آرایه‌ای از برچسب‌ها
 */
function kb_getArticleTags($articleId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT t.* 
                          FROM kb_tags t
                          JOIN kb_article_tags at ON t.id = at.tag_id
                          WHERE at.article_id = ?
                          ORDER BY t.name");
    $stmt->execute([$articleId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت پیوست‌های یک مقاله
 * @param int $articleId شناسه مقاله
 * @return array آرایه‌ای از پیوست‌ها
 */
function kb_getArticleAttachments($articleId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as uploader_name
                          FROM kb_attachments a
                          LEFT JOIN users u ON a.uploaded_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.article_id = ?
                          ORDER BY a.created_at DESC");
    $stmt->execute([$articleId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت نظرات یک مقاله
 * @param int $articleId شناسه مقاله
 * @param string $status وضعیت نظرات (approved, pending, rejected, all)
 * @return array آرایه‌ای از نظرات
 */
function kb_getArticleComments($articleId, $status = 'approved') {
    global $pdo;
    
    $query = "SELECT c.*, 
              CONCAT(p.first_name, ' ', p.last_name) as user_name
              FROM kb_comments c
              LEFT JOIN users u ON c.user_id = u.id
              LEFT JOIN personnel p ON c.personnel_id = p.id
              WHERE c.article_id = ? AND c.parent_id IS NULL";
    
    if ($status !== 'all') {
        $query .= " AND c.status = ?";
    }
    
    $query .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    
    if ($status !== 'all') {
        $stmt->execute([$articleId, $status]);
    } else {
        $stmt->execute([$articleId]);
    }
    
    $comments = $stmt->fetchAll();
    
    // دریافت پاسخ‌های هر نظر
    foreach ($comments as &$comment) {
        $query = "SELECT c.*, 
                 CONCAT(p.first_name, ' ', p.last_name) as user_name
                 FROM kb_comments c
                 LEFT JOIN users u ON c.user_id = u.id
                 LEFT JOIN personnel p ON c.personnel_id = p.id
                 WHERE c.parent_id = ?";
        
        if ($status !== 'all') {
            $query .= " AND c.status = ?";
        }
        
        $query .= " ORDER BY c.created_at ASC";
        
        $stmt = $pdo->prepare($query);
        
        if ($status !== 'all') {
            $stmt->execute([$comment['id'], $status]);
        } else {
            $stmt->execute([$comment['id']]);
        }
        
        $comment['replies'] = $stmt->fetchAll();
    }
    
    return $comments;
}

/**
 * افزودن یک بازدید جدید به مقاله
 * @param int $articleId شناسه مقاله
 * @return bool موفقیت یا عدم موفقیت
 */
function kb_addArticleView($articleId) {
    global $pdo;
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $personnelId = null;
    
    // اگر کاربر عادی نیست، بررسی کنیم شناسه پرسنل را بگیریم
    if ($userId && !isAdmin()) {
        $stmt = $pdo->prepare("SELECT id FROM personnel WHERE user_id = ?");
        $stmt->execute([$userId]);
        $personnelId = $stmt->fetchColumn();
    }
    
    // افزایش شمارنده بازدید مقاله
    $stmt = $pdo->prepare("UPDATE kb_articles SET views_count = views_count + 1 WHERE id = ?");
    $stmt->execute([$articleId]);
    
    // ثبت بازدید در جدول kb_views
    $stmt = $pdo->prepare("INSERT INTO kb_views (article_id, user_id, personnel_id, ip_address) 
                          VALUES (?, ?, ?, ?)");
    return $stmt->execute([$articleId, $userId, $personnelId, $_SERVER['REMOTE_ADDR']]);
}

/**
 * ثبت امتیاز برای مقاله
 * @param int $articleId شناسه مقاله
 * @param int $rating امتیاز (1 تا 5)
 * @param string|null $feedback بازخورد (اختیاری)
 * @return bool موفقیت یا عدم موفقیت
 */
function kb_rateArticle($articleId, $rating, $feedback = null) {
    global $pdo;
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $personnelId = null;
    
    // اگر کاربر عادی نیست، بررسی کنیم شناسه پرسنل را بگیریم
    if ($userId && !isAdmin()) {
        $stmt = $pdo->prepare("SELECT id FROM personnel WHERE user_id = ?");
        $stmt->execute([$userId]);
        $personnelId = $stmt->fetchColumn();
    }
    
    // بررسی اینکه کاربر قبلاً امتیاز داده یا خیر
    $stmt = $pdo->prepare("SELECT id FROM kb_ratings 
                          WHERE article_id = ? AND 
                          ((user_id IS NOT NULL AND user_id = ?) OR 
                           (personnel_id IS NOT NULL AND personnel_id = ?))");
    $stmt->execute([$articleId, $userId, $personnelId]);
    $existingRatingId = $stmt->fetchColumn();
    
    if ($existingRatingId) {
        // به‌روزرسانی امتیاز قبلی
        $stmt = $pdo->prepare("UPDATE kb_ratings 
                              SET rating = ?, feedback = ?, created_at = CURRENT_TIMESTAMP 
                              WHERE id = ?");
        return $stmt->execute([$rating, $feedback, $existingRatingId]);
    } else {
        // ثبت امتیاز جدید
        $stmt = $pdo->prepare("INSERT INTO kb_ratings 
                              (article_id, user_id, personnel_id, rating, feedback, ip_address) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$articleId, $userId, $personnelId, $rating, $feedback, $_SERVER['REMOTE_ADDR']]);
    }
}

/**
 * ثبت نظر برای مقاله
 * @param int $articleId شناسه مقاله
 * @param string $comment متن نظر
 * @param int|null $parentId شناسه نظر والد (برای پاسخ به نظر)
 * @return bool موفقیت یا عدم موفقیت
 */
function kb_addComment($articleId, $comment, $parentId = null) {
    global $pdo;
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $personnelId = null;
    $authorName = null;
    
    // اگر کاربر عادی نیست، بررسی کنیم شناسه پرسنل را بگیریم
    if ($userId && !isAdmin()) {
        $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
                              FROM personnel WHERE user_id = ?");
        $stmt->execute([$userId]);
        $personnel = $stmt->fetch();
        
        if ($personnel) {
            $personnelId = $personnel['id'];
            $authorName = $personnel['full_name'];
        }
    } else if ($userId && isAdmin()) {
        $authorName = 'مدیر سیستم';
    } else {
        // کاربر مهمان
        $authorName = 'کاربر مهمان';
    }
    
    // تنظیم وضعیت نظر - برای مدیران و پرسنل، نظر به صورت خودکار تأیید می‌شود
    $status = (isAdmin() || isset($_SESSION['logged_in'])) ? 'approved' : 'pending';
    
    // ثبت نظر
    $stmt = $pdo->prepare("INSERT INTO kb_comments 
                          (article_id, parent_id, user_id, personnel_id, author_name, comment, status, ip_address) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$articleId, $parentId, $userId, $personnelId, $authorName, $comment, $status, $_SERVER['REMOTE_ADDR']]);
}

/**
 * جستجو در مقالات پایگاه دانش
 * @param string $query عبارت جستجو
 * @param int $limit محدودیت تعداد (اختیاری)
 * @param int $offset شروع از (اختیاری)
 * @return array آرایه‌ای از نتایج جستجو
 */
function kb_searchArticles($query, $limit = 20, $offset = 0) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    // جستجو در عنوان، خلاصه و محتوای مقالات
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating,
                          MATCH(a.title, a.content, a.excerpt) AGAINST (? IN BOOLEAN MODE) as relevance
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.company_id = ? AND a.status = 'published'
                          AND (
                              MATCH(a.title, a.content, a.excerpt) AGAINST (? IN BOOLEAN MODE)
                              OR a.title LIKE ?
                              OR a.content LIKE ?
                              OR a.excerpt LIKE ?
                          )
                          ORDER BY relevance DESC, a.views_count DESC
                          LIMIT ? OFFSET ?");
    $likeQuery = "%$query%";
    $stmt->execute([$query, $companyId, $query, $likeQuery, $likeQuery, $likeQuery, $limit, $offset]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت مقالات پیشنهادی مرتبط با یک مقاله
 * @param int $articleId شناسه مقاله
 * @param int $limit محدودیت تعداد (اختیاری)
 * @return array آرایه‌ای از مقالات پیشنهادی
 */
function kb_getRelatedArticles($articleId, $limit = 5) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    // ابتدا دسته‌بندی‌های مقاله فعلی را بگیریم
    $stmt = $pdo->prepare("SELECT category_id FROM kb_article_categories WHERE article_id = ?");
    $stmt->execute([$articleId]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($categories)) {
        // اگر دسته‌بندی ندارد، محبوب‌ترین مقالات را برگردانیم
        $stmt = $pdo->prepare("SELECT a.*, 
                              CONCAT(p.first_name, ' ', p.last_name) as creator_name
                              FROM kb_articles a
                              LEFT JOIN users u ON a.created_by = u.id
                              LEFT JOIN personnel p ON u.id = p.user_id
                              WHERE a.company_id = ? AND a.status = 'published' AND a.id != ?
                              ORDER BY a.views_count DESC
                              LIMIT ?");
        $stmt->execute([$companyId, $articleId, $limit]);
        
        return $stmt->fetchAll();
    }
    
    // مقالات مرتبط در همان دسته‌بندی‌ها
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COUNT(DISTINCT ac.category_id) as category_matches
                          FROM kb_articles a
                          JOIN kb_article_categories ac ON a.id = ac.article_id
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.company_id = ? AND a.status = 'published' AND a.id != ?
                          AND ac.category_id IN ($placeholders)
                          GROUP BY a.id
                          ORDER BY category_matches DESC, a.views_count DESC
                          LIMIT ?");
    
    $params = array_merge([$companyId, $articleId], $categories, [$limit]);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * دریافت مقالات محبوب
 * @param int $limit محدودیت تعداد (اختیاری)
 * @return array آرایه‌ای از مقالات محبوب
 */
function kb_getPopularArticles($limit = 5) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    // Convert limit to integer and include directly in query
    $limit = (int)$limit;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.company_id = ? AND a.status = 'published'
                          ORDER BY a.views_count DESC
                          LIMIT $limit");
    $stmt->execute([$companyId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت مقالات برتر (بیشترین امتیاز)
 * @param int $limit محدودیت تعداد (اختیاری)
 * @return array آرایه‌ای از مقالات برتر
 */
function kb_getTopRatedArticles($limit = 5) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    // Convert limit to integer and include directly in query
    $limit = (int)$limit;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.company_id = ? AND a.status = 'published'
                          AND EXISTS (SELECT 1 FROM kb_ratings WHERE article_id = a.id)
                          ORDER BY average_rating DESC, a.views_count DESC
                          LIMIT $limit");
    $stmt->execute([$companyId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت مقالات ویژه
 * @param int $limit محدودیت تعداد (اختیاری)
 * @return array آرایه‌ای از مقالات ویژه
 */
function kb_getFeaturedArticles($limit = 5) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    // Convert limit to integer and include directly in query
    $limit = (int)$limit;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.company_id = ? AND a.status = 'published' AND a.is_featured = 1
                          ORDER BY a.published_at DESC
                          LIMIT $limit");
    $stmt->execute([$companyId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت تمام برچسب‌های فعال در یک شرکت
 * @return array آرایه‌ای از برچسب‌ها
 */
function kb_getAllTags() {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT t.*, 
                          (SELECT COUNT(*) FROM kb_article_tags at 
                           JOIN kb_articles a ON at.article_id = a.id
                           WHERE at.tag_id = t.id AND a.status = 'published') as article_count
                          FROM kb_tags t
                          WHERE t.company_id = ?
                          HAVING article_count > 0
                          ORDER BY t.name");
    $stmt->execute([$companyId]);
    
    return $stmt->fetchAll();
}

/**
 * دریافت مقالات یک برچسب
 * @param int $tagId شناسه برچسب
 * @param int $limit محدودیت تعداد (اختیاری)
 * @param int $offset شروع از (اختیاری)
 * @return array آرایه‌ای از مقالات
 */
function kb_getTagArticles($tagId, $limit = 10, $offset = 0) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating,
                          (SELECT COUNT(*) FROM kb_comments WHERE article_id = a.id AND status = 'approved') as comment_count
                          FROM kb_articles a
                          JOIN kb_article_tags at ON a.id = at.article_id
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE at.tag_id = ? AND a.company_id = ? AND a.status = 'published'
                          ORDER BY a.published_at DESC
                          LIMIT ? OFFSET ?");
    $stmt->execute([$tagId, $companyId, $limit, $offset]);
    
    return $stmt->fetchAll();
}

/**
 * تبدیل عنوان مقاله به اسلاگ
 * @param string $title عنوان مقاله
 * @return string اسلاگ تولید شده
 */
function kb_generateSlug($title) {
    // حروف را به انگلیسی کوچک تبدیل می‌کنیم
    $slug = strtolower($title);
    
    // نویسه‌های غیر الفبایی، اعداد و خط فاصله را حذف می‌کنیم
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    
    // فاصله‌ها را به خط فاصله تبدیل می‌کنیم
    $slug = preg_replace('/[\s]+/', '-', $slug);
    
    // خط فاصله‌های اضافی را حذف می‌کنیم
    $slug = preg_replace('/-+/', '-', $slug);
    
    // حذف خط فاصله از ابتدا و انتهای متن
    $slug = trim($slug, '-');
    
    return $slug;
}

/**
 * اطمینان از یکتا بودن اسلاگ
 * @param string $slug اسلاگ اولیه
 * @param int|null $articleId شناسه مقاله (برای ویرایش)
 * @return string اسلاگ یکتا
 */
function kb_ensureUniqueSlug($slug, $articleId = null) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $originalSlug = $slug;
    $counter = 1;
    
    do {
        if ($articleId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_articles 
                                  WHERE slug = ? AND company_id = ? AND id != ?");
            $stmt->execute([$slug, $companyId, $articleId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_articles 
                                  WHERE slug = ? AND company_id = ?");
            $stmt->execute([$slug, $companyId]);
        }
        
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            $slug = $originalSlug . '-' . $counter++;
        }
    } while ($exists);
    
    return $slug;
}

/**
 * بررسی آیا کاربر به مقاله امتیاز داده است
 * @param int $articleId شناسه مقاله
 * @return array|null اطلاعات امتیاز
 */
function kb_getUserRating($articleId) {
    global $pdo;
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    if (!$userId) {
        return null;
    }
    
    $personnelId = null;
    
    // اگر کاربر عادی نیست، بررسی کنیم شناسه پرسنل را بگیریم
    if (!isAdmin()) {
        $stmt = $pdo->prepare("SELECT id FROM personnel WHERE user_id = ?");
        $stmt->execute([$userId]);
        $personnelId = $stmt->fetchColumn();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM kb_ratings 
                          WHERE article_id = ? AND 
                          ((user_id IS NOT NULL AND user_id = ?) OR 
                           (personnel_id IS NOT NULL AND personnel_id = ?))");
    $stmt->execute([$articleId, $userId, $personnelId]);
    
    return $stmt->fetch();
}

/**
 * دریافت آمار پایگاه دانش
 * @return array آمار پایگاه دانش
 */
function kb_getStatistics() {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    $stats = [
        'total_categories' => 0,
        'total_articles' => 0,
        'total_views' => 0,
        'total_comments' => 0,
        'popular_categories' => [],
        'recent_articles' => [],
        'top_rated_articles' => []
    ];
    
    // تعداد دسته‌بندی‌ها
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_categories WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $stats['total_categories'] = $stmt->fetchColumn();
    
    // تعداد مقالات
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_articles WHERE company_id = ? AND status = 'published'");
    $stmt->execute([$companyId]);
    $stats['total_articles'] = $stmt->fetchColumn();
    
    // تعداد بازدیدها
    $stmt = $pdo->prepare("SELECT SUM(views_count) FROM kb_articles WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $stats['total_views'] = $stmt->fetchColumn() ?: 0;
    
    // تعداد نظرات
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kb_comments c
                          JOIN kb_articles a ON c.article_id = a.id
                          WHERE a.company_id = ? AND c.status = 'approved'");
    $stmt->execute([$companyId]);
    $stats['total_comments'] = $stmt->fetchColumn();
    
    // دسته‌بندی‌های محبوب
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.icon, COUNT(ac.article_id) as article_count
                          FROM kb_categories c
                          LEFT JOIN kb_article_categories ac ON c.id = ac.category_id
                          LEFT JOIN kb_articles a ON ac.article_id = a.id AND a.status = 'published'
                          WHERE c.company_id = ?
                          GROUP BY c.id
                          ORDER BY article_count DESC
                          LIMIT 5");
    $stmt->execute([$companyId]);
    $stats['popular_categories'] = $stmt->fetchAll();
    
    // مقالات اخیر
    $stats['recent_articles'] = kb_getRecentArticles(5);
    
    // مقالات با بیشترین امتیاز
    $stats['top_rated_articles'] = kb_getTopRatedArticles(5);
    
    return $stats;
}

/**
 * دریافت مقالات اخیر
 * @param int $limit محدودیت تعداد (اختیاری)
 * @return array آرایه‌ای از مقالات اخیر
 */
function kb_getRecentArticles($limit = 5) {
    global $pdo;
    
    $companyId = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
    
    // Convert limit to integer and include directly in query
    $limit = (int)$limit;
    
    $stmt = $pdo->prepare("SELECT a.*, 
                          CONCAT(p.first_name, ' ', p.last_name) as creator_name,
                          COALESCE(
                              (SELECT AVG(rating) FROM kb_ratings WHERE article_id = a.id), 
                              0
                          ) as average_rating
                          FROM kb_articles a
                          LEFT JOIN users u ON a.created_by = u.id
                          LEFT JOIN personnel p ON u.id = p.user_id
                          WHERE a.company_id = ? AND a.status = 'published'
                          ORDER BY a.published_at DESC
                          LIMIT $limit");
    $stmt->execute([$companyId]);
    
    return $stmt->fetchAll();
}

/**
 * ایجاد درختواره (بردکرامب) برای یک دسته‌بندی
 * @param int $categoryId شناسه دسته‌بندی
 * @return array آرایه‌ای از دسته‌بندی‌های والد
 */
function kb_getCategoryBreadcrumb($categoryId) {
    global $pdo;
    
    $breadcrumb = [];
    $current = $categoryId;
    
    while ($current) {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM kb_categories WHERE id = ?");
        $stmt->execute([$current]);
        $category = $stmt->fetch();
        
        if (!$category) {
            break;
        }
        
        array_unshift($breadcrumb, $category);
        $current = $category['parent_id'];
    }
    
    return $breadcrumb;
}
?>