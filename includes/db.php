<?php
/**
 * GTAW Furniture Catalog - Database Connection
 * 
 * Establishes PDO connection to MySQL database.
 * Included by init.php - do not include directly.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Global PDO instance
$pdo = null;

/**
 * Get database connection (singleton pattern)
 */
function getDb(): PDO
{
    global $pdo;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    $configFile = dirname(__DIR__) . '/config.php';
    
    if (!file_exists($configFile)) {
        throw new RuntimeException(
            'Configuration file not found.'
        );
    }
    
    $config = require $configFile;
    
    if (!isset($config['db'])) {
        throw new RuntimeException('Database configuration missing in config.php');
    }
    
    $db = $config['db'];
    $host = $db['host'] ?? 'localhost';
    $name = $db['name'] ?? '';
    $user = $db['user'] ?? '';
    $pass = $db['pass'] ?? '';
    $charset = $db['charset'] ?? 'utf8mb4';
    
    if (empty($name) || empty($user)) {
        throw new RuntimeException('Database name and user are required in config.php');
    }
    
    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
    ];
    
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed. Please check your configuration.');
    }
    
    return $pdo;
}

// Initialize connection
try {
    $pdo = getDb();
} catch (RuntimeException $e) {
    // Allow application to run for setup/error pages
    $pdo = null;
}

