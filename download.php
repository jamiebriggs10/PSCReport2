<?php
/**
 * Secure file download / inline view endpoint
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/config/database.php';

// Require authentication
requireAuth();

$problemId = isset($_GET['problem']) ? (int)$_GET['problem'] : 0;
$fileParam = $_GET['file'] ?? '';

if ($problemId <= 0 || !$fileParam) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

// Basic filename whitelist (no path separators)
if (preg_match('/[\\\/]/', $fileParam)) {
    http_response_code(400);
    echo 'Invalid filename';
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, image_urls FROM problems WHERE id = ?');
    $stmt->execute([$problemId]);
    $problem = $stmt->fetch();
    if (!$problem) {
        http_response_code(404);
        echo 'Problem not found';
        exit;
    }
    $attachments = json_decode($problem['image_urls'] ?? '[]', true) ?: [];
    $meta = null;
    foreach ($attachments as $att) {
        if (isset($att['filename']) && $att['filename'] === $fileParam) { $meta = $att; break; }
    }
    if (!$meta) {
        http_response_code(404);
        echo 'File metadata not found';
        exit;
    }
    $filePath = UPLOAD_DIR . $fileParam; // flat storage
    if (!is_file($filePath)) {
        http_response_code(404);
        echo 'File missing';
        exit;
    }

    // Determine mime type
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $inlineExt = ['pdf','png','jpg','jpeg','gif','webp','mp4','webm'];
    $mimeMap = [
        'pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp',
        'mp4'=>'video/mp4','webm'=>'video/webm','txt'=>'text/plain','csv'=>'text/csv','json'=>'application/json'
    ];
    $finfoMime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $finfoMime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
        }
    }
    $mime = $finfoMime ?: ($mimeMap[$ext] ?? 'application/octet-stream');

    // Decide disposition
    $disposition = in_array($ext, $inlineExt, true) ? 'inline' : 'attachment';
    $downloadName = $meta['original_name'] ?? $fileParam;
    // Sanitize header filename
    $downloadName = preg_replace('/[^A-Za-z0-9._() \-]/','_', $downloadName);

    // Send headers
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');

    // Stream file
    $chunkSize = 8192;
    $fh = fopen($filePath, 'rb');
    if ($fh) {
        while (!feof($fh)) {
            echo fread($fh, $chunkSize);
        }
        fclose($fh);
    }
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Server error';
    exit;
}
?>