<?php
/**
 * Change Password Page
 * Presswick Sailing Club Issue Reporting System
 */

require_once 'includes/auth.php';
require_once 'includes/utils.php';
require_once 'config/database.php';

// Require authentication
requireAuth();

$user = getCurrentUser();
$isRequired = isset($_GET['required']) && $_GET['required'] == '1';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid request. Please try again.';
    }
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (empty($currentPassword)) {
        $errors['current_password'] = 'Current password is required';
    }
    
    // Validate new password
    if (!validatePassword($newPassword, $errors, 'new_password')) {
        // Error set by validatePassword function
    }
    
    // Confirm password match
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your new password';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Verify current password if no validation errors
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
                $errors['current_password'] = 'Current password is incorrect';
            }
        } catch (PDOException $e) {
            error_log("Password verification error: " . $e->getMessage());
            $errors['general'] = 'An error occurred. Please try again.';
        }
    }
    
    // Update password if all validations pass
    if (empty($errors)) {
        try {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?, must_change_password = 0, updated_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$newPasswordHash, $user['id']])) {
                // Update session to clear password change requirement
                $_SESSION['must_change_password'] = false;
                
                $success = true;
                
                // Set success message and redirect
                $_SESSION['flash_message'] = 'Password changed successfully!';
                $_SESSION['flash_type'] = 'success';
                
                // Redirect based on context
                if ($isRequired) {
                    header('Location: ' . getFullUrl());
                } else {
                    header('Location: ' . getFullUrl('profile.php'));
                }
                exit;
            } else {
                $errors['general'] = 'Failed to update password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            $errors['general'] = 'An error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Change Password';
include 'includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1><?= $isRequired ? 'Password Change Required' : 'Change Password' ?></h1>
                <p class="page-description"><?= $isRequired ? 'You must change your password to continue' : 'Update your account password' ?></p>
            </div>
        </div>
        
        <section class="panel">
            <header class="panel-header">
                <div>
                    <h2>Password Update</h2>
                    <p>Enter your current and new password details</p>
                </div>
            </header>
            <div class="panel-body">
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
                
                <?php if ($isRequired): ?>
                    <div class="alert alert-warning">
                        <strong>Action Required:</strong> You must change your password before continuing to use the system.
                    </div>
                <?php endif; ?>
                
                <form method="POST" data-validate class="password-form">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your current password"
                        >
                        <?php if (isset($errors['current_password'])): ?>
                            <div class="invalid-feedback"><?= h($errors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                            required
                            autocomplete="new-password"
                            placeholder="Enter your new password"
                        >
                        <small class="form-help">
                            Choose a secure password with letters, numbers, and symbols
                        </small>
                        <?php if (isset($errors['new_password'])): ?>
                            <div class="invalid-feedback"><?= h($errors['new_password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                            required
                            data-match="#new_password"
                            autocomplete="new-password"
                            placeholder="Re-enter your new password"
                        >
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= h($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Change Password
                        </button>
                        
                        <?php if (!$isRequired): ?>
                            <a href="<?= getFullUrl('profile.php') ?>" class="btn btn-secondary btn-sm">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>
        
        <div class="security-tips">
            <div class="tips-content">
                <h3>Password Security Tips</h3>
                <ul>
                    <li>Use a mix of uppercase and lowercase letters</li>
                    <li>Include numbers and special characters</li>
                    <li>Avoid using personal information</li>
                    <li>Make it at least 8 characters long</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<style>
/* Modern panel styling consistent with admin dashboard */
.page-header {display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;}
.page-header h1 {font-size:1.5rem;margin:0;font-weight:600;}
.page-header p.page-description {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}

.panel {background:#fff;border:1px solid #e2e5e9;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;margin-bottom:1.25rem;}
.panel-header {padding:.85rem 1rem;border-bottom:1px solid #eceff2;display:flex;justify-content:space-between;align-items:center;}
.panel-header h2 {font-size:1rem;margin:0;font-weight:600;}
.panel-header p {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.panel-body {padding:1rem;}

.password-form {display:flex;flex-direction:column;gap:1rem;}
.form-group {display:flex;flex-direction:column;gap:.4rem;}
.form-label {font-size:.7rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.03em;margin:0;}
.required {color:#dc3545;}
.form-help {font-size:.65rem;color:#6c757d;margin:0;line-height:1.2;}

.button-row {display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;}
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;}

.alert {padding:.75rem;margin-bottom:1rem;border:1px solid transparent;border-radius:8px;}
.alert-danger {color:#721c24;background-color:#f8d7da;border-color:#f5c6cb;}
.alert-warning {color:#856404;background-color:#fff3cd;border-color:#ffeaa7;}

.invalid-feedback {color:#dc3545;font-size:.65rem;margin-top:.25rem;}

.security-tips {background:#f8f9fa;border:1px solid #e9ecef;border-radius:14px;padding:1rem;margin-top:1.5rem;}
.tips-content h3 {font-size:.9rem;margin:0 0 .75rem;color:#495057;font-weight:600;}
.tips-content ul {margin:0;padding-left:1.25rem;color:#6c757d;font-size:.75rem;line-height:1.4;}
.tips-content li {margin-bottom:.25rem;}

/* Mobile optimizations */
@media (max-width:768px){
  .page-header {flex-direction:column;align-items:stretch;}
  .panel-body {padding:.85rem;}
  .button-row {flex-direction:column;}
  .btn.btn-sm {width:100%;}
  .security-tips {padding:.75rem;}
}
</style>