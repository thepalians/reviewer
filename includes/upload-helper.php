<?php
/**
 * Fast Image Upload Helper
 * Optimized for quick uploads to palians.com/image-host
 */

/**
 * Upload image to palians.com image host with optimization
 * @param array $file - $_FILES array element
 * @param int $maxWidth - Maximum width for resize (default 1200px)
 * @param int $quality - JPEG quality (default 85)
 * @return array - ['success' => bool, 'url' => string, 'error' => string]
 */
function uploadImageFast($file, $maxWidth = 1200, $quality = 85) {
    // Validate file
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        return [
            'success' => false, 
            'error' => $errorMessages[$file['error']] ?? 'Upload error'
        ];
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size must be less than 5MB'];
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, GIF, WebP allowed'];
    }
    
    // Compress image before upload for faster transfer
    $compressedFile = compressImageForUpload($file['tmp_name'], $mimeType, $maxWidth, $quality);
    $uploadPath = $compressedFile ?: $file['tmp_name'];
    
    // Upload to image host with optimized cURL
    $result = uploadToPaliansHost($uploadPath, $file['name'], $mimeType);
    
    // Clean up temp compressed file
    if ($compressedFile && file_exists($compressedFile)) {
        @unlink($compressedFile);
    }
    
    return $result;
}

/**
 * Compress image for faster upload
 */
function compressImageForUpload($sourcePath, $mimeType, $maxWidth = 1200, $quality = 85) {
    // Get image info
    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) return null;
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    // Skip if already small enough
    if ($width <= $maxWidth && filesize($sourcePath) < 500000) {
        return null;
    }
    
    // Create image resource
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($sourcePath);
            break;
        default:
            return null;
    }
    
    if (!$image) return null;
    
    // Calculate new dimensions
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = intval($height * ($maxWidth / $width));
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    // Resize if needed
    if ($newWidth !== $width) {
        $resized = imagescale($image, $newWidth, $newHeight);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Save to temp file
    $tempFile = sys_get_temp_dir() . '/upload_' . uniqid() . '.jpg';
    
    // Always save as JPEG for smaller size
    $result = imagejpeg($image, $tempFile, $quality);
    imagedestroy($image);
    
    if ($result && file_exists($tempFile)) {
        return $tempFile;
    }
    
    return null;
}

/**
 * Upload to palians.com image host with optimized settings
 */
function uploadToPaliansHost($filePath, $originalName, $mimeType) {
    $cfile = new CURLFile($filePath, $mimeType, $originalName);
    
    $ch = curl_init();
    
    // Optimized cURL settings for faster upload
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://palians.com/image-host/upload.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => $cfile],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'ReviewerApp/1.0',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        // Speed optimizations
        CURLOPT_TCP_FASTOPEN => true,
        CURLOPT_TCP_NODELAY => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $uploadTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    // Log upload time for debugging
    error_log("Image upload completed in {$uploadTime}s - HTTP: $httpCode");
    
    if ($httpCode === 200 && !empty($response)) {
        $lines = explode("\n", trim($response));
        if (!empty($lines[0]) && filter_var($lines[0], FILTER_VALIDATE_URL)) {
            return ['success' => true, 'url' => $lines[0]];
        }
    }
    
    return [
        'success' => false, 
        'error' => $error ?: "Upload failed (HTTP $httpCode)"
    ];
}

/**
 * Validate image file without uploading
 */
function validateImageFile($file) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'No file uploaded'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['valid' => false, 'error' => 'File too large (max 5MB)'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    return ['valid' => true];
}
