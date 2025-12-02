<?php
/**
 * GTAW Furniture Catalog - Base API Controller
 * 
 * Provides common initialization and helper methods for API endpoints.
 * Reduces code duplication across api.php, admin/api.php, and dashboard/api.php.
 * 
 * This class can be used in two ways:
 * 1. As a base class for class-based API controllers (future refactoring)
 * 2. As a helper for common initialization patterns (current usage)
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'api-controller.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Base API Controller class
 * 
 * Handles common API initialization tasks:
 * - Setting JSON content type
 * - Database connection
 * - CSRF verification
 * - Request method and action extraction
 */
abstract class ApiController
{
    protected PDO $pdo;
    protected string $method;
    protected string $action;
    
    /**
     * Initialize API controller
     * 
     * Sets up JSON headers, database connection, and extracts request info.
     * 
     * @throws RuntimeException If database connection fails
     */
    public function __construct()
    {
        // Set JSON content type
        header('Content-Type: application/json');
        
        // Get database connection
        try {
            $this->pdo = getDb();
        } catch (RuntimeException $e) {
            jsonError('Database connection failed', 500);
        }
        
        // Get request info
        $this->method = requestMethod();
        $this->action = getQuery('action', '');
    }
    
    /**
     * Verify CSRF token for state-changing operations
     * 
     * GET requests are exempt from CSRF verification.
     * 
     * @return void Exits with error if CSRF verification fails
     */
    protected function requireCsrf(): void
    {
        if ($this->method !== 'GET' && !verifyApiCsrf()) {
            jsonError('Invalid or missing CSRF token', 403);
        }
    }
    
    /**
     * Require that the request method matches the allowed method
     * 
     * @param string $allowedMethod The allowed HTTP method (e.g., 'GET', 'POST')
     * @return void Exits with error if method doesn't match
     */
    protected function requireMethod(string $allowedMethod): void
    {
        if ($this->method !== strtoupper($allowedMethod)) {
            jsonError('Method not allowed', 405);
        }
    }
    
    /**
     * Require that the request method is one of the allowed methods
     * 
     * @param array $allowedMethods Array of allowed HTTP methods (e.g., ['GET', 'POST'])
     * @return void Exits with error if method doesn't match
     */
    protected function requireMethods(array $allowedMethods): void
    {
        $allowedMethods = array_map('strtoupper', $allowedMethods);
        
        if (!in_array($this->method, $allowedMethods, true)) {
            jsonError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle the API request
     * 
     * Subclasses should implement this method to handle their specific endpoints.
     * 
     * @return void
     */
    abstract public function handle(): void;
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
    // Set JSON content type
    header('Content-Type: application/json');
    
    // Get database connection
    try {
        $pdo = getDb();
    } catch (RuntimeException $e) {
        jsonError('Database connection failed', 500);
    }
    
    // Get request info
    $method = requestMethod();
    $action = getQuery('action', '');
    
    // Verify CSRF for all POST/DELETE requests
    if ($method !== 'GET' && !verifyApiCsrf()) {
        jsonError('Invalid or missing CSRF token', 403);
    }
    
    return [
        'method' => $method,
        'action' => $action,
        'pdo' => $pdo,
    ];
}

