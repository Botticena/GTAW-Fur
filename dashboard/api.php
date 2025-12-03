<?php
/**
 * GTAW Furniture Catalog - Dashboard API
 * 
 * Protected API endpoints for dashboard operations.
 * All endpoints require user authentication.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/collections.php';
require_once __DIR__ . '/../includes/submissions.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/api-controller.php';

// Require authentication for all endpoints
$userId = getCurrentUserId();
if (!$userId) {
    jsonError(ERROR_AUTH_REQUIRED, 401);
}

// Initialize common API patterns (headers, DB connection, CSRF, request info)
$api = initializeApi();
$method = $api['method'];
$action = $api['action'];
$pdo = $api['pdo'];

$user = getUserById($pdo, $userId);
if ($user && $user['is_banned']) {
    jsonError('Your account has been banned', 403);
}

/**
 * Require that the user owns the specified collection
 * Exits with 404 error if collection not found or user doesn't own it
 * 
 * @param PDO $pdo Database connection
 * @param int $userId Current user ID
 * @param int $collectionId Collection ID to check
 * @return void Exits with error if ownership check fails
 */
function requireCollectionOwnership(PDO $pdo, int $userId, int $collectionId): void
{
    if ($collectionId <= 0 || !userOwnsCollection($pdo, $userId, $collectionId)) {
        jsonError(ERROR_NOT_FOUND, 404);
    }
}

