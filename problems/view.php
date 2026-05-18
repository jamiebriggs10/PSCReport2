<?php
/**
 * View Problem Page
 * Presswick Sailing Club Issue Reporting System
 */

require_once '../includes/auth.php';
require_once '../includes/utils.php';
require_once '../includes/upload.php';
require_once '../config/database.php';

// Require authentication
requireAuth();
checkPasswordChangeRequired();

$user = getCurrentUser();
$problemId = (int)($_GET['id'] ?? 0);

if ($problemId <= 0) {
    header('Location: ' . getFullUrl());
    exit;
}

$problem = null;
$canResolve = false;

try {
    $pdo = getDbConnection();
    
    // Get problem with reporter info
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.full_name as reporter_name,
            pc.name as category_name,
            pc.color as category_color
        FROM problems p
        LEFT JOIN users u ON p.reported_by = u.id
        LEFT JOIN problem_categories pc ON p.problem_category_id = pc.id
        WHERE p.id = ?
    ");
    $stmt->execute([$problemId]);
    $problem = $stmt->fetch();
    
    if (!$problem) {
        $_SESSION['flash_message'] = 'Problem not found.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . getFullUrl());
        exit;
    }

    // Enforce visibility: non-admin users cannot view admin_only category problems unless they reported them
    if (!isAdmin()) {
        try {
            // Determine if user can resolve or reopen this problem
            $canReopen  = isAdmin() || ($problem['reported_by'] == $user['id']);
            $catStmt = $pdo->prepare("SELECT admin_only FROM problem_categories WHERE id = ?");
            $catStmt->execute([$problem['problem_category_id']]);
            $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
            if ($cat && (int)$cat['admin_only'] === 1 && $problem['reported_by'] != $user['id']) {
                $_SESSION['flash_message'] = 'You do not have permission to view that problem.';
                $_SESSION['flash_type'] = 'danger';
                header('Location: ' . getFullUrl());
                exit;
            }
        } catch (Exception $e) {
            // Fail safe: deny access if lookup fails and user isn't reporter
            if ($problem['reported_by'] != $user['id']) {
                $_SESSION['flash_message'] = 'You do not have permission to view that problem.';
                $_SESSION['flash_type'] = 'danger';
                header('Location: ' . getFullUrl());
                exit;
            }
        }
    }
    
    // Permission flags (computed after visibility check)
    $canModify = isAdmin() || ($problem['reported_by'] == $user['id']); // retained in case needed elsewhere
    // Any authenticated user (already passed requireAuth) can resolve or reopen
    $canResolve = ($problem['status'] === 'OPEN');
    $canReopen  = ($problem['status'] === 'RESOLVED');
    
} catch (PDOException $e) {
    error_log("Problem view error: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Error loading problem.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . getFullUrl());
    exit;
}

// Unified action handler (resolve / reopen) executed after successful load & permission computation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid request.';
        $_SESSION['flash_type'] = 'danger';
        header("Location: " . getFullUrl("problems/view.php?id={$problemId}"));
        exit;
    }
    if ($action === 'resolve') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Process resolution notes
            $resolutionNotes = trim($_POST['resolution_notes'] ?? '');
            
            // Handle file uploads for resolution
            $resolutionAttachments = [];
            if (isset($_FILES['resolution_files']) && !empty($_FILES['resolution_files']['name'][0])) {
                try {
                    $uploadResult = handleResolutionFileUploads($_FILES);
                    $resolutionAttachments = $uploadResult['files'];
                    
                    // Handle upload errors
                    if (!empty($uploadResult['errors'])) {
                        error_log('Resolution file upload errors: ' . implode(', ', $uploadResult['errors']));
                        $_SESSION['flash_message'] = 'Problem resolved, but some files failed to upload: ' . implode(', ', $uploadResult['errors']);
                        $_SESSION['flash_type'] = 'warning';
                    }
                } catch (Exception $uploadError) {
                    error_log('Resolution file upload error: ' . $uploadError->getMessage());
                    $_SESSION['flash_message'] = 'Problem resolved, but there was an issue with file uploads: ' . $uploadError->getMessage();
                    $_SESSION['flash_type'] = 'warning';
                    // Continue with resolution even if uploads fail
                }
            }
            
            // Update problem status to RESOLVED
            $stmt = $pdo->prepare("UPDATE problems SET status='RESOLVED', updated_at=NOW() WHERE id=? AND status='OPEN'");
            if (!$stmt->execute([$problemId])) {
                throw new Exception("Failed to update problem status");
            }
            
            // Create resolution record
            $stmt = $pdo->prepare("INSERT INTO resolutions (problem_id, action, action_by, action_at, notes, attachments) VALUES (?, 'RESOLVE', ?, NOW(), ?, ?)");
            if (!$stmt->execute([$problemId, $user['id'], $resolutionNotes, json_encode($resolutionAttachments)])) {
                throw new Exception("Failed to create resolution record");
            }
            
            // Commit transaction
            $pdo->commit();
            
            if (!isset($_SESSION['flash_message'])) { // Only set success message if no upload warning
                $_SESSION['flash_message'] = 'Problem marked as resolved.';
                $_SESSION['flash_type'] = 'success';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Problem resolve error: ' . $e->getMessage());
            $_SESSION['flash_message'] = 'Error resolving problem.';
            $_SESSION['flash_type'] = 'danger';
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Problem resolve general error: ' . $e->getMessage());
            $_SESSION['flash_message'] = 'Error resolving problem: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }
    } elseif ($action === 'reopen') {
        if ($problem['status'] !== 'RESOLVED') {
            $_SESSION['flash_message'] = 'Only resolved problems can be reopened.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update problem status to OPEN
                $stmt = $pdo->prepare("UPDATE problems SET status='OPEN', updated_at=NOW() WHERE id=? AND status='RESOLVED'");
                if (!$stmt->execute([$problemId])) {
                    throw new Exception("Failed to update problem status");
                }
                
                // Create reopen record
                $stmt = $pdo->prepare("INSERT INTO resolutions (problem_id, action, action_by, action_at, notes) VALUES (?, 'REOPEN', ?, NOW(), ?)");
                $reopenNote = 'Problem reopened by ' . $user['full_name'];
                if (!$stmt->execute([$problemId, $user['id'], $reopenNote])) {
                    throw new Exception("Failed to create reopen record");
                }
                
                // Commit transaction
                $pdo->commit();
                
                $_SESSION['flash_message'] = 'Problem reopened.';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Problem reopen error: ' . $e->getMessage());
                $_SESSION['flash_message'] = 'Error reopening problem.';
                $_SESSION['flash_type'] = 'danger';
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Problem reopen general error: ' . $e->getMessage());
                $_SESSION['flash_message'] = 'Error reopening problem: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    header("Location: " . getFullUrl("problems/view.php?id={$problemId}"));
    exit;
}

$urgencyTags = parseUrgencyTags($problem['urgency_tags']);
$attachments = getProblemAttachments($problem['id'], $problem['image_urls']);

// Get resolution history for this problem
function getResolutionHistory($problemId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            u.full_name as action_by_name
        FROM resolutions r
        LEFT JOIN users u ON r.action_by = u.id
        WHERE r.problem_id = ?
        ORDER BY r.action_at DESC
    ");
    $stmt->execute([$problemId]);
    return $stmt->fetchAll();
}

$resolutionHistory = getResolutionHistory($problemId, $pdo);

$pageTitle = 'Problem: ' . $problem['title'];
include '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <nav>
                <a href="<?= getFullUrl() ?>" class="btn btn-outline btn-sm">← Back to Dashboard</a>
            </nav>
            
            <?php if ($canResolve && $problem['status'] === 'OPEN'): ?>
                <button type="button" class="btn btn-success" onclick="showResolveModal()">
                    Mark as Resolved
                </button>
            <?php endif; ?>
                <?php if ($canReopen && $problem['status'] === 'RESOLVED'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="reopen">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Reopen this problem?')">
                            Reopen Issue
                        </button>
                    </form>
                <?php endif; ?>
        </div>
        
        <div class="card" <?php if (!empty($problem['category_name'])): ?>style="border-left: 6px solid <?= h($problem['category_color']) ?>;"<?php endif; ?>>
            <div class="card-header">
                <?php if (!empty($problem['category_name'])): ?>
                    <div class="problem-category-header" style="
                        background-color: <?= h($problem['category_color']) ?>; 
                        color: white; 
                        padding: 4px 12px; 
                        border-radius: 4px; 
                        font-size: 0.8rem; 
                        font-weight: 600;
                        display: inline-block;
                        margin-bottom: 12px;
                    ">
                        <?= h($problem['category_name']) ?>
                    </div>
                <?php endif; ?>
                <h1><?= h($problem['title']) ?></h1>
                <div class="d-flex align-items-center gap-3 mt-3 flex-wrap" style="margin-top: 16px !important;">
                    <?= getStatusBadgeHtml($problem['status']) ?>
                    <?= getUrgencyBadgeHtml($urgencyTags, true) ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <?php if (!empty($problem['details'])): ?>
                            <div class="mb-4">
                                <h3>Problem Details</h3>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; white-space: pre-wrap;"><?= h($problem['details']) ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($attachments)): ?>
                            <div class="mb-4">
                                <h3>Attachments</h3>
                                <div class="image-gallery">
                                    <?php foreach ($attachments as $file): ?>
                                        <?php if (!empty($file['is_image']) && $file['is_image']): ?>
                                            <div class="image-item">
                                                <img src="<?= h($file['url']) ?>" alt="<?= h($file['original_name']) ?>" title="Click to view full size" onclick="showImageModal('<?= h($file['url']) ?>', '<?= h($file['original_name']) ?>')">
                                                <div class="image-caption"><?= h($file['original_name']) ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="image-item" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1rem;border:1px solid #eee;border-radius:8px;background:#fafafa;">
                                                <div style="color:#64748b;display:flex;align-items:center;justify-content:center;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 1 1-8.49-8.49l9.19-9.19a4 4 0 1 1 5.66 5.66l-9.2 9.19a2 2 0 1 1-2.83-2.83l8.49-8.48"/></svg></div>
                                                <div class="image-caption" style="text-align:center;max-width:140px;word-break:break-word;">
                                                    <a href="<?= getFullUrl('download.php?problem=' . $problemId . '&file=' . urlencode($file['filename'])) ?>" target="_blank" rel="noopener"><?= h($file['original_name']) ?></a>
                                                    <div style="font-size:0.75rem;color:#666;"><?= h(strtoupper($file['extension'] ?? '')) ?> (<?= h(formatBytes($file['size'])) ?>)</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <strong>Problem Information</strong>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Status:</strong><br>
                                    <?= getStatusBadgeHtml($problem['status']) ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Reported By:</strong><br>
                                    <?= h($problem['reporter_name']) ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Reported Date:</strong><br>
                                    <?= formatDate($problem['created_at']) ?>
                                </div>
                                
                                <?php if (!empty($resolutionHistory)): ?>
                                    <div class="mb-3">
                                        <strong>Last Action:</strong><br>
                                        <?php $lastResolution = $resolutionHistory[0]; ?>
                                        <span class="badge badge-<?= $lastResolution['action'] === 'RESOLVE' ? 'success' : 'warning' ?>">
                                            <?= $lastResolution['action'] === 'RESOLVE' ? 'RESOLVED' : 'REOPENED' ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            by <?= h($lastResolution['action_by_name']) ?><br>
                                            on <?= formatDate($lastResolution['action_at']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Urgency Levels:</strong><br>
                                    <?php foreach ($urgencyTags as $tag): ?>
                                        <?= getUrgencyBadgeHtml([$tag]) ?><br>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (!empty($attachments)): ?>
                                    <div class="mb-3">
                                        <strong>Attachments:</strong><br>
                                        <?= count($attachments) ?> file<?= count($attachments) !== 1 ? 's' : '' ?> attached
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resolution History Section -->
        <?php if (!empty($resolutionHistory)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Resolution History</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($resolutionHistory as $index => $resolution): ?>
                        <div class="resolution-entry <?= $index < count($resolutionHistory) - 1 ? 'mb-4' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span class="badge badge-<?= $resolution['action'] === 'RESOLVE' ? 'success' : 'warning' ?> badge-lg">
                                        <?= $resolution['action'] === 'RESOLVE' ? 'RESOLVED' : 'REOPENED' ?>
                                    </span>
                                    <span class="resolution-by">
                                        by <strong><?= h($resolution['action_by_name']) ?></strong>
                                    </span>
                                    <?php if ($index === 0): ?>
                                        <span class="badge badge-primary badge-sm">Current Status</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted">
                                    <strong><?= formatDate($resolution['action_at']) ?></strong>
                                </div>
                            </div>
                            
                            <?php if (!empty($resolution['notes']) && $resolution['action'] === 'RESOLVE'): ?>
                                <div class="mb-3">
                                    <h5>Resolution Notes</h5>
                                    <div class="resolution-notes">
                                        <?= h($resolution['notes']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            $resolutionAttachments = [];
                            if (!empty($resolution['attachments'])) {
                                $resolutionAttachments = json_decode($resolution['attachments'], true) ?: [];
                            }
                            if (!empty($resolutionAttachments)): 
                            ?>
                                <div class="mb-3">
                                    <h5>Attachments</h5>
                                    <div class="image-gallery">
                                        <?php foreach ($resolutionAttachments as $attachment): ?>
                                            <?php if (!empty($attachment['is_image']) && $attachment['is_image']): ?>
                                                <div class="image-item">
                                                    <img src="<?= getFullUrl('uploads/' . h($attachment['filename'])) ?>" alt="<?= h($attachment['original_name']) ?>" title="Click to view full size" onclick="showImageModal('<?= getFullUrl('uploads/' . h($attachment['filename'])) ?>', '<?= h($attachment['original_name']) ?>')">
                                                    <div class="image-caption"><?= h($attachment['original_name']) ?></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="image-item" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1rem;border:1px solid #eee;border-radius:8px;background:#fafafa;">
                                                    <div style="color:#64748b;display:flex;align-items:center;justify-content:center;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 1 1-8.49-8.49l9.19-9.19a4 4 0 1 1 5.66 5.66l-9.2 9.19a2 2 0 1 1-2.83-2.83l8.49-8.48"/></svg></div>
                                                    <div class="image-caption" style="text-align:center;max-width:140px;word-break:break-word;">
                                                        <a href="<?= getFullUrl('uploads/' . h($attachment['filename'])) ?>" target="_blank" rel="noopener"><?= h($attachment['original_name']) ?></a>
                                                        <div style="font-size:0.75rem;color:#666;"><?= h(strtoupper($attachment['extension'] ?? '')) ?> (<?= h(number_format($attachment['size'] / 1024 / 1024, 2)) ?> MB)</div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($index < count($resolutionHistory) - 1): ?>
                                <hr class="my-4">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($problem['status'] === 'RESOLVED'): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Resolution History</h3>
                </div>
                <div class="card-body">
                    <div class="text-center text-muted">
                        <p>No resolution history available for this problem.</p>
                        <small>This problem was marked as resolved before the resolution tracking system was implemented.</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" style="display: none;">
    <div class="image-modal-content">
        <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="" alt="">
        <div id="modalCaption" class="image-modal-caption"></div>
    </div>
</div>

<!-- Resolution Modal -->
<div id="resolveModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark Problem as Resolved</h3>
            <button type="button" class="modal-close" onclick="closeResolveModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="resolution-info">
                <div class="resolution-summary">
                    <h4>Marking as Resolved</h4>
                    <p style="color: #6c757d; margin-bottom: 1.5rem;">This will mark the problem as resolved. You can optionally add notes or attachments below.</p>
                </div>
                
                <div class="resolved-problem-summary">
                    <h5><?= h($problem['title']) ?></h5>
                    <p><strong>Reported by:</strong> <?= h($problem['reporter_name']) ?></p>
                    <p><strong>Category:</strong> <?= h($problem['category_name'] ?? 'Uncategorized') ?></p>
                </div>
                
                <form id="resolveForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="resolve">
                    
                    <!-- Optional Details Section -->
                    <div class="optional-section">
                        <div class="optional-header" onclick="toggleOptionalSection()">
                            <span>Add resolution details (optional)</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        
                        <div class="optional-content" id="optionalContent" style="display: none;">
                            <div class="form-group">
                                <label for="resolutionNotes" class="form-label">Resolution Notes</label>
                                <textarea 
                                    id="resolutionNotes" 
                                    name="resolution_notes" 
                                    class="form-control" 
                                    rows="3"
                                    placeholder="What was done to resolve this? Any parts used, observations, etc..."
                                ></textarea>
                                <small class="form-text">Describe how the problem was resolved</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Photos & Documents</label>
                                <div class="file-upload" onclick="document.getElementById('resolutionFiles').click()">
                                    <div class="upload-content">
                                        <i class="fas fa-camera" style="font-size: 1.5rem; color: #6c757d; margin-bottom: 0.5rem;"></i>
                                        <p style="margin: 0; font-size: 0.9rem;">Click to upload photos or documents</p>
                                        <small style="color: #6c757d;">Before/after photos, receipts, etc.</small>
                                    </div>
                                </div>
                                <input 
                                    type="file" 
                                    id="resolutionFiles" 
                                    name="resolution_files[]" 
                                    multiple 
                                    style="display: none;"
                                    accept="image/*,.pdf,.doc,.docx,.txt"
                                >
                                <div class="file-preview"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="resolution-metadata">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Resolved By</label>
                                <input type="text" class="form-control" value="<?= h($user['full_name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Resolution Date</label>
                                <input type="text" class="form-control" value="<?= date('Y-m-d H:i') ?>" readonly>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeResolveModal()">Cancel</button>
            <button type="button" class="btn btn-success btn-sm" onclick="submitResolution()">
                <i class="fas fa-check"></i> Mark as Resolved
            </button>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2c3e50;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.resolved-problem-summary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.resolved-problem-summary h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.resolved-problem-summary h5 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    font-size: 1.1rem;
}

.resolved-problem-summary p {
    margin: 0.25rem 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.resolution-summary h4 {
    color: #28a745;
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
}

/* Resolution History Styles */
.resolution-entry {
    border-left: 4px solid #dee2e6;
    padding-left: 1.5rem;
    position: relative;
}

.resolution-entry::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 8px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #dee2e6;
}

.resolution-entry:first-child {
    border-left-color: #28a745;
}

.resolution-entry:first-child::before {
    background: #28a745;
}

.resolution-notes {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    white-space: pre-wrap;
    font-family: inherit;
    line-height: 1.5;
}

.badge-lg {
    font-size: 0.9rem;
    padding: 0.5em 0.75em;
}

.badge-sm {
    font-size: 0.75rem;
    padding: 0.35em 0.6em;
}

.resolution-by {
    color: #6c757d;
    font-size: 0.9rem;
    white-space: nowrap;
}

.resolution-by strong {
    color: #495057;
}

.optional-section {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.optional-header {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #dee2e6;
    transition: background-color 0.2s;
}

.optional-header:hover {
    background: #e9ecef;
}

.optional-header span:first-child {
    font-weight: 500;
    color: #495057;
}

.toggle-icon {
    transition: transform 0.2s;
    color: #6c757d;
    font-size: 0.8rem;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

.optional-content {
    padding: 1rem;
    background: white;
    transition: all 0.3s ease;
}

.optional-content.show {
    display: block !important;
}

.form-text {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 600;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.file-upload-area {
    border: 2px dashed #ced4da;
    border-radius: 4px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s;
}

.file-upload-area:hover {
    border-color: #007bff;
}

.file-upload-area.dragover {
    border-color: #2196f3;
    background: #e3f2fd;
    transform: scale(1.02);
}

.file-upload-area.file-removed {
    background: #fff3cd;
    border-color: #ffc107;
}

.file-list {
    margin-top: 1rem;
}

.file-list-summary {
    margin-bottom: 0.75rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #007bff;
}

.file-preview-item {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    margin-bottom: 0.75rem;
    overflow: hidden;
    transition: all 0.2s ease;
}

.file-preview-item:hover {
    border-color: #007bff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.file-preview-content {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    gap: 0.75rem;
}

.file-thumbnail {
    flex-shrink: 0;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 4px;
    overflow: hidden;
}

.file-image-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.file-icon {
    font-size: 1.5rem;
    color: #6c757d;
}

.file-icon .fa-image { color: #28a745; }
.file-icon .fa-file-pdf { color: #dc3545; }
.file-icon .fa-file-word { color: #007bff; }
.file-icon .fa-file-excel { color: #28a745; }
.file-icon .fa-file-alt { color: #6c757d; }

.file-info {
    flex: 1;
    min-width: 0;
}

.file-name {
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-details {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #6c757d;
}

.file-remove-btn {
    background: #dc3545;
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
    flex-shrink: 0;
}

.file-remove-btn:hover {
    background: #c82333;
}

.file-remove-btn i {
    font-size: 0.875rem;
}

/* Image Modal Styles */
.image-modal {
    position: fixed;
    inset: 0;
    z-index: 2100;
    background-color: rgba(0,0,0,0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: calc(env(safe-area-inset-top, 0) + 64px) 16px calc(env(safe-area-inset-bottom, 0) + 16px);
    overflow: hidden;
}

.image-modal-content {
    position: static;
    width: 100%;
    max-width: 1100px;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.image-modal img {
    max-width: 100%;
    max-height: calc(100dvh - env(safe-area-inset-top, 0) - env(safe-area-inset-bottom, 0) - 160px);
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.6);
}

.image-modal-close {
    position: fixed;
    top: calc(env(safe-area-inset-top, 0) + 12px);
    right: calc(env(safe-area-inset-right, 0) + 12px);
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 28px;
    line-height: 1;
    font-weight: 400;
    cursor: pointer;
    z-index: 2110;
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 999px;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: background 0.15s ease, transform 0.15s ease;
    user-select: none;
}

.image-modal-close:hover,
.image-modal-close:focus-visible {
    background: rgba(30, 41, 59, 0.85);
    outline: none;
}

.image-modal-close:active {
    transform: scale(0.94);
}

.image-modal-caption {
    text-align: center;
    color: #e2e8f0;
    padding: 8px 14px;
    background: rgba(0,0,0,0.55);
    border-radius: 8px;
    max-width: min(80%, 720px);
    word-break: break-word;
    font-size: 0.85rem;
}

/* Improve image item hover effect */
.image-item img {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.image-item img:hover {
    transform: scale(1.05);
}

.resolution-metadata {
    background: #e9ecef;
    border-radius: 4px;
    padding: 1rem;
    margin-top: 1rem;
}

.resolution-metadata .form-group input {
    background: white;
    color: #495057;
    font-weight: 500;
}

@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 1rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}

/* =========================================================
   Desktop compaction — keep the mobile layout untouched
   ========================================================= */
@media (min-width: 769px) {
    /* Tighter container + main padding so the page fits a typical laptop screen */
    .main { padding: 1rem 0 5rem; }

    /* Top action strip — compact, single line */
    main .container > .d-flex.mb-3 { margin-bottom: 0.75rem !important; }

    /* Primary card: smaller h1, tighter header */
    main .container > .card > .card-header { padding: 0.85rem 1.1rem; }
    main .container > .card > .card-header h1 { font-size: 1.25rem; line-height: 1.25; letter-spacing: -0.015em; }
    main .container > .card > .card-header .problem-category-header {
        font-size: 0.7rem !important;
        padding: 3px 9px !important;
        margin-bottom: 8px !important;
        border-radius: 999px !important;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    main .container > .card > .card-header .d-flex { margin-top: 10px !important; gap: 0.5rem !important; }

    main .container > .card > .card-body { padding: 1.1rem 1.25rem; }
    main .container > .card > .card-body h3 {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 0.55rem;
    }
    main .container > .card > .card-body .mb-4 { margin-bottom: 1rem !important; }

    /* Problem Details body */
    main .container > .card > .card-body > .row > .col-md-8 > .mb-4 > div[style*="background"] {
        background: var(--surface-alt) !important;
        border: 1px solid var(--border-color);
        padding: 0.85rem 1rem !important;
        border-radius: 10px !important;
        font-size: 0.9rem;
        line-height: 1.55;
    }

    /* Right-hand "Problem Information" sidebar — tight definition list look */
    main .container > .card > .card-body > .row > .col-md-4 > .card {
        margin-bottom: 0;
        position: sticky;
        top: 70px;
    }
    main .container > .card > .card-body > .row > .col-md-4 > .card > .card-header {
        padding: 0.6rem 0.9rem;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }
    main .container > .card > .card-body > .row > .col-md-4 > .card > .card-body { padding: 0.85rem 0.9rem; }
    main .container > .card > .card-body > .row > .col-md-4 > .card .mb-3 {
        margin-bottom: 0 !important;
        padding: 0.55rem 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }
    main .container > .card > .card-body > .row > .col-md-4 > .card .mb-3:first-child { padding-top: 0; }
    main .container > .card > .card-body > .row > .col-md-4 > .card .mb-3:last-child { padding-bottom: 0; border-bottom: 0; }
    main .container > .card > .card-body > .row > .col-md-4 > .card .mb-3 strong:first-child {
        display: block;
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--text-subtle);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 3px;
    }
    main .container > .card > .card-body > .row > .col-md-4 > .card .mb-3 br:first-of-type { display: none; }
    main .container > .card > .card-body > .row > .col-md-4 > .card small.text-muted { font-size: 0.72rem; line-height: 1.45; display: block; margin-top: 2px; }

    /* Resolution History — tighter on desktop */
    main .container > .card.mt-4 > .card-header { padding: 0.7rem 1.1rem; }
    main .container > .card.mt-4 > .card-header h3 { font-size: 0.95rem; }
    main .container > .card.mt-4 > .card-body { padding: 1rem 1.25rem; }

    /* Attachments grid — denser on desktop */
    .image-gallery { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.65rem; }
    .image-gallery img { height: 140px; border-radius: 10px; }
}
</style>

<script>
function showResolveModal() {
    document.getElementById('resolveModal').style.display = 'flex';
    
    // Focus on notes field
    setTimeout(() => {
        document.getElementById('resolutionNotes').focus();
    }, 100);
}

function closeResolveModal() {
    document.getElementById('resolveModal').style.display = 'none';
    
    // Reset form
    document.getElementById('resolveForm').reset();
    
    // Clear file preview using app.js functionality
    const filePreview = document.querySelector('#resolveModal .file-preview');
    if (filePreview) {
        filePreview.innerHTML = '';
    }
    
    // Reset optional section
    const optionalContent = document.getElementById('optionalContent');
    const icon = document.querySelector('.toggle-icon');
    if (optionalContent && icon) {
        optionalContent.style.display = 'none';
        icon.textContent = '▼';
    }
}

function submitResolution() {
    document.getElementById('resolveForm').submit();
}

function toggleOptionalSection() {
    const content = document.getElementById('optionalContent');
    const icon = document.querySelector('.toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▲';
        icon.classList.add('rotated');
    } else {
        content.style.display = 'none';
        icon.textContent = '▼';
        icon.classList.remove('rotated');
    }
}

// File upload handling with enhanced preview
document.getElementById('resolutionFiles').addEventListener('change', function() {
    const fileList = document.getElementById('resolutionFileList');
    fileList.innerHTML = '';
    
    Array.from(this.files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-preview-item';
        
        // Create file preview content
        const isImage = file.type.startsWith('image/');
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileIcon = getFileIcon(file.type, file.name);
        
        fileItem.innerHTML = `
            <div class="file-preview-content">
                <div class="file-thumbnail">
                    ${isImage ? `<img src="#" alt="Preview" class="file-image-preview" data-index="${index}">` : `<div class="file-icon">${fileIcon}</div>`}
                </div>
                <div class="file-info">
                    <div class="file-name" title="${file.name}">${file.name}</div>
                    <div class="file-details">
                        <span class="file-size">${fileSize} MB</span>
                        <span class="file-type">${file.type || 'Unknown'}</span>
                    </div>
                </div>
                <button type="button" class="file-remove-btn" onclick="removeFile(${index})" title="Remove file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        fileList.appendChild(fileItem);
        
        // Load image preview if it's an image
        if (isImage) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = fileItem.querySelector('.file-image-preview');
                if (img) {
                    img.src = e.target.result;
                }
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Update upload area text
    updateUploadAreaText(this.files.length);
});

function getFileIcon(fileType, fileName) {
    const extension = fileName.split('.').pop().toLowerCase();
    
    if (fileType.startsWith('image/')) {
        return '<i class="fas fa-image"></i>';
    } else if (fileType === 'application/pdf' || extension === 'pdf') {
        return '<i class="fas fa-file-pdf"></i>';
    } else if (fileType.includes('word') || ['doc', 'docx'].includes(extension)) {
        return '<i class="fas fa-file-word"></i>';
    } else if (fileType.includes('sheet') || ['xls', 'xlsx'].includes(extension)) {
        return '<i class="fas fa-file-excel"></i>';
    } else if (fileType.startsWith('text/') || extension === 'txt') {
        return '<i class="fas fa-file-alt"></i>';
    } else {
        return '<i class="fas fa-file"></i>';
    }
}

function updateUploadAreaText(fileCount) {
    const uploadArea = document.querySelector('.file-upload-area p');
    if (uploadArea) {
        if (fileCount > 0) {
            uploadArea.textContent = `${fileCount} file${fileCount > 1 ? 's' : ''} selected - Click to add more`;
        } else {
            uploadArea.textContent = 'Click to upload photos or documents';
        }
    }
    
    // Update file list summary
    const fileList = document.getElementById('resolutionFileList');
    if (fileCount > 0) {
        const totalSize = Array.from(document.getElementById('resolutionFiles').files)
            .reduce((total, file) => total + file.size, 0);
        const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
        
        let summary = document.querySelector('.file-list-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'file-list-summary';
            fileList.parentNode.insertBefore(summary, fileList);
        }
        summary.innerHTML = `<small class="text-muted"><i class="fas fa-info-circle"></i> ${fileCount} file${fileCount > 1 ? 's' : ''} selected, ${totalSizeMB} MB total</small>`;
    } else {
        const summary = document.querySelector('.file-list-summary');
        if (summary) {
            summary.remove();
        }
    }
}

function removeFile(index) {
    const fileInput = document.getElementById('resolutionFiles');
    const dt = new DataTransfer();
    
    // Rebuild file list without the removed file
    Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    // Update the input and trigger change event
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event('change'));
    
    // Show feedback
    if (fileInput.files.length === 0) {
        const uploadArea = document.querySelector('.file-upload-area');
        if (uploadArea) {
            uploadArea.classList.add('file-removed');
            setTimeout(() => uploadArea.classList.remove('file-removed'), 300);
        }
    }
}

// Enhanced drag and drop functionality
const uploadArea = document.querySelector('.file-upload-area');
if (uploadArea) {
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
        
        const fileInput = document.getElementById('resolutionFiles');
        const droppedFiles = e.dataTransfer.files;
        
        if (droppedFiles.length > 0) {
            // Combine existing files with dropped files
            const dt = new DataTransfer();
            
            // Add existing files
            Array.from(fileInput.files).forEach(file => dt.items.add(file));
            
            // Add dropped files
            Array.from(droppedFiles).forEach(file => dt.items.add(file));
            
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
}

// Image Modal Functions
function showImageModal(imageSrc, caption) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    
    modal.style.display = 'flex';
    modalImg.src = imageSrc;
    modalCaption.textContent = caption;
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
    
    // Restore body scroll
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside the image
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<style>
/* Badge improvements for view page */
.status-badge-new, .urgency-badge {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
    transition: all 0.2s ease !important;
    margin-right: 0 !important;
}

.status-badge-new:hover, .urgency-badge:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important;
}

/* Better badge container with improved spacing */
.d-flex.align-items-center.gap-3 {
    min-height: 40px;
    align-items: center !important;
    gap: 12px !important;
}

/* Ensure consistent badge alignment */
.d-flex.align-items-center.gap-3 > * {
    vertical-align: middle;
    display: inline-flex;
    align-items: center;
}

/* Problem category header styling */
.problem-category-header {
    box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
}
</style>

<?php include '../includes/footer.php'; ?>