<?php
/**
 * GTAW Furniture Catalog - Data Functions
 * 
 * All data retrieval and manipulation functions.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// ============================================
// FURNITURE FUNCTIONS
// ============================================

/**
 * Get furniture list with pagination and filters
 */
function getFurnitureList(
    int $page = 1,
    int $perPage = 24,
    ?string $category = null,
    array $tags = [],
    string $sort = 'name',
    string $order = 'asc'
): array {
    global $pdo;
    
    if ($pdo === null) {
        return ['items' => [], 'pagination' => createPagination(0, $page, $perPage)];
    }
    
    $perPage = min(max(1, $perPage), 100);
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
    
    if ($category) {
        $where[] = 'c.slug = ?';
        $params[] = $category;
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
    $items = attachTagsToFurniture($items);
    
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
 */
function searchFurniture(string $query, int $page = 1, int $perPage = 24): array
{
    global $pdo;
    
    if ($pdo === null || strlen($query) < 2) {
        return ['items' => [], 'pagination' => createPagination(0, $page, $perPage)];
    }
    
    $perPage = min(max(1, $perPage), 100);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    // Use LIKE for search across name, category, and tags
    $searchTerm = '%' . $query . '%';
    
    // Count total distinct furniture items matching the search
    $countSql = '
        SELECT COUNT(DISTINCT f.id) 
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE f.name LIKE ? 
           OR c.name LIKE ? 
           OR t.name LIKE ?
    ';
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $total = (int) $stmt->fetchColumn();
    
    // Get items with relevance ordering
    // Name matches are most relevant, then category, then tags
    $sql = '
        SELECT DISTINCT f.id, f.name, f.category_id, f.price, f.image_url, f.created_at,
               c.name as category_name, c.slug as category_slug,
               CASE 
                   WHEN f.name LIKE ? THEN 1
                   WHEN c.name LIKE ? THEN 2
                   ELSE 3
               END as relevance
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        LEFT JOIN furniture_tags ft ON f.id = ft.furniture_id
        LEFT JOIN tags t ON ft.tag_id = t.id
        WHERE f.name LIKE ? 
           OR c.name LIKE ? 
           OR t.name LIKE ?
        ORDER BY relevance ASC, f.name ASC
        LIMIT ? OFFSET ?
    ';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $searchTerm, $searchTerm,           // For CASE statement
        $searchTerm, $searchTerm, $searchTerm, // For WHERE clause
        $perPage, $offset
    ]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Remove relevance field from results (internal use only)
    foreach ($items as &$item) {
        unset($item['relevance']);
    }
    
    $items = attachTagsToFurniture($items);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Get single furniture item by ID
 */
function getFurnitureById(int $id): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
    $stmt = $pdo->prepare('
        SELECT f.id, f.name, f.category_id, f.price, f.image_url, f.created_at, f.updated_at,
               c.name as category_name, c.slug as category_slug
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        WHERE f.id = ?
    ');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        return null;
    }
    
    // Get tags
    $stmt = $pdo->prepare('
        SELECT t.id, t.name, t.slug, t.color
        FROM tags t
        INNER JOIN furniture_tags ft ON t.id = ft.tag_id
        WHERE ft.furniture_id = ?
    ');
    $stmt->execute([$id]);
    $item['tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get favorite count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE furniture_id = ?');
    $stmt->execute([$id]);
    $item['favorite_count'] = (int) $stmt->fetchColumn();
    
    return $item;
}

/**
 * Attach tags to furniture items
 */
function attachTagsToFurniture(array $items): array
{
    global $pdo;
    
    if ($pdo === null || empty($items)) {
        return $items;
    }
    
    $ids = array_column($items, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT ft.furniture_id, t.id, t.name, t.slug, t.color
        FROM furniture_tags ft
        INNER JOIN tags t ON ft.tag_id = t.id
        WHERE ft.furniture_id IN ({$placeholders})
    ");
    $stmt->execute($ids);
    
    $tagMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $furnitureId = $row['furniture_id'];
        unset($row['furniture_id']);
        $tagMap[$furnitureId][] = $row;
    }
    
    foreach ($items as &$item) {
        $item['tags'] = $tagMap[$item['id']] ?? [];
    }
    
    return $items;
}

/**
 * Create furniture item
 */
function createFurniture(array $data): int
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database not available');
    }
    
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
        syncFurnitureTags($furnitureId, $data['tags']);
    }
    
    return $furnitureId;
}

