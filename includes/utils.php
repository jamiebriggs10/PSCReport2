<?php
/**
 * General Utility Functions
 * Presswick Sailing Club Issue Reporting System
 */

/**
 * Urgency levels with their priority order (lower number = higher priority)
 */
function getUrgencyLevels() {
    return [
        'Safety-Critical' => 1,
        'High (Blocks Use)' => 2,
        'Medium (Workaround)' => 3,
        'Low (Minor)' => 4,
        'Cosmetic' => 5,
        'Monitoring' => 6
    ];
}

/**
 * Get urgency colors for CSS classes
 */
function getUrgencyColors() {
    return [
        'Safety-Critical' => '#dc3545', // Red
        'High (Blocks Use)' => '#fd7e14', // Orange
        'Medium (Workaround)' => '#ffc107', // Yellow
        'Low (Minor)' => '#28a745', // Green
        'Cosmetic' => '#6c757d', // Gray
        'Monitoring' => '#17a2b8' // Teal
    ];
}

/**
 * Parse urgency tags from database SET field
 */
function parseUrgencyTags($urgencyString) {
    if (empty($urgencyString)) {
        return [];
    }
    return explode(',', $urgencyString);
}

/**
 * Get highest priority urgency tag
 */
function getHighestUrgencyTag($urgencyTags) {
    $levels = getUrgencyLevels();
    $highestPriority = 999;
    $highestTag = '';
    
    foreach ($urgencyTags as $tag) {
        if (isset($levels[$tag]) && $levels[$tag] < $highestPriority) {
            $highestPriority = $levels[$tag];
            $highestTag = $tag;
        }
    }
    
    return $highestTag;
}

/**
 * Get urgency badge HTML
 */
function getUrgencyBadgeHtml($urgencyTags, $showAll = false) {
    $colors = getUrgencyColors();
    $html = '';
    
    if ($showAll) {
        foreach ($urgencyTags as $tag) {
            $color = $colors[$tag] ?? '#6c757d';
            $html .= "<span class='urgency-badge' style='background-color: {$color}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; margin-right: 8px;'>" . h($tag) . "</span>";
        }
        // Remove the trailing margin from the last badge
        $html = rtrim($html);
    } else {
        $highestTag = getHighestUrgencyTag($urgencyTags);
        if ($highestTag) {
            $color = $colors[$highestTag] ?? '#6c757d';
            $html = "<span class='urgency-badge' style='background-color: {$color}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center;'>" . h($highestTag) . "</span>";
        }
    }
    
    return $html;
}

/**
 * Get status badge HTML
 */
function getStatusBadgeHtml($status) {
    $statusConfig = [
        'OPEN' => [
            'color' => '#dc3545',
            'bgColor' => '#f8d7da',
            'label' => 'Open',
            'icon' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
        ],
        'RESOLVED' => [
            'color' => '#155724',
            'bgColor' => '#d4edda', 
            'label' => 'Resolved',
            'icon' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle></svg>'
        ]
    ];
    
    $config = $statusConfig[$status] ?? [
        'color' => '#6c757d',
        'bgColor' => '#f8f9fa',
        'label' => h($status),
        'icon' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
    ];
    
    return "<span class='status-badge-new' style='color: {$config['color']}; background-color: {$config['bgColor']}; border: 1px solid {$config['color']}; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;'>{$config['icon']} {$config['label']}</span>";
}

/**
 * Format date for display
 */
function formatDate($dateString, $format = 'M j, Y g:i A') {
    if (empty($dateString)) {
        return '';
    }
    
    $date = new DateTime($dateString);
    return $date->format($format);
}

/**
 * Get relative time (e.g., "2 hours ago")
 */
