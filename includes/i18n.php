<?php
/**
 * GTAW Furniture Catalog - Internationalization (i18n) System
 * 
 * Lightweight translation system using PHP arrays.
 * Supports placeholder replacement and pluralization.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'i18n.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Translation cache to avoid reloading files
 */
$GLOBALS['_translations'] = null;
$GLOBALS['_translations_locale'] = null;

/**
 * Get translated string
 * 
 * @param string $key Translation key (e.g., 'nav.dashboard')
 * @param array $replacements Placeholder replacements (e.g., ['name' => 'John'])
 * @return string Translated string or key as fallback
 */
function __($key, array $replacements = []): string
{
    $locale = getCurrentLocale();
    
    // Load translations if not cached or locale changed
    if ($GLOBALS['_translations'] === null || $GLOBALS['_translations_locale'] !== $locale) {
        $file = __DIR__ . "/../lang/{$locale}.php";
        
        if (file_exists($file)) {
            $GLOBALS['_translations'] = require $file;
        } else {
            // Fallback to English if requested locale file doesn't exist
            $fallbackFile = __DIR__ . '/../lang/en.php';
            $GLOBALS['_translations'] = file_exists($fallbackFile) ? require $fallbackFile : [];
        }
        
        $GLOBALS['_translations_locale'] = $locale;
    }
    
    // Get translation or return key as fallback
    $text = $GLOBALS['_translations'][$key] ?? $key;
    
    // Handle simple pluralization (format: "singular|plural")
    if (str_contains($text, '|') && isset($replacements['count'])) {
        $parts = explode('|', $text);
        $count = (int) $replacements['count'];
        $text = $count === 1 ? $parts[0] : ($parts[1] ?? $parts[0]);
    }
    
    // Replace placeholders: {name} → value
    foreach ($replacements as $placeholder => $value) {
        $text = str_replace("{{$placeholder}}", (string) $value, $text);
    }
    
    return $text;
}

/**
 * Echo translated and escaped string
 * 
 * @param string $key Translation key
 * @param array $replacements Placeholder replacements
 */
function _e($key, array $replacements = []): void
{
    echo e(__($key, $replacements));
}

/**
 * Get translated string without escaping (for use in attributes where escaping is handled separately)
 * 
 * @param string $key Translation key
 * @param array $replacements Placeholder replacements
 * @return string Translated string
 */
function _raw($key, array $replacements = []): string
{
    return __($key, $replacements);
}

/**
 * Check if a translation key exists
 * 
 * @param string $key Translation key
 * @return bool True if key exists
 */
function __exists($key): bool
{
    $locale = getCurrentLocale();
    
    if ($GLOBALS['_translations'] === null || $GLOBALS['_translations_locale'] !== $locale) {
        // Force load translations
        __($key);
    }
    
    return isset($GLOBALS['_translations'][$key]);
}

/**
 * Get all translations for current locale
 * Useful for passing to JavaScript
 * 
 * @param array|null $keys Specific keys to include, or null for all
 * @return array Translation array
 */
function getTranslations(?array $keys = null): array
{
    $locale = getCurrentLocale();
    
    // Ensure translations are loaded
    if ($GLOBALS['_translations'] === null || $GLOBALS['_translations_locale'] !== $locale) {
        __('_load'); // Trigger load
    }
    
    if ($keys === null) {
        return $GLOBALS['_translations'] ?? [];
    }
    
    $result = [];
    foreach ($keys as $key) {
        if (isset($GLOBALS['_translations'][$key])) {
            $result[$key] = $GLOBALS['_translations'][$key];
        }
    }
    
    return $result;
}

/**
 * Get translations needed for JavaScript
 * Returns only the keys used in frontend JS
 * 
 * @return array Translation array for JS
 */
function getJsTranslations(): array
{
    return getTranslations([
        // Card & Copy
        'card.copied',
        'card.copy_failed',
        'card.copy',
        'card.copy_command',
        
        // Favorites
        'favorites.login_required',
        'favorites.added',
        'favorites.removed',
        'favorites.failed',
        'favorites.confirm_remove',
        'favorites.confirm_clear',
        'favorites.cleared',
        'favorites.exported',
        'favorites.nothing_to_export',
        'favorites.nothing_to_clear',
        'favorites.export_failed',
        
        // Collections
        'collections.added',
        'collections.removed',
        'collections.deleted',
        'collections.duplicated',
        'collections.link_copied',
        'collections.reordered',
        'collections.reorder_failed',
        'collections.confirm_delete',
        'collections.confirm_duplicate',
        'collections.confirm_remove_item',
        'collections.nothing_to_export',
        'collections.pick_title',
        'collections.no_collections',
        'collections.create_first',
        'collections.new_collection',
        'collections.added_status',
        
        // Submissions
        'submissions.confirm_cancel',
        'submissions.cancelled',
        
        // Theme
        'theme.switched',
        'theme.dark',
        'theme.light',
        
        // Lightbox
        'lightbox.share_copied',
        
        // Search & Filter
        'search.no_results',
        'search.try_adjusting',
        'search.translated_from',
        'search.also_searching',
        'search.did_you_mean',
        'search.try_category',
        'search.dismiss',
        'filter.clear_all',
        'filter.clear_all_short',
        'filter.remove_tag',
        
        // Empty states
        'empty.loading',
        'empty.please_wait',
        'empty.welcome',
        'empty.start_browsing',
        'empty.not_found',
        
        // Errors
        'error.generic',
        'error.loading',
        'error.network',
        'error.network_retry',
        'error.failed_to_load',
        
        // Success
        'success.saved',
        
        // Forms
        'form.save',
        'form.saving',
        
        // Pagination
        'pagination.previous',
        'pagination.next',
        'pagination.previous_page',
        'pagination.next_page',
        'pagination.page_info',
        'pagination.items',
    ]);
}

/**
 * Clear translation cache
 * Useful for testing or when locale changes mid-request
 */
function clearTranslationCache(): void
{
    $GLOBALS['_translations'] = null;
    $GLOBALS['_translations_locale'] = null;
}
