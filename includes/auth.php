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
function createOrUpdateUser(int $gtawId, string $username, ?string $gtawRole, ?string $mainCharacter): array
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database connection not available');
    }
    
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
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
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
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Admin authentication required']);
            exit;
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
function verifyAdminCredentials(string $username, string $password): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
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
 */
function isAdminLoginRateLimited(): bool
{
    $key = 'admin_login_attempts';
    $maxAttempts = 5;
    $windowSeconds = 900; // 15 minutes
    
    $now = time();
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => $now];
    
    // Reset if window has passed
    if (($now - $attempts['first_attempt']) >= $windowSeconds) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => $now];
        return false;
    }
    
    return $attempts['count'] >= $maxAttempts;
}

/**
 * Record a failed admin login attempt
 */
function recordFailedAdminLogin(): void
{
    $key = 'admin_login_attempts';
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => $now];
    } else {
        $_SESSION[$key]['count']++;
    }
}

/**
 * Clear admin login rate limit
 */
function clearAdminLoginRateLimit(): void
{
    unset($_SESSION['admin_login_attempts']);
}

