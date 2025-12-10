<?php
/**
 * GTAW Furniture Catalog - Admin API
 * 
 * Protected API endpoints for admin operations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image.php';
require_once __DIR__ . '/../includes/submissions.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/api-controller.php';
require_once __DIR__ . '/../includes/settings.php';

// Require admin authentication
requireAdmin();

$api = initializeApi();
$method = $api['method'];
$action = $api['action'];
$pdo = $api['pdo'];

/**
 * Validate a setting value based on its key and type
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param string $type Setting type
 * @return string|null Error message if invalid, null if valid
 */
function validateSettingValue(string $key, mixed $value, string $type): ?string
{
    // Integer validation
    if ($type === 'integer') {
        if (!is_int($value)) {
            return 'Must be an integer';
        }
        
        switch ($key) {
            case 'app.items_per_page':
            case 'app.max_items_per_page':
                if ($value < 1) {
                    return 'Must be at least 1';
                }
                if ($value > 1000) {
                    return 'Must be at most 1000';
                }
                break;
        }
    }
    
    // String validation
    if ($type === 'string') {
        if (!is_string($value)) {
            return 'Must be a string';
        }
        
        switch ($key) {
            case 'app.maintenance_message':
                if (strlen($value) > 500) {
                    return 'Must be at most 500 characters';
                }
                break;
        }
    }
    
    // Boolean validation
    if ($type === 'boolean') {
        if (!is_bool($value)) {
            return 'Must be a boolean';
        }
    }
    
    return null;
}

