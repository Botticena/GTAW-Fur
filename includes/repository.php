<?php
/**
 * GTAW Furniture Catalog - Repository Base Class
 * 
 * Provides common CRUD operations to reduce code duplication.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'repository.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Load utility functions
require_once __DIR__ . '/utils.php';

/**
 * Base Repository class for common CRUD operations
 */
abstract class Repository
{
    protected PDO $pdo;
    protected string $table;
    protected array $fillable;
    protected string $primaryKey = 'id';
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        
        // Validate table name contains only safe characters (SQL identifier rules)
        // Must start with letter or underscore, followed by letters, digits, or underscores
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $this->table)) {
            throw new InvalidArgumentException(
                "Invalid table name '{$this->table}'. Table names must contain only alphanumeric characters and underscores, and start with a letter or underscore."
            );
        }
        
        // Validate primary key name contains only safe characters
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $this->primaryKey)) {
            throw new InvalidArgumentException(
                "Invalid primary key name '{$this->primaryKey}'. Primary key names must contain only alphanumeric characters and underscores, and start with a letter or underscore."
            );
        }
    }
    
    /**
     * Validate SQL identifier (table/column name) for safety
     * 
     * @param string $identifier Identifier to validate
     * @return bool True if valid
     */
    protected function isValidSqlIdentifier(string $identifier): bool
    {
        // Must start with letter or underscore, followed by letters, digits, or underscores
        return (bool) preg_match('/^[a-z_][a-z0-9_]*$/i', $identifier);
    }
    
    /**
     * Create a new record
     * 
     * @param array $data Data to insert
     * @return int The ID of the newly created record
     * @throws RuntimeException If no valid data to insert or insert fails
     */
    public function create(array $data): int
    {
        // Filter data to only include fillable fields
        $filteredData = $this->filterFillable($data);
        
        // Allow subclasses to modify data before insert
        $filteredData = $this->beforeCreate($filteredData);
        
        if (empty($filteredData)) {
            throw new RuntimeException('No valid data to insert');
        }
        
        $fields = array_keys($filteredData);
        
        // Validate all field names are safe SQL identifiers
        foreach ($fields as $field) {
            if (!$this->isValidSqlIdentifier($field)) {
                throw new InvalidArgumentException("Invalid field name '{$field}'");
            }
        }
        
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fieldNames = implode(', ', $fields);
        
        $sql = "INSERT INTO {$this->table} ({$fieldNames}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($filteredData));
        
        $id = (int) $this->pdo->lastInsertId();
        
        // Allow subclasses to perform post-create operations
        $this->afterCreate($id, $data);
        
        return $id;
    }
    
    /**
     * Update a record
     * 
     * @param int $id Record ID
     * @param array $data Data to update
     * @return bool True on success
     * @throws RuntimeException If no data to update or update fails
     */
    public function update(int $id, array $data): bool
    {
        // Filter data to only include fillable fields
        $filteredData = $this->filterFillable($data);
        
        // Allow subclasses to modify data before update
        $filteredData = $this->beforeUpdate($id, $filteredData);
        
        if (empty($filteredData)) {
            throw new RuntimeException('No data provided to update');
        }
        
        $fields = [];
        $params = [];
        
        foreach ($filteredData as $field => $value) {
            // Validate field name is a safe SQL identifier
            if (!$this->isValidSqlIdentifier($field)) {
                throw new InvalidArgumentException("Invalid field name '{$field}'");
            }
            
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new RuntimeException("Failed to update {$this->table} record");
        }
        
        // Allow subclasses to perform post-update operations
        $this->afterUpdate($id, $data);
        
        return true;
    }
    
    /**
     * Delete a record
     * 
     * @param int $id Record ID
     * @return bool True on success
     * @throws RuntimeException If deletion prevented or deletion fails
     */
    public function delete(int $id): bool
    {
        // Allow subclasses to perform pre-delete checks
        if (!$this->beforeDelete($id)) {
            throw new RuntimeException("Cannot delete {$this->table} record (pre-delete check failed)");
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $result = $stmt->execute([$id]);
        
        if (!$result) {
            throw new RuntimeException("Failed to delete {$this->table} record");
        }
        
        // Allow subclasses to perform post-delete operations
        $this->afterDelete($id);
        
        return true;
    }
    
    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Hook called before create - subclasses can override
     */
    protected function beforeCreate(array $data): array
    {
        return $data;
    }
    
    /**
     * Hook called after create - subclasses can override
     */
    protected function afterCreate(int $id, array $originalData): void
    {
        // Override in subclasses if needed
    }
    
    /**
     * Hook called before update - subclasses can override
     */
    protected function beforeUpdate(int $id, array $data): array
    {
        return $data;
    }
    
    /**
     * Hook called after update - subclasses can override
     */
    protected function afterUpdate(int $id, array $originalData): void
    {
        // Override in subclasses if needed
    }
    
    /**
     * Hook called before delete - subclasses can override
     * Return false to prevent deletion
     */
    protected function beforeDelete(int $id): bool
    {
        return true;
    }
    
    /**
     * Hook called after delete - subclasses can override
     */
    protected function afterDelete(int $id): void
    {
        // Override in subclasses if needed
    }
}

