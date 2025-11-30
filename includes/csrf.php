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

