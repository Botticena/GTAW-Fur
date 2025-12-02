<?php
/**
 * GTAW Furniture Catalog - Admin Header Template
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

$admin = getCurrentAdmin();
$currentPage = getQuery('page', 'dashboard');
$appName = config('app.name', 'GTAW Furniture Catalog');
$pageTitle = isset($pageTitle) ? "{$pageTitle} - Admin" : "Admin - {$appName}";
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
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <a href="/admin/" class="admin-logo">
                <span>⚙️</span>
                <span>Admin Panel</span>
            </a>
            
            <nav>
                <ul class="admin-nav">
                    <li>
                        <a href="/admin/?page=dashboard" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                            <span class="nav-icon">📊</span>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="/admin/?page=furniture" class="<?= $currentPage === 'furniture' ? 'active' : '' ?>">
                            <span class="nav-icon">🪑</span>
                            Furniture
                        </a>
                    </li>
                    <li>
                        <a href="/admin/?page=categories" class="<?= $currentPage === 'categories' ? 'active' : '' ?>">
                            <span class="nav-icon">📁</span>
                            Categories
                        </a>
                    </li>
                    <li>
                        <a href="/admin/?page=tags" class="<?= $currentPage === 'tags' ? 'active' : '' ?>">
                            <span class="nav-icon">🏷️</span>
                            Tags
                        </a>
                    </li>
                    <li>
                        <a href="/admin/?page=tag-groups" class="<?= $currentPage === 'tag-groups' ? 'active' : '' ?>">
                            <span class="nav-icon">📂</span>
                            Tag Groups
                        </a>
                    </li>
                    
                    <li class="admin-nav-divider"></li>
                    
                    <li>
                        <a href="/admin/?page=import" class="<?= $currentPage === 'import' ? 'active' : '' ?>">
                            <span class="nav-icon">📥</span>
                            Import CSV
                        </a>
                    </li>
                    <li>
                        <a href="/admin/?page=export" class="<?= $currentPage === 'export' ? 'active' : '' ?>">
                            <span class="nav-icon">📤</span>
                            Export Data
                        </a>
                    </li>
                    
                    <li class="admin-nav-divider"></li>
                    
                    <li>
                        <a href="/admin/?page=users" class="<?= $currentPage === 'users' ? 'active' : '' ?>">
                            <span class="nav-icon">👥</span>
                            Users
                        </a>
                    </li>
                    <li>
                        <?php 
                        require_once __DIR__ . '/../../includes/submissions.php';
                        try {
                            $pdo = getDb();
                            $pendingCount = getPendingSubmissionsCount($pdo);
                        } catch (RuntimeException $e) {
                            $pendingCount = 0;
                        }
                        ?>
                        <a href="/admin/?page=submissions" class="<?= $currentPage === 'submissions' ? 'active' : '' ?>">
                            <span class="nav-icon">📝</span>
                            Submissions
                            <?php if ($pendingCount > 0): ?>
                            <span class="nav-badge"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="admin-nav-divider"></li>
                    
                    <li>
                        <a href="/" target="_blank">
                            <span class="nav-icon">🌐</span>
                            View Site
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="admin-user">
                <p>Logged in as <strong><?= e($admin['username'] ?? 'Admin') ?></strong></p>
                <a href="/admin/logout.php" class="btn btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="admin-main">

