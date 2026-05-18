<?php
/**
 * File Upload Utilities
 * Presswick Sailing Club Issue Reporting System
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Upload attachments for a problem (supports any file type)
 * Detects images for thumbnail purposes.
 */
function uploadProblemFiles($files, $problemId) {
    $uploadedFiles = [];
    $errors = [];

    try {
        if (!isset($files['images']) || !is_array($files['images']['name'])) {
            return ['files' => $uploadedFiles, 'errors' => $errors];
        }

        // Ensure base upload dir exists (flat structure)
        if (!file_exists(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                $errors[] = "Failed to create upload directory";
                return ['files' => $uploadedFiles, 'errors' => $errors];
            }
        }

        $fileCount = count($files['images']['name']);
        $uploadCount = 0;

        for ($i = 0; $i < $fileCount && $uploadCount < MAX_IMAGES_PER_PROBLEM; $i++) {
            try {
                $fileName = $files['images']['name'][$i];
                $fileTmpName = $files['images']['tmp_name'][$i];
                $fileSize = $files['images']['size'][$i];
                $fileError = $files['images']['error'][$i];

                if (empty($fileName)) continue;
                
                if ($fileError !== UPLOAD_ERR_OK) { 
                    $errors[] = "Upload error for file {$fileName} (Error code: {$fileError})"; 
                    continue; 
                }
                
                if ($fileSize > MAX_UPLOAD_SIZE) { 
                    $errors[] = "File {$fileName} is too large (max " . formatBytes(MAX_UPLOAD_SIZE) . ")"; 
                    continue; 
                }

                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                // If ALLOWED_EXTENSIONS not empty, enforce list
                if (!empty(ALLOWED_EXTENSIONS) && !in_array($fileExt, ALLOWED_EXTENSIONS)) {
                    $errors[] = "File {$fileName} has invalid extension.";
                    continue;
                }

                $isImage = false;
                $imageInfo = @getimagesize($fileTmpName);
                if ($imageInfo !== false) {
                    $isImage = true;
                }

                // Preserve original filename; handle collisions by appending (1), (2), etc. before extension
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $candidateName = $fileName; // default
                $counter = 1;
                while (file_exists(UPLOAD_DIR . $candidateName)) {
                    $candidateName = $baseName . ' (' . $counter . ').' . $fileExt;
                    $counter++;
                }
                $destination = UPLOAD_DIR . $candidateName;
                
                if (move_uploaded_file($fileTmpName, $destination)) {
                    $uploadedFiles[] = [
                        'original_name' => $fileName,
                        'filename' => $candidateName,
                        'path' => $destination,
                        'url' => UPLOAD_URL . $candidateName,
                        'size' => $fileSize,
                        'is_image' => $isImage,
                        'extension' => $fileExt
                    ];
                    $uploadCount++;
                } else {
                    $errors[] = "Failed to upload file {$fileName}";
                }
            } catch (Exception $fileError) {
                error_log("File upload error for {$fileName}: " . $fileError->getMessage());
                $errors[] = "Error processing file {$fileName}: " . $fileError->getMessage();
            }
        }
    } catch (Exception $e) {
        error_log("Upload function error: " . $e->getMessage());
        $errors[] = "Upload system error: " . $e->getMessage();
    }

    return ['files' => $uploadedFiles, 'errors' => $errors];
}

/**
 * Handle resolution file uploads specifically
 */
function handleResolutionFileUploads($files) {
    $uploadedFiles = [];
    $errors = [];

    try {
        if (!isset($files['resolution_files']) || !is_array($files['resolution_files']['name'])) {
            return ['files' => $uploadedFiles, 'errors' => $errors];
        }

        // Ensure upload directory exists
        if (!file_exists(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                $errors[] = "Failed to create upload directory";
                return ['files' => $uploadedFiles, 'errors' => $errors];
            }
        }

        $fileCount = count($files['resolution_files']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            try {
                $fileName = $files['resolution_files']['name'][$i];
                $fileTmpName = $files['resolution_files']['tmp_name'][$i];
                $fileSize = $files['resolution_files']['size'][$i];
                $fileError = $files['resolution_files']['error'][$i];

                if (empty($fileName)) continue;

                if ($fileError !== UPLOAD_ERR_OK) { 
                    $errors[] = "Upload error for file {$fileName} (Error code: {$fileError})"; 
                    continue; 
                }

                if ($fileSize > MAX_UPLOAD_SIZE) { 
                    $errors[] = "File {$fileName} is too large (max " . formatBytes(MAX_UPLOAD_SIZE) . ")"; 
                    continue; 
                }

                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // If ALLOWED_EXTENSIONS not empty, enforce list
                if (!empty(ALLOWED_EXTENSIONS) && !in_array($fileExt, ALLOWED_EXTENSIONS)) {
                    $errors[] = "File {$fileName} has invalid extension.";
                    continue;
                }

                // Check if it's an image
                $isImage = false;
                $imageInfo = @getimagesize($fileTmpName);
                if ($imageInfo !== false) {
                    $isImage = true;
                }

                // Generate unique filename with original name preservation - same logic as uploadProblemFiles
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $candidateName = $fileName;
                $counter = 1;
                while (file_exists(UPLOAD_DIR . $candidateName)) {
                    $candidateName = $baseName . ' (' . $counter . ').' . $fileExt;
                    $counter++;
                }
                $destination = UPLOAD_DIR . $candidateName;

                if (move_uploaded_file($fileTmpName, $destination)) {
                    $uploadedFiles[] = [
                        'original_name' => $fileName,
                        'filename' => $candidateName,
                        'path' => $destination,
                        'url' => UPLOAD_URL . $candidateName,
                        'size' => $fileSize,
                        'is_image' => $isImage,
                        'extension' => $fileExt
                    ];
                } else {
                    $errors[] = "Failed to upload file {$fileName}";
                }
            } catch (Exception $fileError) {
                error_log("Resolution file upload error for {$fileName}: " . $fileError->getMessage());
                $errors[] = "Error processing file {$fileName}: " . $fileError->getMessage();
            }
        }
    } catch (Exception $e) {
        error_log("Resolution upload function error: " . $e->getMessage());
        $errors[] = "Upload system error: " . $e->getMessage();
    }

    return ['files' => $uploadedFiles, 'errors' => $errors];
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Delete problem images
 */
function deleteProblemImages($problemId) {
    // With flat storage we cannot safely bulk delete by problem without metadata.
    return;
}

/**
 * Get problem images
 */
function getProblemAttachments($problemId, $json) {
    if (empty($json)) return [];
    $items = json_decode($json, true);
    if (!is_array($items)) return [];
    $result = [];
    foreach ($items as $item) {
        $filePath = UPLOAD_DIR . $item['filename'];
        if (file_exists($filePath)) {
            $result[] = $item + [
                'url' => UPLOAD_URL . $item['filename']
            ];
        }
    }
    return $result;
}

/**
 * Create thumbnail for image (optional feature for future enhancement)
 */
function createThumbnail($sourcePath, $destPath, $maxWidth = 150, $maxHeight = 150) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $imageType = $imageInfo[2];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $destPath, 80);
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $destPath);
            break;
        case IMAGETYPE_GIF:
            imagegif($newImage, $destPath);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($newImage);
    
    return true;
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($extension) {
    if (empty(ALLOWED_EXTENSIONS)) {
        // If no restrictions, allow common safe types
        $defaultAllowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
        return in_array(strtolower($extension), $defaultAllowed);
    }
    return in_array(strtolower($extension), ALLOWED_EXTENSIONS);
}

