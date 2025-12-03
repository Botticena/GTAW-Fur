<?php
/**
 * GTAW Furniture Catalog - Authentication Helpers
 * 
 * Provides OAuth and session management functions.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Exchange authorization code for access token
 */
function exchangeCodeForToken(string $code): ?array
{
    $clientId = config('oauth.client_id');
    $clientSecret = config('oauth.client_secret');
    $redirectUri = config('oauth.redirect_uri');
    $tokenUrl = config('oauth.token_url', 'https://ucp.gta.world/oauth/token');
    
    if (empty($clientId) || empty($clientSecret)) {
        error_log('OAuth credentials not configured');
        return null;
    }
    
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("OAuth token exchange cURL error: {$error}");
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("OAuth token exchange failed. HTTP {$httpCode}: {$response}");
        return null;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("OAuth token exchange: Invalid JSON response");
        return null;
    }
    
    return $data;
}

/**
 * Fetch user data from GTAW API using access token
 */
function fetchGtawUserData(string $accessToken): ?array
{
    $userUrl = config('oauth.user_url', 'https://ucp.gta.world/api/user');
    
    $ch = curl_init($userUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("GTAW user API cURL error: {$error}");
        return null;
    }
    
    if ($httpCode !== 200) {
        error_log("GTAW user API failed. HTTP {$httpCode}: {$response}");
        return null;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("GTAW user API: Invalid JSON response");
        return null;
    }
    
    return $data;
}

/**
 * Create or update user in database after OAuth login
 */
function createOrUpdateUser(PDO $pdo, int $gtawId, string $username, ?string $gtawRole, ?string $mainCharacter): array
{
    // Check if user exists
    $stmt = $pdo->prepare('SELECT * FROM users WHERE gtaw_id = ?');
    $stmt->execute([$gtawId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Update existing user
        $stmt = $pdo->prepare('
            UPDATE users 
            SET username = ?, gtaw_role = ?, main_character = ?, last_login = NOW() 
            WHERE gtaw_id = ?
        ');
        $stmt->execute([$username, $gtawRole, $mainCharacter, $gtawId]);
        
        // Refresh user data
        $stmt = $pdo->prepare('SELECT * FROM users WHERE gtaw_id = ?');
        $stmt->execute([$gtawId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Create new user
    $stmt = $pdo->prepare('
        INSERT INTO users (gtaw_id, username, gtaw_role, main_character, last_login, created_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ');
    $stmt->execute([$gtawId, $username, $gtawRole, $mainCharacter]);
    
    return [
        'id' => (int) $pdo->lastInsertId(),
        'gtaw_id' => $gtawId,
        'username' => $username,
        'gtaw_role' => $gtawRole,
        'main_character' => $mainCharacter,
        'is_banned' => false,
        'ban_reason' => null,
    ];
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require user to be logged in
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        if (isAjax()) {
            jsonError(ERROR_AUTH_REQUIRED, 401);
        }
        redirect('/login.php');
    }
}

/**
 * Get current logged-in user data from session
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'gtaw_id' => $_SESSION['gtaw_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'main_character' => $_SESSION['main_character'] ?? null,
    ];
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int
{
    return isLoggedIn() ? ($_SESSION['user_id'] ?? null) : null;
}

/**
 * Create user session after successful OAuth
 */
function createUserSession(array $user): void
{
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['gtaw_id'] = $user['gtaw_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['main_character'] = $user['main_character'];
    $_SESSION['logged_in'] = true;
}

/**
 * Destroy user session (logout)
 */
function destroyUserSession(): void
{
    // Clear session data
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require admin to be logged in
 */
function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        if (isAjax()) {
            jsonError(ERROR_ADMIN_REQUIRED, 401);
        }
        redirect('/admin/login.php');
    }
}

/**
 * Get current admin data from session
 */
function getCurrentAdmin(): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? null,
    ];
}

/**
 * Verify admin credentials
 */
function verifyAdminCredentials(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return null;
    }
    
    // Update last login
    $stmt = $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$admin['id']]);
    
    return $admin;
}

/**
 * Create admin session
 */
function createAdminSession(array $admin): void
{
    session_regenerate_id(true);
    
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_logged_in'] = true;
}

/**
 * Destroy admin session
 */
function destroyAdminSession(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_logged_in']
    );
}

/**
 * Check admin login rate limit
 * Returns true if rate limited, false if allowed
 * 
 * Uses the generic rate limiting system with admin-specific parameters.
 */
function isAdminLoginRateLimited(): bool
{
    return isRateLimited('admin_login_attempts', 5, 900); // 5 attempts per 15 minutes
}

