<?php
/**
 * GTAW Furniture Catalog - Data Functions
 * 
 * Error handling pattern:
 * - Return false/null for expected conditions (not found, already exists)
 * - Throw RuntimeException for unexpected errors (DB failures, invalid state)
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/search.php';

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
    int $perPage = 50,
    ?string $category = null,
    array $tags = [],
    string $sort = 'name',
    string $order = 'asc',
    ?int $userFavoritesId = null
): array {
    if (!is_array($tags)) {
        $tags = [];
    }
    $tags = array_filter(array_map('trim', array_map('strval', $tags)));
    
    $maxPerPage = getMaxItemsPerPage();
    $perPage = min(max(1, $perPage), $maxPerPage);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    $validSorts = ['name', 'price', 'created_at'];
    $sortColumn = in_array($sort, $validSorts) ? $sort : 'name';
    if ($sort === 'newest') {
        $sortColumn = 'created_at';
        $order = 'desc';
    }
    
    $orderDir = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    
    $where = [];
    $params = [];
    $extraJoins = '';
    
    // Category filter via junction table
    $categoryJoin = '';
    if ($category) {
        $categoryJoin = 'INNER JOIN furniture_categories fc_filter ON f.id = fc_filter.furniture_id
                         INNER JOIN categories c_filter ON fc_filter.category_id = c_filter.id AND c_filter.slug = ?';
        $params[] = $category;
    }
    
    if ($userFavoritesId !== null) {
        $extraJoins .= ' INNER JOIN favorites fav ON f.id = fav.furniture_id AND fav.user_id = ?';
        $params[] = $userFavoritesId;
    }
    
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
    
    $countSql = "SELECT COUNT(DISTINCT f.id) 
                 FROM furniture f
                 {$categoryJoin}
                 {$extraJoins}
                 {$tagJoin}
                 {$whereClause}";
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    
    $sql = "SELECT DISTINCT f.id, f.name, f.price, f.image_url, f.created_at
            FROM furniture f
            {$categoryJoin}
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
    
    $items = attachCategoriesToFurniture($pdo, $items);
    $items = attachTagsToFurniture($pdo, $items);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Search furniture by name, category, and tags
 * 
 * Uses enhanced search system with FULLTEXT support, optimized synonyms,
 * query tokenization, and search analytics.
 * 
 * @param PDO $pdo Database connection
 * @param string $query Search query
 * @param int $page Page number
 * @param int $perPage Items per page
 * @param int|null $userFavoritesId Filter to user's favorites only
 * @param bool $expandSynonyms Expand query with synonyms (default: true)
 * @return array ['items' => [...], 'pagination' => [...], 'search_meta' => [...]]
 */
function searchFurniture(
    PDO $pdo, 
    string $query, 
    int $page = 1, 
    int $perPage = 50, 
    ?int $userFavoritesId = null,
    bool $expandSynonyms = true
): array {
    // Delegate to enhanced search system
    return searchFurnitureEnhanced($pdo, $query, $page, $perPage, $userFavoritesId, $expandSynonyms);
}

/**
 * Find potential duplicate furniture items
 * 
 * Strict matching - only finds exact name matches (case-insensitive).
 * Same category adds bonus points to prioritize more likely duplicates.
 * 
 * Scoring:
 * - Exact name match: 100 points
 * - Same category bonus: +20 points
 * 
 * This is an advisory feature to help users avoid creating duplicates.
 * It does not block submissions.
 * 
 * @param PDO $pdo Database connection
 * @param string $name Furniture name to check (exact match)
 * @param int|null $categoryId Category ID for bonus scoring
 * @param int|null $excludeId Exclude this ID (for edit forms)
 * @param int $limit Maximum results (default 5)
 * @return array Matches with similarity scores
 */
function findPotentialDuplicates(
    PDO $pdo,
    string $name,
    ?int $categoryId = null,
    ?int $excludeId = null,
    int $limit = 5
): array {
    $name = trim($name);
    if (strlen($name) < 3) {
        return [];
    }
    
    $normalizedName = strtolower($name);
    
    // Check if item shares at least one category with the provided category
    $sql = "
        SELECT DISTINCT
            f.id, 
            f.name, 
            f.price, 
            f.image_url,
            100 + CASE WHEN EXISTS (
                SELECT 1 FROM furniture_categories fc2 
                WHERE fc2.furniture_id = f.id AND fc2.category_id = :cat_id
            ) THEN 20 ELSE 0 END as similarity_score
        FROM furniture f
        WHERE LOWER(f.name) = :exact_name
    ";
    
    $params = [
        ':exact_name' => $normalizedName,
        ':cat_id' => $categoryId ?? 0,
    ];
    
    if ($excludeId !== null) {
        $sql .= " AND f.id != :exclude_id";
        $params[':exclude_id'] = $excludeId;
    }
    
    $sql .= " ORDER BY similarity_score DESC, f.name ASC LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters - limit requires explicit INT type
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Attach categories
    return attachCategoriesToFurniture($pdo, $items);
}

