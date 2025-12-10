<?php
/**
 * GTAW Furniture Catalog - Settings Management
 * 
 * Provides database-backed settings with caching and type conversion.
 * Settings can be managed through the admin panel.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'settings.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

/**
 * Settings cache to avoid repeated database queries
 */
$GLOBALS['_settings_cache'] = null;

/**
 * Get a setting value from the database
 * Falls back to config() if setting doesn't exist in DB
 * 
 * @param string $key Setting key (e.g., 'app.maintenance_mode')
 * @param mixed $default Default value if not found
 * @return mixed The setting value, type-converted appropriately
 */
function getSetting(string $key, mixed $default = null): mixed
{
    // Load cache if needed
    if ($GLOBALS['_settings_cache'] === null) {
        loadSettingsCache();
    }
    
    // Check cache first
    if (isset($GLOBALS['_settings_cache'][$key])) {
        return $GLOBALS['_settings_cache'][$key]['value'];
    }
    
    // Fall back to config() for backwards compatibility
    $configValue = config($key);
    if ($configValue !== null) {
        return $configValue;
    }
    
    return $default;
}

/**
 * Set a setting value in the database
 * 
 * @param string $key Setting key
 * @param mixed $value Value to set
 * @param string|null $type Type hint ('string', 'integer', 'boolean', 'json')
 * @param int|null $adminId Admin user ID who made the change (for audit log)
 * @return bool True on success
 */
