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

/**
 * Send success response
 * 
 * @param mixed $data Optional data to include in response
 * @param string|null $message Optional success message
 * @param array|null $pagination Optional pagination metadata
 * @return never
 */
function jsonSuccess(mixed $data = null, ?string $message = null, ?array $pagination = null): never
{
    $response = ['success' => true];
    
    // Always include data if provided (even if empty array)
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    // Always include pagination if provided
    if ($pagination !== null) {
        $response['pagination'] = $pagination;
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
        jsonError('Method not allowed', 405);
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
        jsonError('Method not allowed', 405);
    }
}