/**
 * Get related furniture based on shared tags and category
 * 
 * Algorithm:
 * 1. Find items sharing tags with current item
 * 2. Prioritize same-category matches
 * 3. Order by shared tag count, then random for variety
 */
function getRelatedFurniture(PDO $pdo, int $id, int $limit = 4): array
{
    // Get primary category and tags for current item
    $stmt = $pdo->prepare('
        SELECT fc.category_id, GROUP_CONCAT(DISTINCT ft.tag_id) as tag_ids
        FROM furniture f
        LEFT JOIN furniture_categories fc ON f.id = fc.furniture_id AND fc.is_primary = 1
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        WHERE f.id = ?
        GROUP BY f.id, fc.category_id
    ');
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        return [];
    }
    
    $categoryId = (int)($current['category_id'] ?? 0);
    $tagIds = $current['tag_ids'] ? explode(',', $current['tag_ids']) : [];
    
    if (empty($tagIds)) {
        // No tags - find items in same category
        $stmt = $pdo->prepare('
            SELECT DISTINCT f.id, f.name, f.price, f.image_url
            FROM furniture f
            INNER JOIN furniture_categories fc ON f.id = fc.furniture_id
            WHERE fc.category_id = ? AND f.id != ?
            ORDER BY f.created_at DESC, f.id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $id, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return attachCategoriesToFurniture($pdo, $items);
    }
    
    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    
    $sql = "
        SELECT 
            f.id, 
            f.name, 
            f.price, 
            f.image_url,
            COUNT(DISTINCT ft.tag_id) as shared_tags,
            EXISTS (
                SELECT 1 FROM furniture_categories fc2 
                WHERE fc2.furniture_id = f.id AND fc2.category_id = ?
            ) as same_category
        FROM furniture f
        INNER JOIN furniture_tags ft ON f.id = ft.furniture_id
        WHERE ft.tag_id IN ({$placeholders})
          AND f.id != ?
        GROUP BY f.id, f.name, f.price, f.image_url
        ORDER BY same_category DESC, shared_tags DESC, f.id DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    
    $paramIndex = 1;
    $stmt->bindValue($paramIndex++, $categoryId, PDO::PARAM_INT);
    foreach ($tagIds as $tagId) {
        $stmt->bindValue($paramIndex++, (int)$tagId, PDO::PARAM_INT);
    }
    $stmt->bindValue($paramIndex++, $id, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return attachCategoriesToFurniture($pdo, $items);
}

/**
 * Get single furniture item by ID
 * 
 * Returns categories array with primary category first.
 * Backwards compatible: also sets category_id, category_name, category_slug from primary.
 */
function getFurnitureById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT f.id, f.name, f.price, f.image_url, f.created_at, f.updated_at,
               (SELECT COUNT(*) FROM favorites WHERE furniture_id = f.id) as favorite_count,
               GROUP_CONCAT(
                   JSON_OBJECT("id", t.id, "name", t.name, "slug", t.slug, "color", t.color)
                   ORDER BY t.name SEPARATOR "|||"
               ) as tags_json
        FROM furniture f
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE f.id = ?
        GROUP BY f.id, f.name, f.price, f.image_url, f.created_at, f.updated_at
    ');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        return null;
    }
    
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
    
    $item['favorite_count'] = (int) $item['favorite_count'];
    
    // Fetch categories (primary first)
    $catStmt = $pdo->prepare('
        SELECT c.id, c.name, c.slug, c.icon, fc.is_primary
        FROM furniture_categories fc
        INNER JOIN categories c ON fc.category_id = c.id
        WHERE fc.furniture_id = ?
        ORDER BY fc.is_primary DESC, c.sort_order ASC
    ');
    $catStmt->execute([$id]);
    $item['categories'] = [];
    while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $item['categories'][] = [
            'id' => (int) $cat['id'],
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'icon' => $cat['icon'],
            'is_primary' => (bool) $cat['is_primary'],
        ];
    }
    
    // Backwards compatibility
    addBackwardsCompatibilityCategoryFields($item);
    
    return $item;
}

/**
 * Get multiple furniture items by IDs
 * 
 * Efficient batch query for recently viewed, etc.
 * Preserves the order of IDs passed in.
 */
function getFurnitureByIds(PDO $pdo, array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT f.id, f.name, f.price, f.image_url
        FROM furniture f
        WHERE f.id IN ({$placeholders})
    ");
    $stmt->execute($ids);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Attach categories
    $items = attachCategoriesToFurniture($pdo, $items);
    
    $indexed = [];
    foreach ($items as $item) {
        $indexed[$item['id']] = $item;
    }
    
    $result = [];
    foreach ($ids as $id) {
        if (isset($indexed[$id])) {
            $result[] = $indexed[$id];
        }
    }
    
    return $result;
}

