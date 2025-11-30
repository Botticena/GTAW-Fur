<?php
/**
 * GTAW Furniture Catalog - OAuth Callback Handler
 * 
 * Processes the OAuth callback from GTA World and creates user session.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

// Error helper
function oauthError(string $message): never
{
    error_log("OAuth Error: {$message}");
    
    // Show user-friendly error page
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Error - GTAW Furniture Catalog</title>
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
                    <h1>Login Failed</h1>
                </header>
                <p><?= e($message) ?></p>
                <footer>
                    <a href="/" role="button">Return to Catalog</a>
                </footer>
            </article>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// 1. Verify state parameter (CSRF protection)
$state = $_GET['state'] ?? '';
$storedState = $_SESSION['oauth_state'] ?? '';

if (empty($state) || empty($storedState) || !hash_equals($storedState, $state)) {
    oauthError('Invalid state parameter. Please try logging in again.');
}

// Clear used state
unset($_SESSION['oauth_state']);

// 2. Check for errors from OAuth provider
if (isset($_GET['error'])) {
    $errorDesc = $_GET['error_description'] ?? $_GET['error'];
    oauthError("Authorization denied: {$errorDesc}");
}

// 3. Check for authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    oauthError('Authorization code not received.');
}

// 4. Exchange code for access token
$tokenResponse = exchangeCodeForToken($code);
if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
    oauthError('Failed to obtain access token. Please try again.');
}

// 5. Fetch user data from GTAW API
$userData = fetchGtawUserData($tokenResponse['access_token']);
if (!$userData || !isset($userData['user'])) {
    oauthError('Failed to retrieve user data. Please try again.');
}

// 6. Extract user information
$gtawUser = $userData['user'];

$gtawId = (int) ($gtawUser['id'] ?? 0);
if ($gtawId <= 0) {
    oauthError('Invalid user data received.');
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

// 7. Create or update user in our database
try {
    $user = createOrUpdateUser($gtawId, $username, $gtawRole, $mainCharacter);
} catch (Exception $e) {
    error_log("Failed to create/update user: " . $e->getMessage());
    oauthError('Failed to process login. Please try again.');
}

// 8. Check if user is banned
if (!empty($user['is_banned'])) {
    $reason = $user['ban_reason'] ?? 'No reason provided.';
    oauthError("Your account has been banned. Reason: {$reason}");
}

// 9. Create session (access token is NOT stored)
createUserSession($user);

// 10. Redirect to home page
redirect('/');

