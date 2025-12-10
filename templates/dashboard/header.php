<?php
/**
 * GTAW Furniture Catalog - User Dashboard Header Template
 * Uses admin panel styling for consistency
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

$user = getCurrentUser();
$currentPage = getQuery('page', 'overview');
$appName = config('app.name', 'GTAW Furniture Catalog');
$pageTitle = isset($pageTitle) ? "{$pageTitle} - " . __('dashboard.title') : __('dashboard.title') . " - {$appName}";

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
    <meta name="robots" content="noindex, nofollow">
    
    <title><?= e($pageTitle) ?></title>
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
    
    <!-- Current locale for JS -->
    <meta name="locale" content="<?= e($currentLocale) ?>">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/admin.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🪑</text></svg>">
    
    <!-- Theme script -->
    <script>
        (function() {
            const theme = localStorage.getItem('gtaw_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <a href="/" class="admin-logo">
                <span>🪑</span>
                <span><?= e(__('dashboard.title')) ?></span>
            </a>
            
            <nav>
                <ul class="admin-nav">
                    <li>
                        <a href="/dashboard/" class="<?= $currentPage === 'overview' ? 'active' : '' ?>">
                            <span class="nav-icon">📊</span>
                            <?= e(__('dashboard.overview')) ?>
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard/?page=favorites" class="<?= $currentPage === 'favorites' ? 'active' : '' ?>">
                            <span class="nav-icon">❤️</span>
                            <?= e(__('dashboard.favorites')) ?>
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard/?page=collections" class="<?= $currentPage === 'collections' ? 'active' : '' ?>">
                            <span class="nav-icon">📁</span>
                            <?= e(__('dashboard.collections')) ?>
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard/?page=submissions" class="<?= $currentPage === 'submissions' ? 'active' : '' ?>">
                            <span class="nav-icon">📝</span>
                            <?= e(__('dashboard.submissions')) ?>
                        </a>
                    </li>
                    
                    <li class="admin-nav-divider"></li>
                    
                    <li>
                        <a href="/">
                            <span class="nav-icon">🔍</span>
                            <?= e(__('dashboard.browse')) ?>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="admin-user">
                <!-- Community/Locale Switcher in Sidebar -->
                <div class="sidebar-community-switcher" id="sidebar-community-switcher">
                    <button class="sidebar-community-toggle" id="sidebar-community-toggle"
                            title="<?= e(__('community.switch')) ?>"
                            aria-expanded="false">
                        <?= getCommunityFlag($currentLocale) ?>
                        <span class="sidebar-community-name"><?= e(getCommunityShortName($currentLocale)) ?></span>
                        <span class="sidebar-community-arrow">▼</span>
                    </button>
                    <div class="sidebar-community-dropdown" id="sidebar-community-dropdown">
                        <?php foreach ($communities as $comm): ?>
                        <a href="?set_community=<?= e($comm['id']) ?>&page=<?= e($currentPage) ?>" 
                           class="sidebar-community-option <?= $comm['id'] === $currentCommunity ? 'active' : '' ?>">
                            <span><?= $comm['flag'] ?></span>
                            <span><?= e($comm['short_name']) ?></span>
                            <?php if ($comm['id'] === $currentCommunity): ?>
                            <span class="check">✓</span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <p><?= e(__('dashboard.logged_in_as')) ?> <strong><?= e($user['username'] ?? 'User') ?></strong></p>
                <a href="/logout.php" class="btn btn-sm"><?= e(__('nav.logout')) ?></a>
            </div>
        </aside>
        
        <main class="admin-main">
