<?php
/**
 * GTAW Furniture Catalog - Collection Functions
 * 
 * Functions for managing user collections/wishlists.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'collections.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Load repository base class
require_once __DIR__ . '/repository.php';

// ============================================
// COLLECTION FUNCTIONS
// ============================================

/**
 * Get all collections for a user
 */
function getUserCollections(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT c.*, 
               u.username as owner_username,
               (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) as item_count
        FROM collections c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get collection by ID
 */
function getCollectionById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT c.*, u.username as owner_username
        FROM collections c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Get collection by user ID and slug
 */
function getCollectionBySlug(PDO $pdo, int $userId, string $slug): ?array
{
    $stmt = $pdo->prepare('
        SELECT c.*, u.username as owner_username
        FROM collections c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.user_id = ? AND c.slug = ?
    ');
    $stmt->execute([$userId, $slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Get public collection by username and slug (for sharing)
 */
function getPublicCollection(PDO $pdo, string $username, string $slug): ?array
{
    $stmt = $pdo->prepare('
        SELECT c.*, u.username as owner_username
        FROM collections c
        INNER JOIN users u ON c.user_id = u.id
        WHERE u.username = ? AND c.slug = ? AND c.is_public = 1
    ');
    $stmt->execute([$username, $slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create collection
 */
function createCollection(PDO $pdo, int $userId, array $data): int
{
    $repo = new CollectionRepository($pdo, $userId);
    return $repo->create($data);
}

/**
 * Update collection
 */
function updateCollection(PDO $pdo, int $id, array $data): bool
{
    // Get collection to determine user_id
    $collection = getCollectionById($pdo, $id);
    if (!$collection) {
        return false;
    }
    
    $repo = new CollectionRepository($pdo, (int) $collection['user_id']);
    return $repo->update($id, $data);
}

/**
 * Delete collection
 */
function deleteCollection(PDO $pdo, int $id): bool
{
    // Get collection to determine user_id (needed for repository, though not used in delete)
    $collection = getCollectionById($pdo, $id);
    if (!$collection) {
        return false;
    }
    
    $repo = new CollectionRepository($pdo, (int) $collection['user_id']);
    return $repo->delete($id);
}

/**
 * Check if user owns collection
 */
function userOwnsCollection(PDO $pdo, int $userId, int $collectionId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM collections WHERE id = ? AND user_id = ?');
    $stmt->execute([$collectionId, $userId]);
    return $stmt->fetch() !== false;
}

// ============================================
// COLLECTION ITEMS FUNCTIONS
// ============================================

/**
 * Get items in a collection
 */
function getCollectionItems(PDO $pdo, int $collectionId): array
{
    $stmt = $pdo->prepare('
        SELECT f.id, f.name, f.category_id, f.price, f.image_url,
               c.name as category_name, c.slug as category_slug,
               ci.sort_order, ci.added_at
        FROM collection_items ci
        INNER JOIN furniture f ON ci.furniture_id = f.id
        INNER JOIN categories c ON f.category_id = c.id
        WHERE ci.collection_id = ?
        ORDER BY ci.sort_order ASC, ci.added_at DESC
    ');
    $stmt->execute([$collectionId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Attach tags
    return attachTagsToFurniture($pdo, $items);
}

/**
 * Add item to collection
 * 
 * @return bool True on success, false if already in collection
 * @throws RuntimeException If database is not available
 */
function addToCollection(PDO $pdo, int $collectionId, int $furnitureId): bool
{
    // Get max sort order
    $stmt = $pdo->prepare('SELECT MAX(sort_order) FROM collection_items WHERE collection_id = ?');
    $stmt->execute([$collectionId]);
    $maxOrder = (int) $stmt->fetchColumn();
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO collection_items (collection_id, furniture_id, sort_order)
            VALUES (?, ?, ?)
        ');
        $result = $stmt->execute([$collectionId, $furnitureId, $maxOrder + 1]);
        return $result;
    } catch (PDOException $e) {
        // Duplicate entry - already in collection (expected condition, not an error)
        if ($e->getCode() == 23000) {
            return false;
        }
        // Re-throw other database errors as runtime exceptions
        throw new RuntimeException('Failed to add item to collection: ' . $e->getMessage(), 0, $e);
    }
}

/**
 * Remove item from collection
 * 
 * @return bool True on success, false if not found
 * @throws RuntimeException If database is not available
 */
function removeFromCollection(PDO $pdo, int $collectionId, int $furnitureId): bool
{
    $stmt = $pdo->prepare('DELETE FROM collection_items WHERE collection_id = ? AND furniture_id = ?');
    $result = $stmt->execute([$collectionId, $furnitureId]);
    
    // Return true if row was deleted, false if not found (expected condition)
    return $result && $stmt->rowCount() > 0;
}

/**
 * Check if item is in collection
 */
function isInCollection(PDO $pdo, int $collectionId, int $furnitureId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM collection_items WHERE collection_id = ? AND furniture_id = ?');
    $stmt->execute([$collectionId, $furnitureId]);
    return $stmt->fetch() !== false;
}

/**
 * Reorder collection items
 * 
 * @throws RuntimeException If database is not available or update fails
 */
function reorderCollectionItems(PDO $pdo, int $collectionId, array $order): bool
{
    $stmt = $pdo->prepare('
        UPDATE collection_items SET sort_order = ? 
        WHERE collection_id = ? AND furniture_id = ?
    ');
    
    foreach ($order as $item) {
        $result = $stmt->execute([(int) $item['order'], $collectionId, (int) $item['id']]);
        if (!$result) {
            throw new RuntimeException('Failed to reorder collection items');
        }
    }
    
    return true;
}

/**
 * Count items in collection
 */
function countCollectionItems(PDO $pdo, int $collectionId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM collection_items WHERE collection_id = ?');
    $stmt->execute([$collectionId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get user's collections that contain a specific furniture item
 */
function getCollectionsContainingItem(PDO $pdo, int $userId, int $furnitureId): array
{
    $stmt = $pdo->prepare('
        SELECT c.id, c.name, c.slug
        FROM collections c
        INNER JOIN collection_items ci ON c.id = ci.collection_id
        WHERE c.user_id = ? AND ci.furniture_id = ?
    ');
    $stmt->execute([$userId, $furnitureId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

