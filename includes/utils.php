<?php
/**
 * GTAW Furniture Catalog - Utility Functions
 * 
 * Shared utility functions used across multiple files.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'utils.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Create URL-friendly slug
 * 
 * @param string $text The text to convert to a slug
 * @return string URL-friendly slug
 */
function createSlug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Normalize category IDs from input data
 * 
 * Accepts either category_ids (array) or category_id (single) for backwards compatibility.
 * 
 * @param array $data Input data array
 * @return array Array of category IDs (integers)
 */
function normalizeCategoryIds(array $data): array
{
    if (isset($data['category_ids']) && is_array($data['category_ids'])) {
        return array_filter(array_map('intval', $data['category_ids']));
    }
    
    if (isset($data['category_id'])) {
        $id = (int) $data['category_id'];
        return $id > 0 ? [$id] : [];
    }
    
    return [];
}

/**
 * Add backwards compatibility category fields to furniture item
 * 
 * Sets category_id, category_name, category_slug from primary category
 * for backwards compatibility with code expecting single category.
 * 
 * @param array $item Furniture item array (modified in place)
 * @return void
 */
function addBackwardsCompatibilityCategoryFields(array &$item): void
{
    if (!empty($item['categories']) && is_array($item['categories'])) {
        $primary = $item['categories'][0];
        $item['category_id'] = $primary['id'] ?? null;
        $item['category_name'] = $primary['name'] ?? null;
        $item['category_slug'] = $primary['slug'] ?? null;
    }
}

/**
 * APCu cache helper: Get value from cache
 * 
 * @param string $key Cache key
 * @param mixed $default Default value if not found
 * @param bool|null $success Optional reference to receive success status
 * @return mixed Cached value or default
 */
function cacheGet(string $key, mixed $default = false, ?bool &$success = null): mixed
{
    if (!function_exists('apcu_fetch')) {
        if ($success !== null) {
            $success = false;
        }
        return $default;
    }
    $value = apcu_fetch($key, $fetchSuccess);
    if ($success !== null) {
        $success = $fetchSuccess;
    }
    return $fetchSuccess && $value !== false ? $value : $default;
}

/**
 * APCu cache helper: Store value in cache
 * 
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds (0 = default)
 * @return bool True on success
 */
function cacheSet(string $key, mixed $value, int $ttl = 0): bool
{
    if (!function_exists('apcu_store')) {
        return false;
    }
    return apcu_store($key, $value, $ttl);
}

/**
 * APCu cache helper: Delete value from cache
 * 
 * @param string $key Cache key
 * @return bool True on success
 */
function cacheDelete(string $key): bool
{
    if (!function_exists('apcu_delete')) {
        return false;
    }
    return apcu_delete($key);
}

