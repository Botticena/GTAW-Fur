<?php
/**
 * GTAW Furniture Catalog - API Initialization Helper
 * 
 * Provides common initialization for API endpoints.
 * Reduces code duplication across api.php, admin/api.php, and dashboard/api.php.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'api-controller.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * API initialization helper
 * 
 * Provides a simple way to initialize common API patterns without requiring
 * full class-based refactoring. Returns an array with common values.
 * 
 * @return array{method: string, action: string, pdo: PDO}
 */
function initializeApi(): array
{
    header('Content-Type: application/json');
    
    try {
        $pdo = getDb();
    } catch (RuntimeException $e) {
        jsonError(ERROR_DB_CONNECTION, 500);
    }
    
    $method = requestMethod();
    $action = getQuery('action', '');
    
    if ($method !== 'GET' && !verifyApiCsrf()) {
        jsonError(ERROR_CSRF_INVALID, 403);
    }
    
    return [
        'method' => $method,
        'action' => $action,
        'pdo' => $pdo,
    ];
}

