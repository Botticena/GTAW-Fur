<?php
/**
 * GTAW Furniture Catalog - Data Functions
 * 
 * All data retrieval and manipulation functions.
 * 
 * ERROR HANDLING PATTERN:
 * 
 * This file follows a consistent error handling pattern:
 * 
 * 1. Return false/null for EXPECTED business conditions:
 *    - Item not found (e.g., furniture doesn't exist)
 *    - Already exists (e.g., already favorited)
 *    - Business logic constraints (e.g., cannot delete category with items)
 *    These are normal business outcomes, not errors.
 * 
 * 2. Throw RuntimeException for UNEXPECTED errors:
 *    - Database connection failures
 *    - SQL execution failures
 *    - Invalid state (e.g., no data to update)
 *    - System-level errors
 *    These indicate something went wrong that shouldn't happen.
 * 
 * Examples:
 * - addFavorite() returns false if already favorited (expected) but throws on DB error (unexpected)
 * - removeFavorite() returns false if not found (expected) but throws on DB error (unexpected)
 * - updateFurniture() throws RuntimeException on any failure (all failures are unexpected)
 * 
 * This pattern allows callers to distinguish between expected business outcomes and actual errors.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Load utility functions
require_once __DIR__ . '/utils.php';

// Load repository base class
require_once __DIR__ . '/repository.php';

// Load validator class
require_once __DIR__ . '/validator.php';

// ============================================
// FURNITURE FUNCTIONS
// ============================================

/**
 * Get furniture list with pagination and filters
 * 
 * @param PDO $pdo Database connection
 * @param int $page Current page number
 * @param int $perPage Items per page
 * @param string|null $category Category slug to filter by
 * @param array $tags Array of tag slugs to filter by
 * @param string $sort Sort column (name, price, newest)
 * @param string $order Sort order (asc, desc)
 * @param int|null $userFavoritesId If set, only return favorites for this user ID
 */
