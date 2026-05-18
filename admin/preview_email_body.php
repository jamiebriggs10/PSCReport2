<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
require_once '../config/database.php';
requireAdmin();

header('Content-Type: text/html; charset=UTF-8');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['problem'])) {
    echo '<p style="color: red;">Invalid preview request</p>';
    exit;
}

$sampleProblem = $input['problem'];

// Add some sample attachments for preview
$sampleAttachments = [
    ['name' => 'photo1.jpg', 'is_image' => true],
    ['name' => 'document.pdf', 'is_image' => false]
];

// Generate the email body
$emailHtml = buildProblemNotificationBody($sampleProblem, getFullUrl(), $sampleAttachments);

// Add some styling to make it look better in the preview box
$styledHtml = <<<HTML
<div style="background: white; padding: 15px; border-radius: 8px; max-width: 600px; font-family: Arial, sans-serif; line-height: 1.4;">
    $emailHtml
</div>
HTML;

echo $styledHtml;