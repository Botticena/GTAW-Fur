<?php
/**
 * GTAW Furniture Catalog - API Response Helpers
 * 
 * Shared functions for consistent JSON API responses across all endpoints.
 */

declare(strict_types=1);

// Prevent direct access
if (str_contains($_SERVER['PHP_SELF'] ?? '', 'includes/api.php') || 
    str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'includes/api.php')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// ============================================
// ERROR MESSAGE CONSTANTS
// ============================================

/**
 * Standard error messages for consistent API responses
 */
const ERROR_NOT_FOUND = 'Resource not found';
const ERROR_INVALID_ID = 'Invalid ID';
const ERROR_AUTH_REQUIRED = 'Authentication required';
const ERROR_LOGIN_REQUIRED = 'Login required';
const ERROR_METHOD_NOT_ALLOWED = 'Method not allowed';
const ERROR_CSRF_INVALID = 'Invalid or missing CSRF token';
const ERROR_DB_CONNECTION = 'Database connection failed';
const ERROR_DB_ERROR = 'Database error';
const ERROR_INTERNAL = 'Internal server error';
const ERROR_ADMIN_REQUIRED = 'Admin authentication required';
const ERROR_UNKNOWN_ENDPOINT = 'Unknown endpoint';

/**
 * Send success response
 * 
 * @param mixed $data Optional data to include in response
 * @param string|null $message Optional success message
 * @param array|null $pagination Optional pagination metadata
 * @param array|null $extra Optional extra fields to merge into response
 * @param bool $cacheable Whether this response can be cached (default: false for dynamic data)
 * @param int $cacheMaxAge Cache max age in seconds (default: 300 for cacheable responses)
 * @return never
 */
function jsonSuccess(mixed $data = null, ?string $message = null, ?array $pagination = null, ?array $extra = null, bool $cacheable = false, int $cacheMaxAge = 300): never
{
    $response = ['success' => true];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if ($pagination !== null) {
        $response['pagination'] = $pagination;
    }
    
    if ($extra !== null) {
        $response = array_merge($response, $extra);
    }
    
    if ($cacheable) {
        $etag = md5(json_encode($response));
        header("ETag: \"{$etag}\"");
        
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === "\"{$etag}\"") {
            http_response_code(304);
            exit;
        }
        
        header("Cache-Control: public, max-age={$cacheMaxAge}");
    } else {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Send error response
 * 
 * @param string $message Error message
 * @param int $code HTTP status code (default: 400)
 * @return never
 */
function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Require that a furniture item exists and return it
 * Exits with error if ID is invalid or furniture not found
 * 
 * @param PDO $pdo Database connection
 * @param int $id Furniture ID to validate and fetch
 * @return array Furniture item data
 * @return never Exits with error if validation fails
 */
function requireFurniture(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        jsonError(ERROR_INVALID_ID);
    }
    
    $item = getFurnitureById($pdo, $id);
    if (!$item) {
        jsonError(ERROR_NOT_FOUND, 404);
    }
    
    return $item;
}

/**
 * Require that the request method matches the allowed method
 * Exits with 405 error if method doesn't match
 * 
 * @param string $allowedMethod The allowed HTTP method (e.g., 'GET', 'POST')
 * @return void Exits with error if method doesn't match
 */
function requireMethod(string $allowedMethod): void
{
    if (requestMethod() !== strtoupper($allowedMethod)) {
        jsonError(ERROR_METHOD_NOT_ALLOWED, 405);
    }
}

/**
 * Require that the request method is one of the allowed methods
 * Exits with 405 error if method doesn't match any allowed method
 * 
 * @param array $allowedMethods Array of allowed HTTP methods (e.g., ['GET', 'POST'])
 * @return void Exits with error if method doesn't match
 */
function requireMethods(array $allowedMethods): void
{
    $method = requestMethod();
    $allowedMethods = array_map('strtoupper', $allowedMethods);
    
    if (!in_array($method, $allowedMethods, true)) {
        jsonError(ERROR_METHOD_NOT_ALLOWED, 405);
    }
}

