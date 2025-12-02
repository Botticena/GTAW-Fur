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
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/api-controller.php';

// Initialize common API patterns (headers, DB connection, CSRF, request info)
$api = initializeApi();
$method = $api['method'];
$action = $api['action'];
$pdo = $api['pdo'];

// Route requests
try {
    switch ($action) {

        // =============================================
        // PUBLIC ENDPOINTS
        // =============================================

        case 'furniture':
            requireMethod('GET');

            $page = max(1, getQueryInt('page', 1));
            $perPage = min(MAX_ITEMS_PER_PAGE, max(1, getQueryInt('per_page', 24)));
            $category = getQuery('category', null);
            $category = $category !== null && $category !== '' ? $category : null;
            $tagsStr = getQuery('tags', '');
            $tags = $tagsStr !== '' 
                ? array_filter(array_map('trim', explode(',', $tagsStr))) 
                : [];
            $sort = getQuery('sort', 'name');
            $sort = in_array($sort, ['name', 'price', 'newest']) ? $sort : 'name';
            $order = strtolower(getQuery('order', 'asc')) === 'desc' ? 'desc' : 'asc';

            // Favorites only filter
            $favoritesOnly = !empty(getQuery('favorites_only', ''));
            $userFavoritesId = null;
            if ($favoritesOnly) {
                $userFavoritesId = getCurrentUserId();
                if (!$userFavoritesId) {
                    jsonError(ERROR_LOGIN_REQUIRED . ' for favorites filter', 401);
                }
            }

            $result = getFurnitureList($pdo, $page, $perPage, $category, $tags, $sort, $order, $userFavoritesId);
            jsonSuccess($result['items'] ?? [], null, $result['pagination'] ?? null);
            break;

        case 'furniture/search':
            requireMethod('GET');

            $query = getQuery('q', '');
            if (strlen($query) < 2) {
                jsonError('Search query must be at least 2 characters');
            }

            $page = max(1, getQueryInt('page', 1));
            $perPage = min(MAX_ITEMS_PER_PAGE, max(1, getQueryInt('per_page', 24)));

            // Favorites only filter
            $favoritesOnly = !empty(getQuery('favorites_only', ''));
            $userFavoritesId = null;
            if ($favoritesOnly) {
                $userFavoritesId = getCurrentUserId();
                if (!$userFavoritesId) {
                    jsonError(ERROR_LOGIN_REQUIRED . ' for favorites filter', 401);
                }
            }

            $result = searchFurniture($pdo, $query, $page, $perPage, $userFavoritesId);
            jsonSuccess($result['items'] ?? [], null, $result['pagination'] ?? null);
            break;

        case 'furniture/single':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            $item = requireFurniture($pdo, $id);

            // Add favorite status if user is logged in
            $userId = getCurrentUserId();
            if ($userId) {
                $item['is_favorited'] = isFavorited($pdo, $userId, $id);
            }

            jsonSuccess($item);
            break;

        case 'categories':
            requireMethod('GET');
            jsonSuccess(getCategories($pdo));
            break;

        case 'tags':
            requireMethod('GET');
            // Return grouped structure for frontend filtering UI
            jsonSuccess(getTagsGrouped($pdo));
            break;
        
        case 'tags/flat':
            // Return flat list (for backwards compatibility or simple use cases)
            requireMethod('GET');
            jsonSuccess(getTags($pdo));
            break;

        // =============================================
        // PROTECTED ENDPOINTS (Require User Login)
        // =============================================

        case 'favorites':
            $userId = getCurrentUserId();

            if ($method === 'GET') {
                if (!$userId) {
                    jsonError(ERROR_AUTH_REQUIRED, 401);
                }
                jsonSuccess(getUserFavorites($pdo, $userId));
            }

            if ($method === 'POST') {
                if (!$userId) {
                    jsonError(ERROR_AUTH_REQUIRED, 401);
                }

                // Rate limiting for favorites (per user)
                withRateLimit(
                    'api_favorites',
                    RATE_LIMIT_FAVORITES['max'],
                    RATE_LIMIT_FAVORITES['window'],
                    function () use ($pdo, $userId) {
                        $input = getJsonInput();
                        $furnitureId = (int) ($input['furniture_id'] ?? 0);

                        $idResult = Validator::furnitureId($furnitureId);
                        if (!$idResult['valid']) {
                            jsonError($idResult['error']);
                        }
                        $furnitureId = $idResult['data'];

                        try {
                            if (addFavorite($pdo, $userId, $furnitureId)) {
                                jsonSuccess(null, 'Added to favorites');
                            } else {
                                jsonError('Already in favorites or ' . strtolower(ERROR_NOT_FOUND));
                            }
                        } catch (RuntimeException $e) {
                            jsonError('Failed to add favorite: ' . $e->getMessage());
                        }
                    },
                    (string) $userId
                );
            }

            if ($method === 'DELETE') {
                if (!$userId) {
                    jsonError(ERROR_AUTH_REQUIRED, 401);
                }

                // Rate limiting for favorites (per user)
                withRateLimit(
                    'api_favorites',
                    RATE_LIMIT_FAVORITES['max'],
                    RATE_LIMIT_FAVORITES['window'],
                    function () use ($pdo, $userId) {
                        $input = getJsonInput();
                        $furnitureId = isset($input['furniture_id']) ? (int) $input['furniture_id'] : 0;

                        $idResult = Validator::furnitureId($furnitureId);
                        if (!$idResult['valid']) {
                            jsonError($idResult['error']);
                        }
                        $furnitureId = $idResult['data'];

                        try {
                            removeFavorite($pdo, $userId, $furnitureId);
                            jsonSuccess(null, 'Removed from favorites');
                        } catch (RuntimeException $e) {
                            jsonError('Failed to remove favorite: ' . $e->getMessage());
                        }
                    },
                    (string) $userId
                );
            }

            jsonError('Method not allowed', 405);
            break;

        case 'user':
            requireMethod('GET');

            if (!isLoggedIn()) {
                jsonError('Authentication required', 401);
            }

            $user = getCurrentUser();
            $user['favorites_count'] = countUserFavorites($pdo, (int) $_SESSION['user_id']);
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

