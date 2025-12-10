<?php
/**
 * GTAW Furniture Catalog - OAuth Login Initiator
 * 
 * Redirects users to GTA World OAuth for authentication.
 * Supports multiple communities (EN/FR) via query parameter.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Redirect home
if (isLoggedIn()) {
    redirect('/');
}

// Get community from query param or current setting
$community = getQuery('community', getCurrentCommunity());
if (!in_array($community, SUPPORTED_COMMUNITIES, true)) {
    $community = DEFAULT_COMMUNITY;
}

// Check if community is enabled
if (!isCommunityEnabled($community)) {
    http_response_code(403);
    exit(__('login.community_disabled'));
}

// Store community for callback
$_SESSION['oauth_community'] = $community;

// Update community preference
setCurrentCommunity($community);

// Get OAuth configuration for this community
try {
    $oauth = getOAuthConfig($community);
} catch (RuntimeException $e) {
    http_response_code(500);
    exit(__('login.oauth_not_configured'));
}

$clientId = $oauth['client_id'] ?? '';
$redirectUri = $oauth['redirect_uri'] ?? '';
$authorizeUrl = $oauth['authorize_url'] ?? 'https://ucp.gta.world/oauth/authorize';

if (empty($clientId) || empty($redirectUri)) {
    http_response_code(500);
    exit(__('login.oauth_not_configured'));
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
