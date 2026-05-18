<?php
/**
 * Header Include — common HTML head and navigation
 */

if (!isset($pageTitle)) {
    $pageTitle = 'PSC Issues';
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Presswick Sailing Club Issue Reporting System">
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <title><?= h($pageTitle) ?> - <?= APP_NAME ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= getFullUrl('manifest.json') ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?= getFullUrl('logo.jpg') ?>">

    <!-- PWA meta tags for mobile -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PSC Issues">
    <link rel="apple-touch-icon" href="<?= getFullUrl('logo.jpg') ?>">

    <!-- Theme color matches refined header -->
    <meta name="theme-color" content="#0f2540">

    <!-- PWA Install & Service Worker -->
    <script src="<?= getFullUrl('assets/js/install-app.js') ?>" defer></script>
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('<?= getFullUrl('service-worker.js') ?>').catch(() => {});
        });
      }
    </script>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="<?= getFullUrl() ?>" class="logo">
                    <span class="logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v18"/>
                            <path d="M12 6l7 12H5z"/>
                            <path d="M4 21h16"/>
                        </svg>
                    </span>
                    <span class="logo-text"><?= APP_NAME ?></span>
                </a>

                <?php if ($user): ?>
                <div class="header-menu" style="display: flex; align-items: center; gap: 12px;">
                    <button type="button" class="btn btn-sm" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #fff; box-shadow: none;" onclick="showInstallModal()" title="Add this app to your home screen">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        Add as App
                    </button>
                    <button type="button" class="menu-button" aria-label="Open menu">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="4" y1="7" x2="20" y2="7"/>
                            <line x1="4" y1="12" x2="20" y2="12"/>
                            <line x1="4" y1="17" x2="20" y2="17"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-identity">
                            <div class="dropdown-avatar"><?= h(strtoupper(substr($user['full_name'] ?? '?', 0, 1))) ?></div>
                            <div class="dropdown-identity-text">
                                <strong><?= h($user['full_name']) ?></strong>
                                <small><?= h($user['role']) ?></small>
                            </div>
                        </div>
                        <a href="<?= getFullUrl() ?>" class="dropdown-link">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 11l9-8 9 8"/>
                                <path d="M5 10v10h14V10"/>
                            </svg>
                            Dashboard
                        </a>
                        <a href="<?= getFullUrl('profile.php') ?>" class="dropdown-link">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>
                            </svg>
                            Profile
                        </a>
                        <a href="<?= getFullUrl('change_password.php') ?>" class="dropdown-link">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4" y="11" width="16" height="10" rx="2"/>
                                <path d="M8 11V8a4 4 0 018 0v3"/>
                            </svg>
                            Change Password
                        </a>
                        <a href="<?= getFullUrl('maintenance.php') ?>" class="dropdown-link">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="16" rx="2"/>
                                <path d="M16 3v4M8 3v4M3 11h18"/>
                            </svg>
                            Maintenance Calendar
                        </a>
                        <?php if (isAdmin()): ?>
                            <a href="<?= getFullUrl('admin/dashboard.php') ?>" class="dropdown-link">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 3l9 4v5c0 5-3.5 8.5-9 10-5.5-1.5-9-5-9-10V7l9-4z"/>
                                </svg>
                                Admin Dashboard
                            </a>
                        <?php endif; ?>
                        <a href="<?= getFullUrl('logout.php') ?>" class="dropdown-link dropdown-link-danger">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 17l5-5-5-5"/>
                                <path d="M20 12H9"/>
                                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php
    // Show password change requirement notice
    if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] && basename($_SERVER['PHP_SELF']) !== 'change_password.php'):
    ?>
    <div class="alert alert-warning" style="margin: 0; border-radius: 0;">
        <div class="container">
            <strong>Password Change Required:</strong>
            You must <a href="<?= getFullUrl('change_password.php') ?>">change your password</a> before continuing.
        </div>
    </div>
    <?php endif; ?>

    <?php
    // Show success/error messages from session
    if (isset($_SESSION['flash_message'])):
        $flashType = $_SESSION['flash_type'] ?? 'info';
        $flashMessage = $_SESSION['flash_message'];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    ?>
    <div class="alert alert-<?= h($flashType) ?>" style="margin: 0; border-radius: 0;">
        <div class="container">
            <?= h($flashMessage) ?>
        </div>
    </div>
    <?php endif; ?>
