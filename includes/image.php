<?php
/**
 * GTAW Furniture Catalog - Image Processing
 * 
 * Handles image download, resize, and WebP conversion.
 * Uses PHP's GD library - no external dependencies.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'image.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Get the furniture images directory path
 */
function getImagesDir(): string
{
    return dirname(__DIR__) . '/images/furniture/';
}

/**
 * Get the web-accessible path for furniture images
 */
function getImagesWebPath(): string
{
    return '/images/furniture/';
}

/**
 * Check if GD library is available with required features
 */
function isGdAvailable(): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $info = gd_info();
    return !empty($info['WebP Support']);
}

/**
 * Download image from URL to a temporary file
 * 
 * @param string $url External image URL
 * @return string|null Path to temp file on success, null on failure
 */
function downloadImageToTemp(string $url): ?string
{
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        error_log("Image download: Invalid URL - {$url}");
        return null;
    }
    
    // Only allow http/https
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower($scheme), ['http', 'https'])) {
        error_log("Image download: Invalid scheme - {$scheme}");
        return null;
    }
    
    // Create temp file
    $tempFile = tempnam(sys_get_temp_dir(), 'gtaw_img_');
    if ($tempFile === false) {
        error_log("Image download: Failed to create temp file");
        return null;
    }
    
    // Download with timeout and size limit
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'max_redirects' => 3,
            'user_agent' => 'GTAW-Furniture-Catalog/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    
    // Use cURL if available for better control
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'GTAW-Furniture-Catalog/1.0',
            // Limit to 10MB
            CURLOPT_MAXFILESIZE => 10 * 1024 * 1024,
        ]);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200 || $imageData === false) {
            error_log("Image download: cURL failed - HTTP {$httpCode}, Error: {$error}");
            @unlink($tempFile);
            return null;
        }
    } else {
        // Fallback to file_get_contents
        $imageData = @file_get_contents($url, false, $context);
        if ($imageData === false) {
            error_log("Image download: file_get_contents failed for {$url}");
            @unlink($tempFile);
            return null;
        }
    }
    
    // Check minimum size (likely not a valid image if too small)
    if (strlen($imageData) < 100) {
        error_log("Image download: File too small - " . strlen($imageData) . " bytes");
        @unlink($tempFile);
        return null;
    }
    
    // Write to temp file
    if (file_put_contents($tempFile, $imageData) === false) {
        error_log("Image download: Failed to write temp file");
        @unlink($tempFile);
        return null;
    }
    
    return $tempFile;
}

/**
 * Load image from file using GD
 * 
 * @param string $filePath Path to image file
 * @return GdImage|null GD image resource on success
 */
function loadImage(string $filePath): ?GdImage
{
    $imageInfo = @getimagesize($filePath);
    if ($imageInfo === false) {
        error_log("Image load: Not a valid image - {$filePath}");
        return null;
    }
    
    $image = match ($imageInfo[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($filePath),
        IMAGETYPE_PNG => @imagecreatefrompng($filePath),
        IMAGETYPE_GIF => @imagecreatefromgif($filePath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($filePath),
        IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($filePath) : false,
        default => false,
    };
    
    if ($image === false) {
        error_log("Image load: Failed to create GD image - type {$imageInfo[2]}");
        return null;
    }
    
    return $image;
}

/**
 * Resize image while maintaining aspect ratio
 * 
 * @param GdImage $image Source image
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height (0 = auto based on width)
 * @return GdImage Resized image
 */
function resizeImage(GdImage $image, int $maxWidth, int $maxHeight = 0): GdImage
{
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculate new dimensions maintaining aspect ratio
    if ($maxHeight === 0) {
        // Only constrain by width
        if ($width <= $maxWidth) {
            return $image; // No resize needed
        }
        $newWidth = $maxWidth;
        $newHeight = (int) round($height * ($maxWidth / $width));
    } else {
        // Constrain by both dimensions
        $widthRatio = $maxWidth / $width;
        $heightRatio = $maxHeight / $height;
        $ratio = min($widthRatio, $heightRatio);
        
        if ($ratio >= 1) {
            return $image; // No resize needed
        }
        
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);
    }
    
    // Create new image with transparency support
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG/GIF
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);
    
    // High quality resampling
    imagecopyresampled(
        $resized, $image,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $width, $height
    );
    
    // Free original image memory
    imagedestroy($image);
    
    return $resized;
}