function getFurnitureList(
    PDO $pdo,
    int $page = 1,
    int $perPage = 24,
    ?string $category = null,
    array $tags = [],
    string $sort = 'name',
    string $order = 'asc',
    ?int $userFavoritesId = null
): array {
    $perPage = min(max(1, $perPage), MAX_ITEMS_PER_PAGE);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    // Validate sort column
    $validSorts = ['name', 'price', 'created_at'];
    $sortColumn = in_array($sort, $validSorts) ? $sort : 'name';
    if ($sort === 'newest') {
        $sortColumn = 'created_at';
        $order = 'desc';
    }
    
    $orderDir = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    
    // Build query
    $where = [];
    $params = [];
    $extraJoins = '';
    
    if ($category) {
        $where[] = 'c.slug = ?';
        $params[] = $category;
    }
    
    // Handle favorites-only filter
    if ($userFavoritesId !== null) {
        $extraJoins .= ' INNER JOIN favorites fav ON f.id = fav.furniture_id AND fav.user_id = ?';
        $params[] = $userFavoritesId;
    }
    
    // Handle tags filter
    $tagJoin = '';
    if (!empty($tags)) {
        $tags = array_filter(array_map('trim', $tags));
        if (!empty($tags)) {
            $placeholders = implode(',', array_fill(0, count($tags), '?'));
            $tagJoin = "INNER JOIN furniture_tags ft ON f.id = ft.furniture_id
                        INNER JOIN tags t ON ft.tag_id = t.id AND t.slug IN ({$placeholders})";
            $params = array_merge($params, $tags);
        }
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total
    $countSql = "SELECT COUNT(DISTINCT f.id) 
                 FROM furniture f
                 INNER JOIN categories c ON f.category_id = c.id
                 {$extraJoins}
                 {$tagJoin}
                 {$whereClause}";
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    
    // Get items
    $sql = "SELECT DISTINCT f.id, f.name, f.category_id, f.price, f.image_url, f.created_at,
                   c.name as category_name, c.slug as category_slug
            FROM furniture f
            INNER JOIN categories c ON f.category_id = c.id
            {$extraJoins}
            {$tagJoin}
            {$whereClause}
            ORDER BY f.{$sortColumn} {$orderDir}
            LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load tags for each item
    $items = attachTagsToFurniture($pdo, $items);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Search furniture by name, category, and tags
 * 
 * Searches across:
 * - Furniture name
 * - Category name
 * - Tag names
 * 
 * Results are ordered by relevance (name matches first, then category, then tags)
 * 
 * @param PDO $pdo Database connection
 * @param string $query Search query
 * @param int $page Current page number
 * @param int $perPage Items per page
 * @param int|null $userFavoritesId If set, only return favorites for this user ID
 */
function searchFurniture(PDO $pdo, string $query, int $page = 1, int $perPage = 24, ?int $userFavoritesId = null): array
{
    if (strlen($query) < MIN_SEARCH_LENGTH) {
        return ['items' => [], 'pagination' => createPagination(0, $page, $perPage)];
    }
    
    $perPage = min(max(1, $perPage), MAX_ITEMS_PER_PAGE);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    // Use LIKE for search across name, category, and tags
    $searchTerm = '%' . $query . '%';
    
    // Build favorites join if needed
    $favoritesJoin = '';
    $favoritesParams = [];
    if ($userFavoritesId !== null) {
        $favoritesJoin = 'INNER JOIN favorites fav ON f.id = fav.furniture_id AND fav.user_id = ?';
        $favoritesParams[] = $userFavoritesId;
    }
    
    // Count total distinct furniture items matching the search
    $countSql = "
        SELECT COUNT(DISTINCT f.id) 
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        {$favoritesJoin}
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE (f.name LIKE ? 
           OR c.name LIKE ? 
           OR t.name LIKE ?)
    ";
    
    $countParams = array_merge($favoritesParams, [$searchTerm, $searchTerm, $searchTerm]);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int) $stmt->fetchColumn();
    
    // Get items with relevance ordering
    // Name matches are most relevant, then category, then tags
    $sql = "
        SELECT DISTINCT f.id, f.name, f.category_id, f.price, f.image_url, f.created_at,
               c.name as category_name, c.slug as category_slug,
               CASE 
                   WHEN f.name LIKE ? THEN 1
                   WHEN c.name LIKE ? THEN 2
                   ELSE 3
               END as relevance
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        {$favoritesJoin}
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE (f.name LIKE ? 
           OR c.name LIKE ? 
           OR t.name LIKE ?)
        ORDER BY relevance ASC, f.name ASC
        LIMIT ? OFFSET ?
    ";
    
    $params = array_merge(
        [$searchTerm, $searchTerm],  // For CASE statement
        $favoritesParams,             // For favorites join
        [$searchTerm, $searchTerm, $searchTerm], // For WHERE clause
        [$perPage, $offset]
    );
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Remove relevance field from results (internal use only)
    foreach ($items as &$item) {
        unset($item['relevance']);
    }
    
    $items = attachTagsToFurniture($pdo, $items);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Get single furniture item by ID
 * 
 * Optimized to use a single query with JOINs and subqueries,
 * reducing database round-trips from 3 queries to 1.
 */
function getFurnitureById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT f.id, f.name, f.category_id, f.price, f.image_url, f.created_at, f.updated_at,
               c.name as category_name, c.slug as category_slug,
               (SELECT COUNT(*) FROM favorites WHERE furniture_id = f.id) as favorite_count,
               GROUP_CONCAT(
                   JSON_OBJECT("id", t.id, "name", t.name, "slug", t.slug, "color", t.color)
                   ORDER BY t.name SEPARATOR "|||"
               ) as tags_json
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE f.id = ?
        GROUP BY f.id, f.name, f.category_id, f.price, f.image_url, f.created_at, f.updated_at,
                 c.name, c.slug
    ');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        return null;
    }
    
    // Parse tags JSON
    $item['tags'] = [];
    if (!empty($item['tags_json'])) {
        foreach (explode('|||', $item['tags_json']) as $tagJson) {
            $tag = json_decode($tagJson, true);
            if ($tag && is_array($tag)) {
                $item['tags'][] = $tag;
            }
        }
    }
    unset($item['tags_json']);
    
    // Ensure favorite_count is an integer
    $item['favorite_count'] = (int) $item['favorite_count'];
    
    return $item;
}

