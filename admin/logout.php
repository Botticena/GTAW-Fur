<?php
/**
 * GTAW Furniture Catalog - Admin Logout Handler
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Destroy admin session
destroyAdminSession();

// Redirect to admin login
redirect('/admin/login.php');

