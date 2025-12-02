<?php
/**
 * GTAW Furniture Catalog - Validation Class
 * 
 * Centralized validation logic for all input validation.
 * All validation methods return a consistent array structure:
 * ['valid' => bool, 'error' => string|null, 'data' => mixed]
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'validator.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Validator class for input validation
 */
class Validator
{
    /**
     * Validate furniture name
     * 
     * @param string $name The name to validate
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function furnitureName(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Name is required', 'data' => null];
        }
        
        if (strlen($name) > MAX_FURNITURE_NAME_LENGTH) {
            return ['valid' => false, 'error' => 'Name must be ' . MAX_FURNITURE_NAME_LENGTH . ' characters or less', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $name];
    }

    /**
     * Validate category ID
     * 
     * @param int $categoryId The category ID to validate
     * @return array{valid: bool, error: string|null, data: int|null}
     */
    public static function categoryId(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return ['valid' => false, 'error' => 'Category is required', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $categoryId];
    }

    /**
     * Validate price
     * 
     * @param int $price The price to validate
     * @return array{valid: bool, error: string|null, data: int}
     */
    public static function price(int $price): array
    {
        if ($price < 0) {
            return ['valid' => false, 'error' => 'Price cannot be negative', 'data' => 0];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $price];
    }

    /**
     * Validate image URL
     * 
     * Validates image URLs with security checks:
     * - Relative paths must be within allowed directories (e.g., /images/furniture/)
     * - Prevents directory traversal attacks (../, ..\, etc.)
     * - Absolute URLs must be valid HTTP/HTTPS URLs
     * 
     * @param string $imageUrl The image URL to validate
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function imageUrl(string $imageUrl): array
    {
        $imageUrl = trim($imageUrl);
        
        if ($imageUrl === '') {
            return ['valid' => true, 'error' => null, 'data' => null];
        }
        
        if (strlen($imageUrl) > 500) {
            return ['valid' => false, 'error' => 'Image URL is too long', 'data' => null];
        }
        
        if (str_starts_with($imageUrl, '/')) {
            // Validate relative path - prevent directory traversal
            if (str_contains($imageUrl, '..') || str_contains($imageUrl, '\\')) {
                return ['valid' => false, 'error' => 'Invalid image path: directory traversal not allowed', 'data' => null];
            }
            
            // Normalize path to check for allowed directories
            $normalizedPath = str_replace('//', '/', $imageUrl);
            
            // Allow paths starting with /images/furniture/ (processed images)
            // Also allow other /images/ paths for flexibility (e.g., placeholders)
            if (str_starts_with($normalizedPath, '/images/')) {
                return ['valid' => true, 'error' => null, 'data' => $imageUrl];
            }
            
            // Reject other relative paths for security
            return ['valid' => false, 'error' => 'Image path must be in /images/ directory or a valid URL', 'data' => null];
        }
        
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            // Only allow HTTP and HTTPS URLs for security
            $scheme = parse_url($imageUrl, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
                return ['valid' => false, 'error' => 'Image URL must use HTTP or HTTPS protocol', 'data' => null];
            }
            return ['valid' => true, 'error' => null, 'data' => $imageUrl];
        }
        
        return ['valid' => false, 'error' => 'Image must be a relative path in /images/ directory or a valid HTTP/HTTPS URL', 'data' => null];
    }

    /**
     * Validate tags array
     * 
     * @param array $tags The tags array to validate
     * @return array{valid: bool, error: string|null, data: array<int>}
     */
    public static function tags(array $tags): array
    {
        if (!is_array($tags)) {
            return ['valid' => true, 'error' => null, 'data' => []];
        }
        
        $validTags = array_filter(array_map('intval', $tags));
        return ['valid' => true, 'error' => null, 'data' => $validTags];
    }

