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

