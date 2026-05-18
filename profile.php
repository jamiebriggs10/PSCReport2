<?php
/**
 * User Profile Page
 * Presswick Sailing Club Issue Reporting System
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

// Require authentication
requireAuth();
checkPasswordChangeRequired();

$user = getCurrentUser();

$pageTitle = 'Profile';
include 'includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>User Profile</h1>
                <p class="page-description">View and manage your account information</p>
            </div>
        </div>
        
        <section class="panel">
            <header class="panel-header">
                <div>
                    <h2>Profile Information</h2>
                    <p>Your account details and settings</p>
                </div>
            </header>
            <div class="panel-body">
                <?php if ($user['must_change_password']): ?>
                <div class="alert alert-warning">
                    <strong>Password Change Required:</strong> 
                    You must change your password to continue using the system.
                </div>
                <?php endif; ?>
                
                <div class="profile-info">
                    <div class="profile-field">
                        <label class="profile-label">Full Name</label>
                        <div class="profile-value">
                            <?= h($user['full_name']) ?>
                        </div>
                    </div>
                    
                    <div class="profile-field">
                        <label class="profile-label">Username</label>
                        <div class="profile-value">
                            <?= h($user['username']) ?>
                        </div>
                    </div>
                    
                    <div class="profile-field">
                        <label class="profile-label">Role</label>
                        <div class="profile-value">
                            <span class="role-badge role-<?= strtolower($user['role']) ?>">
                                <?= h($user['role']) ?>
                            </span>
                            <?php if ($user['role'] === 'ADMIN'): ?>
                                <span class="admin-indicator">Administrator Access</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <a href="<?= getFullUrl('change_password.php') ?>" class="btn btn-primary btn-sm">
                        Change Password
                    </a>
                    <a href="<?= getFullUrl('logout.php') ?>" class="btn btn-outline btn-sm">
                        Logout
                    </a>
                </div>
            </div>
        </section>
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

.profile-info {display:flex;flex-direction:column;gap:1rem;margin-bottom:1.5rem;}
.profile-field {display:flex;flex-direction:column;gap:.25rem;}
.profile-label {font-size:.7rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.03em;margin:0;}
.profile-value {display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;font-size:.85rem;}

.role-badge {padding:.2rem .5rem;border-radius:12px;font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em;}
.role-admin {background:#dc3545;color:#fff;}
.role-user {background:#007bff;color:#fff;}

.admin-indicator {font-size:.75rem;color:#6c757d;margin-left:.5rem;}

.profile-actions {display:flex;gap:.5rem;flex-wrap:wrap;}
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;}

.alert {padding:.75rem;margin-bottom:1rem;border:1px solid transparent;border-radius:8px;}
.alert-warning {color:#856404;background-color:#fff3cd;border-color:#ffeaa7;}

/* Mobile optimizations */
@media (max-width:768px){
  .page-header {flex-direction:column;align-items:stretch;}
  .panel-body {padding:.85rem;}
  .profile-actions {flex-direction:column;}
  .btn.btn-sm {width:100%;}
}
</style>