/**
 * Attach tags to furniture items
 * 
 * Uses request-level caching and batch queries for efficiency.
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
    
    static $tagCache = [];
    
    $ids = array_column($items, 'id');
    $idsToFetch = [];
    $tagMap = [];
    
    foreach ($ids as $id) {
        if (isset($tagCache[$id])) {
            $tagMap[$id] = $tagCache[$id];
        } else {
            $idsToFetch[] = $id;
        }
    }
    
    if (!empty($idsToFetch)) {
        $placeholders = implode(',', array_fill(0, count($idsToFetch), '?'));
        
        $stmt = $pdo->prepare("
            SELECT ft.furniture_id, t.id, t.name, t.slug, t.color
            FROM furniture_tags ft
            INNER JOIN tags t ON ft.tag_id = t.id
            WHERE ft.furniture_id IN ({$placeholders})
        ");
        $stmt->execute($idsToFetch);
        
        foreach ($idsToFetch as $id) {
            $tagCache[$id] = [];
            $tagMap[$id] = [];
        }
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $furnitureId = $row['furniture_id'];
            unset($row['furniture_id']);
            $tagCache[$furnitureId][] = $row;
            $tagMap[$furnitureId][] = $row;
        }
    }
    
    foreach ($items as &$item) {
        $item['tags'] = $tagMap[$item['id']] ?? [];
    }
    
    return $items;
}

/**
 * Create furniture item
 * 
 * Accepts either category_ids (array) or category_id (single) for backwards compatibility.
 */
function createFurniture(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO furniture (name, price, image_url, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ');
    
    $stmt->execute([
        $data['name'],
        $data['price'] ?? 0,
        $data['image_url'] ?? null,
    ]);
    
    $furnitureId = (int) $pdo->lastInsertId();
    
    // Handle categories - accept array or single ID
    $categoryIds = normalizeCategoryIds($data);
    if (!empty($categoryIds)) {
        syncFurnitureCategories($pdo, $furnitureId, $categoryIds);
    }
    
    if (!empty($data['tags'])) {
        syncFurnitureTags($pdo, $furnitureId, $data['tags']);
    }
    
    return $furnitureId;
}

/**
 * Update furniture item
 * 
 * Accepts either category_ids (array) or category_id (single) for backwards compatibility.
 * 
 * @throws RuntimeException If update fails or no data provided
 */
function updateFurniture(PDO $pdo, int $id, array $data): bool
{
    $fields = [];
    $params = [];
    
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
    }
    if (array_key_exists('price', $data)) {
        $fields[] = 'price = ?';
        $params[] = $data['price'] ?? 0;
    }
    if (array_key_exists('image_url', $data)) {
        $fields[] = 'image_url = ?';
        $params[] = $data['image_url'];
    }
    
    // Handle categories - accept array or single ID
    $categoryIds = null;
    if (isset($data['category_ids']) || isset($data['category_id'])) {
        $categoryIds = normalizeCategoryIds($data);
    }
    $hasCategoryUpdate = $categoryIds !== null;
    
    if (empty($fields) && !$hasCategoryUpdate && !isset($data['tags'])) {
        throw new RuntimeException('No data provided to update');
    }
    
    // Update furniture table fields if any
    if (!empty($fields)) {
        $params[] = $id;
        $sql = 'UPDATE furniture SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new RuntimeException('Failed to update furniture item');
        }
    }
    
    // Sync categories if provided
    if ($hasCategoryUpdate && !empty($categoryIds)) {
        syncFurnitureCategories($pdo, $id, $categoryIds);
    }
    
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
    $newTagIds = array_map('intval', $tagIds);
    $newTagIds = array_unique($newTagIds);
    sort($newTagIds);
    
    $stmt = $pdo->prepare('SELECT tag_id FROM furniture_tags WHERE furniture_id = ?');
    $stmt->execute([$furnitureId]);
    $currentTagIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $currentTagIds = array_map('intval', $currentTagIds);
    sort($currentTagIds);
    
    if ($newTagIds === $currentTagIds) {
        return;
    }
    
    $toAdd = array_diff($newTagIds, $currentTagIds);
    $toRemove = array_diff($currentTagIds, $newTagIds);
    
    if (!empty($toRemove)) {
        $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
        $stmt = $pdo->prepare("DELETE FROM furniture_tags WHERE furniture_id = ? AND tag_id IN ({$placeholders})");
        $stmt->execute(array_merge([$furnitureId], $toRemove));
    }
    
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

/**
 * Sync furniture categories
 * 
 * First category in array is marked as primary.
 * Optimized to only update categories that have changed.
 */
