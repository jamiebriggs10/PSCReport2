<?php
/**
 * Error Page - 404 and other errors
 * Presswick Sailing Club Issue Reporting System
 */

// Get error information
$errorCode = $_GET['error'] ?? '404';
$errorMessage = '';
$errorDescription = '';
$showLogin = false;

switch ($errorCode) {
    case '404':
        $errorMessage = 'Page Not Found';
        $errorDescription = 'The page you are looking for does not exist or has been moved.';
        break;
    case '403':
        $errorMessage = 'Access Denied';
        $errorDescription = 'You do not have permission to access this resource.';
        $showLogin = true;
        break;
    case '500':
        $errorMessage = 'Internal Server Error';
        $errorDescription = 'Something went wrong on our end. Please try again later.';
        break;
    default:
        $errorMessage = 'Error';
        $errorDescription = 'An unexpected error occurred.';
}

// Check if user is logged in
session_start();
$isLoggedIn = isset($_SESSION['user_id']);

$pageTitle = $errorMessage;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PSC Issues</title>
    <link rel="stylesheet" href="<?= getFullUrl('assets/css/style.css') ?>">
    <link rel="icon" type="image/x-icon" href="<?= getFullUrl('assets/images/favicon.ico') ?>">
</head>
<body>
    <main class="main">
        <div class="container-sm">
            <div class="text-center" style="padding: 4rem 0;">
                <div style="color: var(--danger-color); margin-bottom: 1.25rem; display: flex; justify-content: center;">
                    <?php if ($errorCode === '404'): ?>
                        <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <?php elseif ($errorCode === '403'): ?>
                        <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>
                    <?php else: ?>
                        <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l10 18H2L12 3z"/><line x1="12" y1="10" x2="12" y2="15"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
                    <?php endif; ?>
                </div>
                
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--text-color);">
                    <?= htmlspecialchars($errorMessage) ?>
                </h1>
                
                <p style="font-size: 1.125rem; color: var(--text-muted); margin-bottom: 2rem;">
                    <?= htmlspecialchars($errorDescription) ?>
                </p>
                
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= getFullUrl() ?>" class="btn btn-primary">
                            Go to Dashboard
                        </a>
                        <a href="<?= getFullUrl('problems/create.php') ?>" class="btn btn-success">
                            Report Problem
                        </a>
                    <?php else: ?>
                        <a href="<?= getFullUrl('login.php') ?>" class="btn btn-primary">
                            Sign In
                        </a>
                    <?php endif; ?>
                    
                    <button onclick="history.back()" class="btn btn-secondary">
                        ← Go Back
                    </button>
                </div>
                
                <?php if ($showLogin && !$isLoggedIn): ?>
                    <div style="margin-top: 2rem;">
                        <div class="card" style="max-width: 400px; margin: 0 auto;">
                            <div class="card-body">
                                <h3>Need to Login?</h3>
                                <p>This page requires authentication. Please log in to continue.</p>
                                <a href="<?= getFullUrl('login.php') ?>" class="btn btn-primary w-100">
                                    Login to PSC Issues
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Help Section -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h3>Need Help?</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h4>Common Issues</h4>
                            <ul>
                                <li>Check your internet connection</li>
                                <li>Clear your browser cache</li>
                                <li>Try refreshing the page</li>
                                <li>Ensure you're logged in</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h4>Quick Actions</h4>
                            <ul>
                                <li><a href="<?= getFullUrl() ?>">Dashboard</a></li>
                                <li><a href="<?= getFullUrl('problems/create.php') ?>">Report Problem</a></li>
                                <li><a href="<?= getFullUrl('profile.php') ?>">Profile</a></li>
                                <li><a href="<?= getFullUrl('login.php') ?>">Login</a></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h4>Contact Support</h4>
                            <p>If you continue to experience issues, please contact your administrator.</p>
                            <p><strong>Presswick Sailing Club</strong><br>
                            IT Support</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <style>
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: -0.5rem;
    }
    .col-md-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
        padding: 0.5rem;
    }
    @media (max-width: 768px) {
        .col-md-4 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
    </style>
</body>
</html>