function getRelativeTime($dateString) {
    if (empty($dateString)) {
        return '';
    }
    try {
        $date = new DateTime($dateString);
    } catch (Exception $e) {
        return '';
    }
    $now = new DateTime();
    // Future dates: just show absolute date
    if ($date > $now) {
        return formatDate($dateString, 'M j, Y');
    }

    $diff = $now->diff($date);
    $totalSeconds = $now->getTimestamp() - $date->getTimestamp();
    $totalMinutes = (int) floor($totalSeconds / 60);
    $totalHours   = (int) floor($totalMinutes / 60);
    $totalDays    = (int) $diff->days; // total day span, not just day component

    if ($totalMinutes < 1) {
        return 'Just now';
    }
    if ($totalMinutes < 60) {
        return $totalMinutes . ' minute' . ($totalMinutes === 1 ? '' : 's') . ' ago';
    }
    if ($totalHours < 24) {
        return $totalHours . ' hour' . ($totalHours === 1 ? '' : 's') . ' ago';
    }
    if ($totalDays < 7) {
        return $totalDays . ' day' . ($totalDays === 1 ? '' : 's') . ' ago';
    }
    if ($totalDays < 30) {
        $weeks = (int) floor($totalDays / 7);
        if ($weeks < 1) { $weeks = 1; }
        return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
    }

    // Same calendar year – show Month + Day
    if ($now->format('Y') === $date->format('Y')) {
        return $date->format('M j');
    }
    // Older than current year – include year for clarity
    return $date->format('M j, Y');
}

/**
 * Generate username from full name
 */
function generateUsername($fullName) {
    // Remove spaces and special characters, convert to lowercase
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullName));
    return $username;
}

/**
 * Validate problem form data
 */
function validateProblemData($data, &$errors) {
    $isValid = true;
    
    // Title validation
    if (empty(trim($data['title']))) {
        $errors['title'] = 'Title is required';
        $isValid = false;
    } elseif (strlen(trim($data['title'])) > 255) {
        $errors['title'] = 'Title must be less than 255 characters';
        $isValid = false;
    }
    
    // Details validation (optional but limit length if provided)
    if (!empty($data['details']) && strlen($data['details']) > 5000) {
        $errors['details'] = 'Details must be less than 5000 characters';
        $isValid = false;
    }
    
    // Urgency validation
    if (empty($data['urgency_level'])) {
        $errors['urgency_level'] = 'Urgency level is required';
        $isValid = false;
    } else {
        // Validate against active urgency levels from database
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT name FROM urgency_levels WHERE is_active = 1");
            $stmt->execute();
            $validUrgencyLevels = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array($data['urgency_level'], $validUrgencyLevels)) {
                $errors['urgency_level'] = 'Invalid urgency level selected';
                $isValid = false;
            }
        } catch (Exception $e) {
            $errors['urgency_level'] = 'Error validating urgency level';
            $isValid = false;
        }
    }
    
    // Problem category validation
    if (empty($data['problem_category_id'])) {
        $errors['problem_category_id'] = 'Problem type is required';
        $isValid = false;
    } else {
        // Validate against active problem categories from database (admin_only allowed for submission by normal users)
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id FROM problem_categories WHERE is_active = 1");
            $stmt->execute();
            $validCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array($data['problem_category_id'], $validCategories)) {
                $errors['problem_category_id'] = 'Invalid problem type selected';
                $isValid = false;
            }
        } catch (Exception $e) {
            $errors['problem_category_id'] = 'Error validating problem type';
            $isValid = false;
        }
    }
    
    return $isValid;
}

/**
 * Validate user form data
 */
function validateUserData($data, &$errors, $isEdit = false) {
    $isValid = true;
    
    // Full name validation
    if (empty(trim($data['full_name']))) {
        $errors['full_name'] = 'Full name is required';
        $isValid = false;
    } elseif (strlen(trim($data['full_name'])) > 100) {
        $errors['full_name'] = 'Full name must be less than 100 characters';
        $isValid = false;
    }
    
    // Role validation
    if (empty($data['role']) || !in_array($data['role'], ['ADMIN', 'USER'])) {
        $errors['role'] = 'Valid role is required';
        $isValid = false;
    }
    
    return $isValid;
}

/**
 * Validate password
 */
function validatePassword($password, &$errors, $fieldName = 'password') {
    $isValid = true;
    
    if (empty($password)) {
        $errors[$fieldName] = 'Password is required';
        $isValid = false;
    }
    
    return $isValid;
}

