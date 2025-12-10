<?php
/**
 * GTAW Furniture Catalog - Community Helpers
 * 
 * Handles multi-community support for GTA World EN and FR servers.
 * Communities affect OAuth routing and user identity.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'community.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Supported community identifiers
 */
const SUPPORTED_COMMUNITIES = ['en', 'fr'];

/**
 * Default community
 */
const DEFAULT_COMMUNITY = 'en';

/**
 * Cookie names for community and locale preferences
 */
const COMMUNITY_COOKIE = 'gtaw_community';
const LOCALE_COOKIE = 'gtaw_locale';

/**
 * Community metadata
 */
const COMMUNITY_META = [
    'en' => [
        'name' => 'GTA World (English)',
        'short_name' => 'English',
        'flag' => '🇬🇧',
        'lang_code' => 'en',
        'ucp_domain' => 'ucp.gta.world',
    ],
    'fr' => [
        'name' => 'GTA World (French)',
        'short_name' => 'Français',
        'flag' => '🇫🇷',
        'lang_code' => 'fr',
        'ucp_domain' => 'ucp-fr.gta.world',
    ],
];

/**
 * Get current community from session, cookie, or default
 * Priority: Session > Cookie > Default
 * 
 * @return string Community identifier ('en' or 'fr')
 */
function getCurrentCommunity(): string
{
    // 1. Session takes priority (logged-in user's community)
    if (isset($_SESSION['community']) && in_array($_SESSION['community'], SUPPORTED_COMMUNITIES, true)) {
        return $_SESSION['community'];
    }
    
    // 2. Cookie for preference persistence
    if (isset($_COOKIE[COMMUNITY_COOKIE]) && in_array($_COOKIE[COMMUNITY_COOKIE], SUPPORTED_COMMUNITIES, true)) {
        return $_COOKIE[COMMUNITY_COOKIE];
    }
    
    // 3. Config default or hardcoded default
    return config('app.default_community', DEFAULT_COMMUNITY);
}

/**
 * Set current community preference
 * Updates both cookie and session
 * 
 * @param string $community Community identifier ('en' or 'fr')
 * @return bool True if set successfully
 */