// Route requests
try {
    switch ($action) {

        // =============================================
        // COLLECTIONS ENDPOINTS
        // =============================================

        case 'collections':
            requireMethod('GET');
            jsonSuccess(getUserCollections($pdo, $userId));
            break;

        case 'collections/create':
            requireMethod('POST');

            // Rate limiting for collection creation (per user)
            withRateLimit(
                'api_collections_create',
                RATE_LIMIT_COLLECTIONS_CREATE['max'],
                RATE_LIMIT_COLLECTIONS_CREATE['window'],
                function () use ($pdo, $userId) {
                    $input = getJsonInput() ?? $_POST;
                    $name = trim($input['name'] ?? '');
                    $nameResult = Validator::collectionName($name);
                    if (!$nameResult['valid']) {
                        jsonError($nameResult['error']);
                    }

                    $description = trim($input['description'] ?? '');
                    if ($description !== '' && strlen($description) > 5000) {
                        jsonError('Description is too long (maximum 5000 characters)');
                    }
                    
                    $data = [
                        'name' => $name,
                        'description' => $description !== '' ? $description : null,
                        'is_public' => getInputBool($input, 'is_public', true),
                    ];

                    $id = createCollection($pdo, $userId, $data);
                    jsonSuccess(['id' => $id], 'Collection created');
                },
                (string) $userId
            );
            break;

        case 'collections/update':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            requireCollectionOwnership($pdo, $userId, $id);

            $input = getJsonInput() ?? $_POST;
            $data = [];
            if (isset($input['name'])) {
                $name = trim($input['name'] ?? '');
                $nameResult = Validator::collectionName($name);
                if (!$nameResult['valid']) {
                    jsonError($nameResult['error']);
                }
                $data['name'] = $nameResult['data'];
            }
            if (isset($input['description'])) {
                $description = trim($input['description'] ?? '');
                if ($description !== '' && strlen($description) > 5000) {
                    jsonError('Description is too long (maximum 5000 characters)');
                }
                $data['description'] = $description !== '' ? $description : null;
            }
            $data['is_public'] = getInputBool($input, 'is_public', false);

            try {
                updateCollection($pdo, $id, $data);
                jsonSuccess(null, 'Collection updated');
            } catch (RuntimeException $e) {
                jsonError('Failed to update collection: ' . $e->getMessage());
            }
            break;

        case 'collections/delete':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            requireCollectionOwnership($pdo, $userId, $id);

            try {
                deleteCollection($pdo, $id);
                jsonSuccess(null, 'Collection deleted');
            } catch (RuntimeException $e) {
                jsonError('Failed to delete collection: ' . $e->getMessage());
            }
            break;

        case 'collections/duplicate':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $collectionId = (int)($input['collection_id'] ?? 0);
            
            requireCollectionOwnership($pdo, $userId, $collectionId);
            
            $stmt = $pdo->prepare('
                SELECT id, name, description, is_public 
                FROM collections 
                WHERE id = ? AND user_id = ?
            ');
            $stmt->execute([$collectionId, $userId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$original) {
                jsonError('Collection not found', 404);
            }
            
            $newName = 'Copy of ' . $original['name'];
            $baseSlug = createSlug($newName);
            $newSlug = $baseSlug . '-' . substr(uniqid(), -6);
            
            $pdo->beginTransaction();
            
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO collections (user_id, name, slug, description, is_public)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $userId,
                    $newName,
                    $newSlug,
                    $original['description'],
                    $original['is_public']
                ]);
                
                $newId = (int)$pdo->lastInsertId();
                
                // Copy all items with sort order
                $stmt = $pdo->prepare('
                    INSERT INTO collection_items (collection_id, furniture_id, sort_order)
                    SELECT ?, furniture_id, sort_order
                    FROM collection_items
                    WHERE collection_id = ?
                ');
                $stmt->execute([$newId, $collectionId]);
                
                $itemCount = $stmt->rowCount();
                
                $pdo->commit();
                
                jsonSuccess([
                    'id' => $newId,
                    'slug' => $newSlug,
                    'name' => $newName,
                    'item_count' => $itemCount,
                    'message' => 'Collection duplicated successfully'
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                logException('collections_duplicate', $e);
                jsonError('Failed to duplicate collection', 500);
            }
            break;

        case 'collections/items':
            requireMethod('GET');

            $id = getQueryInt('id', 0);
            requireCollectionOwnership($pdo, $userId, $id);

            jsonSuccess(getCollectionItems($pdo, $id));
            break;

        case 'collections/add-item':
            requireMethod('POST');

            // Rate limiting for collection item operations (per user)
            withRateLimit(
                'api_collections_items',
                RATE_LIMIT_COLLECTIONS_ITEMS['max'],
                RATE_LIMIT_COLLECTIONS_ITEMS['window'],
                function () use ($pdo, $userId) {
                    $input = getJsonInput() ?? $_POST;
                    $collectionId = (int) ($input['collection_id'] ?? 0);
                    $furnitureId = (int) ($input['furniture_id'] ?? 0);

                    requireCollectionOwnership($pdo, $userId, $collectionId);
                    
                    $idResult = Validator::furnitureId($furnitureId);
                    if (!$idResult['valid']) {
                        jsonError($idResult['error']);
                    }
                    $furnitureId = $idResult['data'];

                    try {
                        if (addToCollection($pdo, $collectionId, $furnitureId)) {
                            jsonSuccess(null, 'Added to collection');
                        } else {
                            jsonError('Already in collection or ' . strtolower(ERROR_NOT_FOUND));
                        }
                    } catch (RuntimeException $e) {
                        jsonError('Failed to add to collection: ' . $e->getMessage());
                    }
                },
                (string) $userId
            );
            break;

        case 'collections/remove-item':
            requireMethod('POST');

            // Rate limiting for collection item operations (per user)
            withRateLimit(
                'api_collections_items',
                RATE_LIMIT_COLLECTIONS_ITEMS['max'],
                RATE_LIMIT_COLLECTIONS_ITEMS['window'],
                function () use ($pdo, $userId) {
                    $input = getJsonInput() ?? $_POST;
                    $collectionId = (int) ($input['collection_id'] ?? 0);
                    $furnitureId = (int) ($input['furniture_id'] ?? 0);

                    requireCollectionOwnership($pdo, $userId, $collectionId);
                    
                    $idResult = Validator::furnitureId($furnitureId);
                    if (!$idResult['valid']) {
                        jsonError($idResult['error']);
                    }
                    $furnitureId = $idResult['data'];

                    try {
                        removeFromCollection($pdo, $collectionId, $furnitureId);
                        jsonSuccess(null, 'Removed from collection');
                    } catch (RuntimeException $e) {
                        jsonError('Failed to remove from collection: ' . $e->getMessage());
                    }
                },
                (string) $userId
            );
            break;

        case 'collections/reorder-items':
            requireMethod('POST');

            $input = getJsonInput() ?? $_POST;
            $collectionId = (int) ($input['collection_id'] ?? 0);
            $order = $input['order'] ?? [];

            requireCollectionOwnership($pdo, $userId, $collectionId);

            if (!is_array($order) || empty($order)) {
                jsonError('Invalid order array');
            }

            foreach ($order as $item) {
                if (!isset($item['id']) || !isset($item['order'])) {
                    jsonError('Invalid order format. Each item must have "id" and "order" fields');
                }
            }

            try {
                reorderCollectionItems($pdo, $collectionId, $order);
                jsonSuccess(null, 'Items reordered');
            } catch (RuntimeException $e) {
                jsonError('Failed to reorder items: ' . $e->getMessage());
            }
            break;

        case 'collections/contains':
            requireMethod('GET');

            $furnitureId = getQueryInt('furniture_id', 0);
            $idResult = Validator::furnitureId($furnitureId);
            if (!$idResult['valid']) {
                jsonError($idResult['error']);
            }
            $furnitureId = $idResult['data'];

            $collections = getCollectionsContainingItem($pdo, $userId, $furnitureId);
            $ids = array_column($collections, 'id');
            jsonSuccess($ids);
            break;

        // =============================================
        // SUBMISSIONS ENDPOINTS
        // =============================================

        case 'submissions':
            requireMethod('GET');

            $page = max(1, getQueryInt('page', 1));
            $result = getUserSubmissions($pdo, $userId, $page);
            jsonSuccess($result['items'], null);
            break;

        case 'submissions/create':
            requireMethod('POST');

            // Rate limiting for submission creation (per user)
            withRateLimit(
                'api_submissions_create',
                RATE_LIMIT_SUBMISSIONS_CREATE['max'],
                RATE_LIMIT_SUBMISSIONS_CREATE['window'],
                function () use ($pdo, $userId) {
                    $furnitureId = getQueryInt('furniture_id', 0);
                    $type = $furnitureId > 0 ? SUBMISSION_TYPE_EDIT : SUBMISSION_TYPE_NEW;
                    
                    $validation = validateSubmissionInput($_POST, $type);
                    if (!$validation['valid']) {
                        jsonError(implode(', ', $validation['errors']));
                    }

                    if ($type === SUBMISSION_TYPE_EDIT) {
                        $id = submitFurnitureEdit($pdo, $userId, $furnitureId, $validation['data']);
                    } else {
                        $id = submitNewFurniture($pdo, $userId, $validation['data']);
                    }

                    jsonSuccess(['id' => $id], 'Submission received');
                },
                (string) $userId,
                'Too many submissions. Please wait a minute before submitting again.'
            );
            break;

        case 'submissions/cancel':
            requireMethod('POST');

            $id = getQueryInt('id', 0);
            if ($id <= 0 || !userOwnsSubmission($pdo, $userId, $id)) {
                jsonError(ERROR_NOT_FOUND, 404);
            }

            $submission = getSubmissionById($pdo, $id);
            if ($submission['status'] !== SUBMISSION_STATUS_PENDING) {
                jsonError('Cannot cancel a ' . $submission['status'] . ' submission');
            }

            try {
                deleteSubmission($pdo, $id);
                jsonSuccess(null, 'Submission cancelled');
            } catch (RuntimeException $e) {
                jsonError('Failed to cancel submission: ' . $e->getMessage());
            }
            break;

        default:
            jsonError('Unknown endpoint', 404);
    }

} catch (PDOException $e) {
    logException('dashboard_api_db', $e);
    jsonError('Database error', 500);
} catch (Exception $e) {
    logException('dashboard_api', $e);
    jsonError('Internal server error', 500);
}