/**
 * Update furniture item
 */
function updateFurniture(int $id, array $data): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
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
        return false;
    }
    
    $params[] = $id;
    $sql = 'UPDATE furniture SET ' . implode(', ', $fields) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    // Update tags if provided
    if (isset($data['tags'])) {
        syncFurnitureTags($id, $data['tags']);
    }
    
    return $result;
}

/**
 * Delete furniture item
 */
function deleteFurniture(int $id): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('DELETE FROM furniture WHERE id = ?');
    return $stmt->execute([$id]);
}

/**
 * Update furniture image URL only
 */
function updateFurnitureImage(int $id, string $imageUrl): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('UPDATE furniture SET image_url = ? WHERE id = ?');
    return $stmt->execute([$imageUrl, $id]);
}

/**
 * Sync furniture tags
 */
function syncFurnitureTags(int $furnitureId, array $tagIds): void
{
    global $pdo;
    
    if ($pdo === null) {
        return;
    }
    
    // Remove existing tags
    $stmt = $pdo->prepare('DELETE FROM furniture_tags WHERE furniture_id = ?');
    $stmt->execute([$furnitureId]);
    
    // Add new tags
    if (!empty($tagIds)) {
        $stmt = $pdo->prepare('INSERT INTO furniture_tags (furniture_id, tag_id) VALUES (?, ?)');
        foreach ($tagIds as $tagId) {
            $stmt->execute([$furnitureId, $tagId]);
        }
    }
}

// ============================================
// CATEGORY FUNCTIONS
// ============================================

/**
 * Get all categories with item counts
 */
function getCategories(): array
{
    global $pdo;
    
    if ($pdo === null) {
        return [];
    }
    
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
function getCategoryById(int $id): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Get category by slug
 */
function getCategoryBySlug(string $slug): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create category
 */
function createCategory(array $data): int
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database not available');
    }
    
    $slug = createSlug($data['name']);
    
    $stmt = $pdo->prepare('
        INSERT INTO categories (name, slug, icon, sort_order)
        VALUES (?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $data['name'],
        $slug,
        $data['icon'] ?? '📁',
        $data['sort_order'] ?? 0,
    ]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Update category
 */
function updateCategory(int $id, array $data): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $fields = [];
    $params = [];
    
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
        $fields[] = 'slug = ?';
        $params[] = createSlug($data['name']);
    }
    if (isset($data['icon'])) {
        $fields[] = 'icon = ?';
        $params[] = $data['icon'];
    }
    if (isset($data['sort_order'])) {
        $fields[] = 'sort_order = ?';
        $params[] = $data['sort_order'];
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $params[] = $id;
    $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Delete category (only if no furniture items)
 */
function deleteCategory(int $id): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    // Check for furniture items
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM furniture WHERE category_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        return false;
    }
    
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    return $stmt->execute([$id]);
}

// ============================================
// TAG GROUP FUNCTIONS
// ============================================

/**
 * Get all tag groups
 */
function getTagGroups(): array
{
    global $pdo;
    
    if ($pdo === null) {
        return [];
    }
    
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
function getTagGroupById(int $id): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM tag_groups WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create tag group
 */
function createTagGroup(array $data): int
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database not available');
    }
    
    $slug = createSlug($data['name']);
    
    $stmt = $pdo->prepare('
        INSERT INTO tag_groups (name, slug, color, sort_order)
        VALUES (?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $data['name'],
        $slug,
        $data['color'] ?? '#6b7280',
        $data['sort_order'] ?? 0,
    ]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Update tag group
 */
function updateTagGroup(int $id, array $data): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $fields = [];
    $params = [];
    
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
        $fields[] = 'slug = ?';
        $params[] = createSlug($data['name']);
    }
    if (isset($data['color'])) {
        $fields[] = 'color = ?';
        $params[] = $data['color'];
    }
    if (isset($data['sort_order'])) {
        $fields[] = 'sort_order = ?';
        $params[] = $data['sort_order'];
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $params[] = $id;
    $sql = 'UPDATE tag_groups SET ' . implode(', ', $fields) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Delete tag group (tags will have group_id set to NULL)
 */
function deleteTagGroup(int $id): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('DELETE FROM tag_groups WHERE id = ?');
    return $stmt->execute([$id]);
}

