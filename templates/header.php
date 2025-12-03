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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse and discover furniture items for GTA World interior mapping. Find props, copy commands, and save favorites.">
    <meta name="theme-color" content="#0a0a0a">
    
    <title><?= e($pageTitle) ?></title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
    
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
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <header class="site-header">
        <div class="container">
            <a href="/" class="site-logo">
                <span class="logo-icon">🪑</span>
                <span class="logo-text"><?= e($appName) ?></span>
            </a>
            
            <div class="header-actions">
                <button class="theme-toggle" id="theme-toggle" title="Toggle theme" aria-label="Toggle dark/light theme">
                    <span class="icon-sun">☀️</span>
                    <span class="icon-moon">🌙</span>
                </button>
                <?php if ($currentUser): ?>
                    <a href="/dashboard/" class="btn-dashboard" title="My Dashboard">
                        📊 Dashboard
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn-login">
                        Login with GTA World
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main id="main-content">