function setSetting(string $key, mixed $value, ?string $type = null, ?int $adminId = null): bool
{
    try {
        $pdo = getDb();
    } catch (RuntimeException $e) {
        return false;
    }
    
    // Get old value for audit log
    $oldValue = null;
    try {
        $stmt = $pdo->prepare('SELECT setting_value, setting_type FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($old) {
            $oldValue = convertSettingFromString($old['setting_value'], $old['setting_type']);
        }
    } catch (Exception $e) {
        // Setting doesn't exist yet, that's fine
    }
    
    // Auto-detect type if not provided
    if ($type === null) {
        if (is_bool($value)) {
            $type = 'boolean';
        } elseif (is_int($value)) {
            $type = 'integer';
        } elseif (is_array($value)) {
            $type = 'json';
        } else {
            $type = 'string';
        }
    }
    
    // Convert value to string for storage
    $stringValue = convertSettingToString($value, $type);
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO settings (setting_key, setting_value, setting_type, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()
        ');
        $stmt->execute([$key, $stringValue, $type]);
        
        // Log change to audit log if table exists
        if ($adminId !== null) {
            logSettingChange($pdo, $key, $oldValue, $value, $adminId);
        }
        
        // Update in-memory cache
        $GLOBALS['_settings_cache'][$key] = [
            'value' => $value,
            'type' => $type,
            'raw' => $stringValue,
        ];
        
        // Update APCu cache if it exists
        $cacheKey = 'gtaw_settings_v1';
        $cached = cacheGet($cacheKey, null, $success);
        if ($success && is_array($cached)) {
            $cached[$key] = $GLOBALS['_settings_cache'][$key];
            cacheSet($cacheKey, $cached, 300);
        }
        
        // Clear communities cache if community setting changed
        if (str_starts_with($key, 'community.')) {
            if (function_exists('clearCommunitiesCache')) {
                clearCommunitiesCache();
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to set setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a setting change to the audit log
 * 
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $oldValue Old value
 * @param mixed $newValue New value
 * @param int $adminId Admin user ID
 * @return void
 */
function logSettingChange(PDO $pdo, string $key, mixed $oldValue, mixed $newValue, int $adminId): void
{
    // Check if audit log table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings_audit'");
        if ($stmt->rowCount() === 0) {
            // Table doesn't exist, skip logging
            return;
        }
    } catch (Exception $e) {
        // Table doesn't exist, skip logging
        return;
    }
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO settings_audit (setting_key, old_value, new_value, admin_id, changed_at)
            VALUES (?, ?, ?, ?, NOW())
        ');
        
        $oldValueStr = $oldValue !== null ? json_encode($oldValue) : null;
        $newValueStr = json_encode($newValue);
        
        $stmt->execute([$key, $oldValueStr, $newValueStr, $adminId]);
    } catch (Exception $e) {
        // Log error but don't fail the setting update
        error_log("Failed to log setting change: " . $e->getMessage());
    }
}

/**
 * Delete a setting from the database
 * 
 * @param string $key Setting key
 * @return bool True on success
 */
function deleteSetting(string $key): bool
{
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare('DELETE FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        
        // Remove from cache
        unset($GLOBALS['_settings_cache'][$key]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Failed to delete setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all settings as an array
 * 
 * @param string|null $prefix Only get settings with this key prefix
 * @return array Associative array of settings
 */
function getAllSettings(?string $prefix = null): array
{
    if ($GLOBALS['_settings_cache'] === null) {
        loadSettingsCache();
    }
    
    $settings = [];
    foreach ($GLOBALS['_settings_cache'] as $key => $data) {
        if ($prefix === null || str_starts_with($key, $prefix)) {
            $settings[$key] = $data['value'];
        }
    }
    
    return $settings;
}

/**
 * Get all settings with full metadata (for admin panel)
 * Cached with APCu for performance
 * 
 * @return array Array of settings with metadata
 */
function getAllSettingsWithMeta(): array
{
    // Try APCu cache first (1 minute TTL - metadata doesn't change often)
    $cacheKey = 'gtaw_settings_meta_v1';
    $cached = cacheGet($cacheKey, null, $success);
    if ($success && is_array($cached)) {
        return $cached;
    }
    
    // Cache miss - load from database
    try {
        $pdo = getDb();
        $stmt = $pdo->query('SELECT * FROM settings ORDER BY setting_key');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[] = [
                'id' => (int) $row['id'],
                'key' => $row['setting_key'],
                'value' => convertSettingFromString($row['setting_value'], $row['setting_type']),
                'raw_value' => $row['setting_value'],
                'type' => $row['setting_type'],
                'description' => $row['description'],
                'is_sensitive' => (bool) $row['is_sensitive'],
                'updated_at' => $row['updated_at'],
            ];
        }
        
        // Store in APCu cache
        cacheSet($cacheKey, $settings, 60);
        
        return $settings;
    } catch (Exception $e) {
        error_log("Failed to get all settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Load settings into cache
 * Uses APCu cache if available, falls back to in-memory cache
 */
function loadSettingsCache(): void
{
    // Try APCu cache first (cross-request caching)
    $cacheKey = 'gtaw_settings_v1';
    $cached = cacheGet($cacheKey, null, $success);
    if ($success && is_array($cached)) {
        $GLOBALS['_settings_cache'] = $cached;
        return;
    }
    
    // Cache miss - load from database
    $GLOBALS['_settings_cache'] = [];
    
    try {
        $pdo = getDb();
        $stmt = $pdo->query('SELECT setting_key, setting_value, setting_type FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $GLOBALS['_settings_cache'][$row['setting_key']] = [
                'value' => convertSettingFromString($row['setting_value'], $row['setting_type']),
                'type' => $row['setting_type'],
                'raw' => $row['setting_value'],
            ];
        }
        
        // Store in APCu cache (5 minute TTL - settings don't change often)
        cacheSet($cacheKey, $GLOBALS['_settings_cache'], 300);
    } catch (Exception $e) {
        // Database might not have settings table yet - that's okay
        error_log("Failed to load settings cache: " . $e->getMessage());
    }
}

/**
 * Clear the settings cache
 * Clears both in-memory and APCu cache (including metadata cache)
 */
function clearSettingsCache(): void
{
    $GLOBALS['_settings_cache'] = null;
    cacheDelete('gtaw_settings_v1');
    cacheDelete('gtaw_settings_meta_v1');
}

/**
 * Convert a setting value from string (database storage) to proper type
 * 
 * @param string|null $value Stored string value
 * @param string $type Setting type
 * @return mixed Converted value
 */
function convertSettingFromString(?string $value, string $type): mixed
{
    if ($value === null) {
        return null;
    }
    
    return match ($type) {
        'boolean' => $value === '1' || strtolower($value) === 'true',
        'integer' => (int) $value,
        'json' => json_decode($value, true) ?? [],
        default => $value,
    };
}

/**
 * Convert a setting value to string for database storage
 * 
 * @param mixed $value Value to convert
 * @param string $type Setting type
 * @return string String representation
 */
function convertSettingToString(mixed $value, string $type): string
{
    return match ($type) {
        'boolean' => $value ? '1' : '0',
        'integer' => (string) (int) $value,
        'json' => json_encode($value),
        default => (string) $value,
    };
}

/**
 * Check if maintenance mode is enabled
 * 
 * @return bool True if maintenance mode is active
 */
function isMaintenanceMode(): bool
{
    return (bool) getSetting('app.maintenance_mode', false);
}

/**
 * Get maintenance message
 * 
 * @return string Maintenance message
 */
function getMaintenanceMessage(): string
{
    return (string) getSetting('app.maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
}

/**
 * Check if a feature is enabled
 * 
 * @param string $feature Feature name (e.g., 'submissions_enabled')
 * @return bool True if enabled
 */
function isFeatureEnabled(string $feature): bool
{
    return (bool) getSetting("features.{$feature}", true);
}

/**
 * Check if a community is enabled
 * 
 * @param string $community Community identifier ('en' or 'fr')
 * @return bool True if enabled
 */
function isCommunityEnabled(string $community): bool
{
    return (bool) getSetting("community.{$community}.enabled", true);
}

/**
 * Update multiple settings at once
 * 
 * @param array $settings Associative array of key => value
 * @return bool True if all succeeded
 */
function updateSettings(array $settings): bool
{
    $success = true;
    foreach ($settings as $key => $value) {
        if (!setSetting($key, $value)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Check if settings table exists
 * 
 * @return bool True if table exists
 */
function settingsTableExists(): bool
{
    try {
        $pdo = getDb();
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