/**
 * Sanitize filename for safe storage
 */
function sanitizeFileName($filename) {
    // Remove dangerous characters
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
    // Remove multiple consecutive underscores
    $filename = preg_replace('/_+/', '_', $filename);
    // Trim underscores from start and end
    return trim($filename, '_');
}

/**
 * Generate unique filename to avoid collisions
 */
function generateUniqueFileName($baseName, $extension) {
    $candidateName = $baseName . '.' . $extension;
    $counter = 1;
    
    while (file_exists(UPLOAD_DIR . $candidateName)) {
        $candidateName = $baseName . '_' . $counter . '.' . $extension;
        $counter++;
    }
    
    return $candidateName;
}

/**
 * Handle file uploads for maintenance events
 * Uses the same structure and validation as problem files
 */
function handleMaintenanceFileUploads($files) {
    $uploadedFiles = [];
    $errors = [];

    try {
        error_log("handleMaintenanceFileUploads called with: " . print_r($files, true));
        
        // Ensure base upload dir exists
        if (!file_exists(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0777, true)) {
                $errors[] = "Failed to create upload directory";
                return ['files' => $uploadedFiles, 'errors' => $errors];
            }
        }
        
        // Check if upload directory is writable
        if (!is_writable(UPLOAD_DIR)) {
            $errors[] = "Upload directory is not writable: " . UPLOAD_DIR;
            return ['files' => $uploadedFiles, 'errors' => $errors];
        }

        $fileCount = count($files['name']);
        $uploadCount = 0;
        
        error_log("Processing {$fileCount} files for maintenance upload");

        for ($i = 0; $i < $fileCount && $uploadCount < MAX_IMAGES_PER_PROBLEM; $i++) {
            try {
                $fileName = $files['name'][$i];
                $fileTmpName = $files['tmp_name'][$i];
                $fileSize = $files['size'][$i];
                $fileError = $files['error'][$i];
                $fileType = $files['type'][$i] ?? '';

                if (empty($fileName)) continue;
                
                if ($fileError !== UPLOAD_ERR_OK) { 
                    $errors[] = "Upload error for file {$fileName} (Error code: {$fileError})"; 
                    continue; 
                }
                
                if ($fileSize > MAX_UPLOAD_SIZE) { 
                    $errors[] = "File {$fileName} is too large (max " . formatBytes(MAX_UPLOAD_SIZE) . ")"; 
                    continue; 
                }

                $pathInfo = pathinfo($fileName);
                $extension = strtolower($pathInfo['extension'] ?? '');
                
                // Validate file extension
                if (!isAllowedFileType($extension)) {
                    $errors[] = "File type not allowed: {$fileName}";
                    continue;
                }

                // Generate unique filename with smart duplicate handling
                $baseName = sanitizeFileName($pathInfo['filename']);
                $uniqueFileName = generateUniqueFileName($baseName, $extension);

                $filePath = UPLOAD_DIR . $uniqueFileName;

                if (move_uploaded_file($fileTmpName, $filePath)) {
                    // Determine if it's an image
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    
                    $fileInfo = [
                        'filename' => $uniqueFileName,
                        'original_name' => $fileName,
                        'size' => $fileSize,
                        'extension' => $extension,
                        'is_image' => $isImage,
                        'type' => $fileType,
                        'upload_time' => date('Y-m-d H:i:s')
                    ];

                    $uploadedFiles[] = $fileInfo;
                    $uploadCount++;
                } else {
                    $errors[] = "Failed to move uploaded file: {$fileName}";
                }
            } catch (Exception $e) {
                $errors[] = "Error processing file {$fileName}: " . $e->getMessage();
            }
        }

        return ['files' => $uploadedFiles, 'errors' => $errors];

    } catch (Exception $e) {
        error_log("Maintenance file upload error: " . $e->getMessage());
        $errors[] = "File upload failed: " . $e->getMessage();
        return ['files' => $uploadedFiles, 'errors' => $errors];
    }
}
?>