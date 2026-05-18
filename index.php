<?php
/**
 * Presswick Sailing Club Issue Reporting System
 * Main Dashboard / Problems List Page
 */

require_once 'includes/auth.php';
require_once 'includes/utils.php';
require_once 'includes/upload.php';
require_once 'config/database.php';

// Require authentication
requireAuth();

// Check if password change is required
checkPasswordChangeRequired();

// Get current user
$user = getCurrentUser();

// Get filters from query parameters
$filters = [
    'status' => $_GET['status'] ?? 'OPEN',
    'urgency' => $_GET['urgency'] ?? '',
    'reporter' => $_GET['reporter'] ?? '',
    'problem_type' => $_GET['problem_type'] ?? '',
    'sort' => $_GET['sort'] ?? 'newest',
    'q' => $_GET['q'] ?? ''
];

try {
    $pdo = getDbConnection();

    // Stable open count (independent of other filters except visibility)
    $openCountSql = "SELECT COUNT(*) FROM problems p LEFT JOIN problem_categories pc ON p.problem_category_id = pc.id WHERE p.status = 'OPEN'";
    $openCountParams = [];
    if (!isAdmin()) {
        $openCountSql .= " AND (pc.admin_only = 0 OR pc.admin_only IS NULL OR p.reported_by = ?)";
        $openCountParams[] = $user['id'];
    }
    $stmtOpen = $pdo->prepare($openCountSql);
    $stmtOpen->execute($openCountParams);
    $totalOpenCount = (int)$stmtOpen->fetchColumn();

    // Build WHERE clause for filters. Free-text search ($filters['q']) is intentionally
    // excluded here — the dashboard returns the full status/urgency/etc-scoped list and
    // the in-page search box narrows it client-side as the user types.
    $serverFilters = $filters;
    unset($serverFilters['q']);
    $filterData = buildProblemFilters($serverFilters);
    // Visibility rules for non-admin users:
    // Show all non-admin_only problems plus the user's own (even if admin_only)
    if (!isAdmin()) {
        $filterData['where'][] = "(pc.admin_only = 0 OR pc.admin_only IS NULL OR p.reported_by = ?)";
        $filterData['params'][] = $user['id'];
    }
    $whereClause = !empty($filterData['where']) ? 'WHERE ' . implode(' AND ', $filterData['where']) : '';
    
    // Get sort order (special case: resolved view sorts by resolution date newest first)
    $orderBy = buildProblemSort($filters['sort']);
    if ($filters['status'] === 'RESOLVED' && $filters['sort'] === 'newest') {
        $orderBy = 'latest_resolution.action_at DESC, p.updated_at DESC';
    }
    
    // Get problems with user information and categories
    $sql = "
        SELECT 
            p.*,
            u.full_name as reporter_name,
            latest_resolution.action_by_name as resolver_name,
            latest_resolution.action_at as resolved_at,
            pc.name as category_name,
            pc.color as category_color
        FROM problems p
        LEFT JOIN users u ON p.reported_by = u.id
        LEFT JOIN (
            SELECT 
                r1.problem_id,
                u.full_name as action_by_name,
                r1.action_at
            FROM resolutions r1
            LEFT JOIN users u ON r1.action_by = u.id
            WHERE r1.action = 'RESOLVE' 
            AND r1.action_at = (
                SELECT MAX(r2.action_at) 
                FROM resolutions r2 
                WHERE r2.problem_id = r1.problem_id 
                AND r2.action = 'RESOLVE'
            )
        ) latest_resolution ON p.id = latest_resolution.problem_id
        LEFT JOIN problem_categories pc ON p.problem_category_id = pc.id
        {$whereClause}
        ORDER BY {$orderBy}
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filterData['params']);
    $problems = $stmt->fetchAll();
    if (!isset($totalOpenCount)) {
        $totalOpenCount = (int)array_sum(array_map(fn($p)=>$p['status']==='OPEN'?1:0,$problems));
    }
    
    // Get available problem categories (all active, normal users can also create admin_only types)
    $availableCategories = $pdo->query("SELECT * FROM problem_categories WHERE is_active = 1 ORDER BY display_order, name")->fetchAll();
    
    // Get all users for filter dropdown (now visible to all users per new requirements)
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $problems = [];
    $users = [];
}

