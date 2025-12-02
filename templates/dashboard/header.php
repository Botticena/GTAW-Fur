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
$pageTitle = isset($pageTitle) ? "{$pageTitle} - Dashboard" : "Dashboard - {$appName}";
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?= e($pageTitle) ?></title>
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    
    <!-- Custom Styles - Use admin.css for consistency -->
    <link rel="stylesheet" href="/css/style.css">
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
                <span>My Dashboard</span>
            </a>
            
            <nav>
                <ul class="admin-nav">
                    <li>
                        <a href="/dashboard/" class="<?= $currentPage === 'overview' ? 'active' : '' ?>">
                            <span class="nav-icon">📊</span>
                            Overview
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard/?page=favorites" class="<?= $currentPage === 'favorites' ? 'active' : '' ?>">
                            <span class="nav-icon">❤️</span>
                            Favorites
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard/?page=collections" class="<?= $currentPage === 'collections' ? 'active' : '' ?>">
                            <span class="nav-icon">📁</span>
                            Collections
                        </a>
                    </li>
                    <li>
                        <a href="/dashboard/?page=submissions" class="<?= $currentPage === 'submissions' ? 'active' : '' ?>">
                            <span class="nav-icon">📝</span>
                            Submissions
                        </a>
                    </li>
                    
                    <li class="admin-nav-divider"></li>
                    
                    <li>
                        <a href="/">
                            <span class="nav-icon">🔍</span>
                            Browse Catalog
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="admin-user">
                <p>Logged in as <strong><?= e($user['username'] ?? 'User') ?></strong></p>
                <a href="/logout.php" class="btn btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="admin-main">
