<?php
declare(strict_types=1);

/**
 * CDN Functions
 * Image optimization and CDN support
 */

/**
 * Upload image to CDN
 */
function uploadToCDN(string $filePath, string $destination): ?string {
    // This is a placeholder for CDN integration
    // Implement actual CDN upload logic (e.g., AWS S3, Cloudinary, etc.)
    
    try {
        $cdnEnabled = (bool)getSetting('cdn_enabled', false);
        
        if (!$cdnEnabled) {
            return $filePath; // Return local path if CDN is disabled
        }
        
        $cdnType = getSetting('cdn_type', 'local');
        
        switch ($cdnType) {
            case 's3':
                return uploadToS3($filePath, $destination);
                
            case 'cloudinary':
                return uploadToCloudinary($filePath, $destination);
                
            default:
                return $filePath;
        }
    } catch (Exception $e) {
        error_log("CDN upload error: " . $e->getMessage());
        return null;
    }
}

/**
 * Upload to AWS S3 (placeholder)
 */
function uploadToS3(string $filePath, string $destination): ?string {
    // Implement AWS S3 upload logic
    // This requires AWS SDK
    return null;
}

/**
 * Upload to Cloudinary (placeholder)
 */
function uploadToCloudinary(string $filePath, string $destination): ?string {
    // Implement Cloudinary upload logic
    return null;
}

/**
 * Optimize image
 */
function optimizeImage(string $filePath, array $options = []): bool {
    try {
        $quality = $options['quality'] ?? 85;
        $maxWidth = $options['max_width'] ?? 1920;
        $maxHeight = $options['max_height'] ?? 1080;
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }
        
        $mime = $imageInfo['mime'];
        
        // Create image resource
        $image = match($mime) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            'image/gif' => imagecreatefromgif($filePath),
            'image/webp' => imagecreatefromwebp($filePath),
            default => null
        };
        
        if (!$image) {
            return false;
        }
        
        // Get dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calculate new dimensions if image is too large
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($mime === 'image/png' || $mime === 'image/gif') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        
        // Save optimized image
        $result = match($mime) {
            'image/jpeg' => imagejpeg($image, $filePath, $quality),
            'image/png' => imagepng($image, $filePath, (int)(9 - ($quality / 10))),
            'image/gif' => imagegif($image, $filePath),
            'image/webp' => imagewebp($image, $filePath, $quality),
            default => false
        };
        
        imagedestroy($image);
        
        return $result;
    } catch (Exception $e) {
        error_log("Image optimization error: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert image to WebP
 */
function convertToWebP(string $filePath): ?string {
    try {
        if (!function_exists('imagewebp')) {
            return null;
        }
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }
        
        $mime = $imageInfo['mime'];
        
        $image = match($mime) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            'image/gif' => imagecreatefromgif($filePath),
            default => null
        };
        
        if (!$image) {
            return null;
        }
        
        $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $filePath);
        
        if (imagewebp($image, $webpPath, 85)) {
            imagedestroy($image);
            return $webpPath;
        }
        
        imagedestroy($image);
        return null;
    } catch (Exception $e) {
        error_log("WebP conversion error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get CDN URL for file
 */
function getCDNUrl(string $filePath): string {
    $cdnEnabled = (bool)getSetting('cdn_enabled', false);
    
    if (!$cdnEnabled) {
        return $filePath;
    }
    
    $cdnDomain = getSetting('cdn_domain', '');
    
    if (empty($cdnDomain)) {
        return $filePath;
    }
    
    // Remove leading slash from file path
    $filePath = ltrim($filePath, '/');
    
    return rtrim($cdnDomain, '/') . '/' . $filePath;
}

/**
 * Purge CDN cache
 */
function purgeCDNCache(array $urls): bool {
    // Implement CDN cache purging logic
    // This depends on the CDN provider
    return true;
}
