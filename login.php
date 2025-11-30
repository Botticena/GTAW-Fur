<?php
/**
 * GTAW Furniture Catalog - OAuth Login Initiator
 * 
 * Redirects users to GTA World OAuth for authentication.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Redirect home
if (isLoggedIn()) {
    redirect('/');
}

// Get OAuth configuration
$clientId = config('oauth.client_id');
$redirectUri = config('oauth.redirect_uri');
$authorizeUrl = config('oauth.authorize_url', 'https://ucp.gta.world/oauth/authorize');

if (empty($clientId) || empty($redirectUri)) {
    http_response_code(500);
    exit('OAuth is not configured. Please contact the administrator.');
}

// Generate state token for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Build authorization URL
$authUrl = $authorizeUrl . '?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => '',
    'state' => $state,
]);

// Redirect to GTA World OAuth
header('Location: ' . $authUrl);
exit;