// Fetch active urgency levels for filters (dynamic so disabled ones disappear)
try {
    if (!isset($pdo)) {
        $pdo = getDbConnection();
    }
    $availableUrgencyLevels = $pdo->query("SELECT name, color FROM urgency_levels WHERE is_active = 1 ORDER BY display_order, name")->fetchAll();
} catch (Exception $e) {
    $availableUrgencyLevels = [];
}

$pageTitle = 'Dashboard';
include 'includes/header.php';
?>

<main class="main">
    <div class="container">
        <!-- Filters -->
        <div class="filters new-filter-bar">
            <form method="GET" class="filter-form" id="problem-filters">
                <input type="hidden" name="status" value="<?= h($filters['status']) ?>" />
                <div class="filter-top-row">
                    <div class="status-segmented" role="group" aria-label="Status filter">
                        <button type="submit" name="status" value="OPEN" class="seg-btn seg-open <?= $filters['status']==='OPEN' ? 'active' : '' ?>">Open (<?= $totalOpenCount ?>)</button>
                        <button type="submit" name="status" value="RESOLVED" class="seg-btn seg-resolved <?= $filters['status']==='RESOLVED' ? 'active' : '' ?>">Resolved</button>
                    </div>
                    <div class="search-wrapper" id="liveSearchWrapper">
                        <span class="search-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="q"
                            id="liveSearchInput"
                            value="<?= h($filters['q']) ?>"
                            placeholder="Filter problems&hellip;"
                            class="search-input"
                            aria-label="Filter problems"
                            autocomplete="off"
                        />
                        <span class="search-count" id="liveSearchCount" aria-live="polite"></span>
                        <button type="button" class="clear-search" id="liveSearchClear" title="Clear search" aria-label="Clear search" style="<?= empty($filters['q']) ? 'display:none;' : '' ?>">&times;</button>
                    </div>
                </div>
                <?php
                    $activeFilterCount = 0;
                    if (!empty($filters['problem_type'])) $activeFilterCount++;
                    if (!empty($filters['urgency']))      $activeFilterCount++;
                    if (!empty($filters['reporter']))     $activeFilterCount++;
                    if (!empty($filters['sort']) && $filters['sort'] !== 'newest') $activeFilterCount++;
                ?>
                <button type="button" class="filter-toggle" id="filterToggle" aria-expanded="<?= $activeFilterCount > 0 ? 'true' : 'false' ?>" aria-controls="filterRow">
                    <span class="filter-toggle-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="4" y1="6" x2="20" y2="6"/>
                            <line x1="7" y1="12" x2="17" y2="12"/>
                            <line x1="10" y1="18" x2="14" y2="18"/>
                        </svg>
                        Filters<?php if ($activeFilterCount > 0): ?> <span class="filter-toggle-count"><?= $activeFilterCount ?></span><?php endif; ?>
                    </span>
                    <svg class="filter-toggle-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="filter-row<?= $activeFilterCount > 0 ? ' is-open' : '' ?>" id="filterRow">
                    <div class="filter-group compact">
                        <label for="problem_type" class="form-label">Problem Type</label>
                        <select name="problem_type" id="problem_type" class="form-control">
                            <option value="">All Problem Types</option>
                            <?php foreach ($availableCategories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= $filters['problem_type'] == $category['id'] ? 'selected' : '' ?>
                                        style="color: <?= h($category['color']) ?>">
                                    <?= h($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group compact">
                        <label for="urgency" class="form-label">Urgency Level</label>
                        <select name="urgency" id="urgency" class="form-control urgency-select">
                            <option value="">All Urgency Levels</option>
                            <?php foreach ($availableUrgencyLevels as $level): 
                                $color = $level['color'] ?: '#6c757d';
                                $name = $level['name'];
                            ?>
                                <option value="<?= h($name) ?>" 
                                        <?= $filters['urgency'] === $name ? 'selected' : '' ?>
                                        data-color="<?= h($color) ?>">
                                    <?= h($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($users)): ?>
                        <div class="filter-group compact">
                        <label for="reporter" class="form-label">Reporter</label>
                        <select name="reporter" id="reporter" class="form-control">
                            <option value="">All Reporters</option>
                            <?php foreach ($users as $reporterUser): ?>
                                <option value="<?= $reporterUser['id'] ?>" <?= $filters['reporter'] == $reporterUser['id'] ? 'selected' : '' ?>>
                                    <?= h($reporterUser['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group compact">
                        <label for="sort" class="form-label">Sort By</label>
                        <select name="sort" id="sort" class="form-control">
                            <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="urgency" <?= $filters['sort'] === 'urgency' ? 'selected' : '' ?>>By Urgency</option>
                        </select>
                    </div>
                    <div class="filter-group compact" style="align-self:flex-end;">
                        <button type="submit" class="btn btn-outline btn-sm" style="width:100%;">Apply</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Problems List -->
        <?php if (empty($problems)): ?>
            <div class="card">
                <div class="card-body text-center">
                    <h3>No Problems Found</h3>
                    <p class="text-muted">No issues match your current filters.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="search-empty" id="searchEmptyState" hidden>
                <span>No problems match <span id="searchEmptyQuery"></span></span>
                <button type="button" class="btn-link-clear" id="searchEmptyClear">Clear</button>
            </div>
            <div class="problem-list">
                <?php
                $now = new DateTime();
                $currentGroup = null;
                $useDateField = ($filters['status'] === 'RESOLVED') ? 'resolved_at' : 'created_at';

                function getProblemGroupLabel($dateString, $now) {
                    if (empty($dateString)) return 'Unknown';
                    $dt = new DateTime($dateString);
                    $today = (clone $now)->setTime(0,0,0);
                    $targetDay = (clone $dt)->setTime(0,0,0);
                    $diffDays = (int)$today->diff($targetDay)->format('%r%a');

                    // Week calculations (ISO weeks starting Monday)
                    $weekStart = (clone $today)->modify('monday this week');
                    $lastWeekStart = (clone $weekStart)->modify('-7 days');
                    $lastWeekEnd = (clone $weekStart)->modify('-1 day');

                    if ($diffDays === 0) return 'Today';
                    if ($diffDays === -1) return 'Yesterday';
                    if ($targetDay >= $weekStart) return 'This Week';
                    if ($targetDay >= $lastWeekStart && $targetDay <= $lastWeekEnd) return 'Last Week';

                    // Month / Year buckets
                    $currentMonthStart = (clone $today)->modify('first day of this month');
                    $lastMonthStart = (clone $currentMonthStart)->modify('-1 month');
                    $lastMonthEnd = (clone $currentMonthStart)->modify('-1 day');
                    $currentYearStart = (clone $today)->modify('first day of January this year');
                    $lastYearStart = (clone $currentYearStart)->modify('-1 year');
                    $lastYearEnd = (clone $currentYearStart)->modify('-1 day');

                    if ($targetDay >= $currentMonthStart) return 'Earlier This Month';
                    if ($targetDay >= $lastMonthStart && $targetDay <= $lastMonthEnd) return 'Last Month';
                    if ($targetDay >= $currentYearStart) return 'Earlier This Year';
                    if ($targetDay >= $lastYearStart && $targetDay <= $lastYearEnd) return 'Last Year';
                    return 'Older';
                }
                ?>
                <?php foreach ($problems as $problem): ?>
                    <?php
                        $dateForGroup = $problem[$useDateField] ?: $problem['created_at'];
                        $groupLabel = getProblemGroupLabel($dateForGroup, $now);
                        if ($groupLabel !== $currentGroup) {
                            $currentGroup = $groupLabel;
                            echo "<div class='time-divider'><span>" . h($currentGroup) . "</span></div>";
                        }
                    ?>
                    <?php
                    $urgencyTags = parseUrgencyTags($problem['urgency_tags']); // still returns array; single value stored now
                    $attachments = getProblemAttachments($problem['id'], $problem['image_urls']);
                    $thumbnail = '';
                    if (!empty($attachments)) {
                        foreach ($attachments as $att) {
                            if (!empty($att['is_image']) && $att['is_image']) { $thumbnail = $att['url']; break; }
                        }
                    }
                    ?>
                    
                    <?php
                        $q = $filters['q'] ?? '';
                        $titleHtml = highlightSearch($problem['title'], $q);
                        $reporterHtml = highlightSearch($problem['reporter_name'], $q);
                        $categoryHtml = !empty($problem['category_name']) ? highlightSearch($problem['category_name'], $q) : '';

                        $urgencyText = is_array($urgencyTags) ? implode(' ', $urgencyTags) : (string)$urgencyTags;
                        $haystack = strtolower(trim(
                            ($problem['title']         ?? '') . ' ' .
                            ($problem['details']       ?? '') . ' ' .
                            ($problem['reporter_name'] ?? '') . ' ' .
                            ($problem['resolver_name'] ?? '') . ' ' .
                            ($problem['category_name'] ?? '') . ' ' .
                            $urgencyText . ' ' .
                            ($problem['status']        ?? '')
                        ));
                    ?>
                    <a href="problems/view.php?id=<?= $problem['id'] ?>" class="problem-item" data-search="<?= h($haystack) ?>" style="position: relative;">
                        <?php if (!empty($categoryHtml)): ?>
                            <div class="problem-type-vertical" style="
                                position: absolute;
                                right: 0;
                                top: 0;
                                bottom: 0;
                                width: 40px;
                                background-color: <?= h($problem['category_color']) ?>;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                border-radius: 0 8px 8px 0;
                                z-index: 1;
                                overflow: hidden;
                            ">
                                <span style="
                                    color: white;
                                    font-size: 0.65rem;
                                    font-weight: 700;
                                    text-transform: uppercase;
                                    letter-spacing: 0.5px;
                                    writing-mode: vertical-rl;
                                    text-orientation: mixed;
                                    white-space: nowrap;
                                    padding: 8px 0;
                                    line-height: 1.1;
                                    max-height: 100%;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                "><?= strip_tags($categoryHtml) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-start" style="<?= !empty($categoryHtml) ? 'padding-right: 48px;' : '' ?>">
                            <div class="problem-content">
                                <div class="problem-title">
                                    <?= $titleHtml ?>
                                </div>
                                
                                <div class="problem-meta">
                                    <span class="meta-chunk meta-reporter">
                                        <span class="meta-label">Reported by</span>
                                        <strong class="meta-value reporter-name"><?= $reporterHtml ?></strong>
                                    </span>
                                    <span class="meta-separator">•</span>
                                    <span class="meta-chunk meta-created">
                                        <span class="meta-label">Reported</span>
                                        <em class="meta-time"><?= getRelativeTime($problem['created_at']) ?></em>
                                    </span>
                                    <?php if ($problem['status'] === 'RESOLVED' && $problem['resolver_name']): ?>
                                        <span class="meta-separator">•</span>
                                        <span class="meta-chunk meta-resolver">
                                            <span class="meta-label">Resolved by</span>
                                            <strong class="meta-value resolver-name"><?= h($problem['resolver_name']) ?></strong>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="problem-badges" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                                    <?= getStatusBadgeHtml($problem['status']) ?>
                                    <?= getUrgencyBadgeHtml($urgencyTags) ?>
                                </div>
                            </div>
                            
                            <?php if ($thumbnail): ?>
                                <div class="problem-thumbnail-container">
                                    <img src="<?= h($thumbnail) ?>" alt="Problem thumbnail" class="problem-thumbnail">
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Floating Action Button -->
<a href="problems/create.php" class="fab" title="Report New Problem">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</a>

<style>
/* Compact filter bar — aligned to refined design system */
.new-filter-bar {background:var(--surface-color);border:1px solid var(--border-color);padding:.9rem 1rem;border-radius:var(--radius-lg);margin-bottom:1.25rem;box-shadow:var(--shadow-xs);}
.filter-top-row {display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:.85rem;}
.status-segmented {display:inline-flex;border:1px solid var(--border-color);border-radius:10px;overflow:hidden;background:var(--light-color);padding:3px;gap:3px;}
.seg-btn {background:transparent;border:0;padding:7px 14px;font-size:.82rem;cursor:pointer;color:var(--text-muted);line-height:1.3;display:flex;align-items:center;gap:.35rem;font-weight:500;border-radius:7px;transition:background 160ms ease,color 160ms ease,box-shadow 160ms ease;font-family:inherit;}
.seg-btn.active {background:var(--primary-color);color:#fff;box-shadow:var(--shadow-xs);}
.seg-btn.seg-open.active {background:var(--danger-color);color:#fff;}
.seg-btn.seg-resolved.active {background:var(--success-color);color:#fff;}
.seg-btn:not(.active):hover {background:var(--surface-color);color:var(--text-color);}
.search-wrapper {position:relative;flex:1;min-width:200px;}
.search-input {width:100%;padding:8px 64px 8px 12px;border:1px solid var(--border-strong);border-radius:10px;font-size:.85rem;background:var(--surface-color);color:var(--text-color);font-family:inherit;transition:border-color var(--transition),box-shadow var(--transition);}
.search-input:focus {outline:none;border-color:var(--accent);box-shadow:var(--ring);}
.search-input::placeholder {color:var(--text-subtle);}
.search-submit {position:absolute;right:32px;top:50%;transform:translateY(-50%);background:var(--primary-color);border:0;color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background var(--transition);}
.search-submit:hover {background:var(--primary-dark);}
.clear-search {position:absolute;right:4px;top:50%;transform:translateY(-50%);background:var(--light-color);border:0;width:24px;height:24px;border-radius:50%;cursor:pointer;font-weight:bold;line-height:1;color:var(--text-muted);transition:background var(--transition),color var(--transition);}
.clear-search:hover {background:var(--border-color);color:var(--text-color);}
.inline-actions {display:none;}
.filter-row {display:flex;flex-wrap:wrap;gap:.75rem;}
.filter-group {min-width:160px;}

/* Collapsible filter toggle — visible on mobile only */
.filter-toggle {
    display: none;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding: 0.55rem 0.85rem;
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-color);
    font-family: inherit;
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 140ms ease, border-color 140ms ease;
    -webkit-tap-highlight-color: transparent;
}
.filter-toggle:hover { background: var(--light-color); border-color: var(--border-strong); }
.filter-toggle-label { display: inline-flex; align-items: center; gap: 8px; color: var(--text-color); }
.filter-toggle-label svg { color: var(--text-muted); }
.filter-toggle-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 6px;
    background: var(--primary-color);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 999px;
    margin-left: 4px;
}
.filter-toggle-chevron {
    color: var(--text-muted);
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
}
.filter-toggle[aria-expanded="true"] .filter-toggle-chevron { transform: rotate(180deg); }

.filter-group.compact label {font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;}
.filter-group.compact select, .filter-group.compact .btn {font-size:.78rem;padding:.45rem .55rem;height:34px;}
.filter-group.compact .form-control {font-size:.78rem;padding:.4rem .55rem;height:34px;border-radius:8px;}
.problem-list .problem-item mark {background:#fef3c7;color:#78350f;padding:0 3px;border-radius:3px;font-weight:600;}

/* Time dividers */
.time-divider {position:relative;display:flex;align-items:center;margin:1.5rem 0 .75rem;}
.time-divider span {background:var(--surface-color);padding:3px 12px;font-size:.62rem;font-weight:600;letter-spacing:.08em;color:var(--text-muted);text-transform:uppercase;border:1px solid var(--border-color);border-radius:999px;box-shadow:var(--shadow-xs);}
.time-divider:before {content:'';flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--border-color));margin-right:.75rem;}
.time-divider:first-child {margin-top:0.2rem;}
@media (max-width:600px){
    .time-divider {margin:1rem 0 .4rem;}
    .time-divider span {font-size:.6rem;padding:2px 8px;}
}

.status-toggle-buttons input[type="radio"]:checked + .status-btn-open {
    background: #dc3545;
    color: white;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.status-toggle-buttons input[type="radio"]:checked + .status-btn-resolved {
    background: #28a745;
    color: white;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.status-icon {
    font-size: 16px;
}

/* Problem type badges in problem list */
.problem-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-left: 8px;
}

/* Problem thumbnail improvements */
.problem-content {
    flex: 1;
    min-width: 0; /* Allow content to shrink */
    padding-right: 1rem; /* Space between content and thumbnail */
}

.problem-thumbnail-container {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.problem-thumbnail {
    width: 64px !important;
    height: 64px !important;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-xs);
    transition: transform 200ms cubic-bezier(0.16,1,0.3,1), box-shadow 200ms ease;
}

.problem-item:hover .problem-thumbnail {
    transform: scale(1.05);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

@media (max-width: 768px) {
    .problem-content {
        padding-right: 0.75rem; /* Less padding on mobile */
    }
    
    .problem-thumbnail {
        width: 50px !important;
        height: 50px !important;
        border-radius: 4px;
    }
}

/* Responsive meta layout */
.problem-meta {color:var(--text-muted);font-size:0.72rem;display:flex;flex-wrap:wrap;gap:8px;line-height:1.3;margin-top:6px;font-weight:400;}
.problem-meta .meta-chunk {white-space:nowrap;display:inline-flex;align-items:baseline;gap:4px;}
.problem-meta .meta-label {font-size:0.62rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-subtle);font-weight:600;}
.problem-meta .meta-value {font-weight:600;color:var(--text-color);}

/* Badge improvements */
.problem-badges {
    margin-top: 8px !important;
}

.status-badge-new, .urgency-badge {
    box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
    transition: all 0.2s ease !important;
}

.status-badge-new:hover, .urgency-badge:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15) !important;
}

/* Problem card vertical type styling */
.problem-item {
    overflow: hidden !important;
    border-radius: 12px !important;
}

.problem-type-vertical {
    border-radius: 0 11px 11px 0 !important;
    box-shadow: inset 1px 0 0 rgba(255,255,255,0.18), inset -1px 0 0 rgba(0,0,0,0.04);
}

.problem-item:hover .problem-type-vertical {
    box-shadow: inset 1px 0 0 rgba(255,255,255,0.25), inset -1px 0 0 rgba(0,0,0,0.06);
}
.problem-meta .reporter-name, .problem-meta .resolver-name {color:var(--text-color);}
.problem-meta .meta-time {font-style:normal;color:var(--text-muted);}
.problem-meta .meta-separator {color:var(--border-strong);}
@media (max-width:600px) {
        .problem-meta {flex-direction:column;align-items:flex-start;gap:3px;font-size:0.7rem;}
        .problem-meta .meta-separator {display:none;}
        .problem-meta .meta-chunk {white-space:normal;}
        .problem-meta .meta-label {font-size:0.6rem;}
        .problem-meta .meta-created {order:2;}
        .problem-meta .meta-resolver {order:3;}
}

/* Improve tap targets on mobile */
@media (max-width:600px){
    .seg-btn {padding:8px 12px;font-size:0.8rem;}
    .search-input {font-size:0.8rem;}
}

/* Mobile: collapse the dropdown filters behind a toggle */
@media (max-width: 768px) {
    .filter-toggle { display: flex; }
    .filter-row {
        display: none;
        flex-direction: column;
        gap: 0.6rem;
        margin-top: 0.6rem;
        padding-top: 0.6rem;
        border-top: 1px solid var(--border-color);
        animation: filterRowIn 200ms cubic-bezier(0.16, 1, 0.3, 1);
    }
    .filter-row.is-open { display: flex; }
    .filter-row .filter-group.compact,
    .filter-row .filter-group { width: 100%; min-width: 0; }
    .filter-row .filter-group.compact select,
    .filter-row .filter-group.compact .form-control,
    .filter-row .filter-group.compact .btn {
        height: 40px;
        font-size: 0.875rem;
        padding: 0.5rem 0.6rem;
    }
}
@keyframes filterRowIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ====== In-page live filter ====== */
.search-wrapper { position: relative; }
.search-wrapper .search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-subtle);
    display: inline-flex;
    pointer-events: none;
}
.search-wrapper .search-input { padding-left: 32px; padding-right: 80px; }
.search-wrapper .search-input:focus + .search-count,
.search-wrapper.has-query .search-input + .search-count { opacity: 1; }
.search-count {
    position: absolute;
    right: 36px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.7rem;
    color: var(--text-muted);
    font-variant-numeric: tabular-nums;
    opacity: 0;
    transition: opacity 160ms ease;
    pointer-events: none;
    background: var(--light-color);
    padding: 2px 7px;
    border-radius: 999px;
    font-weight: 600;
    white-space: nowrap;
}
.problem-item.is-hidden,
.time-divider.is-hidden { display: none !important; }
.problem-item mark,
.problem-meta mark {
    background: #fef3c7;
    color: #78350f;
    padding: 0 2px;
    border-radius: 3px;
    font-weight: 600;
}
.search-empty {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.9rem;
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
}
.search-empty #searchEmptyQuery:not(:empty) {
    font-family: var(--font-mono);
    color: var(--primary-color);
    background: var(--primary-50);
    padding: 1px 7px;
    border-radius: 6px;
    margin-left: 2px;
    font-size: 0.78rem;
}
.btn-link-clear {
    background: none;
    border: 0;
    color: var(--accent);
    font: inherit;
    font-weight: 500;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 120ms ease;
}
.btn-link-clear:hover { background: var(--light-color); }
</style>