// ============================================
// TAG FUNCTIONS
// ============================================

/**
 * Get all tags with group information
 */
function getTags(): array
{
    global $pdo;
    
    if ($pdo === null) {
        return [];
    }
    
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
function getTagsGrouped(): array
{
    global $pdo;
    
    if ($pdo === null) {
        return ['groups' => [], 'ungrouped' => []];
    }
    
    // Get all groups
    $groups = getTagGroups();
    
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
function getTagById(int $id): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM tags WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Get or create tag by slug
 */
function getOrCreateTag(string $name): int
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database not available');
    }
    
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
function createTag(array $data): int
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database not available');
    }
    
    $slug = createSlug($data['name']);
    
    $stmt = $pdo->prepare('INSERT INTO tags (name, slug, color, group_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $data['name'],
        $slug,
        $data['color'] ?? '#6b7280',
        $data['group_id'] ?? null,
    ]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Update tag
 */
function updateTag(int $id, array $data): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $fields = [];
    $params = [];
    
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
        $fields[] = 'slug = ?';
        $params[] = createSlug($data['name']);
    }
    if (isset($data['color'])) {
        $fields[] = 'color = ?';
        $params[] = $data['color'];
    }
    if (array_key_exists('group_id', $data)) {
        $fields[] = 'group_id = ?';
        $params[] = $data['group_id'];
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $params[] = $id;
    $sql = 'UPDATE tags SET ' . implode(', ', $fields) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Delete tag
 */
function deleteTag(int $id): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('DELETE FROM tags WHERE id = ?');
    return $stmt->execute([$id]);
}

// ============================================
// FAVORITES FUNCTIONS
// ============================================

/**
 * Get user's favorites
 */
function getUserFavorites(int $userId): array
{
    global $pdo;
    
    if ($pdo === null) {
        return [];
    }
    
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
    
    return attachTagsToFurniture($items);
}

/**
 * Get user's favorite IDs (for quick checking)
 */
function getUserFavoriteIds(int $userId): array
{
    global $pdo;
    
    if ($pdo === null) {
        return [];
    }
    
    $stmt = $pdo->prepare('SELECT furniture_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Add furniture to favorites
 */
function addFavorite(int $userId, int $furnitureId): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    // Verify furniture exists
    $stmt = $pdo->prepare('SELECT id FROM furniture WHERE id = ?');
    $stmt->execute([$furnitureId]);
    if (!$stmt->fetch()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare('INSERT INTO favorites (user_id, furniture_id) VALUES (?, ?)');
        return $stmt->execute([$userId, $furnitureId]);
    } catch (PDOException $e) {
        // Duplicate entry - already favorited
        if ($e->getCode() == 23000) {
            return false;
        }
        throw $e;
    }
}

/**
 * Remove furniture from favorites
 */
function removeFavorite(int $userId, int $furnitureId): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND furniture_id = ?');
    return $stmt->execute([$userId, $furnitureId]);
}

/**
 * Count user's favorites
 */
function countUserFavorites(int $userId): int
{
    global $pdo;
    
    if ($pdo === null) {
        return 0;
    }
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Check if furniture is favorited by user
 */
function isFavorited(int $userId, int $furnitureId): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND furniture_id = ?');
    $stmt->execute([$userId, $furnitureId]);
    return $stmt->fetch() !== false;
}

// ============================================
// USER FUNCTIONS
// ============================================

/**
 * Get all users (for admin)
 */
