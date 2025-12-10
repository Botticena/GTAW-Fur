<?php
/**
 * GTAW Furniture Catalog - OAuth Callback Handler
 * 
 * Processes the OAuth callback from GTA World and creates user session.
 * Supports multiple communities (EN/FR).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

// Get community from session (set during login initiation)
$community = $_SESSION['oauth_community'] ?? getCurrentCommunity();
unset($_SESSION['oauth_community']);

// Validate community
if (!in_array($community, SUPPORTED_COMMUNITIES, true)) {
    $community = DEFAULT_COMMUNITY;
}

// Check if community is enabled
if (!isCommunityEnabled($community)) {
    oauthError(__('login.community_disabled'));
}

// Get OAuth config for this community
try {
    $oauth = getOAuthConfig($community);
} catch (RuntimeException $e) {
    oauthError(__('login.oauth_not_configured'));
}

/**
 * Error helper - shows localized error page
 */
function oauthError(string $message): never
{
    global $community;
    
    // Log via centralized helper if available
    if (function_exists('logException')) {
        logException('oauth', new RuntimeException($message));
    } else {
        error_log("OAuth Error: {$message}");
    }
    
    $locale = getCurrentLocale();
    $langCode = getCommunityLangCode($locale);
    
    // Show user-friendly error page
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="<?= e($langCode) ?>" data-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e(__('login.error_title')) ?> - GTAW Furniture Catalog</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .error-box { max-width: 400px; text-align: center; }
        </style>
    </head>
    <body>
        <main class="container">
            <article class="error-box">
                <header>
                    <h1><?= e(__('login.error_title')) ?></h1>
                </header>
                <p><?= e($message) ?></p>
                <footer>
                    <a href="/" role="button"><?= e(__('login.return_to_catalog')) ?></a>
                </footer>
            </article>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Rate limiting for OAuth callback attempts (per IP)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (isRateLimited('oauth_callback', 10, 300, $clientIp)) { // 10 attempts per 5 minutes per IP
    oauthError(__('login.rate_limited'));
}

// 1. Verify state parameter (CSRF protection)
$state = getQuery('state', '');
$storedState = $_SESSION['oauth_state'] ?? '';

if (empty($state) || empty($storedState) || !hash_equals($storedState, $state)) {
    recordRateLimitAttempt('oauth_callback', $clientIp);
    oauthError(__('login.invalid_state'));
}

// Clear used state
unset($_SESSION['oauth_state']);

// 2. Check for errors from OAuth provider
if (isset($_GET['error'])) {
    $errorDesc = getQuery('error_description', getQuery('error', ''));
    oauthError(__('login.denied') . ": {$errorDesc}");
}

// 3. Check for authorization code
$code = getQuery('code', '');
if (empty($code)) {
    oauthError(__('login.no_code'));
}

// 4. Exchange code for access token (using community-specific config)
$tokenResponse = exchangeCodeForToken($code, $oauth);
if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
    recordRateLimitAttempt('oauth_callback', $clientIp);
    oauthError(__('login.token_failed'));
}

// 5. Fetch user data from GTAW API (using community-specific config)
$userData = fetchGtawUserData($tokenResponse['access_token'], $oauth);
if (!$userData || !isset($userData['user'])) {
    recordRateLimitAttempt('oauth_callback', $clientIp);
    oauthError(__('login.user_failed'));
}

// 6. Extract user information
$gtawUser = $userData['user'];

$gtawId = (int) ($gtawUser['id'] ?? 0);
if ($gtawId <= 0) {
    recordRateLimitAttempt('oauth_callback', $clientIp);
    oauthError(__('login.invalid_data'));
}

$username = $gtawUser['username'] ?? 'Unknown';

// Extract role_id from nested role object
$gtawRole = null;
if (isset($gtawUser['role']) && is_array($gtawUser['role'])) {
    $gtawRole = $gtawUser['role']['role_id'] ?? null;
}

// Get first character's full name if available
$mainCharacter = null;
if (!empty($gtawUser['character']) && is_array($gtawUser['character'])) {
    $firstChar = $gtawUser['character'][0] ?? null;
    if ($firstChar && isset($firstChar['firstname'], $firstChar['lastname'])) {
        $mainCharacter = trim($firstChar['firstname'] . ' ' . $firstChar['lastname']);
    }
}

// 7. Create or update user in our database (with community)
try {
    $pdo = getDb();
} catch (RuntimeException $e) {
    throw new RuntimeException('Database connection not available');
}

// Check if user exists (to determine if this is a new registration)
$stmt = $pdo->prepare('SELECT * FROM users WHERE gtaw_id = ? AND community = ?');
$stmt->execute([$gtawId, $community]);
$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

// If this is a new user registration, check if registration is enabled
if (!$existingUser && !isFeatureEnabled('registration_enabled')) {
    oauthError(__('login.registration_disabled'));
}

try {
    $user = createOrUpdateUser($pdo, $gtawId, $community, $username, $gtawRole, $mainCharacter);
} catch (Exception $e) {
    error_log("Failed to create/update user: " . $e->getMessage());
    oauthError(__('login.process_failed'));
}

// 8. Check if user is banned
if (!empty($user['is_banned'])) {
    $reason = $user['ban_reason'] ?? 'No reason provided.';
    oauthError(__('login.banned', ['reason' => $reason]));
}

// 9. Create session (access token is NOT stored)
createUserSession($user, $community);

// Clear rate limit on successful OAuth
clearRateLimit('oauth_callback', $clientIp);

// 10. Redirect to home page
redirect('/');
