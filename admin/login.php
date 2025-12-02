<?php
/**
 * GTAW Furniture Catalog - Admin Login Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';
$appName = config('app.name', 'GTAW Furniture Catalog');

// Already logged in? Redirect to dashboard
if (isAdminLoggedIn()) {
    redirect('/admin/');
}

// Handle login form submission
if (requestMethod() === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken(getPost('csrf_token', ''))) {
        $error = 'Invalid request. Please try again.';
    } elseif (isAdminLoginRateLimited()) {
        $error = 'Too many failed attempts. Please wait 15 minutes.';
    } else {
        $username = getPost('username', '');
        $password = getPost('password', '');
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
            recordFailedAdminLogin();
        } else {
            try {
                $pdo = getDb();
            } catch (RuntimeException $e) {
                throw new RuntimeException('Database connection not available');
            }
            $admin = verifyAdminCredentials($pdo, $username, $password);
            
            if ($admin) {
                clearAdminLoginRateLimit();
                createAdminSession($admin);
                redirect('/admin/');
            } else {
                recordFailedAdminLogin();
                $error = 'Invalid username or password.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    
    <title>Admin Login - <?= e($appName) ?></title>
    
    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
</head>
<body class="admin-login-page">
    <div class="admin-login-box">
        <h1>🔐 Admin Login</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    autofocus
                    autocomplete="username"
                    value="<?= e(getPost('username', '')) ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg">Login</button>
        </form>
        
        <a href="/" class="back-link">← Back to Catalog</a>
    </div>
</body>
</html>