function setCurrentCommunity(string $community): bool
{
    if (!in_array($community, SUPPORTED_COMMUNITIES, true)) {
        return false;
    }
    
    // Set cookie for persistent preference (1 year)
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie(COMMUNITY_COOKIE, $community, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Also set in session for immediate use
    $_SESSION['community'] = $community;
    
    return true;
}

/**
 * Get current locale (UI language)
 * Can differ from community for bilingual users
 * Priority: Session > Cookie > Community
 * 
 * @return string Locale identifier ('en' or 'fr')
 */
function getCurrentLocale(): string
{
    // 1. Session locale preference
    if (isset($_SESSION['locale']) && in_array($_SESSION['locale'], SUPPORTED_COMMUNITIES, true)) {
        return $_SESSION['locale'];
    }
    
    // 2. Cookie for preference persistence
    if (isset($_COOKIE[LOCALE_COOKIE]) && in_array($_COOKIE[LOCALE_COOKIE], SUPPORTED_COMMUNITIES, true)) {
        return $_COOKIE[LOCALE_COOKIE];
    }
    
    // 3. Default to current community
    return getCurrentCommunity();
}

/**
 * Set current locale preference
 * 
 * @param string $locale Locale identifier ('en' or 'fr')
 * @return bool True if set successfully
 */
function setCurrentLocale(string $locale): bool
{
    if (!in_array($locale, SUPPORTED_COMMUNITIES, true)) {
        return false;
    }
    
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie(LOCALE_COOKIE, $locale, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    $_SESSION['locale'] = $locale;
    
    return true;
}

/**
 * Get OAuth configuration for a specific community
 * 
 * @param string $community Community identifier ('en' or 'fr')
 * @return array OAuth configuration array
 * @throws RuntimeException If OAuth not configured for community
 */
function getOAuthConfig(string $community): array
{
    if (!in_array($community, SUPPORTED_COMMUNITIES, true)) {
        throw new RuntimeException("Invalid community: {$community}");
    }
    
    // Try community-specific config first
    $communityConfig = config("oauth.{$community}");
    if ($communityConfig && !empty($communityConfig['client_id'])) {
        return $communityConfig;
    }
    
    // Fallback to legacy flat config (for backwards compatibility during migration)
    $legacyConfig = [
        'client_id' => config('oauth.client_id'),
        'client_secret' => config('oauth.client_secret'),
        'redirect_uri' => config('oauth.redirect_uri'),
        'authorize_url' => config('oauth.authorize_url', 'https://ucp.gta.world/oauth/authorize'),
        'token_url' => config('oauth.token_url', 'https://ucp.gta.world/oauth/token'),
        'user_url' => config('oauth.user_url', 'https://ucp.gta.world/api/user'),
    ];
    
    // Only return legacy config if it's for EN community (original config)
    if ($community === 'en' && !empty($legacyConfig['client_id'])) {
        return $legacyConfig;
    }
    
    throw new RuntimeException("OAuth not configured for community: {$community}");
}

/**
 * Check if OAuth is configured for a community
 * 
 * @param string $community Community identifier
 * @return bool True if configured
 */
function isOAuthConfigured(string $community): bool
{
    try {
        $config = getOAuthConfig($community);
        return !empty($config['client_id']) && !empty($config['client_secret']);
    } catch (RuntimeException $e) {
        return false;
    }
}

/**
 * Get community display name
 * 
 * @param string $community Community identifier
 * @return string Display name
 */
function getCommunityName(string $community): string
{
    return COMMUNITY_META[$community]['name'] ?? 'Unknown';
}

/**
 * Get community short name
 * 
 * @param string $community Community identifier
 * @return string Short name
 */
function getCommunityShortName(string $community): string
{
    return COMMUNITY_META[$community]['short_name'] ?? 'Unknown';
}

/**
 * Get community flag emoji
 * 
 * @param string $community Community identifier
 * @return string Flag emoji
 */
function getCommunityFlag(string $community): string
{
    return COMMUNITY_META[$community]['flag'] ?? '🌐';
}

/**
 * Get community language code for HTML lang attribute
 * 
 * @param string $community Community identifier
 * @return string Language code (e.g., 'en', 'fr')
 */
function getCommunityLangCode(string $community): string
{
    return COMMUNITY_META[$community]['lang_code'] ?? 'en';
}

/**
 * Get all supported communities with metadata
 * Filters out disabled communities based on settings
 * Cached with APCu for performance
 * 
 * @return array Array of community data
 */
function getSupportedCommunities(): array
{
    // Try APCu cache first (1 minute TTL - community settings don't change often)
    $cacheKey = 'gtaw_communities_v1';
    $cached = cacheGet($cacheKey, null, $success);
    if ($success && is_array($cached)) {
        return $cached;
    }
    
    // Cache miss - build communities list
    $communities = [];
    foreach (SUPPORTED_COMMUNITIES as $id) {
        // Check if community is enabled via settings
        if (!isCommunityEnabled($id)) {
            continue;
        }
        
        $communities[$id] = [
            'id' => $id,
            'name' => getCommunityName($id),
            'short_name' => getCommunityShortName($id),
            'flag' => getCommunityFlag($id),
            'lang_code' => getCommunityLangCode($id),
            'oauth_configured' => isOAuthConfigured($id),
        ];
    }
    
    // Store in APCu cache
    cacheSet($cacheKey, $communities, 60);
    
    return $communities;
}

/**
 * Clear communities cache
 * Call this when community settings change
 */
function clearCommunitiesCache(): void
{
    cacheDelete('gtaw_communities_v1');
}

/**
 * Handle community/locale switch request
 * Call this early in init.php to process ?set_community= or ?set_locale= params
 */
function handleCommunitySwitch(): void
{
    // Handle community switch
    if (isset($_GET['set_community'])) {
        $community = $_GET['set_community'];
        if (in_array($community, SUPPORTED_COMMUNITIES, true)) {
            setCurrentCommunity($community);
            // Also update locale to match community by default
            setCurrentLocale($community);
        }
        
        // Remove the parameter and redirect to clean URL
        $params = $_GET;
        unset($params['set_community']);
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        header('Location: ' . $url);
        exit;
    }
    
    // Handle locale-only switch (for users who want different UI language than their community)
    if (isset($_GET['set_locale'])) {
        $locale = $_GET['set_locale'];
        if (in_array($locale, SUPPORTED_COMMUNITIES, true)) {
            setCurrentLocale($locale);
        }
        
        $params = $_GET;
        unset($params['set_locale']);
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        header('Location: ' . $url);
        exit;
    }
}
