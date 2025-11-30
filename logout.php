<?php
/**
 * GTAW Furniture Catalog - User Logout
 * 
 * Destroys user session and redirects to home.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';

// Destroy user session
destroyUserSession();

// Redirect to home
redirect('/');

