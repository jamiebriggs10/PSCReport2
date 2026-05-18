<?php
/**
 * Notification helpers
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

/**
 * Get active notification recipients (updated for new system)
 */
function getNotificationRecipients(PDO $pdo = null) {
    $pdo = $pdo ?: getDbConnection();
    
    // Check if new preferences table exists
    try {
        $stmt = $pdo->query("SELECT email FROM email_notification_preferences WHERE is_active = 1 ORDER BY email ASC");
        $newRecipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($newRecipients)) {
            return $newRecipients;
        }
    } catch (Exception $e) {
        // Table doesn't exist yet, fall back to old system
        error_log("New notification system not available, using legacy: " . $e->getMessage());
    }
    
    // Fallback to old system
    $stmt = $pdo->query("SELECT email FROM notification_recipients WHERE is_active = 1 ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Get notification recipients for a specific problem (new function)
 */
function getNotificationRecipientsForProblem(array $problem, PDO $pdo = null) {
    $pdo = $pdo ?: getDbConnection();
    
    try {
        // Check if new preferences table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'email_notification_preferences'");
        if (!$stmt->fetch()) {
            // Fall back to old system
            return getNotificationRecipients($pdo);
        }
        
        $problemUrgencies = array_map('trim', explode(',', $problem['urgency_tags'] ?? ''));
        $problemCategoryId = $problem['problem_category_id'] ?? null;
        
        // Get all active email preferences
        $stmt = $pdo->query("
            SELECT email, problem_categories, urgency_levels 
            FROM email_notification_preferences 
            WHERE is_active = 1
        ");
        $allPreferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $matchingEmails = [];
        
        foreach ($allPreferences as $pref) {
            $emailCategories = json_decode($pref['problem_categories'] ?? '[]', true) ?: [];
            $emailUrgencies = json_decode($pref['urgency_levels'] ?? '[]', true) ?: [];
            
            // Check if problem category matches
            $categoryMatch = false;
            if (empty($emailCategories)) {
                // No categories selected means no notifications
                continue;
            } elseif ($problemCategoryId && in_array($problemCategoryId, $emailCategories)) {
                $categoryMatch = true;
            }
            
            // Check if urgency level matches
            $urgencyMatch = false;
            if (empty($emailUrgencies)) {
                // No urgencies selected means no notifications
                continue;
            } else {
                $urgencyMatch = !empty(array_intersect($problemUrgencies, $emailUrgencies));
            }
            
            // Both must match for this email to receive notification
            if ($categoryMatch && $urgencyMatch) {
                $matchingEmails[] = $pref['email'];
            }
        }
        
        return $matchingEmails;
        
    } catch (Exception $e) {
        error_log("Error getting recipients for problem: " . $e->getMessage());
        // Fall back to old system
        return getNotificationRecipients($pdo);
    }
}

/**
 * Save recipients list (replace existing)
 */
function saveNotificationRecipients(array $emails, PDO $pdo = null) {
    $pdo = $pdo ?: getDbConnection();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM notification_recipients');
        $stmt = $pdo->prepare('INSERT INTO notification_recipients (email) VALUES (?)');
        foreach ($emails as $email) {
            $email = trim($email);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt->execute([$email]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('saveNotificationRecipients error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get notification settings (urgency list)
 */
function getNotificationSettings(PDO $pdo = null) {
    $pdo = $pdo ?: getDbConnection();
    $stmt = $pdo->query("SELECT urgency_levels, problem_categories FROM notification_settings WHERE id = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['urgency_levels' => [], 'problem_categories' => []];
    }
    $levels = array_filter(array_map('trim', explode(',', $row['urgency_levels'] ?: '')));
    $categories = array_filter(array_map('trim', explode(',', $row['problem_categories'] ?: '')));
    return ['urgency_levels' => $levels, 'problem_categories' => $categories];
}

/**
 * Save notification settings
 */
function saveNotificationSettings(array $settings, PDO $pdo = null) {
    $pdo = $pdo ?: getDbConnection();
    
    $updates = [];
    $params = [];
    
    if (isset($settings['urgency_levels'])) {
        $levels = implode(',', $settings['urgency_levels']);
        $updates[] = 'urgency_levels = ?';
        $params[] = $levels;
    }
    
    if (isset($settings['problem_categories'])) {
        $categories = implode(',', $settings['problem_categories']);
        $updates[] = 'problem_categories = ?';
        $params[] = $categories;
    }
    
    if (empty($updates)) return true;
    
    $sql = "UPDATE notification_settings SET " . implode(', ', $updates) . " WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Build email body for notification
 */
function buildProblemNotificationBody(array $problem, $baseUrl = null, array $attachments = []) {
    $baseUrl = $baseUrl ?? getFullUrl();
    $title = h($problem['title'] ?? 'New Problem Reported');
    $details = nl2br(h($problem['details'] ?? ''));
    $urgencies = h($problem['urgency_tags'] ?? '');
    
    // Get problem category info if available
    $categoryInfo = '';
    if (!empty($problem['problem_category_id'])) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT name, color FROM problem_categories WHERE id = ?");
            $stmt->execute([$problem['problem_category_id']]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($category) {
                $categoryInfo = h($category['name']);
            }
        } catch (Exception $e) {
            error_log("Category lookup error in notification: " . $e->getMessage());
        }
    }
    
    $link = $baseUrl . '/problems/view.php?id=' . urlencode($problem['id']);
    $dashboardLink = $baseUrl;
    if (!empty($dashboardLink) && !str_ends_with($dashboardLink, '/')) {
        $dashboardLink .= '/';
    }
    $dashboardLink .= 'index.php';
    $reportedAt = h($problem['created_at'] ?? '');
    
    // Build attachment notice if files exist
    $attachmentNotice = '';
    if (!empty($attachments)) {
        $fileCount = count($attachments);
        $imageCount = count(array_filter($attachments, fn($a) => !empty($a['is_image'])));
        $fileTypes = [];
        
        if ($imageCount > 0) {
            $fileTypes[] = $imageCount . ' image' . ($imageCount > 1 ? 's' : '');
        }
        $otherFiles = $fileCount - $imageCount;
        if ($otherFiles > 0) {
            $fileTypes[] = $otherFiles . ' file' . ($otherFiles > 1 ? 's' : '');
        }
        
        $typeText = implode(' and ', $fileTypes);
        $attachmentNotice = "<p style='margin:10px 0;font-family:Arial,sans-serif;'><strong>{$typeText} attached &mdash; click \"View This Problem\" to see them.</strong></p>";
    }
    
    // Get other unresolved issues for context
    $otherIssues = getOtherUnresolvedIssues($problem['id'] ?? 0);
    $otherIssuesTable = '';
    if (!empty($otherIssues)) {
        $otherIssuesTable = '<p style="margin:20px 0 10px;font-family:Arial,sans-serif;"><a href="' . $dashboardLink . '" style="color:#0d6efd;text-decoration:none;">View All Problems Dashboard</a></p>';
        $otherIssuesTable .= '<h3 style="margin:10px 0 10px;font-family:Arial,sans-serif;color:#666;">Other Open Issues</h3>';
        $otherIssuesTable .= '<table style="width:100%;border-collapse:collapse;margin:10px 0;font-family:Arial,sans-serif;font-size:12px;">';
        $otherIssuesTable .= '<tr style="background:#f8f9fa;"><th style="padding:8px;border:1px solid #ddd;text-align:left;">Title</th><th style="padding:8px;border:1px solid #ddd;text-align:left;">Urgency</th><th style="padding:8px;border:1px solid #ddd;text-align:left;">Reported</th></tr>';
        foreach ($otherIssues as $issue) {
            $issueTitle = h($issue['title']);
            $issueUrgency = h($issue['urgency_tags']);
            $issueDate = h(date('M j', strtotime($issue['created_at'])));
            $issueLink = $baseUrl . '/problems/view.php?id=' . urlencode($issue['id']);
            $otherIssuesTable .= "<tr><td style='padding:8px;border:1px solid #ddd;'><a href='{$issueLink}' style='color:#0d6efd;text-decoration:none;'>{$issueTitle}</a></td><td style='padding:8px;border:1px solid #ddd;'>{$issueUrgency}</td><td style='padding:8px;border:1px solid #ddd;'>{$issueDate}</td></tr>";
        }
        $otherIssuesTable .= '</table>';
    }
    
    $categoryLine = $categoryInfo ? "<p style=\"margin:4px 0;font-family:Arial,sans-serif;\"><strong>Type:</strong> {$categoryInfo}</p>" : '';
    
    return <<<HTML
<h2 style="margin:0 0 10px;font-family:Arial,sans-serif;">New Problem Reported</h2>
<p style="margin:4px 0;font-family:Arial,sans-serif;"><strong>Title:</strong> {$title}</p>
<p style="margin:4px 0;font-family:Arial,sans-serif;"><strong>Urgency:</strong> {$urgencies}</p>
{$categoryLine}
<p style="margin:4px 0;font-family:Arial,sans-serif;"><strong>Reported At:</strong> {$reportedAt}</p>
<p style="margin:10px 0;font-family:Arial,sans-serif;">{$details}</p>
{$attachmentNotice}
<p style="margin:15px 0;font-family:Arial,sans-serif;"><a href="{$link}" style="background:#0d6efd;color:#fff;padding:10px 14px;text-decoration:none;border-radius:4px;display:inline-block;">View This Problem</a></p>
{$otherIssuesTable}
<p style="font-size:12px;color:#666;font-family:Arial,sans-serif;margin-top:20px;">This message was generated by the PSC Issue Reporting System.</p>
HTML;
}

/**
 * Get other unresolved issues for email context (excluding current problem)
 */
function getOtherUnresolvedIssues($excludeId = 0, $limit = 10) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT id, title, urgency_tags, created_at 
            FROM problems 
            WHERE status = 'OPEN' AND id != ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$excludeId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('getOtherUnresolvedIssues error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Send notification emails (updated for new per-email preferences)
 */
function sendProblemNotification(array $problem, PDO $pdo = null, array $options = []) {
    $pdo = $pdo ?: getDbConnection();
    
    // Get recipients based on their individual preferences
    if (!empty($options['bypassFilters'])) {
        // For test emails, use all recipients
        $recipients = getNotificationRecipients($pdo);
    } else {
        // Use new preference-based recipient selection
        $recipients = getNotificationRecipientsForProblem($problem, $pdo);
    }
    
    $diagnostics = [ 'recipients' => count($recipients) ];
    if (empty($recipients)) {
        $options['__last_error'] = 'No matching recipients for this problem type and urgency level';
        return false; // nothing to do
    }

    // For the new system, we don't need the old global filtering
    // since filtering is done per-recipient in getNotificationRecipientsForProblem()
    
    $subject = '[PSC Issues] ' . ($problem['title'] ?? 'Untitled');
    
    // Get attachment info if available
    $attachmentInfo = [];
    if (!empty($problem['id'])) {
        try {
            $stmt = $pdo->prepare('SELECT image_urls FROM problems WHERE id = ?');
            $stmt->execute([$problem['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['image_urls'])) {
                require_once __DIR__ . '/upload.php';
                $attachmentInfo = getProblemAttachments($problem['id'], $row['image_urls']);
            }
        } catch (Exception $e) {
            error_log('Failed to get attachment info: ' . $e->getMessage());
        }
    }
    
    $body = buildProblemNotificationBody($problem, null, $attachmentInfo);

    // Load mail config if present
    $mailConfig = null;
    $configPath = __DIR__ . '/../config/mail.php';
    if (file_exists($configPath)) {
        $mailConfig = require $configPath;
    } else {
        // fallback to dist for defaults
        $distPath = __DIR__ . '/../config/mail.dist.php';
        if (file_exists($distPath)) $mailConfig = require $distPath;
    }

    if (!$mailConfig || ($mailConfig['driver'] ?? 'mail') === 'mail') {
        // Native mail fallback
        $headers = "MIME-Version: 1.0\r\n" .
                   "Content-type:text/html;charset=UTF-8\r\n" .
                   'From: ' . ($mailConfig['from_name'] ?? 'PSC Issues') . ' <' . ($mailConfig['from_email'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) . ">\r\n" .
                   'Date: ' . date('r') . "\r\n" .
                   'Message-ID: <' . uniqid('psc-') . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
        $allOk = true;
        foreach ($recipients as $email) {
            if (!mail($email, $subject, $body, $headers)) {
                $allOk = false;
                error_log('Failed to send notification to ' . $email);
            }
        }
        return $allOk;
    }

    if (($mailConfig['driver'] ?? 'smtp') === 'smtp') {
        return smtpSendMultiple($recipients, $subject, $body, $mailConfig);
    }
    return false;
}

/**
 * Minimal SMTP sender (supports LOGIN / STARTTLS for Gmail). Not a full PHPMailer replacement.
 */
function smtpSendMultiple(array $recipients, string $subject, string $htmlBody, array $cfg) {
    $host = $cfg['host'] ?? 'smtp.gmail.com';
    $port = $cfg['port'] ?? 587;
    $timeout = (int)($cfg['timeout'] ?? 15);
    $username = $cfg['username'] ?? '';
    $password = $cfg['password'] ?? '';
    $encryption = strtolower($cfg['encryption'] ?? 'tls');
    $fromEmail = $cfg['from_email'] ?? $username;
    $fromName = $cfg['from_name'] ?? 'PSC Issues';
    $debug = !empty($cfg['debug']);

    if (!$username || !$password) {
        error_log('SMTP config missing username or password');
        return false;
    }

    $contextOptions = [];
    if ($encryption === 'ssl') {
        $host = 'ssl://' . preg_replace('/^ssl:\/\//','',$host);
    } else {
        $contextOptions['ssl'] = [ 'verify_peer' => false, 'verify_peer_name' => false ];
    }
    $context = stream_context_create($contextOptions);
    $fp = @stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, $timeout);

    $log = function($msg) use ($debug) { if ($debug) error_log('[SMTP] ' . $msg); };
    $read = function() use ($fp, $log) { $resp = ''; while ($line = fgets($fp, 515)) { $resp .= $line; if (preg_match('/^\d{3} /',$line)) break; } $log('S: ' . trim($resp)); return $resp; };
    $write = function($data) use ($fp, $log) { $log('C: ' . trim($data)); fwrite($fp, $data . "\r\n"); };

    $read();
    $write('EHLO localhost'); $ehlo = $read();
    if ($encryption === 'tls') { $write('STARTTLS'); $read(); if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { error_log('Failed STARTTLS'); return false; } $write('EHLO localhost'); $read(); }
    $write('AUTH LOGIN'); $read();
    $write(base64_encode($username)); $read();
    $write(base64_encode($password)); $authResp = $read();
    if (!preg_match('/235/', $authResp)) { error_log('SMTP auth failed'); return false; }

    $allOk = true;
    foreach ($recipients as $to) {
        $write('MAIL FROM: <' . $fromEmail . '>'); $read();
        $write('RCPT TO: <' . $to . '>'); $rcptResp = $read();
        if (!preg_match('/250/',$rcptResp)) { $allOk = false; continue; }
        $write('DATA'); $read();
        $boundary = 'b' . bin2hex(random_bytes(6));
        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . uniqid('psc-') . '@' . (parse_url($host, PHP_URL_HOST) ?: 'localhost') . '>';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $msg = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $write($msg); $dataResp = $read();
        if (!preg_match('/250/',$dataResp)) { $allOk = false; }
    }
    $write('QUIT'); $read();
    fclose($fp);
    return $allOk;
}
?>