/**
 * Record a failed admin login attempt
 * 
 * Uses the generic rate limiting system.
 */
function recordFailedAdminLogin(): void
{
    recordRateLimitAttempt('admin_login_attempts');
}

/**
 * Clear admin login rate limit
 * 
 * Uses the generic rate limiting system.
 */
function clearAdminLoginRateLimit(): void
{
    clearRateLimit('admin_login_attempts');
}

/**
 * Get client IP address with proxy support
 * 
 * Checks for forwarded headers in a safe order, with validation.
 * Falls back to REMOTE_ADDR if no valid proxy headers found.
 * 
 * @return string Client IP address
 */
function getClientIp(): string
{
    // Headers to check for forwarded IP (in order of preference)
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Standard proxy header
        'HTTP_X_REAL_IP',            // Nginx proxy
        'REMOTE_ADDR'                // Direct connection
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            // X-Forwarded-For may contain multiple IPs; take the first (client)
            $ip = $_SERVER[$header];
            if ($header === 'HTTP_X_FORWARDED_FOR') {
                $ips = array_map('trim', explode(',', $ip));
                $ip = $ips[0];
            }
            
            // Validate IP format
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Get rate limit storage directory path
 * 
 * @return string Path to rate limit storage directory
 */
function getRateLimitStoragePath(): string
{
    $path = sys_get_temp_dir() . '/gtaw_ratelimit';
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    return $path;
}

/**
 * Get IP-based rate limit data from file storage
 * 
 * @param string $key Rate limit key
 * @param string $ip Client IP address
 * @return array Rate limit data ['count' => int, 'first_attempt' => int]
 */
function getIpRateLimitData(string $key, string $ip): array
{
    $filename = getRateLimitStoragePath() . '/' . md5($key . '_' . $ip) . '.json';
    $now = time();
    
    if (!file_exists($filename)) {
        return ['count' => 0, 'first_attempt' => $now];
    }
    
    $content = @file_get_contents($filename);
    if ($content === false) {
        return ['count' => 0, 'first_attempt' => $now];
    }
    
    $data = json_decode($content, true);
    if (!is_array($data) || !isset($data['count']) || !isset($data['first_attempt'])) {
        return ['count' => 0, 'first_attempt' => $now];
    }
    
    return $data;
}

/**
 * Save IP-based rate limit data to file storage
 * 
 * Uses LOCK_EX flag for file locking to prevent race conditions.
 * For high-traffic scenarios, consider using Redis or database-based rate limiting.
 * 
 * @param string $key Rate limit key
 * @param string $ip Client IP address
 * @param array $data Rate limit data
 */
function saveIpRateLimitData(string $key, string $ip, array $data): void
{
    $filename = getRateLimitStoragePath() . '/' . md5($key . '_' . $ip) . '.json';
    @file_put_contents($filename, json_encode($data), LOCK_EX);
}

/**
 * Clean up expired rate limit files (call periodically)
 * 
 * @param int $maxAge Maximum age in seconds (default: 1 hour)
 */
function cleanupRateLimitFiles(int $maxAge = 3600): void
{
    $path = getRateLimitStoragePath();
    if (!is_dir($path)) {
        return;
    }
    
    $now = time();
    foreach (glob($path . '/*.json') as $file) {
        if (($now - filemtime($file)) > $maxAge) {
            @unlink($file);
        }
    }
}

/**
 * Generic rate limiting function with IP-based fallback
 * 
 * Uses a hybrid approach:
 * 1. Session-based tracking (primary) - works for logged-in users and those with cookies
 * 2. IP-based file tracking (fallback) - catches users who bypass sessions
 * 
 * A request is rate-limited if EITHER the session OR IP limit is exceeded.
 * 
 * @param string $key Unique identifier for the rate limit (e.g., 'oauth_callback', 'api_favorites')
 * @param int $maxAttempts Maximum number of attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @param string|null $identifier Optional identifier (e.g., user ID) for per-user rate limiting
 * @return bool True if rate limited, false if allowed
 */
function isRateLimited(string $key, int $maxAttempts, int $windowSeconds, ?string $identifier = null): bool
{
    $now = time();
    
    // === Session-based rate limiting (primary) ===
    $sessionKey = $identifier ? "rate_limit_{$key}_{$identifier}" : "rate_limit_{$key}";
    $sessionAttempts = $_SESSION[$sessionKey] ?? ['count' => 0, 'first_attempt' => $now];
    
    // Reset session tracking if window has passed
    if (($now - $sessionAttempts['first_attempt']) >= $windowSeconds) {
        $_SESSION[$sessionKey] = ['count' => 0, 'first_attempt' => $now];
        $sessionAttempts = ['count' => 0, 'first_attempt' => $now];
    }
    
    // Check session limit
    if ($sessionAttempts['count'] >= $maxAttempts) {
        return true;
    }
    
    // === IP-based rate limiting (fallback) ===
    // Only apply IP-based limiting for anonymous requests (no identifier)
    // For logged-in users, the session/user ID tracking is sufficient
    if ($identifier === null) {
        $clientIp = getClientIp();
        $ipAttempts = getIpRateLimitData($key, $clientIp);
        
        // Reset IP tracking if window has passed
        if (($now - $ipAttempts['first_attempt']) >= $windowSeconds) {
            $ipAttempts = ['count' => 0, 'first_attempt' => $now];
            saveIpRateLimitData($key, $clientIp, $ipAttempts);
        }
        
        // Check IP limit
        if ($ipAttempts['count'] >= $maxAttempts) {
            return true;
        }
    }
    
    return false;
}

/**
 * Record an attempt for rate limiting
 * 
 * Records the attempt in both session and IP-based storage.
 * 
 * @param string $key Unique identifier for the rate limit
 * @param string|null $identifier Optional identifier (e.g., user ID)
 */
function recordRateLimitAttempt(string $key, ?string $identifier = null): void
{
    $now = time();
    
    // === Record in session ===
    $sessionKey = $identifier ? "rate_limit_{$key}_{$identifier}" : "rate_limit_{$key}";
    
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 1, 'first_attempt' => $now];
    } else {
        $_SESSION[$sessionKey]['count']++;
    }
    
    // === Record by IP (for anonymous requests) ===
    if ($identifier === null) {
        $clientIp = getClientIp();
        $ipAttempts = getIpRateLimitData($key, $clientIp);
        
        // Reset if window has passed
        if (($now - $ipAttempts['first_attempt']) >= 3600) { // Use 1 hour max window for IP tracking
            $ipAttempts = ['count' => 1, 'first_attempt' => $now];
        } else {
            $ipAttempts['count']++;
        }
        
        saveIpRateLimitData($key, $clientIp, $ipAttempts);
    }
    
    // Periodically clean up old rate limit files (1% chance per request)
    if (mt_rand(1, 100) === 1) {
        cleanupRateLimitFiles();
    }
}