function getUsers(int $page = 1, int $perPage = 50): array
{
    global $pdo;
    
    if ($pdo === null) {
        return ['items' => [], 'pagination' => createPagination(0, $page, $perPage)];
    }
    
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $total = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->prepare('
        SELECT u.*, 
               (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count
        FROM users u
        ORDER BY u.last_login DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$perPage, $offset]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Get user by ID
 */
function getUserById(int $id): ?array
{
    global $pdo;
    
    if ($pdo === null) {
        return null;
    }
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Ban user
 */
function banUser(int $userId, ?string $reason = null): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?');
    return $stmt->execute([$reason, $userId]);
}

/**
 * Unban user
 */
function unbanUser(int $userId): bool
{
    global $pdo;
    
    if ($pdo === null) {
        return false;
    }
    
    $stmt = $pdo->prepare('UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?');
    return $stmt->execute([$userId]);
}

// ============================================
// ADMIN FUNCTIONS
// ============================================

/**
 * Create admin account
 */
function createAdmin(string $username, string $password): int
{
    global $pdo;
    
    if ($pdo === null) {
        throw new RuntimeException('Database not available');
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Get dashboard statistics
 */
function getDashboardStats(): array
{
    global $pdo;
    
    if ($pdo === null) {
        return [
            'total_furniture' => 0,
            'total_categories' => 0,
            'total_tags' => 0,
            'total_users' => 0,
            'total_favorites' => 0,
            'recent_users' => [],
        ];
    }
    
    $stats = [];
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM furniture');
    $stats['total_furniture'] = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM categories');
    $stats['total_categories'] = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM tags');
    $stats['total_tags'] = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $stats['total_users'] = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM favorites');
    $stats['total_favorites'] = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query('
        SELECT id, username, main_character, last_login 
        FROM users 
        ORDER BY last_login DESC 
        LIMIT 5
    ');
    $stats['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Create pagination array
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
 * Create URL-friendly slug
 */
function createSlug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Validate furniture input
 */
function validateFurnitureInput(array $input): array
{
    $errors = [];
    $data = [];
    
    // Name: required, 1-255 chars
    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    } elseif (strlen($name) > 255) {
        $errors['name'] = 'Name must be 255 characters or less';
    } else {
        $data['name'] = $name;
    }
    
    // Category: required, must exist
    $categoryId = (int) ($input['category_id'] ?? 0);
    if ($categoryId <= 0) {
        $errors['category_id'] = 'Category is required';
    } elseif (!getCategoryById($categoryId)) {
        $errors['category_id'] = 'Invalid category';
    } else {
        $data['category_id'] = $categoryId;
    }
    
    // Price: optional, non-negative integer
    $price = (int) ($input['price'] ?? 0);
    if ($price < 0) {
        $errors['price'] = 'Price cannot be negative';
    } else {
        $data['price'] = $price;
    }
    
    // Image URL: optional, allow relative paths or absolute URLs
    $imageUrl = trim($input['image_url'] ?? '');
    if ($imageUrl !== '') {
        if (strlen($imageUrl) > 500) {
            $errors['image_url'] = 'Image URL is too long';
        } elseif (str_starts_with($imageUrl, '/')) {
            // Valid relative path
            $data['image_url'] = $imageUrl;
        } elseif (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            // Valid absolute URL
            $data['image_url'] = $imageUrl;
        } else {
            $errors['image_url'] = 'Image must be a relative path (starting with /) or a valid URL';
        }
    } else {
        $data['image_url'] = null;
    }
    
    // Tags: optional array of tag IDs
    if (isset($input['tags']) && is_array($input['tags'])) {
        $data['tags'] = array_filter(array_map('intval', $input['tags']));
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $data,
    ];
}

/**
 * Parse CSV file for bulk import
 */
function parseCsvImport(string $csvContent): array
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
        
        $category = getCategoryBySlug($categorySlug);
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
                    $item['tags'][] = getOrCreateTag($tagName);
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
 */
function exportFurnitureCsv(): string
{
    global $pdo;
    
    if ($pdo === null) {
        return '';
    }
    
    $output = fopen('php://temp', 'r+');
    
    // Header
    fputcsv($output, ['name', 'category_slug', 'price', 'tags', 'image_url']);
    
    $stmt = $pdo->query('
        SELECT f.id, f.name, f.price, f.image_url, c.slug as category_slug
        FROM furniture f
        INNER JOIN categories c ON f.category_id = c.id
        ORDER BY f.name
    ');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get tags for this item
        $tagStmt = $pdo->prepare('
            SELECT t.name 
            FROM tags t 
            INNER JOIN furniture_tags ft ON t.id = ft.tag_id 
            WHERE ft.furniture_id = ?
        ');
        $tagStmt->execute([$row['id']]);
        $tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        
        fputcsv($output, [
            $row['name'],
            $row['category_slug'],
            $row['price'],
            implode(',', $tags),
            $row['image_url'] ?? '',
        ]);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

