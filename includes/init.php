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
$config = @include dirname(__DIR__) . '/config.php';
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

/**
 * Get configuration value
 */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    
    if ($config === null) {
        $configFile = dirname(__DIR__) . '/config.php';
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

