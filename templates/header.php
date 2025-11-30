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
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪑</text></svg>">
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
                <?php if ($currentUser): ?>
                    <div class="user-info">
                        Welcome, <strong><?= e($currentUser['main_character'] ?? $currentUser['username']) ?></strong>
                        <a href="/logout.php" class="btn-logout">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="/login.php" class="btn-login">
                        Login with GTA World
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main id="main-content">

