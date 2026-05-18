<?php
/**
 * Presswick Sailing Club Issue Reporting System - Create Problem
 */

require_once '../includes/auth.php';
require_once '../includes/utils.php';
require_once '../includes/upload.php';
require_once '../config/database.php';

// Require authentication
requireAuth();
checkPasswordChangeRequired();

$user = getCurrentUser();
$errors = [];
$formData = [
    'title' => '',
    'details' => '',
    'urgency_level' => '',
    'problem_category_id' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid request. Please try again.';
    }
    
    // Get form data
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['details'] = trim($_POST['details'] ?? '');
    $formData['urgency_level'] = trim($_POST['urgency_level'] ?? '');
    $formData['problem_category_id'] = !empty($_POST['problem_category_id']) ? (int)$_POST['problem_category_id'] : null;
    
    // Validate form data
    $isValid = validateProblemData($formData, $errors);
    
    // Create problem if validation passes
    if ($isValid && empty($errors)) {
        try {
            $pdo = getDbConnection();
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert problem
            $stmt = $pdo->prepare("
                INSERT INTO problems (title, details, reported_by, urgency_tags, problem_category_id, image_urls, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $formData['title'],
                $formData['details'],
                $user['id'],
                $formData['urgency_level'],
                $formData['problem_category_id'],
                null // Will be updated after file upload
            ]);
            
            $problemId = $pdo->lastInsertId();
            
            // Handle attachments upload
            $uploadResult = ['files' => [], 'errors' => []];
            try {
                if (!empty($_FILES['images']['name'][0])) {
                    $uploadResult = uploadProblemFiles($_FILES, $problemId);
                    
                    if (!empty($uploadResult['files'])) {
                        // Update problem with image URLs
                        $imageData = json_encode($uploadResult['files']);
                        $stmt = $pdo->prepare("UPDATE problems SET image_urls = ? WHERE id = ?");
                        $stmt->execute([$imageData, $problemId]);
                    }
                }
            } catch (Exception $uploadError) {
                error_log("File upload error during problem creation: " . $uploadError->getMessage());
                $uploadResult['errors'][] = "File upload failed: " . $uploadError->getMessage();
                // Continue without failing the entire problem creation
            }
            
            // Commit transaction
            $pdo->commit();

            // Send notification (non-blocking best-effort)
            try {
                require_once __DIR__ . '/../includes/notifications.php';
                $problemRow = [
                    'id' => $problemId,
                    'title' => $formData['title'],
                    'details' => $formData['details'],
                    'urgency_tags' => $formData['urgency_level'],
                    'problem_category_id' => $formData['problem_category_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                sendProblemNotification($problemRow, $pdo);
            } catch (Exception $e) {
                error_log('Notification send error (problem create): ' . $e->getMessage());
            }
            
            // Set success message
            $_SESSION['flash_message'] = 'Problem reported successfully!';
            $_SESSION['flash_type'] = 'success';
            
            // Add upload warnings if any
            if (!empty($uploadResult['errors'])) {
                $_SESSION['flash_message'] .= ' Note: ' . implode(', ', $uploadResult['errors']);
                $_SESSION['flash_type'] = 'warning';
            }
            
            // Redirect to problem detail
            header("Location: " . getFullUrl("problems/view.php?id={$problemId}"));
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on database error
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            error_log("Problem creation database error: " . $e->getMessage());
            $errors['general'] = 'Database error occurred. Please try again.';
        } catch (Exception $e) {
            // Rollback transaction on any other error
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            error_log("Problem creation general error: " . $e->getMessage());
            $errors['general'] = 'An unexpected error occurred. Please try again.';
        }
    }
}

// Get available problem categories (all active; admin_only visible to normal users for creation)
try {
    $pdo = getDbConnection();
    $availableCategories = $pdo->query("SELECT * FROM problem_categories WHERE is_active = 1 ORDER BY display_order, name")->fetchAll();
} catch (Exception $e) {
    $availableCategories = [];
}

// Get available urgency levels (only enabled ones)
try {
    $availableUrgencyLevels = $pdo->query("SELECT * FROM urgency_levels WHERE is_active = 1 ORDER BY display_order, name")->fetchAll();
} catch (Exception $e) {
    $availableUrgencyLevels = [];
}

$pageTitle = 'New Report';
include '../includes/header.php';
?>

<main class="main">
    <div class="container-sm">
        <div class="card">
            <div class="card-header">
                <h2>New Report</h2>
                <p class="text-muted">Describe the issue you've encountered</p>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?= h($errors['general']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors['csrf'])): ?>
                    <div class="alert alert-danger">
                        <?= h($errors['csrf']) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" data-validate id="reportForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="title" class="form-label">Problem Title <span style="color: red;">*</span></label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                            value="<?= h($formData['title']) ?>"
                            required
                            placeholder="Brief description of the problem"
                        >
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= h($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="details" class="form-label">Problem Details</label>
                        <textarea 
                            id="details" 
                            name="details" 
                            class="form-control <?= isset($errors['details']) ? 'is-invalid' : '' ?>"
                            rows="4"
                            placeholder="Provide additional details about the problem..."
                        ><?= h($formData['details']) ?></textarea>
                        <?php if (isset($errors['details'])): ?>
                            <div class="invalid-feedback"><?= h($errors['details']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="urgency_level" class="form-label">Urgency Level <span style="color: red;">*</span></label>
                        <select name="urgency_level" id="urgency_level" class="form-control" required>
                            <option value="">Select Urgency Level</option>
                            <?php foreach ($availableUrgencyLevels as $level): ?>
                                <option value="<?= h($level['name']) ?>" 
                                        <?= $formData['urgency_level'] == $level['name'] ? 'selected' : '' ?>
                                        data-color="<?= h($level['color']) ?>">
                                    <?= h($level['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['urgency_level'])): ?>
                            <div class="invalid-feedback d-block"><?= h($errors['urgency_level']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="problem_category_id" class="form-label">Problem Type <span style="color: red;">*</span></label>
                        <select name="problem_category_id" id="problem_category_id" class="form-control" required>
                            <option value="">Select Problem Type</option>
                            <?php foreach ($availableCategories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= $formData['problem_category_id'] == $category['id'] ? 'selected' : '' ?>
                                        data-color="<?= h($category['color']) ?>">
                                    <?= h($category['name']) ?>
                                    <?php if ($category['admin_only']): ?> (Admin Only)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($availableCategories)): ?>
                            <small class="form-text text-muted">Choose the type that best describes this problem</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Attachments (Optional)</label>
                        <div class="file-upload">
                            <input 
                                type="file" 
                                id="images" 
                                name="images[]" 
                                multiple 
                                
                                style="display: none;"
                            >
                            <div class="upload-buttons">
                                <button type="button" class="btn btn-secondary" onclick="triggerFileSelect()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2z"/></svg>
                                    Select Files / Photos
                                </button>
                            </div>
                            <div class="upload-help">
                                <small class="text-muted">Attach up to <?= MAX_IMAGES_PER_PROBLEM ?> files (max <?= number_format(MAX_UPLOAD_SIZE/1024/1024,1) ?>MB each). Tap the button again to add more.</small>
                            </div>
                            <div class="file-preview"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reported Date/Time</label>
                        <div class="form-control" style="background: #f8f9fa; cursor: not-allowed;">
                            <?= date('F j, Y g:i A') ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="reportSubmitBtn">
                            <span class="btn-label">Report Problem</span>
                            <span class="btn-spinner" aria-hidden="true"></span>
                        </button>
                        <a href="<?= getFullUrl() ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
                <style>
                #reportSubmitBtn .btn-spinner { display: none; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: pscSpin 0.7s linear infinite; margin-left: 8px; vertical-align: -2px; }
                #reportSubmitBtn.is-loading { pointer-events: none; opacity: 0.75; cursor: not-allowed; }
                #reportSubmitBtn.is-loading .btn-spinner { display: inline-block; }
                #reportSubmitBtn.is-loading .btn-label::after { content: 'ing\2026'; }
                #reportSubmitBtn.is-loading .btn-label { font-variant-numeric: tabular-nums; }
                @keyframes pscSpin { to { transform: rotate(360deg); } }
                </style>
                <script>
                (function(){
                    var form = document.getElementById('reportForm');
                    var btn  = document.getElementById('reportSubmitBtn');
                    if (!form || !btn) return;
                    form.addEventListener('submit', function(e){
                        if (btn.dataset.submitted === '1') { e.preventDefault(); return; }
                        if (!form.checkValidity || form.checkValidity()) {
                            btn.dataset.submitted = '1';
                            btn.classList.add('is-loading');
                            var label = btn.querySelector('.btn-label');
                            if (label) label.textContent = 'Submitt';
                            // Re-enable failsafe in case the browser caches the form (back nav).
                            setTimeout(function(){
                                window.addEventListener('pageshow', function(ev){
                                    if (ev.persisted) {
                                        btn.dataset.submitted = '';
                                        btn.classList.remove('is-loading');
                                        if (label) label.textContent = 'Report Problem';
                                    }
                                });
                            }, 0);
                        }
                    });
                })();
                </script>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>