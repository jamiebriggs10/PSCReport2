<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireAdmin();

$pageTitle = 'Email Diagnostics';
include '../includes/header.php';

$diagnostics = [];
$pdo = getDbConnection();

// Check mail config
$mailConfig = null;
$configPath = __DIR__ . '/../config/mail.php';
if (file_exists($configPath)) {
    $mailConfig = require $configPath;
    $diagnostics[] = '[OK] Mail config file found';
} else {
    $diagnostics[] = '[FAIL] Mail config file missing (config/mail.php)';
}

// Check recipients
$recipients = getNotificationRecipients($pdo);
$diagnostics[] = count($recipients) > 0 ? '[OK] ' . count($recipients) . ' recipients configured' : '[FAIL] No recipients configured';

// Check urgency settings
$settings = getNotificationSettings($pdo);
$urgencyLevels = $settings['urgency_levels'] ?? [];
$diagnostics[] = count($urgencyLevels) > 0 ? '[OK] ' . count($urgencyLevels) . ' urgency levels enabled' : '[FAIL] No urgency filters enabled';

// Test basic connectivity if config exists
$smtpTest = 'Not tested';
if ($mailConfig && isset($mailConfig['host'], $mailConfig['port'])) {
    $host = $mailConfig['host'];
    $port = $mailConfig['port'];
    $timeout = 5;
    
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($fp) {
        $smtpTest = "[OK] Can connect to {$host}:{$port}";
        fclose($fp);
    } else {
        $smtpTest = "[FAIL] Cannot connect to {$host}:{$port} - {$errstr} ({$errno})";
    }
}

?>

<main class="main">
    <div class="container-sm">
        <div class="card">
            <div class="card-header">
                <h2>Email System Diagnostics</h2>
                <p class="text-muted">Check email notification system status</p>
            </div>
            <div class="card-body">
                <h3>System Status</h3>
                <ul class="list-unstyled">
                    <?php foreach ($diagnostics as $diag): ?>
                        <li style="margin-bottom:0.5rem; font-family:monospace;"><?= h($diag) ?></li>
                    <?php endforeach; ?>
                    <li style="margin-bottom:0.5rem; font-family:monospace;"><?= h($smtpTest) ?></li>
                </ul>

                <?php if ($mailConfig): ?>
                <h3>Mail Configuration</h3>
                <table class="table">
                    <tr><td>Driver</td><td><?= h($mailConfig['driver'] ?? 'mail') ?></td></tr>
                    <tr><td>Host</td><td><?= h($mailConfig['host'] ?? 'localhost') ?></td></tr>
                    <tr><td>Port</td><td><?= h($mailConfig['port'] ?? '25') ?></td></tr>
                    <tr><td>Encryption</td><td><?= h($mailConfig['encryption'] ?? 'none') ?></td></tr>
                    <tr><td>Username</td><td><?= h($mailConfig['username'] ?? 'not set') ?></td></tr>
                    <tr><td>Password</td><td><?= isset($mailConfig['password']) && strlen($mailConfig['password']) > 0 ? 'Set (' . strlen($mailConfig['password']) . ' chars)' : 'Not set' ?></td></tr>
                    <tr><td>From Email</td><td><?= h($mailConfig['from_email'] ?? 'not set') ?></td></tr>
                    <tr><td>Debug</td><td><?= !empty($mailConfig['debug']) ? 'Enabled' : 'Disabled' ?></td></tr>
                </table>
                <?php endif; ?>

                <h3>Recipients</h3>
                <?php if ($recipients): ?>
                    <ul>
                        <?php foreach ($recipients as $email): ?>
                            <li><?= h($email) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No recipients configured.</p>
                <?php endif; ?>

                <h3>Active Urgency Filters</h3>
                <?php if ($urgencyLevels): ?>
                    <ul>
                        <?php foreach ($urgencyLevels as $level): ?>
                            <li><?= getUrgencyBadgeHtml([$level]) ?> <?= h($level) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No urgency filters enabled - no automatic emails will be sent.</p>
                <?php endif; ?>

                <div class="form-actions" style="margin-top:2rem;">
                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>