    /**
     * Validate collection name
     * 
     * @param string $name The collection name to validate
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function collectionName(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Collection name is required', 'data' => null];
        }
        
        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Collection name must be 100 characters or less', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $name];
    }

    /**
     * Validate username
     * 
     * @param string $username The username to validate
     * @param int $minLength Minimum length (default: 3)
     * @param int $maxLength Maximum length (default: 50)
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function username(string $username, int $minLength = 3, int $maxLength = 50): array
    {
        $username = trim($username);
        
        if (empty($username)) {
            return ['valid' => false, 'error' => 'Username is required', 'data' => null];
        }
        
        if (strlen($username) < $minLength) {
            return ['valid' => false, 'error' => "Username must be at least {$minLength} characters", 'data' => null];
        }
        
        if (strlen($username) > $maxLength) {
            return ['valid' => false, 'error' => "Username must be {$maxLength} characters or less", 'data' => null];
        }
        
        // Allow alphanumeric, underscore, hyphen, and dot
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            return ['valid' => false, 'error' => 'Username can only contain letters, numbers, dots, underscores, and hyphens', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $username];
    }

    /**
     * Validate furniture ID
     * 
     * @param int $id The furniture ID to validate
     * @param int $max Maximum allowed ID (default: 10000000, reasonable limit)
     * @return array{valid: bool, error: string|null, data: int|null}
     */
    public static function furnitureId(int $id, int $max = 10000000): array
    {
        if ($id <= 0) {
            return ['valid' => false, 'error' => 'Invalid furniture ID', 'data' => null];
        }
        
        if ($id > $max) {
            return ['valid' => false, 'error' => 'Furniture ID out of valid range', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $id];
    }

    /**
     * Validate category name
     * 
     * @param string $name The category name to validate
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function categoryName(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Category name is required', 'data' => null];
        }
        
        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Category name must be 100 characters or less', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $name];
    }

    /**
     * Validate tag group name
     * 
     * @param string $name The tag group name to validate
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function tagGroupName(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Tag group name is required', 'data' => null];
        }
        
        if (strlen($name) > 50) {
            return ['valid' => false, 'error' => 'Tag group name must be 50 characters or less', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $name];
    }

    /**
     * Validate tag name
     * 
     * @param string $name The tag name to validate
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function tagName(string $name): array
    {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Tag name is required', 'data' => null];
        }
        
        if (strlen($name) > 50) {
            return ['valid' => false, 'error' => 'Tag name must be 50 characters or less', 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $name];
    }

    /**
     * Validate password
     * 
     * @param string $password The password to validate
     * @param int $minLength Minimum length (default: 8)
     * @param int $maxLength Maximum length (default: 255)
     * @return array{valid: bool, error: string|null, data: string|null}
     */
    public static function password(string $password, int $minLength = 8, int $maxLength = 255): array
    {
        if (empty($password)) {
            return ['valid' => false, 'error' => 'Password is required', 'data' => null];
        }
        
        if (strlen($password) < $minLength) {
            return ['valid' => false, 'error' => "Password must be at least {$minLength} characters", 'data' => null];
        }
        
        if (strlen($password) > $maxLength) {
            return ['valid' => false, 'error' => "Password must be {$maxLength} characters or less", 'data' => null];
        }
        
        return ['valid' => true, 'error' => null, 'data' => $password];
    }

    /**
     * Validate furniture input (composite validation)
     * 
     * @param array $input Input data to validate
     * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
     */
    public static function furnitureInput(array $input): array
    {
        $errors = [];
        $data = [];
        
        // Name validation
        $nameResult = self::furnitureName($input['name'] ?? '');
        if (!$nameResult['valid']) {
            $errors['name'] = $nameResult['error'];
        } else {
            $data['name'] = $nameResult['data'];
        }
        
        // Category validation
        $categoryId = (int) ($input['category_id'] ?? 0);
        $categoryResult = self::categoryId($categoryId);
        if (!$categoryResult['valid']) {
            $errors['category_id'] = $categoryResult['error'];
        } else {
            $data['category_id'] = $categoryResult['data'];
        }
        
        // Price validation
        $price = (int) ($input['price'] ?? 0);
        $priceResult = self::price($price);
        if (!$priceResult['valid']) {
            $errors['price'] = $priceResult['error'];
        } else {
            $data['price'] = $priceResult['data'];
        }
        
        // Image URL validation
        $imageUrlResult = self::imageUrl($input['image_url'] ?? '');
        if (!$imageUrlResult['valid']) {
            $errors['image_url'] = $imageUrlResult['error'];
        } else {
            $data['image_url'] = $imageUrlResult['data'];
        }
        
        // Tags validation (optional)
        if (isset($input['tags'])) {
            $tagsResult = self::tags($input['tags']);
            if (!$tagsResult['valid']) {
                $errors['tags'] = $tagsResult['error'];
            } else {
                $data['tags'] = $tagsResult['data'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data,
        ];
    }
}

