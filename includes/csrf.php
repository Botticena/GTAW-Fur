<?php
/**
 * GTAW Furniture Catalog - CSRF Protection
 * 
 * Provides CSRF token generation and validation for forms.
 * Included by init.php - do not include directly.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'csrf.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Generate CSRF token and store in session
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate CSRF token (call after successful form submission if needed)
 */
function regenerateCsrfToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF token as hidden input for forms
 */
function csrfInput(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Get CSRF token for JavaScript use
 */
function csrfToken(): string
{
    return generateCsrfToken();
}

/**
 * Verify CSRF token for API requests
 * 
 * Checks for token in:
 * 1. JSON request body (csrf_token field)
 * 2. POST data (csrf_token field)
 * 3. HTTP header (X-CSRF-Token)
 * 
 * GET requests are always allowed (read-only operations).
 * 
 * @return bool True if token is valid or request is GET, false otherwise
 */
function verifyApiCsrf(): bool
{
    if (requestMethod() === 'GET') {
        return true;
    }
    
    $input = getJsonInput();
    if ($input && isset($input['csrf_token'])) {
        return verifyCsrfToken($input['csrf_token']);
    }
    
    if (isset($_POST['csrf_token'])) {
        return verifyCsrfToken($_POST['csrf_token']);
    }
    
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($headerToken) {
        return verifyCsrfToken($headerToken);
    }
    
    return false;
}

