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
 * Circular dependency prevention
 * 
 * This file conditionally requires functions.php. functions.php must NEVER
 * require image.php to avoid circular dependencies.
 */
if (!function_exists('updateFurnitureImage')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Get the furniture images directory path
 */
function getImagesDir(): string
{
    return __DIR__ . '/../images/furniture/';
}

/**
 * Get the web-accessible path for furniture images
 */
function getImagesWebPath(): string
{
    return '/images/furniture/';
}

/**
 * Check if an image URL is already a local image (should skip processing)
 * 
 * @param string $url Image URL to check
 * @return bool True if image is local and should not be processed
 */
function isLocalImage(string $url): bool
{
    return str_starts_with($url, '/images/furniture/') 
        || str_starts_with($url, '/')
        || str_starts_with($url, './')
        || str_contains($url, 'placeholder');
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
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        error_log("Image download: Invalid URL - {$url}");
        return null;
    }
    
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower($scheme), ['http', 'https'])) {
        error_log("Image download: Invalid scheme - {$scheme}");
        return null;
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'gtaw_img_');
    if ($tempFile === false) {
        error_log("Image download: Failed to create temp file");
        return null;
    }
    
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
            CURLOPT_MAXFILESIZE => MAX_IMAGE_SIZE,
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
    
    if (strlen($imageData) < 100) {
        error_log("Image download: File too small - " . strlen($imageData) . " bytes");
        @unlink($tempFile);
        return null;
    }
    
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
    
    if ($maxHeight === 0) {
        if ($width <= $maxWidth) {
            return $image;
        }
        $newWidth = $maxWidth;
        $newHeight = (int) round($height * ($maxWidth / $width));
    } else {
        $widthRatio = $maxWidth / $width;
        $heightRatio = $maxHeight / $height;
        $ratio = min($widthRatio, $heightRatio);
        
        if ($ratio >= 1) {
            return $image;
        }
        
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);
    }
    
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);
    
    imagecopyresampled(
        $resized, $image,
        0, 0, 0, 0,
        $newWidth,         $newHeight,
        $width, $height
    );
    
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
    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Image save: Failed to create directory - {$dir}");
            return false;
        }
    }
    
    if (!imageistruecolor($image)) {
        $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);
        $image = $trueColor;
    }
    
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
 * ImageProcessor service class
 * 
 * Encapsulates image processing operations for better organization and testability.
 */
class ImageProcessor
{
    /**
     * Process image from URL: download, resize, convert to WebP
     * 
     * @param string $url External image URL
     * @param int $furnitureId Furniture item ID (for filename)
     * @param int $maxWidth Maximum image width (default 800px)
     * @param int $quality WebP quality 0-100 (default 80)
     * @return string|null Local web path on success, null on failure
     */
    public function processFromUrl(
        string $url,
        int $furnitureId,
        int $maxWidth = 800,
        int $quality = 80
    ): ?string {
        if (!isGdAvailable()) {
            error_log("Image processing: GD library not available or missing WebP support");
            return null;
        }
        
        if (isLocalImage($url)) {
            return null;
        }
        
        $tempFile = downloadImageToTemp($url);
        if ($tempFile === null) {
            return null;
        }
        
        try {
            $image = loadImage($tempFile);
            if ($image === null) {
                return null;
            }
            
            $image = resizeImage($image, $maxWidth);
            
            $filename = generateImageFilename($furnitureId);
            $outputPath = getImagesDir() . $filename;
            
            if (!saveAsWebp($image, $outputPath, $quality)) {
                return null;
            }
            
            return getImagesWebPath() . $filename;
            
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Process furniture image: download, process, update database, and optionally delete old image
     * 
     * This is a high-level helper method that combines image processing with database updates.
     * Use this instead of calling processFromUrl() and updateFurnitureImage() separately.
     * 
     * @param PDO $pdo Database connection
     * @param int $furnitureId Furniture item ID
     * @param string|null $imageUrl Image URL to process (null to skip)
     * @param string|null $oldImageUrl Optional old image URL to delete if changed
     * @return string|null Local web path on success, null on failure or skip
     */
    public function processFurnitureImage(
        PDO $pdo,
        int $furnitureId,
        ?string $imageUrl,
        ?string $oldImageUrl = null
    ): ?string {
        if (!$imageUrl) {
            return null;
        }
        
        // Skip if already local
        if (isLocalImage($imageUrl)) {
            return null;
        }
        
        $localPath = $this->processFromUrl($imageUrl, $furnitureId);
        if (!$localPath) {
            return null;
        }
        
        try {
            updateFurnitureImage($pdo, $furnitureId, $localPath);
        } catch (RuntimeException $e) {
            error_log("Failed to update furniture image: " . $e->getMessage());
            return null;
        }
        
        if ($oldImageUrl && $oldImageUrl !== $localPath && str_starts_with($oldImageUrl, '/images/furniture/')) {
            $this->deleteImage($oldImageUrl);
        }
        
        return $localPath;
    }

    /**
     * Delete a furniture image file
     * 
     * @param string $imagePath Web path to the image
     * @return bool Success status
     */
    public function deleteImage(string $imagePath): bool
    {
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
    public function cleanupOrphaned(PDO $pdo): int
    {
        $imagesDir = getImagesDir();
        $webPath = getImagesWebPath();
        
        if (!is_dir($imagesDir)) {
            return 0;
        }
        
        $stmt = $pdo->query('SELECT image_url FROM furniture WHERE image_url IS NOT NULL');
        $dbImages = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $dbFilenames = array_map('basename', $dbImages);
        
        $deleted = 0;
        $files = glob($imagesDir . '*.webp');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (!in_array($filename, $dbFilenames, true)) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}