function syncFurnitureCategories(PDO $pdo, int $furnitureId, array $categoryIds): void
{
    $newCategoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
    
    if (empty($newCategoryIds)) {
        throw new RuntimeException('At least one category is required');
    }
    
    $stmt = $pdo->prepare('SELECT category_id, is_primary FROM furniture_categories WHERE furniture_id = ? ORDER BY is_primary DESC');
    $stmt->execute([$furnitureId]);
    $current = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $currentCategoryIds = array_keys($current);
    
    $sortedNew = $newCategoryIds;
    sort($sortedNew);
    $sortedCurrent = $currentCategoryIds;
    sort($sortedCurrent);
    
    $primaryChanged = empty($currentCategoryIds) || $newCategoryIds[0] !== array_search(1, $current, true);
    
    if ($sortedNew === $sortedCurrent && !$primaryChanged) {
        return;
    }
    
    $toAdd = array_diff($newCategoryIds, $currentCategoryIds);
    $toRemove = array_diff($currentCategoryIds, $newCategoryIds);
    
    if (!empty($toRemove)) {
        $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
        $stmt = $pdo->prepare("DELETE FROM furniture_categories WHERE furniture_id = ? AND category_id IN ({$placeholders})");
        $stmt->execute(array_merge([$furnitureId], $toRemove));
    }
    
    if (!empty($toAdd)) {
        $values = [];
        $params = [];
        foreach ($toAdd as $categoryId) {
            $isPrimary = ($categoryId === $newCategoryIds[0]) ? 1 : 0;
            $values[] = '(?, ?, ?)';
            $params[] = $furnitureId;
            $params[] = $categoryId;
            $params[] = $isPrimary;
        }
        $sql = 'INSERT INTO furniture_categories (furniture_id, category_id, is_primary) VALUES ' . implode(', ', $values);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    // Update primary flag if changed
    if ($primaryChanged && !in_array($newCategoryIds[0], $toAdd)) {
        $pdo->prepare('UPDATE furniture_categories SET is_primary = 0 WHERE furniture_id = ?')->execute([$furnitureId]);
        $pdo->prepare('UPDATE furniture_categories SET is_primary = 1 WHERE furniture_id = ? AND category_id = ?')
            ->execute([$furnitureId, $newCategoryIds[0]]);
    }
    
    // Invalidate categories cache (item counts changed)
    cacheDelete('gtaw_categories_v2');
}

/**
 * Attach categories to furniture items
 * 
 * Efficiently loads categories for multiple furniture items in a single query.
 */
function attachCategoriesToFurniture(PDO $pdo, array $items): array
{
    if (empty($items)) {
        return [];
    }
    
    $ids = array_column($items, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT fc.furniture_id, c.id, c.name, c.slug, c.icon, fc.is_primary
        FROM furniture_categories fc
        INNER JOIN categories c ON fc.category_id = c.id
        WHERE fc.furniture_id IN ({$placeholders})
        ORDER BY fc.is_primary DESC, c.sort_order ASC
    ");
    $stmt->execute($ids);
    
    $categoryMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fid = (int) $row['furniture_id'];
        if (!isset($categoryMap[$fid])) {
            $categoryMap[$fid] = [];
        }
        $categoryMap[$fid][] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'icon' => $row['icon'],
            'is_primary' => (bool) $row['is_primary'],
        ];
    }
    
    foreach ($items as &$item) {
        $item['categories'] = $categoryMap[$item['id']] ?? [];
        // Backwards compatibility: set primary category fields
        addBackwardsCompatibilityCategoryFields($item);
    }
    
    return $items;
}

// ============================================
// CATEGORY FUNCTIONS
// ============================================

/**
 * Get all categories with item counts
 */