/**
 * Attach tags to furniture items
 * 
 * Optimized implementation that uses:
 * 1. Request-level caching (static variable) to prevent redundant queries
 *    when the same furniture items are loaded multiple times in a single request
 * 2. Batch querying with IN clause to fetch all uncached tags in a single query
 * 
 * This approach is more efficient than using GROUP_CONCAT because:
 * - It handles variable numbers of items efficiently
 * - The static cache prevents redundant queries across multiple function calls
 * - Simpler and more maintainable than JSON parsing approaches
 * 
 * @param PDO $pdo Database connection
 * @param array $items Array of furniture items
 * @return array Items with tags attached
 */
function attachTagsToFurniture(PDO $pdo, array $items): array
{
    if (empty($items)) {
        return $items;
    }
    
    // Request-level cache for tags (static variable persists for request lifetime)
    static $tagCache = [];
    
    $ids = array_column($items, 'id');
    $idsToFetch = [];
    $tagMap = [];
    
    // Check cache for already-loaded tags
    foreach ($ids as $id) {
        if (isset($tagCache[$id])) {
            $tagMap[$id] = $tagCache[$id];
        } else {
            $idsToFetch[] = $id;
        }
    }
    
    // Fetch tags for items not in cache
    if (!empty($idsToFetch)) {
        $placeholders = implode(',', array_fill(0, count($idsToFetch), '?'));
        
        $stmt = $pdo->prepare("
            SELECT ft.furniture_id, t.id, t.name, t.slug, t.color
            FROM furniture_tags ft
            INNER JOIN tags t ON ft.tag_id = t.id
            WHERE ft.furniture_id IN ({$placeholders})
        ");
        $stmt->execute($idsToFetch);
        
        // Initialize cache entries for all items being fetched
        foreach ($idsToFetch as $id) {
            $tagCache[$id] = [];
            $tagMap[$id] = [];
        }
        
        // Fetch and store tags
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $furnitureId = $row['furniture_id'];
            unset($row['furniture_id']);
            
            // Store in cache and tagMap
            $tagCache[$furnitureId][] = $row;
            $tagMap[$furnitureId][] = $row;
        }
    }
    
    // Attach tags to items
    foreach ($items as &$item) {
        $item['tags'] = $tagMap[$item['id']] ?? [];
    }
    
    return $items;
}

/**
 * Create furniture item
 */
function createFurniture(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO furniture (name, category_id, price, image_url, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ');
    
    $stmt->execute([
        $data['name'],
        $data['category_id'],
        $data['price'] ?? 0,
        $data['image_url'] ?? null,
    ]);
    
    $furnitureId = (int) $pdo->lastInsertId();
    
    // Add tags if provided
    if (!empty($data['tags'])) {
        syncFurnitureTags($pdo, $furnitureId, $data['tags']);
    }
    
    return $furnitureId;
}

/**
 * Update furniture item
 * 
 * Follows error handling pattern: throws RuntimeException for all failures (all failures
 * are considered unexpected errors, not expected business conditions).
 * 
 * @param PDO $pdo Database connection
 * @param int $id Furniture ID to update
 * @param array $data Data to update
 * @return bool True on success
 * @throws RuntimeException If database is not available, update fails, or invalid state (e.g., no data to update)
 */
function updateFurniture(PDO $pdo, int $id, array $data): bool
{
    $fields = [];
    $params = [];
    
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
    }
    if (isset($data['category_id'])) {
        $fields[] = 'category_id = ?';
        $params[] = $data['category_id'];
    }
    if (array_key_exists('price', $data)) {
        $fields[] = 'price = ?';
        $params[] = $data['price'] ?? 0;
    }
    if (array_key_exists('image_url', $data)) {
        $fields[] = 'image_url = ?';
        $params[] = $data['image_url'];
    }
    
    if (empty($fields)) {
        throw new RuntimeException('No data provided to update');
    }
    
    $params[] = $id;
    $sql = 'UPDATE furniture SET ' . implode(', ', $fields) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        throw new RuntimeException('Failed to update furniture item');
    }
    
    // Update tags if provided
    if (isset($data['tags'])) {
        syncFurnitureTags($pdo, $id, $data['tags']);
    }
    
    return true;
}