try {
    switch ($action) {

        // =============================================
        // FURNITURE ENDPOINTS
        // =============================================

        case 'furniture/list':
            requireMethod('GET');

            $page = max(1, getQueryInt('page', 1));
            $perPage = getSetting('app.items_per_page', 50);
            $result = getFurnitureList($pdo, $page, $perPage);
            jsonSuccess($result);
            break;

        case 'furniture/get':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            $item = requireFurniture($pdo, $id);

            jsonSuccess($item);
            break;

        case 'furniture/create':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $validation = validateFurnitureInput($input);

            if (!$validation['valid']) {
                jsonError(implode(', ', $validation['errors']));
            }

            $id = createFurniture($pdo, $validation['data']);
            
            $imageUrl = $validation['data']['image_url'] ?? null;
            $processor = new ImageProcessor();
            $processor->processFurnitureImage($pdo, $id, $imageUrl);
            
            jsonSuccess(['id' => $id], 'Furniture created successfully');
            break;

        case 'furniture/update':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            $currentItem = requireFurniture($pdo, $id);

            $input = getJsonInput() ?? $_POST;
            $validation = validateFurnitureInput($input);

            if (!$validation['valid']) {
                jsonError(implode(', ', $validation['errors']));
            }
            $newImageUrl = $validation['data']['image_url'] ?? null;
            $oldImageUrl = $currentItem['image_url'] ?? null;
            
            if ($newImageUrl && $newImageUrl !== $oldImageUrl) {
                $processor = new ImageProcessor();
                $localPath = $processor->processFurnitureImage($pdo, $id, $newImageUrl, $oldImageUrl);
                if ($localPath) {
                    // Use local processed image
                    $validation['data']['image_url'] = $localPath;
                }
            }

            try {
                updateFurniture($pdo, $id, $validation['data']);
                jsonSuccess(null, 'Furniture updated successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to update furniture: ' . $e->getMessage());
            }
            break;

        case 'furniture/delete':
            requireMethods(['POST', 'DELETE']);

            $id = getQueryInt('id', 0);
            // Verify furniture exists before attempting deletion
            requireFurniture($pdo, $id);

            try {
                deleteFurniture($pdo, $id);
                jsonSuccess(null, 'Furniture deleted successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to delete furniture: ' . $e->getMessage());
            }
            break;

        case 'furniture/batch-delete':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $ids = $input['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                jsonError('No items selected');
            }

            $deleted = 0;
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    try {
                        deleteFurniture($pdo, $id);
                        $deleted++;
                    } catch (RuntimeException $e) {
                        // Log error but continue with other deletions
                        error_log("Failed to delete furniture {$id}: " . $e->getMessage());
                    }
                }
            }

            jsonSuccess(['deleted' => $deleted], "{$deleted} items deleted successfully");
            break;

        // =============================================
        // CATEGORY ENDPOINTS
        // =============================================

        case 'categories/list':
            requireMethod('GET');
            jsonSuccess(getCategories($pdo));
            break;

        case 'categories/get':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $category = getCategoryById($pdo, $id);
            if (!$category) {
                jsonError(ERROR_NOT_FOUND, 404);
            }

            jsonSuccess($category);
            break;

        case 'categories/create':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $name = isset($input['name']) ? (string) $input['name'] : '';
            
            $nameResult = Validator::categoryName($name);
            if (!$nameResult['valid']) {
                jsonError($nameResult['error']);
            }

            $id = createCategory($pdo, [
                'name' => $nameResult['data'],
                'icon' => $input['icon'] ?? '📁',
                'sort_order' => isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
            ]);

            // Invalidate cached categories
            if (function_exists('apcu_delete')) {
                apcu_delete('gtaw_categories_v2');
            }

            jsonSuccess(['id' => $id], 'Category created successfully');
            break;

        case 'categories/update':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $input = getJsonInput() ?? $_POST;
            $data = [];

            if (isset($input['name'])) {
                $nameResult = Validator::categoryName((string) $input['name']);
                if (!$nameResult['valid']) {
                    jsonError($nameResult['error']);
                }
                $data['name'] = $nameResult['data'];
            }
            if (isset($input['icon'])) {
                $data['icon'] = (string) $input['icon'];
            }
            if (isset($input['sort_order'])) {
                $data['sort_order'] = (int) $input['sort_order'];
            }

            if (empty($data)) {
                jsonError('No data to update');
            }

            try {
                updateCategory($pdo, $id, $data);
                if (function_exists('apcu_delete')) {
                    apcu_delete('gtaw_categories_v2');
                }
                jsonSuccess(null, 'Category updated successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to update category: ' . $e->getMessage());
            }
            break;

        case 'categories/delete':
            requireMethods(['POST', 'DELETE']);

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            try {
                deleteCategory($pdo, $id);
                if (function_exists('apcu_delete')) {
                    apcu_delete('gtaw_categories_v2');
                }
                jsonSuccess(null, 'Category deleted successfully');
            } catch (RuntimeException $e) {
                jsonError('Cannot delete category: ' . $e->getMessage());
            }
            break;

        case 'categories/reorder':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $order = $input['order'] ?? [];
            
            if (empty($order) || !is_array($order)) {
                jsonError('Invalid order data');
            }

            $stmt = $pdo->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
            
            foreach ($order as $item) {
                $stmt->execute([(int) $item['order'], (int) $item['id']]);
            }

            if (function_exists('apcu_delete')) {
                apcu_delete('gtaw_categories_v2');
            }
            jsonSuccess(null, 'Order saved successfully');
            break;

        // =============================================
        // TAG GROUP ENDPOINTS
        // =============================================

        case 'tag-groups/list':
            requireMethod('GET');
            jsonSuccess(getTagGroups($pdo));
            break;

        case 'tag-groups/get':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $group = getTagGroupById($pdo, $id);
            if (!$group) {
                jsonError(ERROR_NOT_FOUND, 404);
            }

            jsonSuccess($group);
            break;

        case 'tag-groups/create':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $name = isset($input['name']) ? (string) $input['name'] : '';
            
            $nameResult = Validator::tagGroupName($name);
            if (!$nameResult['valid']) {
                jsonError($nameResult['error']);
            }

            $isGeneral = isset($input['is_general']) ? (bool) $input['is_general'] : true;

            $id = createTagGroup($pdo, [
                'name' => $nameResult['data'],
                'color' => $input['color'] ?? '#6b7280',
                'sort_order' => isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
                'is_general' => $isGeneral ? 1 : 0,
            ]);

            // If category-specific, link to categories if provided
            if (!$isGeneral && !empty($input['category_ids'])) {
                $categoryIds = array_filter(array_map('intval', (array) $input['category_ids']));
                foreach ($categoryIds as $categoryId) {
                    linkTagGroupToCategory($pdo, $id, $categoryId);
                }
            }

            if (function_exists('apcu_delete')) {
                apcu_delete('gtaw_tags_grouped_v2');
            }

            jsonSuccess(['id' => $id], 'Tag group created successfully');
            break;

        case 'tag-groups/update':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $input = getJsonInput() ?? $_POST;
            $data = [];

            if (isset($input['name'])) {
                $nameResult = Validator::tagGroupName((string) $input['name']);
                if (!$nameResult['valid']) {
                    jsonError($nameResult['error']);
                }
                $data['name'] = $nameResult['data'];
            }
            if (isset($input['color'])) {
                $data['color'] = $input['color'];
            }
            if (isset($input['sort_order'])) {
                $data['sort_order'] = (int) $input['sort_order'];
            }

            if (empty($data)) {
                jsonError('No data to update');
            }

            try {
                updateTagGroup($pdo, $id, $data);
                if (function_exists('apcu_clear_cache')) {
                    apcu_clear_cache();
                }
                jsonSuccess(null, 'Tag group updated successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to update tag group: ' . $e->getMessage());
            }
            break;

        case 'tag-groups/delete':
            requireMethods(['POST', 'DELETE']);

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            try {
                deleteTagGroup($pdo, $id);
                if (function_exists('apcu_clear_cache')) {
                    apcu_clear_cache();
                }
                jsonSuccess(null, 'Tag group deleted successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to delete tag group: ' . $e->getMessage());
            }
            break;

        case 'tag-groups/reorder':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $order = $input['order'] ?? [];
            
            if (empty($order) || !is_array($order)) {
                jsonError('Invalid order data');
            }

            $stmt = $pdo->prepare('UPDATE tag_groups SET sort_order = ? WHERE id = ?');
            
            foreach ($order as $item) {
                $stmt->execute([(int) $item['order'], (int) $item['id']]);
            }

            if (function_exists('apcu_delete')) {
                apcu_delete('gtaw_tags_grouped_v2');
            }
            jsonSuccess(null, 'Order saved successfully');
            break;

        case 'tag-groups/get-categories':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $categories = getCategoriesForTagGroup($pdo, $id);
            jsonSuccess($categories);
            break;

        case 'tag-groups/link-category':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $tagGroupId = (int) ($input['tag_group_id'] ?? 0);
            $categoryId = (int) ($input['category_id'] ?? 0);

            if ($tagGroupId <= 0 || $categoryId <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            // Verify tag group is not general
            $group = getTagGroupById($pdo, $tagGroupId);
            if (!$group) {
                jsonError(ERROR_NOT_FOUND, 404);
            }
            if (!empty($group['is_general'])) {
                jsonError('Cannot link general tag groups to categories');
            }

            linkTagGroupToCategory($pdo, $tagGroupId, $categoryId);
            
            // Clear relevant caches
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }
            
            jsonSuccess(null, 'Tag group linked to category');
            break;

        case 'tag-groups/unlink-category':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $tagGroupId = (int) ($input['tag_group_id'] ?? 0);
            $categoryId = (int) ($input['category_id'] ?? 0);

            if ($tagGroupId <= 0 || $categoryId <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            unlinkTagGroupFromCategory($pdo, $tagGroupId, $categoryId);
            
            // Clear relevant caches
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }
            
            jsonSuccess(null, 'Tag group unlinked from category');
            break;

        // =============================================
        // TAG ENDPOINTS
        // =============================================

        case 'tags/list':
            requireMethod('GET');
            jsonSuccess(getTags($pdo));
            break;

        case 'tags/grouped':
            requireMethod('GET');
            jsonSuccess(getTagsGrouped($pdo));
            break;

        case 'tags/create':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $name = isset($input['name']) ? (string) $input['name'] : '';
            
            $nameResult = Validator::tagName($name);
            if (!$nameResult['valid']) {
                jsonError($nameResult['error']);
            }

            $id = createTag($pdo, [
                'name' => $nameResult['data'],
                'color' => $input['color'] ?? '#6b7280',
                'group_id' => isset($input['group_id']) && $input['group_id'] !== '' ? (int) $input['group_id'] : null,
            ]);

            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }

            jsonSuccess(['id' => $id], 'Tag created successfully');
            break;

        case 'tags/update':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $input = getJsonInput() ?? $_POST;
            $data = [];

            if (isset($input['name'])) {
                $nameResult = Validator::tagName((string) $input['name']);
                if (!$nameResult['valid']) {
                    jsonError($nameResult['error']);
                }
                $data['name'] = $nameResult['data'];
            }
            if (isset($input['color'])) {
                $data['color'] = $input['color'];
            }
            if (array_key_exists('group_id', $input)) {
                $data['group_id'] = $input['group_id'] !== '' && $input['group_id'] !== null ? (int) $input['group_id'] : null;
            }

            if (empty($data)) {
                jsonError('No data to update');
            }

            try {
                updateTag($pdo, $id, $data);
                if (function_exists('apcu_clear_cache')) {
                    apcu_clear_cache();
                }
                jsonSuccess(null, 'Tag updated successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to update tag: ' . $e->getMessage());
            }
            break;

        case 'tags/delete':
            requireMethods(['POST', 'DELETE']);

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            try {
                deleteTag($pdo, $id);
                if (function_exists('apcu_clear_cache')) {
                    apcu_clear_cache();
                }
                jsonSuccess(null, 'Tag deleted successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to delete tag: ' . $e->getMessage());
            }
            break;

        // =============================================
        // USER ENDPOINTS
        // =============================================

        case 'users/list':
            requireMethod('GET');

            $page = max(1, getQueryInt('page', 1));
            $perPage = getSetting('app.items_per_page', 50);
            $result = getUsers($pdo, $page, $perPage);
            jsonSuccess($result);
            break;

        case 'users/ban':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $input = getJsonInput() ?? $_POST;
            $reason = isset($input['reason']) ? trim((string) $input['reason']) : '';

            try {
                banUser($pdo, $id, $reason ?: null);
                jsonSuccess(null, 'User banned successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to ban user: ' . $e->getMessage());
            }
            break;

        case 'users/unban':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            try {
                unbanUser($pdo, $id);
                jsonSuccess(null, 'User unbanned successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to unban user: ' . $e->getMessage());
            }
            break;

        // =============================================
        // IMPORT/EXPORT ENDPOINTS
        // =============================================

        case 'import':
            requireMasterAdmin();
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $csvContent = $input['csv'] ?? '';

            if (empty($csvContent)) {
                jsonError('No CSV content provided');
            }

            $result = parseCsvImport($pdo, $csvContent);

            if (!empty($result['errors'])) {
                jsonError('Import errors: ' . implode('; ', array_slice($result['errors'], 0, 5)));
            }

            $imported = 0;
            $imagesProcessed = 0;
            $processor = new ImageProcessor();
            
            foreach ($result['items'] as $item) {
                try {
                    $furnitureId = createFurniture($pdo, $item);
                    $imported++;
                    
                    $imageUrl = $item['image_url'] ?? null;
                    if ($processor->processFurnitureImage($pdo, $furnitureId, $imageUrl)) {
                        $imagesProcessed++;
                    }
                } catch (Exception $e) {
                    error_log("Import error: " . $e->getMessage());
                }
            }

            $message = "Successfully imported {$imported} items";
            if ($imagesProcessed > 0) {
                $message .= " ({$imagesProcessed} images processed)";
            }
            
            jsonSuccess(['imported' => $imported, 'images_processed' => $imagesProcessed], $message);
            break;

        case 'export':
            requireMasterAdmin();
            requireMethod('GET');

            $csv = exportFurnitureCsv($pdo);
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="furniture_export_' . date('Y-m-d') . '.csv"');
            echo $csv;
            exit;

        // =============================================
        // DASHBOARD STATS
        // =============================================

        case 'stats':
            requireMethod('GET');

            jsonSuccess(getDashboardStats($pdo));
            break;

        // =============================================
        // ADMIN MANAGEMENT
        // =============================================

        case 'admins/create':
            requireMasterAdmin();
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $username = isset($input['username']) ? (string) $input['username'] : '';
            $password = $input['password'] ?? '';
            $role = isset($input['role']) ? (string) $input['role'] : 'admin';

            // Validate username
            $usernameResult = Validator::username($username, 3, 50);
            if (!$usernameResult['valid']) {
                jsonError($usernameResult['error']);
            }
            $username = $usernameResult['data'];

            // Validate password
            $passwordResult = Validator::password($password, 8, 255);
            if (!$passwordResult['valid']) {
                jsonError($passwordResult['error']);
            }

            // Validate role (only master or admin allowed)
            if (!in_array($role, ['master', 'admin'], true)) {
                $role = 'admin';
            }

            try {
                $id = createAdmin($pdo, $username, $password, $role);
                jsonSuccess(['id' => $id], 'Admin created successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    jsonError('Username already exists');
                }
                throw $e;
            }
            break;

        // =============================================
        // SUBMISSIONS ENDPOINTS
        // =============================================

        case 'submissions/list':
            requireMethod('GET');

            $status = getQuery('status', null);
            $page = max(1, getQueryInt('page', 1));
            $result = getSubmissions($pdo, $page, 20, $status);
            jsonSuccess($result);
            break;

        case 'submissions/approve':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $admin = getCurrentAdmin();
            $adminId = (int) $admin['id'];

            try {
                approveSubmission($pdo, $id, $adminId);
                jsonSuccess(null, 'Submission approved and furniture created/updated');
            } catch (RuntimeException $e) {
                jsonError('Failed to approve submission: ' . $e->getMessage());
            }
            break;

        case 'submissions/reject':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }

            $input = getJsonInput() ?? $_POST;
            $notes = isset($input['notes']) ? trim((string) $input['notes']) : '';

            $admin = getCurrentAdmin();
            $adminId = (int) $admin['id'];

            try {
                rejectSubmission($pdo, $id, $adminId, $notes ?: null);
                jsonSuccess(null, 'Submission rejected');
            } catch (RuntimeException $e) {
                jsonError('Failed to reject submission: ' . $e->getMessage());
            }
            break;

        case 'submissions/bulk-approve':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $ids = $input['ids'] ?? [];
            
            if (!is_array($ids) || empty($ids)) {
                jsonError('No submission IDs provided');
            }
            
            // Validate and filter IDs (limit to 50 for safety)
            $ids = array_filter(array_map('intval', array_slice($ids, 0, 50)));
            
            if (empty($ids)) {
                jsonError('No valid submission IDs provided');
            }
            
            $admin = getCurrentAdmin();
            $adminId = (int) $admin['id'];
            
            $approved = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    approveSubmission($pdo, $id, $adminId);
                    $approved++;
                } catch (RuntimeException $e) {
                    $errors[] = "ID {$id}: " . $e->getMessage();
                }
            }
            
            jsonSuccess([
                'approved' => $approved,
                'errors' => $errors
            ], "{$approved} submission(s) approved");
            break;

        case 'submissions/bulk-reject':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $ids = $input['ids'] ?? [];
            $notes = isset($input['notes']) ? trim((string) $input['notes']) : '';
            
            if (!is_array($ids) || empty($ids)) {
                jsonError('No submission IDs provided');
            }
            
            // Validate and filter IDs (limit to 50 for safety)
            $ids = array_filter(array_map('intval', array_slice($ids, 0, 50)));
            
            if (empty($ids)) {
                jsonError('No valid submission IDs provided');
            }
            
            $admin = getCurrentAdmin();
            $adminId = (int) $admin['id'];
            
            $rejected = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    rejectSubmission($pdo, $id, $adminId, $notes ?: null);
                    $rejected++;
                } catch (RuntimeException $e) {
                    $errors[] = "ID {$id}: " . $e->getMessage();
                }
            }
            
            jsonSuccess([
                'rejected' => $rejected,
                'errors' => $errors
            ], "{$rejected} submission(s) rejected");
            break;

        // =============================================
        // SETTINGS ENDPOINTS
        // =============================================

        case 'settings/list':
            requireMasterAdmin();
            requireMethod('GET');
            
            $settings = getAllSettingsWithMeta();
            jsonSuccess($settings);
            break;

        case 'settings/update':
            requireMasterAdmin();
            requireMethod('POST');
            
            $input = getJsonInput() ?? $_POST;
            $settings = $input['settings'] ?? [];
            
            if (!is_array($settings) || empty($settings)) {
                jsonError('No settings provided');
            }
            
            // Get admin ID for audit log (once, not per setting)
            $admin = getCurrentAdmin();
            $adminId = $admin ? (int) $admin['id'] : null;
            
            // Prepare all settings for batch update
            $preparedSettings = [];
            $errors = [];
            
            // First pass: validate all settings before updating any
            foreach ($settings as $key => $value) {
                // Validate key format
                if (!preg_match('/^[a-z][a-z0-9_.]+$/i', $key)) {
                    $errors[] = "{$key}: Invalid key format";
                    continue;
                }
                
                // Get existing setting to determine type
                $existing = null;
                try {
                    $stmt = $pdo->prepare('SELECT setting_type, setting_value FROM settings WHERE setting_key = ?');
                    $stmt->execute([$key]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // Setting doesn't exist, will auto-detect type
                }
                
                $type = $existing['setting_type'] ?? null;
                $oldValue = null;
                
                // Get old value for audit log
                if ($existing) {
                    $oldValue = convertSettingFromString($existing['setting_value'], $existing['setting_type']);
                }
                
                // Type detection based on value if not in DB
                if ($type === null) {
                    if ($value === '0' || $value === '1') {
                        $type = 'boolean';
                        $value = $value === '1' || $value === 'true' || $value === true;
                    } elseif (is_numeric($value) && strpos($value, '.') === false) {
                        $type = 'integer';
                        $value = (int) $value;
                    } else {
                        $type = 'string';
                    }
                } else {
                    // Convert value based on stored type
                    if ($type === 'boolean') {
                        $value = $value === '1' || $value === 'true' || $value === true;
                    } elseif ($type === 'integer') {
                        $value = (int) $value;
                    }
                }
                
                // Validate value based on type and key
                $validationError = validateSettingValue($key, $value, $type);
                if ($validationError !== null) {
                    $errors[] = "{$key}: {$validationError}";
                    continue;
                }
                
                // Store prepared setting for batch update
                $preparedSettings[] = [
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'old_value' => $oldValue,
                ];
            }
            
            // If validation errors and no valid settings, return early
            if (!empty($errors) && empty($preparedSettings)) {
                jsonError('Validation failed. Errors: ' . implode(', ', $errors));
            }
            
            // Batch update in transaction
            $updated = 0;
            $updateErrors = [];
            
            try {
                $pdo->beginTransaction();
                
                foreach ($preparedSettings as $setting) {
                    try {
                        $stringValue = convertSettingToString($setting['value'], $setting['type']);
                        
                        // Update setting
                        $stmt = $pdo->prepare('
                            INSERT INTO settings (setting_key, setting_value, setting_type, updated_at)
                            VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()
                        ');
                        $stmt->execute([$setting['key'], $stringValue, $setting['type']]);
                        
                        // Log to audit log
                        if ($adminId !== null) {
                            logSettingChange($pdo, $setting['key'], $setting['old_value'], $setting['value'], $adminId);
                        }
                        
                        $updated++;
                    } catch (Exception $e) {
                        $updateErrors[] = "{$setting['key']}: " . $e->getMessage();
                    }
                }
                
                // Commit transaction if all updates succeeded
                if (empty($updateErrors)) {
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                    $errors = array_merge($errors, $updateErrors);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                jsonError('Failed to update settings: ' . $e->getMessage());
            }
            
            // Clear settings cache
            clearSettingsCache();
            
            // Clear communities cache if any community settings were updated
            $communitySettingsUpdated = false;
            foreach (array_keys($settings) as $key) {
                if (str_starts_with($key, 'community.')) {
                    $communitySettingsUpdated = true;
                    break;
                }
            }
            if ($communitySettingsUpdated && function_exists('clearCommunitiesCache')) {
                clearCommunitiesCache();
            }
            
            if ($updated > 0) {
                jsonSuccess([
                    'updated' => $updated,
                    'errors' => $errors
                ], "{$updated} setting(s) updated" . (!empty($errors) ? ' (some errors occurred)' : ''));
            } else {
                jsonError('No settings were updated. Errors: ' . implode(', ', $errors));
            }
            break;

        case 'settings/get':
            requireMasterAdmin();
            requireMethod('GET');
            
            $key = getQuery('key', '');
            if (empty($key)) {
                jsonError('Setting key is required');
            }
            
            $value = getSetting($key);
            jsonSuccess(['key' => $key, 'value' => $value]);
            break;

        // =============================================
        // SYNONYM ENDPOINTS
        // =============================================

        case 'synonyms/list':
            requireMasterAdmin();
            requireMethod('GET');
            
            $page = max(1, getQueryInt('page', 1));
            $perPage = 50;
            $search = getQuery('search', '');
            
            $result = getSynonymsList($pdo, $page, $perPage, $search ?: null);
            jsonSuccess($result);
            break;

        case 'synonyms/get':
            requireMasterAdmin();
            requireMethod('GET');
            
            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }
            
            $synonym = getSynonymById($pdo, $id);
            if (!$synonym) {
                jsonError(ERROR_NOT_FOUND, 404);
            }
            
            jsonSuccess($synonym);
            break;

        case 'synonyms/create':
            requireMasterAdmin();
            requireMethod('POST');
            
            $input = getJsonInput() ?? $_POST;
            
            $canonical = isset($input['canonical']) ? strtolower(trim((string) $input['canonical'])) : '';
            $synonym = isset($input['synonym']) ? strtolower(trim((string) $input['synonym'])) : '';
            $weight = isset($input['weight']) ? (float) $input['weight'] : 1.0;
            $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : true;
            $source = isset($input['source']) && in_array($input['source'], ['admin', 'analytics', 'translation']) 
                ? (string) $input['source'] 
                : 'admin';
            $language = isset($input['language']) && in_array($input['language'], ['en', 'fr']) 
                ? (string) $input['language'] 
                : 'en';
            $categoryHint = isset($input['category_hint']) 
                ? strtolower(trim((string) $input['category_hint'])) 
                : null;
            
            if (strlen($canonical) < 2) {
                jsonError('Canonical term must be at least 2 characters');
            }
            if (strlen($synonym) < 2) {
                jsonError('Synonym must be at least 2 characters');
            }
            if ($canonical === $synonym) {
                jsonError('Canonical term and synonym cannot be the same');
            }
            if ($weight < 0.1 || $weight > 1.0) {
                jsonError('Weight must be between 0.1 and 1.0');
            }
            
            try {
                $id = createSynonym($pdo, [
                    'canonical' => $canonical,
                    'synonym' => $synonym,
                    'weight' => $weight,
                    'is_active' => $isActive ? 1 : 0,
                    'source' => $source,
                    'language' => $language,
                    'category_hint' => $categoryHint,
                ]);
                
                jsonSuccess(['id' => $id], 'Synonym created successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    jsonError('This synonym already exists');
                }
                throw $e;
            }
            break;

        case 'synonyms/update':
            requireMasterAdmin();
            requireMethod('POST');
            
            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }
            
            $input = getJsonInput() ?? $_POST;
            $data = [];
            
            if (isset($input['canonical'])) {
                $canonical = strtolower(trim((string) $input['canonical']));
                if (strlen($canonical) < 2) {
                    jsonError('Canonical term must be at least 2 characters');
                }
                $data['canonical'] = $canonical;
            }
            if (isset($input['synonym'])) {
                $synonym = strtolower(trim((string) $input['synonym']));
                if (strlen($synonym) < 2) {
                    jsonError('Synonym must be at least 2 characters');
                }
                $data['synonym'] = $synonym;
            }
            if (isset($input['weight'])) {
                $weight = (float) $input['weight'];
                if ($weight < 0.1 || $weight > 1.0) {
                    jsonError('Weight must be between 0.1 and 1.0');
                }
                $data['weight'] = $weight;
            }
            if (isset($input['is_active'])) {
                $data['is_active'] = (bool) $input['is_active'] ? 1 : 0;
            }
            
            if (empty($data)) {
                jsonError('No data to update');
            }
            
            // Check canonical != synonym
            if (isset($data['canonical']) && isset($data['synonym']) && $data['canonical'] === $data['synonym']) {
                jsonError('Canonical term and synonym cannot be the same');
            }
            
            try {
                updateSynonym($pdo, $id, $data);
                jsonSuccess(null, 'Synonym updated successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    jsonError('This synonym already exists');
                }
                throw $e;
            }
            break;

        case 'synonyms/delete':
            requireMasterAdmin();
            requireMethods(['POST', 'DELETE']);
            
            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }
            
            try {
                deleteSynonym($pdo, $id);
                jsonSuccess(null, 'Synonym deleted successfully');
            } catch (RuntimeException $e) {
                jsonError('Failed to delete synonym: ' . $e->getMessage());
            }
            break;

        case 'synonyms/toggle':
            requireMasterAdmin();
            requireMethod('POST');
            
            $id = getQueryInt('id', 0);
            if ($id <= 0) {
                jsonError(ERROR_INVALID_ID);
            }
            
            // Get current state
            $synonym = getSynonymById($pdo, $id);
            if (!$synonym) {
                jsonError(ERROR_NOT_FOUND, 404);
            }
            
            // Toggle
            $newState = $synonym['is_active'] ? 0 : 1;
            updateSynonym($pdo, $id, ['is_active' => $newState]);
            
            jsonSuccess(['is_active' => (bool) $newState], $newState ? 'Synonym enabled' : 'Synonym disabled');
            break;

        // =============================================
        // SEARCH ANALYTICS ENDPOINTS
        // =============================================

        case 'search-analytics/popular':
            requireMethod('GET');
            
            $days = max(1, min(365, getQueryInt('days', 7)));
            $limit = max(1, min(100, getQueryInt('limit', 20)));
            
            $result = getPopularSearches($pdo, $days, $limit);
            jsonSuccess($result);
            break;

        case 'search-analytics/zero-results':
            requireMethod('GET');
            
            $days = max(1, min(365, getQueryInt('days', 7)));
            $limit = max(1, min(100, getQueryInt('limit', 20)));
            
            $result = getZeroResultSearches($pdo, $days, $limit);
            jsonSuccess($result);
            break;

        // =============================================
        // SYNONYM AUTO-DISCOVERY ENDPOINTS
        // =============================================

        case 'synonyms/discover':
            requireMasterAdmin();
            requireMethod('GET');
            
            $days = max(1, min(90, getQueryInt('days', 30)));
            
            $suggestions = SynonymAutoDiscovery::analyzeSearchPatterns($pdo, $days);
            jsonSuccess([
                'suggestions' => $suggestions,
                'count' => count($suggestions),
            ]);
            break;

        case 'synonyms/auto-create':
            requireMasterAdmin();
            requireMethod('POST');
            
            $input = getJsonInput() ?? $_POST;
            $days = max(1, min(90, (int) ($input['days'] ?? 30)));
            $minConfidence = max(0.5, min(1.0, (float) ($input['min_confidence'] ?? 0.7)));
            
            // First analyze patterns
            $suggestions = SynonymAutoDiscovery::analyzeSearchPatterns($pdo, $days);
            
            // Then auto-create synonyms
            $result = SynonymAutoDiscovery::autoCreateSynonyms($pdo, $suggestions, $minConfidence);
            
            jsonSuccess($result, "Created {$result['created']} synonyms, skipped {$result['skipped']}");
            break;

        case 'synonyms/fuzzy-test':
            requireMasterAdmin();
            requireMethod('GET');
            
            $term = getQuery('term', '');
            if (strlen($term) < 2) {
                jsonError('Term must be at least 2 characters');
            }
            
            $synonymData = SynonymManager::getData();
            $vocabulary = array_unique(array_merge(
                array_keys($synonymData['forward'] ?? []),
                array_keys($synonymData['reverse'] ?? [])
            ));
            
            $matches = FuzzyMatcher::findMatches($term, $vocabulary, 5);
            $phoneticMatches = FuzzyMatcher::findPhoneticMatches($term, $vocabulary);
            
            jsonSuccess([
                'term' => $term,
                'fuzzy_matches' => $matches,
                'phonetic_matches' => array_slice($phoneticMatches, 0, 5),
            ]);
            break;

        case 'synonyms/translate':
            requireMasterAdmin();
            requireMethod('GET');
            
            $query = getQuery('q', '');
            if (strlen($query) < 2) {
                jsonError('Query must be at least 2 characters');
            }
            
            $result = LanguageSupport::translateQuery($query);
            $result['detected_language'] = LanguageSupport::detectLanguage($query);
            
            jsonSuccess($result);
            break;

        default:
            jsonError('Unknown endpoint', 404);
    }

} catch (PDOException $e) {
    logException('admin_api_db', $e);
    jsonError('Database error', 500);
} catch (Exception $e) {
    logException('admin_api', $e);
    jsonError('Internal server error', 500);
}

