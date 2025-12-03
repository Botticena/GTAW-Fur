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

// Require admin authentication
requireAdmin();

$api = initializeApi();
$method = $api['method'];
$action = $api['action'];
$pdo = $api['pdo'];

try {
    switch ($action) {

        // =============================================
        // FURNITURE ENDPOINTS
        // =============================================

        case 'furniture/list':
            requireMethod('GET');

            $page = max(1, getQueryInt('page', 1));
            $result = getFurnitureList($pdo, $page, 50);
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
                apcu_delete('gtaw_categories_v1'); // Legacy
                apcu_delete('gtaw_categories_v2'); // Current
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
                    apcu_delete('gtaw_categories_v1'); // Legacy
                    apcu_delete('gtaw_categories_v2'); // Current
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
                    apcu_delete('gtaw_categories_v1'); // Legacy
                    apcu_delete('gtaw_categories_v2'); // Current
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
                apcu_delete('gtaw_categories_v1');
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

            $id = createTagGroup($pdo, [
                'name' => $nameResult['data'],
                'color' => $input['color'] ?? '#6b7280',
                'sort_order' => isset($input['sort_order']) ? (int) $input['sort_order'] : 0,
            ]);

            if (function_exists('apcu_delete')) {
                apcu_delete('gtaw_tag_groups_v1');
                apcu_delete('gtaw_tags_grouped_v1');
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
                if (function_exists('apcu_delete')) {
                    apcu_delete('gtaw_tag_groups_v1');
                    apcu_delete('gtaw_tags_grouped_v1');
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
                if (function_exists('apcu_delete')) {
                    apcu_delete('gtaw_tag_groups_v1');
                    apcu_delete('gtaw_tags_grouped_v1');
                    apcu_delete('gtaw_tags_flat_v1');
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
                apcu_delete('gtaw_tag_groups_v1');
                apcu_delete('gtaw_tags_grouped_v1');
            }
            jsonSuccess(null, 'Order saved successfully');
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

            if (function_exists('apcu_delete')) {
                apcu_delete('gtaw_tags_flat_v1');
                apcu_delete('gtaw_tags_grouped_v1');
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
                if (function_exists('apcu_delete')) {
                    apcu_delete('gtaw_tags_flat_v1');
                    apcu_delete('gtaw_tags_grouped_v1');
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
                if (function_exists('apcu_delete')) {
                    apcu_delete('gtaw_tags_flat_v1');
                    apcu_delete('gtaw_tags_grouped_v1');
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
            $result = getUsers($pdo, $page, 50);
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
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $username = isset($input['username']) ? (string) $input['username'] : '';
            $password = $input['password'] ?? '';

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

            try {
                $id = createAdmin($pdo, $username, $password);
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

