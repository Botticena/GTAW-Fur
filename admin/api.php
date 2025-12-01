<?php
/**
 * GTAW Furniture Catalog - Admin API
 * 
 * Protected API endpoints for admin operations.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/image.php';

// Set JSON content type
header('Content-Type: application/json');

/**
 * Send success response
 */
function jsonSuccess(mixed $data = null, ?string $message = null): never
{
    $response = ['success' => true];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message !== null) {
        $response['message'] = $message;
    }
    echo json_encode($response);
    exit;
}

/**
 * Send error response
 */
function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Require admin authentication
requireAdmin();

// Check database connection
global $pdo;
if ($pdo === null) {
    jsonError('Database connection failed', 500);
}

// Get request info
$method = requestMethod();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // =============================================
        // FURNITURE ENDPOINTS
        // =============================================

        case 'furniture/list':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $result = getFurnitureList($page, 50);
            jsonSuccess($result);
            break;

        case 'furniture/get':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid furniture ID');
            }

            $item = getFurnitureById($id);
            if (!$item) {
                jsonError('Furniture not found', 404);
            }

            jsonSuccess($item);
            break;

        case 'furniture/create':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $input = getJsonInput() ?? $_POST;
            $validation = validateFurnitureInput($input);

            if (!$validation['valid']) {
                jsonError(implode(', ', $validation['errors']));
            }

            $id = createFurniture($validation['data']);
            
            // Process image if URL provided - download, resize, convert to WebP
            $imageUrl = $validation['data']['image_url'] ?? null;
            if ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $localPath = processImageFromUrl($imageUrl, $id);
                if ($localPath) {
                    // Update with local processed image path
                    updateFurnitureImage($id, $localPath);
                }
            }
            
            jsonSuccess(['id' => $id], 'Furniture created successfully');
            break;

        case 'furniture/update':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid furniture ID');
            }

            $input = getJsonInput() ?? $_POST;
            $validation = validateFurnitureInput($input);

            if (!$validation['valid']) {
                jsonError(implode(', ', $validation['errors']));
            }

            // Get current item to check if image changed
            $currentItem = getFurnitureById($id);
            $newImageUrl = $validation['data']['image_url'] ?? null;
            $oldImageUrl = $currentItem['image_url'] ?? null;
            
            // Process new image if URL changed and is external
            if ($newImageUrl && $newImageUrl !== $oldImageUrl && filter_var($newImageUrl, FILTER_VALIDATE_URL)) {
                $localPath = processImageFromUrl($newImageUrl, $id);
                if ($localPath) {
                    // Delete old local image if exists
                    if ($oldImageUrl && str_starts_with($oldImageUrl, '/images/furniture/')) {
                        deleteImageFile($oldImageUrl);
                    }
                    // Use local processed image
                    $validation['data']['image_url'] = $localPath;
                }
            }

            if (!updateFurniture($id, $validation['data'])) {
                jsonError('Failed to update furniture');
            }

            jsonSuccess(null, 'Furniture updated successfully');
            break;

        case 'furniture/delete':
            if ($method !== 'POST' && $method !== 'DELETE') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid furniture ID');
            }

            if (!deleteFurniture($id)) {
                jsonError('Failed to delete furniture');
            }

            jsonSuccess(null, 'Furniture deleted successfully');
            break;

        // =============================================
        // CATEGORY ENDPOINTS
        // =============================================

        case 'categories/list':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            jsonSuccess(getCategories());
            break;

        case 'categories/get':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid category ID');
            }

            $category = getCategoryById($id);
            if (!$category) {
                jsonError('Category not found', 404);
            }

            jsonSuccess($category);
            break;

        case 'categories/create':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $input = getJsonInput() ?? $_POST;
            $name = trim($input['name'] ?? '');
            
            if (empty($name)) {
                jsonError('Category name is required');
            }

            $id = createCategory([
                'name' => $name,
                'icon' => $input['icon'] ?? '📁',
                'sort_order' => (int) ($input['sort_order'] ?? 0),
            ]);

            jsonSuccess(['id' => $id], 'Category created successfully');
            break;

        case 'categories/update':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid category ID');
            }

            $input = getJsonInput() ?? $_POST;
            $data = [];

            if (isset($input['name'])) {
                $data['name'] = trim($input['name']);
            }
            if (isset($input['icon'])) {
                $data['icon'] = $input['icon'];
            }
            if (isset($input['sort_order'])) {
                $data['sort_order'] = (int) $input['sort_order'];
            }

            if (empty($data)) {
                jsonError('No data to update');
            }

            if (!updateCategory($id, $data)) {
                jsonError('Failed to update category');
            }

            jsonSuccess(null, 'Category updated successfully');
            break;

        case 'categories/delete':
            if ($method !== 'POST' && $method !== 'DELETE') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid category ID');
            }

            if (!deleteCategory($id)) {
                jsonError('Cannot delete category with furniture items');
            }

            jsonSuccess(null, 'Category deleted successfully');
            break;

        // =============================================
        // TAG ENDPOINTS
        // =============================================

        case 'tags/list':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            jsonSuccess(getTags());
            break;

        case 'tags/create':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $input = getJsonInput() ?? $_POST;
            $name = trim($input['name'] ?? '');
            
            if (empty($name)) {
                jsonError('Tag name is required');
            }

            $id = createTag([
                'name' => $name,
                'color' => $input['color'] ?? '#6b7280',
            ]);

            jsonSuccess(['id' => $id], 'Tag created successfully');
            break;

        case 'tags/update':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid tag ID');
            }

            $input = getJsonInput() ?? $_POST;
            $data = [];

            if (isset($input['name'])) {
                $data['name'] = trim($input['name']);
            }
            if (isset($input['color'])) {
                $data['color'] = $input['color'];
            }

            if (empty($data)) {
                jsonError('No data to update');
            }

            if (!updateTag($id, $data)) {
                jsonError('Failed to update tag');
            }

            jsonSuccess(null, 'Tag updated successfully');
            break;

        case 'tags/delete':
            if ($method !== 'POST' && $method !== 'DELETE') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid tag ID');
            }

            if (!deleteTag($id)) {
                jsonError('Failed to delete tag');
            }

            jsonSuccess(null, 'Tag deleted successfully');
            break;

        // =============================================
        // USER ENDPOINTS
        // =============================================

        case 'users/list':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $result = getUsers($page, 50);
            jsonSuccess($result);
            break;

        case 'users/ban':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid user ID');
            }

            $input = getJsonInput() ?? $_POST;
            $reason = trim($input['reason'] ?? '');

            if (!banUser($id, $reason ?: null)) {
                jsonError('Failed to ban user');
            }

            jsonSuccess(null, 'User banned successfully');
            break;

        case 'users/unban':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonError('Invalid user ID');
            }

            if (!unbanUser($id)) {
                jsonError('Failed to unban user');
            }

            jsonSuccess(null, 'User unbanned successfully');
            break;

        // =============================================
        // IMPORT/EXPORT ENDPOINTS
        // =============================================

        case 'import':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $input = getJsonInput() ?? $_POST;
            $csvContent = $input['csv'] ?? '';

            if (empty($csvContent)) {
                jsonError('No CSV content provided');
            }

            $result = parseCsvImport($csvContent);

            if (!empty($result['errors'])) {
                jsonError('Import errors: ' . implode('; ', array_slice($result['errors'], 0, 5)));
            }

            $imported = 0;
            $imagesProcessed = 0;
            
            foreach ($result['items'] as $item) {
                try {
                    $furnitureId = createFurniture($item);
                    $imported++;
                    
                    // Process image if URL provided
                    $imageUrl = $item['image_url'] ?? null;
                    if ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        $localPath = processImageFromUrl($imageUrl, $furnitureId);
                        if ($localPath) {
                            updateFurnitureImage($furnitureId, $localPath);
                            $imagesProcessed++;
                        }
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
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $csv = exportFurnitureCsv();
            
            // Return as downloadable file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="furniture_export_' . date('Y-m-d') . '.csv"');
            echo $csv;
            exit;

        // =============================================
        // DASHBOARD STATS
        // =============================================

        case 'stats':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            jsonSuccess(getDashboardStats());
            break;

        // =============================================
        // ADMIN MANAGEMENT
        // =============================================

        case 'admins/create':
            if ($method !== 'POST') {
                jsonError('Method not allowed', 405);
            }

            $input = getJsonInput() ?? $_POST;
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';

            if (empty($username) || strlen($username) < 3) {
                jsonError('Username must be at least 3 characters');
            }

            if (empty($password) || strlen($password) < 8) {
                jsonError('Password must be at least 8 characters');
            }

            try {
                $id = createAdmin($username, $password);
                jsonSuccess(['id' => $id], 'Admin created successfully');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    jsonError('Username already exists');
                }
                throw $e;
            }
            break;

        default:
            jsonError('Unknown endpoint', 404);
    }

} catch (PDOException $e) {
    error_log('Admin API Database Error: ' . $e->getMessage());
    jsonError('Database error', 500);
} catch (Exception $e) {
    error_log('Admin API Error: ' . $e->getMessage());
    jsonError('Internal server error', 500);
}

