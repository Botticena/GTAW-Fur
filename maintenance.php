<?php
/**
 * GTAW Furniture Catalog - Maintenance Mode Page
 * 
 * Displayed when the site is in maintenance mode.
 * Allows admins to bypass and access the site.
 */

declare(strict_types=1);

// Load minimal initialization (without maintenance check to avoid loop)
// We need these files but NOT init.php (which would redirect us back here)

// Start session first (needed for settings)
if (session_status() === PHP_SESSION_NONE) {
    // Minimal session config
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Load database connection
require_once __DIR__ . '/includes/db.php';

// Load utility functions (needed by settings.php for cache functions)
require_once __DIR__ . '/includes/utils.php';

// Load settings (needs database and utils)
require_once __DIR__ . '/includes/settings.php';

// Check if maintenance mode is actually enabled
if (!isMaintenanceMode()) {
    header('Location: /');
    exit;
}

// Load helper functions (e() for escaping)
if (!function_exists('e')) {
    function e(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Load other helpers (after maintenance check to avoid unnecessary work)
require_once __DIR__ . '/includes/community.php';
require_once __DIR__ . '/includes/i18n.php';

// Check if user is an admin (allow them to continue to main site)
$isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get current locale
$locale = getCurrentLocale();
$langCode = $locale === 'fr' ? 'fr' : 'en';

// Get maintenance message
$maintenanceMessage = getMaintenanceMessage();
if (empty($maintenanceMessage)) {
    $maintenanceMessage = __('maintenance.message');
}
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(__('maintenance.title')) ?> - GTAW Furniture Catalog</title>
    <style>
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --bg-dark: #0a0a0a;
            --bg-surface: #141414;
            --bg-card: #1a1a1a;
            --bg-elevated: #262626;
            --text-primary: #fafafa;
            --text-secondary: #a3a3a3;
            --text-muted: #737373;
            --border-color: #2a2a2a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-dark);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            padding: 1rem;
        }
        
        .maintenance-container {
            text-align: center;
            max-width: 600px;
            width: 100%;
            padding: 3rem 2rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        
        .maintenance-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            display: block;
        }
        
        .maintenance-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .maintenance-message {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .admin-notice {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 2rem;
        }
        
        .admin-notice p {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .gear-animation {
            animation: spin 4s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <main class="maintenance-container">
        <span class="maintenance-icon gear-animation">⚙️</span>
        <h1 class="maintenance-title"><?= e(__('maintenance.title')) ?></h1>
        <p class="maintenance-message"><?= e($maintenanceMessage) ?></p>
        
        <?php if ($isAdmin): ?>
        <div class="admin-notice">
            <p>🔐 <?= e(__('maintenance.admin_notice')) ?></p>
            <a href="/" class="btn">
                📂 <?= e(__('nav.browse')) ?>
            </a>
            <a href="/admin/" class="btn" style="margin-left: 0.5rem;">
                ⚙️ Admin Panel
            </a>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
