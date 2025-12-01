<?php
/**
 * GTAW Furniture Catalog - Public API
 * 
 * All public API endpoints in one file.
 * Returns JSON responses.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Set JSON content type
header('Content-Type: application/json');

/**
 * Send success response
 */
function jsonSuccess(mixed $data, ?array $pagination = null): never
{
    $response = ['success' => true, 'data' => $data];
    if ($pagination !== null) {
        $response['pagination'] = $pagination;
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

// Check database connection
global $pdo;
if ($pdo === null) {
    jsonError('Database connection failed', 500);
}

// Get request info
$method = requestMethod();
$action = $_GET['action'] ?? '';

// Route requests
try {
    switch ($action) {

        // =============================================
        // PUBLIC ENDPOINTS
        // =============================================

        case 'furniture':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 24)));
            $category = !empty($_GET['category']) ? trim($_GET['category']) : null;
            $tags = isset($_GET['tags']) && $_GET['tags'] !== '' 
                ? array_filter(array_map('trim', explode(',', $_GET['tags']))) 
                : [];
            $sort = in_array($_GET['sort'] ?? '', ['name', 'price', 'newest']) 
                ? $_GET['sort'] 
                : 'name';
            $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            $result = getFurnitureList($page, $perPage, $category, $tags, $sort, $order);
            jsonSuccess($result['items'], $result['pagination']);
            break;

        case 'furniture/search':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            $query = trim($_GET['q'] ?? '');
            if (strlen($query) < 2) {
                jsonError('Search query must be at least 2 characters');
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 24)));

            $result = searchFurniture($query, $page, $perPage);
            jsonSuccess($result['items'], $result['pagination']);
            break;

        case 'furniture/single':
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

            // Add favorite status if user is logged in
            $userId = getCurrentUserId();
            if ($userId) {
                $item['is_favorited'] = isFavorited($userId, $id);
            }

            jsonSuccess($item);
            break;

        case 'categories':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            jsonSuccess(getCategories());
            break;

        case 'tags':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            // Return grouped structure for frontend filtering UI
            jsonSuccess(getTagsGrouped());
            break;
        
        case 'tags/flat':
            // Return flat list (for backwards compatibility or simple use cases)
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }
            jsonSuccess(getTags());
            break;

        // =============================================
        // PROTECTED ENDPOINTS (Require User Login)
        // =============================================

        case 'favorites':
            $userId = getCurrentUserId();

            if ($method === 'GET') {
                if (!$userId) {
                    jsonError('Authentication required', 401);
                }
                jsonSuccess(getUserFavorites($userId));
            }

            if ($method === 'POST') {
                if (!$userId) {
                    jsonError('Authentication required', 401);
                }

                $input = getJsonInput();
                $furnitureId = (int) ($input['furniture_id'] ?? 0);

                if ($furnitureId <= 0) {
                    jsonError('Invalid furniture ID');
                }

                if (addFavorite($userId, $furnitureId)) {
                    jsonSuccess(['message' => 'Added to favorites']);
                } else {
                    jsonError('Already in favorites or furniture not found');
                }
            }

            if ($method === 'DELETE') {
                if (!$userId) {
                    jsonError('Authentication required', 401);
                }

                $input = getJsonInput();
                $furnitureId = (int) ($input['furniture_id'] ?? 0);

                if ($furnitureId <= 0) {
                    jsonError('Invalid furniture ID');
                }

                removeFavorite($userId, $furnitureId);
                jsonSuccess(['message' => 'Removed from favorites']);
            }

            jsonError('Method not allowed', 405);
            break;

        case 'user':
            if ($method !== 'GET') {
                jsonError('Method not allowed', 405);
            }

            if (!isLoggedIn()) {
                jsonError('Authentication required', 401);
            }

            $user = getCurrentUser();
            $user['favorites_count'] = countUserFavorites((int) $_SESSION['user_id']);
            jsonSuccess($user);
            break;

        default:
            jsonError('Unknown endpoint', 404);
    }

} catch (PDOException $e) {
    error_log('API Database Error: ' . $e->getMessage());
    jsonError('Database error', 500);
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    jsonError('Internal server error', 500);
}