/**
 * Delete furniture item
 * 
 * @throws RuntimeException If database is not available or deletion fails
 */
function deleteFurniture(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM furniture WHERE id = ?');
    $result = $stmt->execute([$id]);
    
    if (!$result) {
        throw new RuntimeException('Failed to delete furniture item');
    }
    
    return true;
}

/**
 * Update furniture image URL only
 * 
 * NOTE: This function is used by includes/image.php. To prevent circular dependencies,
 * this file (functions.php) must NEVER require or include image.php.
 * 
 * @throws RuntimeException If database is not available or update fails
 */
function updateFurnitureImage(PDO $pdo, int $id, string $imageUrl): bool
{
    $stmt = $pdo->prepare('UPDATE furniture SET image_url = ? WHERE id = ?');
    $result = $stmt->execute([$imageUrl, $id]);
    
    if (!$result) {
        throw new RuntimeException('Failed to update furniture image');
    }
    
    return true;
}

/**
 * Sync furniture tags
 * 
 * Optimized to only update tags that have changed, reducing unnecessary
 * database operations when tags haven't been modified.
 * 
 * @throws RuntimeException If database is not available (defensive check)
 * Note: PDO exceptions will be thrown automatically for SQL errors due to PDO::ERRMODE_EXCEPTION
 */