/**
 * Execute a handler with rate limiting
 * 
 * Wraps a callable with rate limiting checks. If rate limited, calls the error handler.
 * Otherwise, records the attempt and executes the handler.
 * 
 * @param string $key Unique identifier for the rate limit (e.g., 'api_favorites', 'oauth_callback')
 * @param int $maxAttempts Maximum number of attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @param callable $handler The handler function to execute if not rate limited
 * @param string|null $identifier Optional identifier (e.g., user ID, IP) for per-user/IP rate limiting
 * @param string $errorMessage Optional custom error message (default: 'Too many requests. Please slow down.')
 * @param int $errorCode Optional HTTP error code (default: 429)
 * @return mixed The return value of the handler function
 */
function withRateLimit(
    string $key,
    int $maxAttempts,
    int $windowSeconds,
    callable $handler,
    ?string $identifier = null,
    string $errorMessage = 'Too many requests. Please slow down.',
    int $errorCode = 429
): mixed {
    if (isRateLimited($key, $maxAttempts, $windowSeconds, $identifier)) {
        // Check if jsonError function exists (for API endpoints)
        if (function_exists('jsonError')) {
            jsonError($errorMessage, $errorCode);
        } else {
            // For non-API contexts, throw an exception or use a different error handler
            throw new RuntimeException($errorMessage);
        }
    }
    
    recordRateLimitAttempt($key, $identifier);
    return $handler();
}

/**
 * Clear rate limit for a specific key
 * 
 * Clears both session-based and IP-based rate limit data.
 * 
 * @param string $key Unique identifier for the rate limit
 * @param string|null $identifier Optional identifier (e.g., user ID)
 */
function clearRateLimit(string $key, ?string $identifier = null): void
{
    // Clear session-based rate limit
    $sessionKey = $identifier ? "rate_limit_{$key}_{$identifier}" : "rate_limit_{$key}";
    unset($_SESSION[$sessionKey]);
    
    // Clear IP-based rate limit (for anonymous requests)
    if ($identifier === null) {
        $clientIp = getClientIp();
        $filename = getRateLimitStoragePath() . '/' . md5($key . '_' . $clientIp) . '.json';
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }
}

