<?php
/**
 * GTAW Furniture Catalog - Admin Panel
 * 
 * Main admin interface with all management views.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Require admin authentication
requireAdmin();

// Get current page
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

// Messages
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Include header
require_once dirname(__DIR__) . '/templates/admin/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php
// Route to appropriate view
switch ($page) {
    case 'dashboard':
        renderDashboard();
        break;
    
    case 'furniture':
        if ($action === 'edit' && $id > 0) {
            renderFurnitureEdit($id);
        } elseif ($action === 'add') {
            renderFurnitureAdd();
        } else {
            renderFurnitureList();
        }
        break;
    
    case 'categories':
        if ($action === 'edit' && $id > 0) {
            renderCategoryEdit($id);
        } elseif ($action === 'add') {
            renderCategoryAdd();
        } else {
            renderCategoryList();
        }
        break;
    
    case 'tag-groups':
        if ($action === 'edit' && $id > 0) {
            renderTagGroupEdit($id);
        } elseif ($action === 'add') {
            renderTagGroupAdd();
        } else {
            renderTagGroupList();
        }
        break;
    
    case 'tags':
        if ($action === 'edit' && $id > 0) {
            renderTagEdit($id);
        } elseif ($action === 'add') {
            renderTagAdd();
        } else {
            renderTagList();
        }
        break;
    
    case 'users':
        renderUserList();
        break;
    
    case 'import':
        renderImport();
        break;
    
    case 'export':
        renderExport();
        break;
    
    default:
        renderDashboard();
}
?>

<?php require_once dirname(__DIR__) . '/templates/admin/footer.php'; ?>

<?php
// =============================================
// VIEW FUNCTIONS
// =============================================

function renderDashboard(): void
{
    $stats = getDashboardStats();
    ?>
    <div class="admin-header">
        <h1>📊 Dashboard</h1>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🪑</div>
            <p class="stat-value"><?= number_format($stats['total_furniture']) ?></p>
            <p class="stat-label">Furniture Items</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📁</div>
            <p class="stat-value"><?= number_format($stats['total_categories']) ?></p>
            <p class="stat-label">Categories</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🏷️</div>
            <p class="stat-value"><?= number_format($stats['total_tags']) ?></p>
            <p class="stat-label">Tags</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <p class="stat-value"><?= number_format($stats['total_users']) ?></p>
            <p class="stat-label">Users</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">❤️</div>
            <p class="stat-value"><?= number_format($stats['total_favorites']) ?></p>
            <p class="stat-label">Total Favorites</p>
        </div>
    </div>
    
    <?php if (!empty($stats['recent_users'])): ?>
    <h2>Recent Users</h2>
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Character</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent_users'] as $user): ?>
                <tr>
                    <td><?= e($user['username']) ?></td>
                    <td><?= e($user['main_character'] ?? '-') ?></td>
                    <td><?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

function renderFurnitureList(): void
{
    $currentPage = max(1, (int) ($_GET['p'] ?? 1));
    $result = getFurnitureList($currentPage, 50);
    $items = $result['items'];
    $pagination = $result['pagination'];
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>🪑 Furniture</h1>
        <div class="actions">
            <a href="/admin/?page=furniture&action=add" class="btn btn-primary">+ Add Furniture</a>
        </div>
    </div>
    
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px">ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th style="width: 80px">Price</th>
                    <th>Tags</th>
                    <th style="width: 140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" class="empty">No furniture items found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr data-id="<?= $item['id'] ?>">
                    <td><?= $item['id'] ?></td>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td><?= e($item['category_name']) ?></td>
                    <td>$<?= number_format($item['price']) ?></td>
                    <td>
                        <?php foreach (($item['tags'] ?? []) as $tag): ?>
                            <span class="badge" style="background: <?= e($tag['color']) ?>">
                                <?= e($tag['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td class="actions">
                        <a href="/admin/?page=furniture&action=edit&id=<?= $item['id'] ?>" class="btn btn-sm">Edit</a>
                        <button class="btn btn-sm btn-danger" 
                                data-delete 
                                data-url="/admin/api.php?action=furniture/delete&id=<?= $item['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                data-confirm="Delete furniture '<?= e(addslashes($item['name'])) ?>'?">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php renderPagination($pagination, '/admin/?page=furniture'); ?>
    <?php
}

function renderFurnitureAdd(): void
{
    $categories = getCategories();
    $tagsGrouped = getTagsGrouped();
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Add Furniture</h1>
        <div class="actions">
            <a href="/admin/?page=furniture" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form two-column" method="POST" data-ajax data-action="/admin/api.php?action=furniture/create" data-redirect="/admin/?page=furniture">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <!-- Left Column: Main Fields -->
        <div class="form-column-left">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required maxlength="255" placeholder="e.g., prop_sofa_01">
                <p class="form-help">The exact prop name used in-game</p>
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= e($cat['icon']) ?> <?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" min="0" value="250">
                <p class="form-help">Default is $250 (most common price in-game)</p>
            </div>
            
            <div class="form-group">
                <label for="image_url">Image URL</label>
                <input type="text" id="image_url" name="image_url" placeholder="/images/furniture/... or https://...">
                <p class="form-help">Relative path (starting with /) or full URL</p>
                <div class="image-preview" id="image-preview">
                    <img src="/images/placeholder.svg" alt="Preview" id="preview-img">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Furniture</button>
                <a href="/admin/?page=furniture" class="btn">Cancel</a>
            </div>
        </div>
        
        <!-- Right Column: Tags by Group -->
        <div class="form-column-right">
            <label class="tags-column-label">Tags</label>
            
            <?php foreach ($tagsGrouped['groups'] as $group): ?>
            <?php if (!empty($group['tags'])): ?>
            <div class="tag-group-section">
                <h4>
                    <span class="group-color-dot" style="background: <?= e($group['color']) ?>"></span>
                    <?= e($group['name']) ?>
                </h4>
                <div class="checkbox-group">
                    <?php foreach ($group['tags'] as $tag): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>">
                        <span><?= e($tag['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (!empty($tagsGrouped['ungrouped'])): ?>
            <div class="tag-group-section">
                <h4>
                    <span class="group-color-dot" style="background: #6b7280"></span>
                    Uncategorized
                </h4>
                <div class="checkbox-group">
                    <?php foreach ($tagsGrouped['ungrouped'] as $tag): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>">
                        <span><?= e($tag['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
    <?php
}

function renderFurnitureEdit(int $id): void
{
    $item = getFurnitureById($id);
    if (!$item) {
        echo '<div class="alert alert-error">Furniture not found</div>';
        return;
    }
    
    $categories = getCategories();
    $tagsGrouped = getTagsGrouped();
    $itemTagIds = array_column($item['tags'] ?? [], 'id');
    $csrfToken = generateCsrfToken();
    
    // Determine current image URL for preview
    $currentImageUrl = $item['image_url'] ?? $item['image'] ?? '';
    ?>
    <div class="admin-header">
        <h1>✏️ Edit Furniture</h1>
        <div class="actions">
            <a href="/admin/?page=furniture" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form two-column" method="POST" data-ajax data-action="/admin/api.php?action=furniture/update&id=<?= $id ?>" data-redirect="/admin/?page=furniture">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <!-- Left Column: Main Fields -->
        <div class="form-column-left">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required maxlength="255" value="<?= e($item['name']) ?>">
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $item['category_id'] ? 'selected' : '' ?>>
                        <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" min="0" value="<?= $item['price'] ?>">
            </div>
            
            <div class="form-group">
                <label for="image_url">Image URL</label>
                <input type="text" id="image_url" name="image_url" value="<?= e($item['image_url'] ?? '') ?>" placeholder="/images/furniture/... or https://...">
                <p class="form-help">Relative path (starting with /) or full URL</p>
                <div class="image-preview" id="image-preview">
                    <img src="<?= e($currentImageUrl ?: '/images/placeholder.svg') ?>" alt="Preview" id="preview-img" onerror="this.src='/images/placeholder.svg'">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Furniture</button>
                <a href="/admin/?page=furniture" class="btn">Cancel</a>
            </div>
        </div>
        
        <!-- Right Column: Tags by Group -->
        <div class="form-column-right">
            <label class="tags-column-label">Tags</label>
            
            <?php foreach ($tagsGrouped['groups'] as $group): ?>
            <?php if (!empty($group['tags'])): ?>
            <div class="tag-group-section">
                <h4>
                    <span class="group-color-dot" style="background: <?= e($group['color']) ?>"></span>
                    <?= e($group['name']) ?>
                </h4>
                <div class="checkbox-group">
                    <?php foreach ($group['tags'] as $tag): ?>
                    <label class="checkbox-item <?= in_array($tag['id'], $itemTagIds) ? 'checked' : '' ?>">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $itemTagIds) ? 'checked' : '' ?>>
                        <span><?= e($tag['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (!empty($tagsGrouped['ungrouped'])): ?>
            <div class="tag-group-section">
                <h4>
                    <span class="group-color-dot" style="background: #6b7280"></span>
                    Uncategorized
                </h4>
                <div class="checkbox-group">
                    <?php foreach ($tagsGrouped['ungrouped'] as $tag): ?>
                    <label class="checkbox-item <?= in_array($tag['id'], $itemTagIds) ? 'checked' : '' ?>">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $itemTagIds) ? 'checked' : '' ?>>
                        <span><?= e($tag['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
    <?php
}

function renderCategoryList(): void
{
    $categories = getCategories();
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>📁 Categories</h1>
        <div class="actions">
            <a href="/admin/?page=categories&action=add" class="btn btn-primary">+ Add Category</a>
        </div>
    </div>
    
    <div class="data-table-container">
        <table class="data-table" data-sortable data-reorder-url="/admin/api.php?action=categories/reorder">
            <thead>
                <tr>
                    <th style="width: 40px">⋮⋮</th>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Items</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr data-id="<?= $cat['id'] ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td style="font-size: 1.5rem;"><?= e($cat['icon']) ?></td>
                    <td><strong><?= e($cat['name']) ?></strong></td>
                    <td><code><?= e($cat['slug']) ?></code></td>
                    <td><?= number_format($cat['item_count']) ?></td>
                    <td><?= $cat['sort_order'] ?></td>
                    <td class="actions">
                        <a href="/admin/?page=categories&action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm">Edit</a>
                        <?php if ($cat['item_count'] == 0): ?>
                        <button class="btn btn-sm btn-danger" 
                                data-delete 
                                data-url="/admin/api.php?action=categories/delete&id=<?= $cat['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                data-confirm="Delete category '<?= e(addslashes($cat['name'])) ?>'?">
                            Delete
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function renderCategoryAdd(): void
{
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Add Category</h1>
        <div class="actions">
            <a href="/admin/?page=categories" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=categories/create" data-redirect="/admin/?page=categories">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required maxlength="100" placeholder="e.g., Seating">
        </div>
        
        <div class="form-group">
            <label for="icon">Icon (Emoji)</label>
            <input type="text" id="icon" name="icon" maxlength="50" value="📁" placeholder="📁">
        </div>
        
        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="0">
            <p class="form-help">Lower numbers appear first</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Category</button>
            <a href="/admin/?page=categories" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderCategoryEdit(int $id): void
{
    $category = getCategoryById($id);
    if (!$category) {
        echo '<div class="alert alert-error">Category not found</div>';
        return;
    }
    
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>✏️ Edit Category</h1>
        <div class="actions">
            <a href="/admin/?page=categories" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=categories/update&id=<?= $id ?>" data-redirect="/admin/?page=categories">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required maxlength="100" value="<?= e($category['name']) ?>">
        </div>
        
        <div class="form-group">
            <label for="icon">Icon (Emoji)</label>
            <input type="text" id="icon" name="icon" maxlength="50" value="<?= e($category['icon']) ?>">
        </div>
        
        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="<?= $category['sort_order'] ?>">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Category</button>
            <a href="/admin/?page=categories" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

// =============================================
// TAG GROUP VIEWS
// =============================================

function renderTagGroupList(): void
{
    $groups = getTagGroups();
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>📁 Tag Groups</h1>
        <div class="actions">
            <a href="/admin/?page=tags" class="btn">🏷️ Manage Tags</a>
            <a href="/admin/?page=tag-groups&action=add" class="btn btn-primary">+ Add Group</a>
        </div>
    </div>
    
    <div class="data-table-container">
        <table class="data-table" data-sortable data-reorder-url="/admin/api.php?action=tag-groups/reorder">
            <thead>
                <tr>
                    <th style="width: 40px">⋮⋮</th>
                    <th>Name</th>
                    <th>Color</th>
                    <th>Tags Count</th>
                    <th>Sort Order</th>
                    <th style="width: 100px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $allTags = getTags();
                $tagCounts = [];
                foreach ($allTags as $tag) {
                    $gid = $tag['group_id'] ?? 0;
                    $tagCounts[$gid] = ($tagCounts[$gid] ?? 0) + 1;
                }
                foreach ($groups as $group): 
                ?>
                <tr data-id="<?= $group['id'] ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td>
                        <span class="group-color-dot" style="background: <?= e($group['color']) ?>"></span>
                        <strong><?= e($group['name']) ?></strong>
                    </td>
                    <td>
                        <span class="color-preview" style="background: <?= e($group['color']) ?>"></span>
                        <code><?= e($group['color']) ?></code>
                    </td>
                    <td><?= $tagCounts[$group['id']] ?? 0 ?></td>
                    <td><?= $group['sort_order'] ?></td>
                    <td class="actions">
                        <a href="/admin/?page=tag-groups&action=edit&id=<?= $group['id'] ?>" class="btn btn-sm">Edit</a>
                        <button class="btn btn-sm btn-danger" 
                                data-delete 
                                data-url="/admin/api.php?action=tag-groups/delete&id=<?= $group['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                data-confirm="Delete this tag group? Tags will become ungrouped.">
                            ×
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($groups)): ?>
                <tr><td colspan="6" class="empty">No tag groups found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function renderTagGroupAdd(): void
{
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Add Tag Group</h1>
        <div class="actions">
            <a href="/admin/?page=tag-groups" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=tag-groups/create" data-redirect="/admin/?page=tag-groups">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required maxlength="50" placeholder="e.g., Style / Era">
        </div>
        
        <div class="form-group">
            <label for="color">Color</label>
            <div class="color-input-wrapper">
                <input type="color" id="color" name="color" value="#6b7280">
                <input type="text" id="color_text" value="#6b7280" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">
            </div>
        </div>
        
        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="0">
            <p class="form-help">Lower numbers appear first</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Group</button>
            <a href="/admin/?page=tag-groups" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderTagGroupEdit(int $id): void
{
    $group = getTagGroupById($id);
    if (!$group) {
        echo '<div class="alert alert-error">Tag group not found</div>';
        return;
    }
    
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>✏️ Edit Tag Group</h1>
        <div class="actions">
            <a href="/admin/?page=tag-groups" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=tag-groups/update&id=<?= $id ?>" data-redirect="/admin/?page=tag-groups">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required maxlength="50" value="<?= e($group['name']) ?>">
        </div>
        
        <div class="form-group">
            <label for="color">Color</label>
            <div class="color-input-wrapper">
                <input type="color" id="color" name="color" value="<?= e($group['color']) ?>">
                <input type="text" id="color_text" value="<?= e($group['color']) ?>" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">
            </div>
        </div>
        
        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" min="0" value="<?= $group['sort_order'] ?>">
            <p class="form-help">Lower numbers appear first</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/admin/?page=tag-groups" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

// =============================================
// TAG VIEWS
// =============================================

function renderTagList(): void
{
    $tags = getTags();
    $groups = getTagGroups();
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>🏷️ Tags</h1>
        <div class="actions">
            <a href="/admin/?page=tag-groups" class="btn">📁 Manage Groups</a>
            <a href="/admin/?page=tags&action=add" class="btn btn-primary">+ Add Tag</a>
        </div>
    </div>
    
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px">ID</th>
                    <th style="width: 50px">Color</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Group</th>
                    <th style="width: 80px">Usage</th>
                    <th style="width: 140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag): ?>
                <tr data-id="<?= $tag['id'] ?>">
                    <td><?= $tag['id'] ?></td>
                    <td>
                        <span class="color-preview" style="background: <?= e($tag['color']) ?>"></span>
                    </td>
                    <td><strong><?= e($tag['name']) ?></strong></td>
                    <td><code><?= e($tag['slug']) ?></code></td>
                    <td>
                        <?php if ($tag['group_name']): ?>
                        <span class="badge" style="background: <?= e($tag['group_color'] ?? '#6b7280') ?>">
                            <?= e($tag['group_name']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($tag['usage_count'] ?? 0) ?></td>
                    <td class="actions">
                        <a href="/admin/?page=tags&action=edit&id=<?= $tag['id'] ?>" class="btn btn-sm">Edit</a>
                        <button class="btn btn-sm btn-danger" 
                                data-delete 
                                data-url="/admin/api.php?action=tags/delete&id=<?= $tag['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                data-confirm="Delete tag '<?= e(addslashes($tag['name'])) ?>'?">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function renderTagAdd(): void
{
    $groups = getTagGroups();
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Add Tag</h1>
        <div class="actions">
            <a href="/admin/?page=tags" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=tags/create" data-redirect="/admin/?page=tags">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required maxlength="50" placeholder="e.g., modern">
        </div>
        
        <div class="form-group">
            <label for="group_id">Tag Group</label>
            <select id="group_id" name="group_id">
                <option value="">No group (uncategorized)</option>
                <?php foreach ($groups as $group): ?>
                <option value="<?= $group['id'] ?>" style="color: <?= e($group['color']) ?>">
                    <?= e($group['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="form-help">Assign this tag to a group for organized filtering</p>
        </div>
        
        <div class="form-group">
            <label for="color">Color</label>
            <div class="color-input-wrapper">
                <input type="color" id="color" name="color" value="#6b7280">
                <input type="text" id="color_text" value="#6b7280" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Tag</button>
            <a href="/admin/?page=tags" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderTagEdit(int $id): void
{
    $tag = getTagById($id);
    if (!$tag) {
        echo '<div class="alert alert-error">Tag not found</div>';
        return;
    }
    
    $groups = getTagGroups();
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>✏️ Edit Tag</h1>
        <div class="actions">
            <a href="/admin/?page=tags" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=tags/update&id=<?= $id ?>" data-redirect="/admin/?page=tags">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required maxlength="50" value="<?= e($tag['name']) ?>">
        </div>
        
        <div class="form-group">
            <label for="group_id">Tag Group</label>
            <select id="group_id" name="group_id">
                <option value="">No group (uncategorized)</option>
                <?php foreach ($groups as $group): ?>
                <option value="<?= $group['id'] ?>" <?= ($tag['group_id'] ?? 0) == $group['id'] ? 'selected' : '' ?>>
                    <?= e($group['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="form-help">Assign this tag to a group for organized filtering</p>
        </div>
        
        <div class="form-group">
            <label for="color">Color</label>
            <div class="color-input-wrapper">
                <input type="color" id="color" name="color" value="<?= e($tag['color']) ?>">
                <input type="text" id="color_text" value="<?= e($tag['color']) ?>" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Tag</button>
            <a href="/admin/?page=tags" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderUserList(): void
{
    $currentPage = max(1, (int) ($_GET['p'] ?? 1));
    $result = getUsers($currentPage, 50);
    $users = $result['items'];
    $pagination = $result['pagination'];
    ?>
    <div class="admin-header">
        <h1>👥 Users</h1>
    </div>
    
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>GTAW ID</th>
                    <th>Username</th>
                    <th>Character</th>
                    <th>Role</th>
                    <th>Favorites</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 2rem;">No users found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= $user['gtaw_id'] ?></td>
                    <td><strong><?= e($user['username']) ?></strong></td>
                    <td><?= e($user['main_character'] ?? '-') ?></td>
                    <td><?= e($user['gtaw_role'] ?? '-') ?></td>
                    <td><?= number_format($user['favorites_count'] ?? 0) ?></td>
                    <td>
                        <?php if ($user['is_banned']): ?>
                            <span class="badge badge-error">Banned</span>
                        <?php else: ?>
                            <span class="badge badge-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                    <td class="actions">
                        <?php if ($user['is_banned']): ?>
                            <button class="btn btn-sm btn-success" onclick="Admin.unbanUser(<?= $user['id'] ?>)">Unban</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-danger" onclick="Admin.banUser(<?= $user['id'] ?>, '<?= e(addslashes($user['username'])) ?>')">Ban</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php renderPagination($pagination, '/admin/?page=users'); ?>
    <?php
}

function renderImport(): void
{
    ?>
    <div class="admin-header">
        <h1>📥 Import CSV</h1>
    </div>
    
    <div class="admin-form">
        <div class="alert alert-info">
            <strong>CSV Format:</strong> name, category_slug, price, tags, image_url
            <br>
            <small>First row should be headers. Tags should be comma-separated within quotes.</small>
        </div>
        
        <div class="form-group">
            <label>Upload CSV File</label>
            <div class="file-upload">
                <input type="file" id="csv-file" accept=".csv,text/csv">
                <div class="upload-icon">📄</div>
                <p>Click or drag to upload CSV file</p>
            </div>
        </div>
        
        <div class="form-group">
            <label for="csv-content">Or Paste CSV Content</label>
            <textarea id="csv-content" rows="10" placeholder="name,category_slug,price,tags,image_url
Modern Sofa,seating,1200,&quot;modern,luxury&quot;,
Oak Table,tables,450,&quot;rustic,medium&quot;,"></textarea>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-primary" onclick="Admin.importCsv()">Import</button>
        </div>
        
        <hr style="margin: 2rem 0;">
        
        <h3>Example CSV</h3>
        <pre style="background: var(--bg-elevated); padding: 1rem; border-radius: var(--radius-sm); overflow-x: auto;">name,category_slug,price,tags,image_url
"prop_sofa_modern_01",seating,1500,"modern,luxury",
"prop_table_wood_01",tables,800,"rustic,large",
"prop_lamp_desk_01",lighting,150,"modern,small",</pre>
    </div>
    <?php
}

function renderExport(): void
{
    ?>
    <div class="admin-header">
        <h1>📤 Export Data</h1>
    </div>
    
    <div class="admin-form">
        <p>Export all furniture data as a CSV file.</p>
        
        <div class="form-actions">
            <button type="button" class="btn btn-primary btn-lg" onclick="Admin.exportData()">
                📥 Download CSV
            </button>
        </div>
        
        <hr style="margin: 2rem 0;">
        
        <h3>Export Format</h3>
        <p>The exported CSV will include the following columns:</p>
        <ul>
            <li><strong>name</strong> - Furniture prop name</li>
            <li><strong>category_slug</strong> - Category identifier</li>
            <li><strong>price</strong> - Item price</li>
            <li><strong>tags</strong> - Comma-separated tag names</li>
            <li><strong>image_url</strong> - Image URL (if set)</li>
        </ul>
    </div>
    <?php
}

function renderPagination(array $pagination, string $baseUrl): void
{
    if ($pagination['total_pages'] <= 1) {
        return;
    }
    
    $page = $pagination['page'];
    $totalPages = $pagination['total_pages'];
    ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.5rem;">
        <?php if ($page > 1): ?>
            <a href="<?= $baseUrl ?>&p=<?= $page - 1 ?>" class="btn btn-sm">← Previous</a>
        <?php endif; ?>
        
        <span style="padding: 0.5rem 1rem; color: var(--text-secondary);">
            Page <?= $page ?> of <?= $totalPages ?>
        </span>
        
        <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrl ?>&p=<?= $page + 1 ?>" class="btn btn-sm">Next →</a>
        <?php endif; ?>
    </div>
    <?php
}