function syncFurnitureTags(PDO $pdo, int $furnitureId, array $tagIds): void
{
    // Normalize tag IDs to integers for comparison
    $newTagIds = array_map('intval', $tagIds);
    $newTagIds = array_unique($newTagIds);
    sort($newTagIds);
    
    // Get current tags
    $stmt = $pdo->prepare('SELECT tag_id FROM furniture_tags WHERE furniture_id = ?');
    $stmt->execute([$furnitureId]);
    $currentTagIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $currentTagIds = array_map('intval', $currentTagIds);
    sort($currentTagIds);
    
    // If tags are identical, no changes needed
    if ($newTagIds === $currentTagIds) {
        return;
    }
    
    // Calculate differences
    $toAdd = array_diff($newTagIds, $currentTagIds);
    $toRemove = array_diff($currentTagIds, $newTagIds);
    
    // Remove tags that are no longer needed
    if (!empty($toRemove)) {
        $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
        $stmt = $pdo->prepare("DELETE FROM furniture_tags WHERE furniture_id = ? AND tag_id IN ({$placeholders})");
        $stmt->execute(array_merge([$furnitureId], $toRemove));
    }
    
    // Add new tags (batch insert for efficiency)
    if (!empty($toAdd)) {
        $values = [];
        $params = [];
        foreach ($toAdd as $tagId) {
            $values[] = '(?, ?)';
            $params[] = $furnitureId;
            $params[] = $tagId;
        }
        $sql = 'INSERT INTO furniture_tags (furniture_id, tag_id) VALUES ' . implode(', ', $values);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

// ============================================
// CATEGORY FUNCTIONS
// ============================================

/**
 * Get all categories with item counts
 */
function getCategories(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT c.id, c.name, c.slug, c.icon, c.sort_order,
               COUNT(f.id) as item_count
        FROM categories c
        LEFT JOIN furniture f ON c.id = f.category_id
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.name ASC
    ');
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get category by ID
 */
function getCategoryById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Get category by slug
 */
function getCategoryBySlug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create category
 */
function createCategory(PDO $pdo, array $data): int
{
    $repo = new CategoryRepository($pdo);
    return $repo->create($data);
}

/**
 * Update category
 */
function updateCategory(PDO $pdo, int $id, array $data): bool
{
    $repo = new CategoryRepository($pdo);
    return $repo->update($id, $data);
}

/**
 * Delete category (only if no furniture items)
 */
function deleteCategory(PDO $pdo, int $id): bool
{
    $repo = new CategoryRepository($pdo);
    return $repo->delete($id);
}

// ============================================
// TAG GROUP FUNCTIONS
// ============================================

/**
 * Get all tag groups
 */
function getTagGroups(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT id, name, slug, color, sort_order
        FROM tag_groups
        ORDER BY sort_order ASC, name ASC
    ');
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get tag group by ID
 */
function getTagGroupById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tag_groups WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create tag group
 */
function createTagGroup(PDO $pdo, array $data): int
{
    $repo = new TagGroupRepository($pdo);
    return $repo->create($data);
}

/**
 * Update tag group
 */
function updateTagGroup(PDO $pdo, int $id, array $data): bool
{
    $repo = new TagGroupRepository($pdo);
    return $repo->update($id, $data);
}

/**
 * Delete tag group (tags will have group_id set to NULL)
 */
function deleteTagGroup(PDO $pdo, int $id): bool
{
    $repo = new TagGroupRepository($pdo);
    return $repo->delete($id);
}

// ============================================
// TAG FUNCTIONS
// ============================================

/**
 * Get all tags with group information
 */
function getTags(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT t.id, t.name, t.slug, t.color, t.group_id,
               tg.name as group_name, tg.slug as group_slug, tg.color as group_color,
               COUNT(ft.furniture_id) as usage_count
        FROM tags t
        LEFT JOIN tag_groups tg ON t.group_id = tg.id
        LEFT JOIN furniture_tags ft ON t.id = ft.tag_id
        GROUP BY t.id
        ORDER BY tg.sort_order ASC, t.name ASC
    ');
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get tags grouped by their tag groups
 * Returns structure: { groups: [...], ungrouped: [...] }
 */
function getTagsGrouped(PDO $pdo): array
{
    // Get all groups
    $groups = getTagGroups($pdo);
    
    // Get all tags with usage count
    $stmt = $pdo->query('
        SELECT t.id, t.name, t.slug, t.color, t.group_id,
               COUNT(ft.furniture_id) as usage_count
        FROM tags t
        LEFT JOIN furniture_tags ft ON t.id = ft.tag_id
        GROUP BY t.id
        ORDER BY t.name ASC
    ');
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize tags by group
    $groupedTags = [];
    $ungrouped = [];
    
    foreach ($allTags as $tag) {
        if ($tag['group_id']) {
            if (!isset($groupedTags[$tag['group_id']])) {
                $groupedTags[$tag['group_id']] = [];
            }
            $groupedTags[$tag['group_id']][] = $tag;
        } else {
            $ungrouped[] = $tag;
        }
    }
    
    // Attach tags to groups
    foreach ($groups as &$group) {
        $group['tags'] = $groupedTags[$group['id']] ?? [];
    }
    
    return [
        'groups' => $groups,
        'ungrouped' => $ungrouped,
    ];
}

/**
 * Get tag by ID
 */
function getTagById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tags WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Get or create tag by slug
 */
function getOrCreateTag(PDO $pdo, string $name): int
{
    $slug = createSlug($name);
    
    $stmt = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
    $stmt->execute([$slug]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        return (int) $existing['id'];
    }
    
    $stmt = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
    $stmt->execute([$name, $slug]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Create tag
 */
function createTag(PDO $pdo, array $data): int
{
    $repo = new TagRepository($pdo);
    return $repo->create($data);
}

/**
 * Update tag
 */
function updateTag(PDO $pdo, int $id, array $data): bool
{
    $repo = new TagRepository($pdo);
    return $repo->update($id, $data);
}

/**
 * Delete tag
 */
function deleteTag(PDO $pdo, int $id): bool
{
    $repo = new TagRepository($pdo);
    return $repo->delete($id);
}

// ============================================
// FAVORITES FUNCTIONS
// ============================================

/**
 * Get user's favorites
 */
function getUserFavorites(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT f.id, f.name, f.category_id, f.price, f.image_url,
               c.name as category_name, c.slug as category_slug,
               fav.created_at as favorited_at
        FROM favorites fav
        INNER JOIN furniture f ON fav.furniture_id = f.id
        INNER JOIN categories c ON f.category_id = c.id
        WHERE fav.user_id = ?
        ORDER BY fav.created_at DESC
    ');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return attachTagsToFurniture($pdo, $items);
}

/**
 * Get user's favorite IDs (for quick checking)
 */
function getUserFavoriteIds(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT furniture_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Add furniture to favorites
 * 
 * Follows error handling pattern: returns false for expected conditions (already favorited,
 * furniture not found), throws RuntimeException for unexpected database errors.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $furnitureId Furniture ID to add
 * @return bool True on success, false if already favorited or furniture not found (expected conditions)
 * @throws RuntimeException If database is not available or unexpected database error occurs
 */
function addFavorite(PDO $pdo, int $userId, int $furnitureId): bool
{
    // Verify furniture exists
    $stmt = $pdo->prepare('SELECT id FROM furniture WHERE id = ?');
    $stmt->execute([$furnitureId]);
    if (!$stmt->fetch()) {
        return false; // Furniture not found - expected condition, not an error
    }
    
    try {
        $stmt = $pdo->prepare('INSERT INTO favorites (user_id, furniture_id) VALUES (?, ?)');
        $result = $stmt->execute([$userId, $furnitureId]);
        return $result;
    } catch (PDOException $e) {
        // Duplicate entry - already favorited (expected condition, not an error)
        if ($e->getCode() == 23000) {
            return false;
        }
        // Re-throw other database errors as runtime exceptions
        throw new RuntimeException('Failed to add favorite: ' . $e->getMessage(), 0, $e);
    }
}

/**
 * Remove furniture from favorites
 * 
 * Follows error handling pattern: returns false for expected conditions (not found),
 * throws RuntimeException for unexpected database errors.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $furnitureId Furniture ID to remove
 * @return bool True on success, false if not found (expected condition)
 * @throws RuntimeException If database is not available or unexpected database error occurs
 */
function removeFavorite(PDO $pdo, int $userId, int $furnitureId): bool
{
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND furniture_id = ?');
    $result = $stmt->execute([$userId, $furnitureId]);
    
    // Return true if row was deleted, false if not found (expected condition)
    return $result && $stmt->rowCount() > 0;
}

/**
 * Count user's favorites
 */
function countUserFavorites(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Check if furniture is favorited by user
 */
function isFavorited(PDO $pdo, int $userId, int $furnitureId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND furniture_id = ?');
    $stmt->execute([$userId, $furnitureId]);
    return $stmt->fetch() !== false;
}

// ============================================
// USER FUNCTIONS
// ============================================

/**
 * Get all users (for admin)
 * 
 * Optimized to use a single query with window function for total count,
 * reducing database round-trips from 2 queries to 1.
 */
function getUsers(PDO $pdo, int $page = 1, int $perPage = 50): array
{
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare('
        SELECT u.*, 
               (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count,
               COUNT(*) OVER() as total
        FROM users u
        ORDER BY u.last_login DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$perPage, $offset]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract total from first row (same for all rows due to window function)
    $total = !empty($items) ? (int) ($items[0]['total'] ?? 0) : 0;
    
    // Remove 'total' from items array to maintain clean data structure
    foreach ($items as &$item) {
        unset($item['total']);
    }
    unset($item);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Get user by ID
 */
function getUserById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Ban user
 * 
 * @throws RuntimeException If database is not available or update fails
 */
function banUser(PDO $pdo, int $userId, ?string $reason = null): bool
{
    $stmt = $pdo->prepare('UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?');
    $result = $stmt->execute([$reason, $userId]);
    
    if (!$result) {
        throw new RuntimeException('Failed to ban user');
    }
    
    return true;
}

/**
 * Unban user
 * 
 * @throws RuntimeException If database is not available or update fails
 */
function unbanUser(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?');
    $result = $stmt->execute([$userId]);
    
    if (!$result) {
        throw new RuntimeException('Failed to unban user');
    }
    
    return true;
}

// ============================================
// ADMIN FUNCTIONS
// ============================================

/**
 * Create admin account
 */
function createAdmin(PDO $pdo, string $username, string $password): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Get dashboard statistics
 * 
 * Optimized to use 2 queries instead of 6 by combining COUNT queries
 * into a single query with subqueries.
 */
function getDashboardStats(PDO $pdo): array
{
    // Get all counts in a single query using subqueries
    $stmt = $pdo->query('
        SELECT 
            (SELECT COUNT(*) FROM furniture) as total_furniture,
            (SELECT COUNT(*) FROM categories) as total_categories,
            (SELECT COUNT(*) FROM tags) as total_tags,
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM favorites) as total_favorites
    ');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent users separately (needs ORDER BY, can't be combined with counts)
    $stmt = $pdo->query('
        SELECT id, username, main_character, last_login 
        FROM users 
        ORDER BY last_login DESC 
        LIMIT 5
    ');
    
    return [
        'total_furniture' => (int) $row['total_furniture'],
        'total_categories' => (int) $row['total_categories'],
        'total_tags' => (int) $row['total_tags'],
        'total_users' => (int) $row['total_users'],
        'total_favorites' => (int) $row['total_favorites'],
        'recent_users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Create pagination array
 * 
 * Returns pagination metadata using snake_case for API consistency.
 */
function createPagination(int $total, int $page, int $perPage): array
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
    ];
}

/**
 * Validate furniture input
 * 
 * @param array $input Input data to validate
 * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
 */
function validateFurnitureInput(array $input): array
{
    return Validator::furnitureInput($input);
}

/**
 * Parse CSV file for bulk import
 */
function parseCsvImport(PDO $pdo, string $csvContent): array
{
    $lines = explode("\n", $csvContent);
    $items = [];
    $errors = [];
    
    // Parse header
    $header = str_getcsv(array_shift($lines));
    $header = array_map('strtolower', array_map('trim', $header));
    
    $nameIndex = array_search('name', $header);
    $categoryIndex = array_search('category_slug', $header);
    $priceIndex = array_search('price', $header);
    $tagsIndex = array_search('tags', $header);
    $imageIndex = array_search('image_url', $header);
    
    if ($nameIndex === false || $categoryIndex === false) {
        return [
            'items' => [],
            'errors' => ['CSV must have "name" and "category_slug" columns'],
        ];
    }
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        $row = str_getcsv($line);
        $rowNum = $lineNum + 2; // Account for header and 1-based numbering
        
        $name = trim($row[$nameIndex] ?? '');
        $categorySlug = trim($row[$categoryIndex] ?? '');
        
        if (empty($name)) {
            $errors[] = "Row {$rowNum}: Name is required";
            continue;
        }
        
        $category = getCategoryBySlug($pdo, $categorySlug);
        if (!$category) {
            $errors[] = "Row {$rowNum}: Category '{$categorySlug}' not found";
            continue;
        }
        
        $item = [
            'name' => $name,
            'category_id' => $category['id'],
            'price' => $priceIndex !== false ? (int) ($row[$priceIndex] ?? 0) : 0,
            'image_url' => $imageIndex !== false ? trim($row[$imageIndex] ?? '') : null,
            'tags' => [],
        ];
        
        // Parse tags
        if ($tagsIndex !== false && !empty($row[$tagsIndex])) {
            $tagNames = array_map('trim', explode(',', $row[$tagsIndex]));
            foreach ($tagNames as $tagName) {
                if (!empty($tagName)) {
                    $item['tags'][] = getOrCreateTag($pdo, $tagName);
                }
            }
        }
        
        $items[] = $item;
    }
    
    return [
        'items' => $items,
        'errors' => $errors,
    ];
}

/**
 * Export furniture as CSV
 * 
 * Optimized to use a single query with GROUP_CONCAT to avoid N+1 query pattern.
 */
function exportFurnitureCsv(PDO $pdo): string
{
    $output = fopen('php://temp', 'r+');
    
    // Header
    fputcsv($output, ['name', 'category_slug', 'price', 'tags', 'image_url'], ',', '"', '');
    
    // Fetch all furniture with tags in a single query using GROUP_CONCAT
    $stmt = $pdo->query('
        SELECT f.id, f.name, f.price, f.image_url, c.slug as category_slug,
               GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ",") as tags
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        GROUP BY f.id, f.name, f.price, f.image_url, c.slug
        ORDER BY f.name
    ');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // GROUP_CONCAT returns NULL if no tags, convert to empty string
        $tags = $row['tags'] ?? '';
        
        fputcsv($output, [
            $row['name'],
            $row['category_slug'],
            $row['price'],
            $tags,
            $row['image_url'] ?? '',
        ], ',', '"', '\\');
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