/**
 * Category Repository
 */
class CategoryRepository extends Repository
{
    protected string $table = 'categories';
    protected array $fillable = ['name', 'slug', 'icon', 'sort_order'];
    
    protected function beforeCreate(array $data): array
    {
        // Generate slug if not provided
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        // Set defaults
        if (!isset($data['icon'])) {
            $data['icon'] = '📁';
        }
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }
        
        return $data;
    }
    
    protected function beforeUpdate(int $id, array $data): array
    {
        // Update slug if name changed
        if (isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        return $data;
    }
    
    protected function beforeDelete(int $id): bool
    {
        // Check if category has furniture items
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM furniture WHERE category_id = ?');
        $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();
        
        return $count === 0;
    }
}

/**
 * Tag Repository
 */
class TagRepository extends Repository
{
    protected string $table = 'tags';
    protected array $fillable = ['name', 'slug', 'color', 'group_id'];
    
    protected function beforeCreate(array $data): array
    {
        // Generate slug if not provided
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        // Set defaults
        if (!isset($data['color'])) {
            $data['color'] = '#6b7280';
        }
        
        return $data;
    }
    
    protected function beforeUpdate(int $id, array $data): array
    {
        // Update slug if name changed
        if (isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        return $data;
    }
}

/**
 * Tag Group Repository
 */
class TagGroupRepository extends Repository
{
    protected string $table = 'tag_groups';
    protected array $fillable = ['name', 'slug', 'color', 'sort_order'];
    
    protected function beforeCreate(array $data): array
    {
        // Generate slug if not provided
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        // Set defaults
        if (!isset($data['color'])) {
            $data['color'] = '#6b7280';
        }
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }
        
        return $data;
    }
    
    protected function beforeUpdate(int $id, array $data): array
    {
        // Update slug if name changed
        if (isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        return $data;
    }
    
    protected function afterDelete(int $id): void
    {
        // Set tags' group_id to NULL when tag group is deleted
        $stmt = $this->pdo->prepare('UPDATE tags SET group_id = NULL WHERE group_id = ?');
        $stmt->execute([$id]);
    }
}

/**
 * Collection Repository
 * Note: Collections have special logic (user_id, slug uniqueness per user)
 */
class CollectionRepository extends Repository
{
    protected string $table = 'collections';
    protected array $fillable = ['user_id', 'name', 'slug', 'description', 'is_public'];
    protected int $userId;
    
    public function __construct(PDO $pdo, int $userId)
    {
        parent::__construct($pdo);
        $this->userId = $userId;
    }
    
    protected function beforeCreate(array $data): array
    {
        // Always set user_id
        $data['user_id'] = $this->userId;
        
        // Generate slug if not provided
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = createSlug($data['name']);
        }
        
        // Ensure unique slug for this user
        if (isset($data['slug'])) {
            $baseSlug = $data['slug'];
            $counter = 1;
            // Check for existing collection with same slug for this user
            $stmt = $this->pdo->prepare('SELECT id FROM collections WHERE user_id = ? AND slug = ?');
            while (true) {
                $stmt->execute([$this->userId, $data['slug']]);
                $existing = $stmt->fetch();
                $stmt->closeCursor(); // Reset cursor for next iteration
                if ($existing === false) {
                    break; // Slug is unique
                }
                $data['slug'] = $baseSlug . '-' . $counter;
                $counter++;
            }
        }
        
        // Set defaults
        if (!isset($data['is_public'])) {
            $data['is_public'] = 1;
        }
        
        return $data;
    }
    
    protected function beforeUpdate(int $id, array $data): array
    {
        // Update slug if name changed
        if (isset($data['name'])) {
            $newSlug = createSlug($data['name']);
            // Check if slug is unique for this user
            $stmt = $this->pdo->prepare('SELECT id FROM collections WHERE user_id = ? AND slug = ?');
            $stmt->execute([$this->userId, $newSlug]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing || (int) $existing['id'] === $id) {
                $data['slug'] = $newSlug;
            }
        }
        
        return $data;
    }
}