function getCategories(PDO $pdo): array
{
    // APCu cache (if available) to avoid repeated lookups
    // Updated to v2 to force cache invalidation after multi-category implementation
    $cacheKey = 'gtaw_categories_v2';
    $cached = cacheGet($cacheKey, false, $success);
    if ($success && is_array($cached)) {
        return $cached;
    }

    // Count all furniture items per category (regardless of primary/secondary status)
    $stmt = $pdo->query('
        SELECT c.id, c.name, c.slug, c.icon, c.sort_order,
               COALESCE(COUNT(DISTINCT fc.furniture_id), 0) as item_count
        FROM categories c
        LEFT JOIN furniture_categories fc ON c.id = fc.category_id
        GROUP BY c.id, c.name, c.slug, c.icon, c.sort_order
        ORDER BY c.sort_order ASC, c.name ASC
    ');
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    cacheSet($cacheKey, $categories, CACHE_TTL_CATEGORIES);

    return $categories;
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
    $cacheKey = 'gtaw_tag_groups_v2';
    $cached = cacheGet($cacheKey, false, $success);
    if ($success && is_array($cached)) {
        return $cached;
    }

    $stmt = $pdo->query('
        SELECT id, name, slug, color, sort_order, is_general
        FROM tag_groups
        ORDER BY sort_order ASC, name ASC
    ');
    
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    cacheSet($cacheKey, $groups, CACHE_TTL_TAG_GROUPS);

    return $groups;
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
    $cacheKey = 'gtaw_tags_flat_v2';
    $cached = cacheGet($cacheKey, false, $success);
    if ($success && is_array($cached)) {
        return $cached;
    }

    $stmt = $pdo->query('
        SELECT t.id, t.name, t.slug, t.color, t.group_id,
               tg.name as group_name, tg.slug as group_slug, tg.color as group_color,
               tg.is_general as group_is_general,
               COUNT(ft.furniture_id) as usage_count
        FROM tags t
        LEFT JOIN tag_groups tg ON t.group_id = tg.id
        LEFT JOIN furniture_tags ft ON t.id = ft.tag_id
        GROUP BY t.id
        ORDER BY tg.sort_order ASC, t.name ASC
    ');
    
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    cacheSet($cacheKey, $tags, CACHE_TTL_TAGS);

    return $tags;
}

/**
 * Get tags grouped by their tag groups
 * Returns structure: { groups: [...], ungrouped: [...] }
 * 
 * Only returns GENERAL tag groups (is_general=1).
 * For category-specific tags, use getTagsForCategories().
 */
function getTagsGrouped(PDO $pdo): array
{
    $cacheKey = 'gtaw_tags_grouped_v2';
    $cached = cacheGet($cacheKey, false, $success);
    if ($success && is_array($cached)) {
        return $cached;
    }

    // Only get general tag groups
    $stmt = $pdo->query('
        SELECT id, name, slug, color, sort_order, is_general
        FROM tag_groups
        WHERE is_general = 1
        ORDER BY sort_order ASC, name ASC
    ');
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query('
        SELECT t.id, t.name, t.slug, t.color, t.group_id,
               COUNT(ft.furniture_id) as usage_count
        FROM tags t
        INNER JOIN tag_groups tg ON t.group_id = tg.id AND tg.is_general = 1
        LEFT JOIN furniture_tags ft ON t.id = ft.tag_id
        GROUP BY t.id
        ORDER BY t.name ASC
    ');
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $groupedTags = [];
    
    foreach ($allTags as $tag) {
        if ($tag['group_id']) {
            if (!isset($groupedTags[$tag['group_id']])) {
                $groupedTags[$tag['group_id']] = [];
            }
            $groupedTags[$tag['group_id']][] = $tag;
        }
    }
    
    foreach ($groups as &$group) {
        $group['tags'] = $groupedTags[$group['id']] ?? [];
    }

    $result = [
        'groups' => $groups,
        'ungrouped' => [],
    ];

    cacheSet($cacheKey, $result, CACHE_TTL_TAGS);
    
    return $result;
}

/**
 * Get tags for specific categories
 * 
 * Returns both general tags AND category-specific tags for the given categories.
 * Structure: { general: { groups: [...] }, category_specific: { groups: [...] } }
 * 
 * @param PDO $pdo Database connection
 * @param array $categoryIds Array of category IDs to get tags for
 * @return array Combined tag structure
 */
function getTagsForCategories(PDO $pdo, array $categoryIds): array
{
    // Always include general tags
    $general = getTagsGrouped($pdo);
    
    if (empty($categoryIds)) {
        return [
            'general' => $general,
            'category_specific' => ['groups' => []],
        ];
    }
    
    // Build cache key from sorted category IDs
    $sortedIds = $categoryIds;
    sort($sortedIds);
    $cacheKey = 'gtaw_tags_cat_' . implode('_', $sortedIds);
    
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey, $success);
        if ($success && is_array($cached)) {
            return [
                'general' => $general,
                'category_specific' => $cached,
            ];
        }
    }
    
    // Get category-specific tag groups for these categories
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT tg.id, tg.name, tg.slug, tg.color, tg.sort_order
        FROM tag_groups tg
        INNER JOIN category_tag_groups ctg ON tg.id = ctg.tag_group_id
        WHERE ctg.category_id IN ({$placeholders})
          AND tg.is_general = 0
        ORDER BY tg.sort_order ASC, tg.name ASC
    ");
    $stmt->execute($categoryIds);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($groups)) {
        $categorySpecific = ['groups' => []];
    } else {
        // Get tags for these groups
        $groupIds = array_column($groups, 'id');
        $groupPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT t.id, t.name, t.slug, t.color, t.group_id,
                   COUNT(ft.furniture_id) as usage_count
            FROM tags t
            LEFT JOIN furniture_tags ft ON t.id = ft.tag_id
            WHERE t.group_id IN ({$groupPlaceholders})
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
        $stmt->execute($groupIds);
        $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $groupedTags = [];
        foreach ($allTags as $tag) {
            if (!isset($groupedTags[$tag['group_id']])) {
                $groupedTags[$tag['group_id']] = [];
            }
            $groupedTags[$tag['group_id']][] = $tag;
        }
        
        foreach ($groups as &$group) {
            $group['tags'] = $groupedTags[$group['id']] ?? [];
        }
        
        $categorySpecific = ['groups' => $groups];
    }
    
    cacheSet($cacheKey, $categorySpecific, CACHE_TTL_TAGS);
    
    return [
        'general' => $general,
        'category_specific' => $categorySpecific,
    ];
}

/**
 * Link a tag group to a category (for category-specific tags)
 */
function linkTagGroupToCategory(PDO $pdo, int $tagGroupId, int $categoryId): bool
{
    $stmt = $pdo->prepare('
        INSERT IGNORE INTO category_tag_groups (category_id, tag_group_id)
        VALUES (?, ?)
    ');
    return $stmt->execute([$categoryId, $tagGroupId]);
}

/**
 * Unlink a tag group from a category
 */
function unlinkTagGroupFromCategory(PDO $pdo, int $tagGroupId, int $categoryId): bool
{
    $stmt = $pdo->prepare('
        DELETE FROM category_tag_groups 
        WHERE category_id = ? AND tag_group_id = ?
    ');
    return $stmt->execute([$categoryId, $tagGroupId]);
}

/**
 * Get categories linked to a tag group
 */
function getCategoriesForTagGroup(PDO $pdo, int $tagGroupId): array
{
    $stmt = $pdo->prepare('
        SELECT c.id, c.name, c.slug, c.icon
        FROM categories c
        INNER JOIN category_tag_groups ctg ON c.id = ctg.category_id
        WHERE ctg.tag_group_id = ?
        ORDER BY c.sort_order ASC
    ');
    $stmt->execute([$tagGroupId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        SELECT f.id, f.name, f.price, f.image_url,
               fav.created_at as favorited_at
        FROM favorites fav
        INNER JOIN furniture f ON fav.furniture_id = f.id
        WHERE fav.user_id = ?
        ORDER BY fav.created_at DESC
    ');
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items = attachCategoriesToFurniture($pdo, $items);
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
 *
 * Optionally filters by a search term (matches username or main character).
 */
function getUsers(PDO $pdo, int $page = 1, int $perPage = 50, ?string $search = null): array
{
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];

    if ($search !== null && $search !== '') {
        $like   = '%' . $search . '%';
        $where  = 'WHERE (u.username LIKE ? OR u.main_character LIKE ?)';
        $params = [$like, $like];
    }
    
    $sql = "
        SELECT 
            u.*, 
            (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count,
            COUNT(*) OVER() as total
        FROM users u
        {$where}
        ORDER BY u.last_login DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = !empty($items) ? (int) ($items[0]['total'] ?? 0) : 0;
    
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
function createAdmin(PDO $pdo, string $username, string $password, string $role = 'admin'): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Validate role
    if (!in_array($role, ['master', 'admin'], true)) {
        $role = 'admin';
    }
    
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hash, $role]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Get dashboard statistics
 */
function getDashboardStats(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT 
            (SELECT COUNT(*) FROM furniture) as total_furniture,
            (SELECT COUNT(*) FROM categories) as total_categories,
            (SELECT COUNT(*) FROM tags) as total_tags,
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM favorites) as total_favorites
    ');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
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

/**
 * Get most popular furniture items by favorite count
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Maximum items to return
 * @return array Popular furniture items with favorite counts
 */
function getPopularFurniture(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare('
        SELECT 
            f.id, 
            f.name, 
            f.price, 
            f.image_url,
            COUNT(fav.furniture_id) as favorite_count
        FROM furniture f
        LEFT JOIN favorites fav ON f.id = fav.furniture_id
        GROUP BY f.id
        HAVING favorite_count > 0
        ORDER BY favorite_count DESC, f.name ASC
        LIMIT ?
    ');
    $stmt->execute([$limit]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return attachCategoriesToFurniture($pdo, $items);
}

/**
 * Get category popularity statistics
 * 
 * @param PDO $pdo Database connection
 * @return array Categories with item counts and favorite counts
 */
function getCategoryStats(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT 
            c.id,
            c.name,
            c.icon,
            COUNT(DISTINCT fc.furniture_id) as item_count,
            COUNT(fav.furniture_id) as favorite_count
        FROM categories c
        LEFT JOIN furniture_categories fc ON c.id = fc.category_id
        LEFT JOIN favorites fav ON fc.furniture_id = fav.furniture_id
        GROUP BY c.id
        ORDER BY favorite_count DESC
    ');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

// ============================================
// VIEW HELPERS
// ============================================

/**
 * Render pagination HTML
 * 
 * Uses data from existing createPagination() function.
 * 
 * @param array $pagination Pagination data from createPagination()
 * @param string $baseUrl Base URL without page parameter
 * @param string $pageParam Parameter name (default: 'p' to avoid collision with dashboard 'page')
 * @return string HTML
 */
function renderPaginationHtml(array $pagination, string $baseUrl, string $pageParam = 'p'): string
{
    $page = $pagination['page'];
    $totalPages = $pagination['total_pages'];
    $total = $pagination['total'];
    
    if ($totalPages <= 1) {
        if ($total > 0) {
            return '<div class="pagination"><span class="page-info">' . 
                   $total . ' item' . ($total === 1 ? '' : 's') . 
                   '</span></div>';
        }
        return '';
    }
    
    $urlParts = parse_url($baseUrl);
    $existingParams = [];
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $existingParams);
    }
    
    $basePath = $urlParts['path'] ?? '';
    
    $prevParams = $existingParams;
    $prevParams[$pageParam] = $page - 1;
    $prevUrl = $page > 1 ? $basePath . '?' . http_build_query($prevParams) : '#';
    
    $nextParams = $existingParams;
    $nextParams[$pageParam] = $page + 1;
    $nextUrl = $page < $totalPages ? $basePath . '?' . http_build_query($nextParams) : '#';
    
    $prevDisabled = $page <= 1 ? 'disabled' : '';
    $nextDisabled = $page >= $totalPages ? 'disabled' : '';
    
    return <<<HTML
    <div class="pagination">
        <a href="{$prevUrl}" class="btn btn-sm {$prevDisabled}">← Previous</a>
        <span class="page-info">Page {$page} of {$totalPages} ({$total} items)</span>
        <a href="{$nextUrl}" class="btn btn-sm {$nextDisabled}">Next →</a>
    </div>
    HTML;
}

/**
 * Render empty state
 * 
 * @param string $icon Emoji icon
 * @param string $title Main message
 * @param string $message Secondary message
 * @param string|null $actionUrl Action button URL
 * @param string|null $actionText Action button text
 * @return string HTML
 */
function renderEmptyState(
    string $icon, 
    string $title, 
    string $message, 
    ?string $actionUrl = null, 
    ?string $actionText = null
): string {
    $action = '';
    if ($actionUrl && $actionText) {
        $action = '<a href="' . e($actionUrl) . '" class="btn btn-primary">' . e($actionText) . '</a>';
    }
    
    return <<<HTML
    <div class="empty-state">
        <div class="empty-state-icon">{$icon}</div>
        <h3>{$title}</h3>
        <p>{$message}</p>
        {$action}
    </div>
    HTML;
}

/**
 * Render a furniture card HTML
 * 
 * Reusable function for server-side rendering of furniture cards.
 * Used in collection.php and other server-rendered pages.
 * 
 * NOTE: A JavaScript equivalent exists in js/app.js (renderCard() method) for
 * client-side rendering. Both functions should maintain consistent HTML structure
 * and class names. The duplication is necessary for server/client separation.
 * 
 * @param array $item Furniture item data (must include: id, name, category_name, price, image_url, tags)
 * @param bool $isFavorited Whether the item is favorited by current user
 * @param bool $showFavoriteButton Whether to show the favorite button (default: true)
 * @param int $maxVisibleTags Maximum number of tags to show before showing "+X more" (default: 3)
 * @return string HTML for the furniture card
 */
function renderFurnitureCard(array $item, bool $isFavorited = false, bool $showFavoriteButton = true, int $maxVisibleTags = 3): string
{
    $id = (int) ($item['id'] ?? 0);
    $name = e($item['name'] ?? '');
    $price = (int) ($item['price'] ?? 0);
    $imageUrl = e($item['image_url'] ?? '/images/placeholder.svg');
    $tags = $item['tags'] ?? [];
    $categories = $item['categories'] ?? [];
    
    // Build category display (primary + overflow)
    $categoryHtml = '';
    if (!empty($categories)) {
        $primaryCat = e($categories[0]['name'] ?? '');
        $categoryHtml = "<span class=\"category\">{$primaryCat}</span>";
        if (count($categories) > 1) {
            $extraCats = count($categories) - 1;
            $allCatNames = implode(', ', array_column($categories, 'name'));
            $categoryHtml .= "<span class=\"category-more\" title=\"" . e($allCatNames) . "\">+{$extraCats}</span>";
        }
    } else {
        // Fallback for backwards compatibility
        $categoryName = e($item['category_name'] ?? '');
        $categoryHtml = "<span class=\"category\">{$categoryName}</span>";
    }
    
    // Prepare tags
    $visibleTags = array_slice($tags, 0, $maxVisibleTags);
    $extraCount = count($tags) - $maxVisibleTags;
    
    $tagsHtml = '';
    foreach ($visibleTags as $tag) {
        $tagColor = e($tag['color'] ?? '#6b7280');
        $tagName = e($tag['name'] ?? '');
        $tagsHtml .= "<span class=\"tag\" style=\"--tag-color: {$tagColor}\">{$tagName}</span>";
    }
    if ($extraCount > 0) {
        $tagsHtml .= "<span class=\"tag-more\">+{$extraCount}</span>";
    }
    
    $formattedPrice = number_format($price, 0, '.', ',');
    
    $favoriteButton = '';
    if ($showFavoriteButton) {
        $favoriteClass = $isFavorited ? 'active' : '';
        $favoriteIcon = $isFavorited ? '❤️' : '🤍';
        $favoriteTitle = $isFavorited ? 'Remove from favorites' : 'Add to favorites';
        $favoriteButton = <<<HTML
        <button 
            class="btn-favorite {$favoriteClass}" 
            data-id="{$id}"
            title="{$favoriteTitle}"
            aria-label="{$favoriteTitle}"
        >
            {$favoriteIcon}
        </button>
        HTML;
    }
    
    // Data attributes use primary category for backwards compatibility
    $dataCategoryName = e($item['category_name'] ?? ($categories[0]['name'] ?? ''));
    
    return <<<HTML
    <article class="furniture-card" 
             data-id="{$id}"
             data-name="{$name}"
             data-category="{$dataCategoryName}"
             data-price="{$price}"
             data-image="{$imageUrl}"
             tabindex="0">
        <div class="card-image" data-action="lightbox">
            <img 
                src="{$imageUrl}" 
                alt="{$name}"
                loading="lazy"
                onerror="this.src='/images/placeholder.svg'"
            >
        </div>
        <div class="card-body">
            <h3 title="{$name}">{$name}</h3>
            <p class="meta">
                {$categoryHtml}
                <span class="separator">•</span>
                <span class="price">\${$formattedPrice}</span>
            </p>
            <div class="tags">{$tagsHtml}</div>
            <div class="actions">
                <button 
                    class="btn-copy" 
                    data-name="{$name}"
                    title="' . e(__('card.copy_command')) . '"
                >
                    <span class="btn-icon">📋</span>
                    <span class="btn-text">' . e(__('card.copy')) . '</span>
                </button>
                {$favoriteButton}
            </div>
        </div>
    </article>
    HTML;
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
    
    $header = str_getcsv(array_shift($lines));
    $header = array_map('strtolower', array_map('trim', $header));
    
    $nameIndex = array_search('name', $header);
    $categoryIndex = array_search('category_slug', $header);
    $categoriesIndex = array_search('category_slugs', $header); // Multi-category support
    $priceIndex = array_search('price', $header);
    $tagsIndex = array_search('tags', $header);
    $imageIndex = array_search('image_url', $header);
    
    // Require name and at least one category column
    $hasCategoryColumn = $categoryIndex !== false || $categoriesIndex !== false;
    if ($nameIndex === false || !$hasCategoryColumn) {
        return [
            'items' => [],
            'errors' => ['CSV must have "name" and "category_slug" (or "category_slugs") columns'],
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
        
        if (empty($name)) {
            $errors[] = "Row {$rowNum}: Name is required";
            continue;
        }
        
        // Parse categories (support both single and multi-category columns)
        $categorySlugs = [];
        if ($categoriesIndex !== false && !empty($row[$categoriesIndex])) {
            $categorySlugs = array_map('trim', explode('|', $row[$categoriesIndex]));
        } elseif ($categoryIndex !== false && !empty($row[$categoryIndex])) {
            $categorySlugs = [trim($row[$categoryIndex])];
        }
        
        if (empty($categorySlugs)) {
            $errors[] = "Row {$rowNum}: At least one category is required";
            continue;
        }
        
        $categoryIds = [];
        foreach ($categorySlugs as $slug) {
            $category = getCategoryBySlug($pdo, $slug);
            if ($category) {
                $categoryIds[] = $category['id'];
            } else {
                $errors[] = "Row {$rowNum}: Category '{$slug}' not found";
            }
        }
        
        if (empty($categoryIds)) {
            continue;
        }
        
        $item = [
            'name' => $name,
            'category_ids' => $categoryIds,
            'price' => $priceIndex !== false ? (int) ($row[$priceIndex] ?? 0) : 0,
            'image_url' => $imageIndex !== false ? trim($row[$imageIndex] ?? '') : null,
            'tags' => [],
        ];
        
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
 * Exports categories as pipe-separated slugs in category_slugs column.
 */
function exportFurnitureCsv(PDO $pdo): string
{
    $output = fopen('php://temp', 'r+');
    
    fputcsv($output, ['name', 'category_slugs', 'price', 'tags', 'image_url'], ',', '"', '');
    
    // First get all furniture with tags
    $stmt = $pdo->query('
        SELECT f.id, f.name, f.price, f.image_url,
               GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ",") as tags
        FROM furniture f
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        GROUP BY f.id, f.name, f.price, f.image_url
        ORDER BY f.name
    ');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for all furniture
    $catStmt = $pdo->query('
        SELECT fc.furniture_id, c.slug
        FROM furniture_categories fc
        INNER JOIN categories c ON fc.category_id = c.id
        ORDER BY fc.is_primary DESC, c.sort_order ASC
    ');
    $categoryMap = [];
    while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryMap[$cat['furniture_id']][] = $cat['slug'];
    }
    
    foreach ($items as $row) {
        $tags = $row['tags'] ?? '';
        $categorySlugs = implode('|', $categoryMap[$row['id']] ?? []);
        
        fputcsv($output, [
            $row['name'],
            $categorySlugs,
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