<script>
(function() {
    'use strict';
    const wrapper = document.getElementById('liveSearchWrapper');
    if (!wrapper) return;
    const input      = document.getElementById('liveSearchInput');
    const clearBtn   = document.getElementById('liveSearchClear');
    const countEl    = document.getElementById('liveSearchCount');
    const list       = document.querySelector('.problem-list');
    const empty      = document.getElementById('searchEmptyState');
    const emptyQuery = document.getElementById('searchEmptyQuery');
    const emptyClear = document.getElementById('searchEmptyClear');
    if (!list) return;

    const items   = Array.from(list.querySelectorAll('.problem-item'));
    const totalCount = items.length;

    // Snapshot every highlightable element's original text once so we can
    // restore + re-highlight cleanly on each keystroke.
    const targets = [];
    items.forEach(item => {
        item.querySelectorAll('.problem-title, .meta-value').forEach(el => {
            targets.push({ el: el, text: el.textContent.replace(/\s+/g, ' ').trim() });
        });
    });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function escapeRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    function applyHighlight(terms) {
        const re = terms.length ? new RegExp('(' + terms.map(escapeRegex).join('|') + ')', 'gi') : null;
        for (const t of targets) {
            if (!re) {
                t.el.textContent = t.text;
            } else {
                t.el.innerHTML = escapeHtml(t.text).replace(re, '<mark>$1</mark>');
            }
        }
    }

    function applyFilter() {
        const raw = input.value;
        const q   = raw.trim().toLowerCase();
        const hasQuery = q.length > 0;

        wrapper.classList.toggle('has-query', hasQuery);
        clearBtn.style.display = hasQuery ? '' : 'none';

        const terms = q.split(/\s+/).filter(Boolean);
        let visibleCount = 0;

        for (const item of items) {
            const hay = item.dataset.search || '';
            const match = terms.every(t => hay.indexOf(t) !== -1);
            item.classList.toggle('is-hidden', !match);
            if (match) visibleCount++;
        }

        // Time dividers — hide ones whose entire group is filtered out.
        const children = Array.from(list.children);
        for (let i = 0; i < children.length; i++) {
            const child = children[i];
            if (!child.classList || !child.classList.contains('time-divider')) continue;
            let anyVisible = false;
            for (let j = i + 1; j < children.length; j++) {
                const sib = children[j];
                if (sib.classList && sib.classList.contains('time-divider')) break;
                if (sib.classList && sib.classList.contains('problem-item') && !sib.classList.contains('is-hidden')) {
                    anyVisible = true;
                    break;
                }
            }
            child.classList.toggle('is-hidden', !anyVisible);
        }

        // Highlight matches (only when there's a 2+ char term — single letters create noise)
        applyHighlight(terms.filter(t => t.length >= 2));

        // Update result count + empty state
        if (hasQuery) {
            countEl.textContent = visibleCount + ' of ' + totalCount;
        } else {
            countEl.textContent = '';
        }
        if (empty) {
            empty.hidden = !(hasQuery && visibleCount === 0);
            if (!empty.hidden && emptyQuery) emptyQuery.textContent = raw.trim();
            list.style.display = (hasQuery && visibleCount === 0) ? 'none' : '';
        }
    }

    // Debounce so typing fast doesn't thrash highlight rendering, but keep it tight
    let debounceId = null;
    function scheduleFilter() {
        if (debounceId) clearTimeout(debounceId);
        debounceId = setTimeout(applyFilter, 60);
    }

    input.addEventListener('input', scheduleFilter);
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            // Live filter is the source of truth — don't reload the page
            e.preventDefault();
            input.blur();
        } else if (e.key === 'Escape' && input.value) {
            e.preventDefault();
            input.value = '';
            applyFilter();
        }
    });

    function clearSearch() {
        input.value = '';
        applyFilter();
        input.focus();
    }
    clearBtn.addEventListener('click', clearSearch);
    if (emptyClear) emptyClear.addEventListener('click', clearSearch);

    // Initial pass — handles ?q= on page load (server already filtered too, this just
    // syncs highlight + counts + empty state with the URL value).
    applyFilter();
})();

// Mobile filter toggle — expand/collapse the dropdown filter row
(function() {
    'use strict';
    const toggle = document.getElementById('filterToggle');
    const row    = document.getElementById('filterRow');
    if (!toggle || !row) return;

    function setOpen(isOpen) {
        row.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    toggle.addEventListener('click', () => {
        setOpen(!row.classList.contains('is-open'));
    });
})();
</script>

<?php include 'includes/footer.php'; ?>