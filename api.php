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
            $defaultPerPage = getDefaultItemsPerPage();
            $maxPerPage = getMaxItemsPerPage();
            $perPage = min($maxPerPage, max(1, getQueryInt('per_page', $defaultPerPage)));
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
            $defaultPerPage = getDefaultItemsPerPage();
            $maxPerPage = getMaxItemsPerPage();
            $perPage = min($maxPerPage, max(1, getQueryInt('per_page', $defaultPerPage)));

            // Favorites only filter
            $favoritesOnly = !empty(getQuery('favorites_only', ''));
            $userFavoritesId = null;
            if ($favoritesOnly) {
                $userFavoritesId = getCurrentUserId();
                if (!$userFavoritesId) {
                    jsonError(ERROR_LOGIN_REQUIRED . ' for favorites filter', 401);
                }
            }
            
            // Category filter for category-aware search
            $categoryFilter = getQuery('category', null);

            $result = searchFurnitureEnhanced(
                $pdo, 
                $query, 
                $page, 
                $perPage, 
                $userFavoritesId, 
                true,  // expandSynonyms
                true,  // logSearchQuery
                $categoryFilter
            );
            
            // Pass through search metadata if synonyms were expanded
            $extra = isset($result['search_meta']) ? ['search_meta' => $result['search_meta']] : null;
            
            jsonSuccess($result['items'] ?? [], null, $result['pagination'] ?? null, $extra);
            break;

        case 'furniture/single':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            $item = requireFurniture($pdo, $id);

            $userId = getCurrentUserId();
            if ($userId) {
                $item['is_favorited'] = isFavorited($pdo, $userId, $id);
            }

            jsonSuccess($item);
            break;

        case 'furniture/batch':
            requireMethod('GET');

            $idsParam = getQuery('ids', '');
            if (empty($idsParam)) {
                jsonSuccess([]);
                break;
            }
            
            // Prevent DoS via extremely long strings (max 1000 chars)
            if (strlen($idsParam) > 1000) {
                jsonError('Invalid request: parameter too long', 400);
                break;
            }
            
            $ids = array_filter(array_map('intval', explode(',', $idsParam)));
            $ids = array_slice($ids, 0, 20);
            
            if (empty($ids)) {
                jsonSuccess([]);
                break;
            }
            
            $items = getFurnitureByIds($pdo, $ids);
            jsonSuccess($items);
            break;

        case 'furniture/check-duplicates':
            requireMethod('GET');

            $name = getQuery('name', '');
            
            // Require minimum length for meaningful matching
            if (strlen($name) < 3) {
                jsonSuccess([]);
                break;
            }
            
            $categoryId = getQuery('category_id', '');
            $categoryId = $categoryId !== '' ? (int)$categoryId : null;
            
            $excludeId = getQuery('exclude_id', '');
            $excludeId = $excludeId !== '' ? (int)$excludeId : null;
            
            // findPotentialDuplicates returns items with categories already attached
            $matches = findPotentialDuplicates($pdo, $name, $categoryId, $excludeId);
            $matches = attachTagsToFurniture($pdo, $matches);
            
            jsonSuccess($matches);
            break;

        case 'categories':
            requireMethod('GET');
            jsonSuccess(getCategories($pdo), null, null, null, true, CACHE_TTL_CATEGORIES);
            break;

        case 'tags':
            requireMethod('GET');
            jsonSuccess(getTagsGrouped($pdo), null, null, null, true, CACHE_TTL_TAGS);
            break;

        case 'tags/for-categories':
            requireMethod('GET');

            $categoryIdsParam = getQuery('category_ids', '');
            $categoryIds = [];
            
            if ($categoryIdsParam !== '') {
                $categoryIds = array_filter(array_map('intval', explode(',', $categoryIdsParam)));
            }
            
            $result = getTagsForCategories($pdo, $categoryIds);
            jsonSuccess($result, null, null, null, true, CACHE_TTL_TAGS);
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

            jsonError(ERROR_METHOD_NOT_ALLOWED, 405);
            break;

        case 'favorites/clear':
            if (!in_array($method, ['POST', 'DELETE'])) {
                jsonError(ERROR_METHOD_NOT_ALLOWED, 405);
            }
            
            $userId = getCurrentUserId();
            if (!$userId) {
                jsonError(ERROR_AUTH_REQUIRED, 401);
            }
            
            // Rate limiting for clear all (stricter - 3 per hour per user)
            withRateLimit(
                'api_favorites_clear',
                3,
                3600,
                function () use ($pdo, $userId) {
                    $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ?');
                    $stmt->execute([$userId]);
                    $count = $stmt->rowCount();
                    
                    jsonSuccess([
                        'message' => 'All favorites cleared',
                        'count' => $count
                    ]);
                },
                (string) $userId
            );
            break;

        case 'user':
            requireMethod('GET');

            if (!isLoggedIn()) {
                jsonError(ERROR_AUTH_REQUIRED, 401);
            }

            $user = getCurrentUser();
            $user['favorites_count'] = countUserFavorites($pdo, (int) $_SESSION['user_id']);
            jsonSuccess($user);
            break;

        default:
            jsonError(ERROR_UNKNOWN_ENDPOINT, 404);
    }

} catch (PDOException $e) {
    logException('api_db', $e);
    jsonError(ERROR_DB_ERROR, 500);
} catch (Exception $e) {
    logException('api', $e);
    jsonError(ERROR_INTERNAL, 500);
}

