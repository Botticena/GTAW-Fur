<?php
/**
 * GTAW Furniture Catalog - Header Template
 * 
 * Include at the beginning of pages after init.php
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('APP_LOADED')) {
    define('APP_LOADED', true);
}

require_once __DIR__ . '/../includes/auth.php';

$currentUser = getCurrentUser();
$appName = config('app.name', 'GTAW Furniture Catalog');
$pageTitle = isset($pageTitle) ? "{$pageTitle} - {$appName}" : $appName;

// Get current locale and community
$currentLocale = getCurrentLocale();
$currentCommunity = getCurrentCommunity();
$langCode = getCommunityLangCode($currentLocale);
$communities = getSupportedCommunities();
?>
<!DOCTYPE html>
<html lang="<?= e($langCode) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse and discover furniture items for GTA World interior mapping. Find props, copy commands, and save favorites.">
    <meta name="theme-color" content="#0a0a0a">
    
    <title><?= e($pageTitle) ?></title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
    
    <!-- Current locale for JS -->
    <meta name="locale" content="<?= e($currentLocale) ?>">
    
    <!-- Preconnect for external resources -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪑</text></svg>">
    
    <!-- Inline critical theme script to prevent flash -->
    <script>
        (function() {
            const theme = localStorage.getItem('gtaw_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <a href="#main-content" class="skip-link"><?= e(__('nav.skip_to_content')) ?></a>
    
    <header class="site-header">
        <div class="container">
            <a href="/" class="site-logo">
                <span class="logo-icon">🪑</span>
                <span class="logo-text"><?= e($appName) ?></span>
            </a>
            
            <div class="header-actions">
                <!-- Community/Locale Switcher -->
                <div class="community-switcher" id="community-switcher">
                    <button class="community-toggle" id="community-toggle" 
                            title="<?= e(__('community.switch')) ?>" 
                            aria-label="<?= e(__('community.switch')) ?>"
                            aria-expanded="false"
                            aria-haspopup="true">
                        <?= getCommunityFlag($currentLocale) ?>
                        <span class="community-toggle-arrow">▼</span>
                    </button>
                    <div class="community-dropdown" id="community-dropdown" role="menu">
                        <?php foreach ($communities as $comm): ?>
                        <a href="?set_community=<?= e($comm['id']) ?>" 
                           class="community-option <?= $comm['id'] === $currentCommunity ? 'active' : '' ?>"
                           role="menuitem"
                           <?php if (!$comm['oauth_configured']): ?>
                           title="<?= e(__('login.oauth_not_configured')) ?>"
                           <?php endif; ?>>
                            <span class="community-flag"><?= $comm['flag'] ?></span>
                            <span class="community-name"><?= e($comm['short_name']) ?></span>
                            <?php if ($comm['id'] === $currentCommunity): ?>
                            <span class="community-check">✓</span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button class="theme-toggle" id="theme-toggle" title="<?= e(__('theme.toggle')) ?>" aria-label="<?= e(__('theme.toggle')) ?>">
                    <span class="icon-sun">☀️</span>
                    <span class="icon-moon">🌙</span>
                </button>
                
                <?php if ($currentUser): ?>
                    <a href="/dashboard/" class="btn-dashboard" title="<?= e(__('nav.dashboard')) ?>">
                        📊 <?= e(__('nav.dashboard')) ?>
                    </a>
                <?php else: ?>
                    <a href="/login.php?community=<?= e($currentCommunity) ?>" class="btn-login">
                        <?= e(__('nav.login')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main id="main-content">