/**
 * Get problem filters for SQL WHERE clause
 */
function buildProblemFilters($filters) {
    $where = [];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "p.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['urgency'])) {
        $where[] = "FIND_IN_SET(?, p.urgency_tags) > 0";
        $params[] = $filters['urgency'];
    }
    
    if (!empty($filters['reporter']) && is_numeric($filters['reporter'])) {
        $where[] = "p.reported_by = ?";
        $params[] = $filters['reporter'];
    }
    
    if (!empty($filters['problem_type']) && is_numeric($filters['problem_type'])) {
        $where[] = "p.problem_category_id = ?";
        $params[] = $filters['problem_type'];
    }

    // Free-text search (q): match across title, details, reporter name, category, urgency tags.
    // Multiple whitespace-separated terms combine with AND logic.
    if (!empty($filters['q'])) {
        $q = trim($filters['q']);
        if ($q !== '') {
            $terms = preg_split('/\s+/', $q);
            foreach ($terms as $term) {
                if ($term === '') continue;
                $where[] = "(p.title LIKE ? OR p.details LIKE ? OR u.full_name LIKE ? OR pc.name LIKE ? OR p.urgency_tags LIKE ?)";
                $like = '%' . $term . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }
    }
    
    return [
        'where' => $where,
        'params' => $params
    ];
}

/**
 * Highlight search query terms inside a given text safely.
 */
function highlightSearch($text, $query) {
    if (empty($query)) {
        return h($text);
    }
    $escaped = h($text);
    $terms = array_filter(preg_split('/\s+/', trim($query)));
    if (empty($terms)) return $escaped;
    // Build regex pattern (escape terms, ignore very short 1-char terms to reduce noise)
    $patterns = [];
    foreach ($terms as $t) {
        if (mb_strlen($t) < 2) continue; // ignore 1-letter fragments
        $patterns[] = preg_quote($t, '/');
    }
    if (empty($patterns)) return $escaped;
    $regex = '/(' . implode('|', $patterns) . ')/i';
    return preg_replace_callback($regex, function($m){ return '<mark>' . $m[0] . '</mark>'; }, $escaped);
}

/**
 * Get sort order for problems
 */
function buildProblemSort($sortBy) {
    switch ($sortBy) {
        case 'urgency':
            // Dynamic ordering based on active urgency levels
            $orderList = getActiveUrgencyOrdering();
            if (empty($orderList)) {
                $orderList = [
                    'Safety-Critical', 'High (Blocks Use)', 'Medium (Workaround)', 'Low (Minor)', 'Cosmetic', 'Monitoring'
                ]; // fallback
            }
            $escaped = array_map(function($v){ return str_replace("'", "''", $v); }, $orderList);
            $fieldList = "'" . implode("','", $escaped) . "'";
            return "FIELD(SUBSTRING_INDEX(p.urgency_tags, ',', 1), $fieldList), p.created_at DESC";
        case 'oldest':
            return "p.created_at ASC";
        case 'newest':
        default:
            return "p.created_at DESC";
    }
}

/**
 * Get active urgency levels in display order
 */
function getActiveUrgencyOrdering() {
    try {
        $pdo = getDbConnection();
        $rows = $pdo->query("SELECT name FROM urgency_levels WHERE is_active = 1 ORDER BY display_order, name")->fetchAll(PDO::FETCH_COLUMN);
        return $rows ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Generate breadcrumb navigation
 */
function getBreadcrumbs($currentPage = '', $customBreadcrumbs = []) {
    $breadcrumbs = [
        ['text' => 'Home', 'url' => getFullUrl()]
    ];
    
    if (!empty($customBreadcrumbs)) {
        $breadcrumbs = array_merge($breadcrumbs, $customBreadcrumbs);
    }
    
    if (!empty($currentPage)) {
        $breadcrumbs[] = ['text' => $currentPage, 'url' => ''];
    }
    
    return $breadcrumbs;
}

/**
 * Generate thumbnail URL for an image
 */
function getThumbnailUrl($imageUrl) {
    // For now, just return the original image
    // In future, could implement actual thumbnail generation
    return $imageUrl;
}
?>