<?php
/**
 * GTAW Furniture Catalog - Application Initialization
 * 
 * This file MUST be included first in every PHP entry point.
 * It handles session configuration, security headers, and loads core dependencies.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'init.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Error reporting (disable display in production)
$config = @include __DIR__ . '/../config.php';
if ($config && isset($config['app']['debug']) && $config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (Enforcement mode)
// Note: 'unsafe-inline' for scripts is needed for onclick handlers throughout the app
// Monitor browser console and server logs for CSP violations after deployment
$cspDirectives = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
    "img-src 'self' data: blob: https:",
    "font-src 'self'",
    "connect-src 'self'",
    "frame-ancestors 'self'",
    "form-action 'self'",
    "base-uri 'self'",
    "object-src 'none'"
];
header('Content-Security-Policy: ' . implode('; ', $cspDirectives));

// Secure session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '86400');

// Only set secure cookie flag if using HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database connection
require_once __DIR__ . '/db.php';

// Load CSRF helpers
require_once __DIR__ . '/csrf.php';

// ============================================
// APPLICATION CONSTANTS
// ============================================

/**
 * Maximum number of items per page in pagination
 */
const MAX_ITEMS_PER_PAGE = 50;

/**
 * Minimum search query length (characters)
 */
const MIN_SEARCH_LENGTH = 2;

/**
 * Maximum image file size in bytes (10MB)
 */
const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

/**
 * Maximum furniture name length (characters)
 */
const MAX_FURNITURE_NAME_LENGTH = 255;

/**
 * Rate limiting constants
 * Format: ['max' => max_attempts, 'window' => window_seconds]
 */
const RATE_LIMIT_FAVORITES = ['max' => 30, 'window' => 60];
const RATE_LIMIT_COLLECTIONS_CREATE = ['max' => 10, 'window' => 60];
const RATE_LIMIT_COLLECTIONS_ITEMS = ['max' => 50, 'window' => 60];
const RATE_LIMIT_SUBMISSIONS_CREATE = ['max' => 5, 'window' => 60];

/**
 * Cache TTL constants (in seconds)
 */
const CACHE_TTL_CATEGORIES = 300; // 5 minutes
const CACHE_TTL_TAGS = 300; // 5 minutes
const CACHE_TTL_TAG_GROUPS = 300; // 5 minutes

/**
 * Centralized exception logging helper.
 *
 * @param string    $context Short context string (e.g., 'api', 'dashboard_api', 'admin_api', 'oauth')
 * @param Throwable $e       Exception to log
 */
function logException(string $context, Throwable $e): void
{
    $message = sprintf(
        '[%s] %s: %s in %s:%d',
        $context,
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );

    error_log($message);
}

/**
 * Get configuration value
 */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    
    if ($config === null) {
        $configFile = __DIR__ . '/../config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            $config = [];
        }
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!is_array($value) || !array_key_exists($k, $value)) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Escape output for HTML context
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate a URL for the application
 */
function url(string $path = ''): string
{
    $baseUrl = config('app.url', '');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect to a URL
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Check if current request is AJAX
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get current request method
 */
function requestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Get JSON input from request body
 * 
 * @return array<string, mixed>|null Parsed JSON data or null if invalid/empty
 */
function getJsonInput(): ?array
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return null;
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $data;
}

/**
 * Get input value from GET or POST (checks POST first, then GET)
 * 
 * @param string $key Input key
 * @param mixed $default Default value if key not found
 * @param bool $trim Whether to trim string values (default: true)
 * @return mixed Input value or default
 */
function getInput(string $key, mixed $default = null, bool $trim = true): mixed
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return $trim && is_string($value) ? trim($value) : $value;
}

/**
 * Get input value from GET only
 * 
 * @param string $key Input key
 * @param mixed $default Default value if key not found
 * @param bool $trim Whether to trim string values (default: true)
 * @return mixed Input value or default
 */
function getQuery(string $key, mixed $default = null, bool $trim = true): mixed
{
    $value = $_GET[$key] ?? $default;
    return $trim && is_string($value) ? trim($value) : $value;
}

/**
 * Get input value from POST only
 * 
 * @param string $key Input key
 * @param mixed $default Default value if key not found
 * @param bool $trim Whether to trim string values (default: true)
 * @return mixed Input value or default
 */
function getPost(string $key, mixed $default = null, bool $trim = true): mixed
{
    $value = $_POST[$key] ?? $default;
    return $trim && is_string($value) ? trim($value) : $value;
}

/**
 * Get integer input value (from GET or POST)
 * 
 * @param string $key Input key
 * @param int $default Default value if key not found or invalid
 * @return int Integer value
 */
function getInputInt(string $key, int $default = 0): int
{
    $value = getInput($key, $default, false);
    return is_numeric($value) ? (int) $value : $default;
}

/**
 * Get integer query parameter (from GET only)
 * 
 * @param string $key Input key
 * @param int $default Default value if key not found or invalid
 * @return int Integer value
 */
function getQueryInt(string $key, int $default = 0): int
{
    $value = getQuery($key, $default, false);
    return is_numeric($value) ? (int) $value : $default;
}

/**
 * Get boolean value from an array (typically from POST or normalized input)
 * 
 * Handles various boolean representations:
 * - true, 'true', '1', 1, 'on', 'yes' → true
 * - false, 'false', '0', 0, '', null, not set → false
 * 
 * @param array $source Source array (e.g., $_POST or $input)
 * @param string $key Array key to check
 * @param bool $default Default value if key not found
 * @return bool Boolean value
 */
function getInputBool(array $source, string $key, bool $default = false): bool
{
    if (!isset($source[$key])) {
        return $default;
    }
    
    $value = $source[$key];
    
    // Handle explicit boolean values
    if (is_bool($value)) {
        return $value;
    }
    
    // Handle string/numeric representations
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