/**
 * Save image as WebP format
 * 
 * @param GdImage $image GD image resource
 * @param string $outputPath Output file path
 * @param int $quality WebP quality (0-100)
 * @return bool Success status
 */
function saveAsWebp(GdImage $image, string $outputPath, int $quality = 80): bool
{
    // Ensure directory exists
    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Image save: Failed to create directory - {$dir}");
            return false;
        }
    }
    
    // Convert to true color if needed (for indexed images)
    if (!imageistruecolor($image)) {
        $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);
        $image = $trueColor;
    }
    
    // Save as WebP
    $success = imagewebp($image, $outputPath, $quality);
    imagedestroy($image);
    
    if (!$success) {
        error_log("Image save: Failed to save WebP - {$outputPath}");
        return false;
    }
    
    return true;
}

/**
 * Generate unique filename for furniture image
 * 
 * @param int $furnitureId Furniture item ID
 * @param string $prefix Optional prefix
 * @return string Unique filename
 */
function generateImageFilename(int $furnitureId, string $prefix = 'furniture'): string
{
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    return "{$prefix}_{$furnitureId}_{$timestamp}_{$random}.webp";
}

/**
 * Process image from URL: download, resize, convert to WebP
 * 
 * This is the main function to call for image processing.
 * 
 * @param string $url External image URL
 * @param int $furnitureId Furniture item ID (for filename)
 * @param int $maxWidth Maximum image width (default 800px)
 * @param int $quality WebP quality 0-100 (default 80)
 * @return string|null Local web path on success, null on failure
 */
function processImageFromUrl(
    string $url,
    int $furnitureId,
    int $maxWidth = 800,
    int $quality = 80
): ?string {
    // Check GD availability
    if (!isGdAvailable()) {
        error_log("Image processing: GD library not available or missing WebP support");
        return null;
    }
    
    // Skip if already a local path
    if (str_starts_with($url, '/') || str_starts_with($url, './')) {
        return null;
    }
    
    // Skip processing for placeholder or local images
    if (str_contains($url, '/images/furniture/') || str_contains($url, 'placeholder')) {
        return null;
    }
    
    // Download to temp file
    $tempFile = downloadImageToTemp($url);
    if ($tempFile === null) {
        return null;
    }
    
    try {
        // Load image
        $image = loadImage($tempFile);
        if ($image === null) {
            return null;
        }
        
        // Resize if needed
        $image = resizeImage($image, $maxWidth);
        
        // Generate output path
        $filename = generateImageFilename($furnitureId);
        $outputPath = getImagesDir() . $filename;
        
        // Save as WebP
        if (!saveAsWebp($image, $outputPath, $quality)) {
            return null;
        }
        
        // Return web-accessible path
        return getImagesWebPath() . $filename;
        
    } finally {
        // Clean up temp file
        @unlink($tempFile);
    }
}

/**
 * Delete a furniture image file
 * 
 * @param string $imagePath Web path to the image
 * @return bool Success status
 */
function deleteImageFile(string $imagePath): bool
{
    // Only delete local furniture images
    if (!str_starts_with($imagePath, '/images/furniture/')) {
        return false;
    }
    
    $filename = basename($imagePath);
    $fullPath = getImagesDir() . $filename;
    
    if (file_exists($fullPath)) {
        return @unlink($fullPath);
    }
    
    return true;
}

/**
 * Clean up old/orphaned images not referenced in database
 * 
 * @param PDO $pdo Database connection
 * @return int Number of files deleted
 */
function cleanupOrphanedImages(PDO $pdo): int
{
    $imagesDir = getImagesDir();
    $webPath = getImagesWebPath();
    
    if (!is_dir($imagesDir)) {
        return 0;
    }
    
    // Get all image paths from database
    $stmt = $pdo->query('SELECT image_url FROM furniture WHERE image_url IS NOT NULL');
    $dbImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $dbFilenames = array_map('basename', $dbImages);
    
    $deleted = 0;
    $files = glob($imagesDir . '*.webp');
    
    foreach ($files as $file) {
        $filename = basename($file);
        if (!in_array($filename, $dbFilenames)) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

