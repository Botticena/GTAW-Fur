<?php
/**
 * GTAW Furniture Catalog - Admin Panel
 * 
 * Main admin interface with all management views.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/submissions.php';

// Require admin authentication
requireAdmin();

try {
    $pdo = getDb();
} catch (RuntimeException $e) {
    throw new RuntimeException('Database connection not available');
}
$page = getQuery('page', 'dashboard');
$action = getQuery('action', 'list');
$id = getQueryInt('id', 0);

// Messages
$success = getQuery('success', null);
$error = getQuery('error', null);

// Include header
require_once __DIR__ . '/../templates/admin/header.php';
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
        renderDashboard($pdo);
        break;
    
    case 'furniture':
        if ($action === 'edit' && $id > 0) {
            renderFurnitureEdit($pdo, $id);
        } elseif ($action === 'add') {
            renderFurnitureAdd($pdo);
        } else {
            renderFurnitureList($pdo);
        }
        break;
    
    case 'categories':
        if ($action === 'edit' && $id > 0) {
            renderCategoryEdit($pdo, $id);
        } elseif ($action === 'add') {
            renderCategoryAdd();
        } else {
            renderCategoryList($pdo);
        }
        break;
    
    case 'tag-groups':
        if ($action === 'edit' && $id > 0) {
            renderTagGroupEdit($pdo, $id);
        } elseif ($action === 'add') {
            renderTagGroupAdd($pdo);
        } else {
            renderTagGroupList($pdo);
        }
        break;
    
    case 'tags':
        if ($action === 'edit' && $id > 0) {
            renderTagEdit($pdo, $id);
        } elseif ($action === 'add') {
            renderTagAdd($pdo);
        } else {
            renderTagList($pdo);
        }
        break;
    
    case 'users':
        renderUserList($pdo);
        break;
    
    case 'submissions':
        if ($action === 'view' && $id > 0) {
            renderSubmissionDetail($pdo, $id);
        } else {
            renderSubmissionList($pdo);
        }
        break;
    
    case 'settings':
        requireMasterAdmin();
        renderSettings($pdo);
        break;
    
    case 'synonyms':
        requireMasterAdmin();
        if ($action === 'edit' && $id > 0) {
            renderSynonymEdit($pdo, $id);
        } elseif ($action === 'add') {
            renderSynonymAdd();
        } elseif ($action === 'analytics') {
            renderSearchAnalytics($pdo);
        } elseif ($action === 'discover') {
            renderSynonymAutoDiscovery($pdo);
        } else {
            renderSynonymList($pdo);
        }
        break;
    
    case 'import':
        requireMasterAdmin();
        renderImport();
        break;
    
    case 'export':
        requireMasterAdmin();
        renderExport();
        break;
    
    default:
        renderDashboard($pdo);
}
?>

<?php require_once __DIR__ . '/../templates/admin/footer.php'; ?>

<?php
// =============================================
// VIEW FUNCTIONS
// =============================================

function renderDashboard(PDO $pdo): void
{
    $stats = getDashboardStats($pdo);
    $pendingSubmissions = getSubmissions($pdo, 1, 5, SUBMISSION_STATUS_PENDING);
    $pendingCount = getPendingSubmissionsCount($pdo);
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
        
        <div class="stat-card" style="<?= $pendingCount > 0 ? 'border-color: var(--warning);' : '' ?>">
            <div class="stat-icon">📝</div>
            <p class="stat-value"><?= number_format($pendingCount) ?></p>
            <p class="stat-label">Pending Submissions</p>
        </div>
    </div>
    
    <?php if ($pendingCount > 0): ?>
    <h2 style="display: flex; align-items: center; gap: var(--spacing-sm);">
        Pending Submissions
        <span class="badge badge-warning"><?= $pendingCount ?></span>
    </h2>
    <div class="data-table-container" style="margin-bottom: var(--spacing-xl);">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Type</th>
                    <th>Submitted By</th>
                    <th>Name</th>
                    <th style="width: 140px;">Date</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingSubmissions['items'] as $sub): ?>
                <tr>
                    <td><span class="badge"><?= $sub['type'] === SUBMISSION_TYPE_NEW ? '✨ New' : '✏️ Edit' ?></span></td>
                    <td><strong><?= e($sub['submitter_username']) ?></strong></td>
                    <td><?= e($sub['data']['name'] ?? 'Untitled') ?></td>
                    <td><?= date('M j, Y', strtotime($sub['created_at'])) ?></td>
                    <td class="actions">
                        <a href="/admin/?page=submissions&action=view&id=<?= $sub['id'] ?>" class="btn btn-sm btn-primary">Review</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-bottom: var(--spacing-xl);">
        <a href="/admin/?page=submissions" class="btn">View All Submissions →</a>
    </p>
    <?php endif; ?>
    
    <!-- Analytics Section -->
    <div class="dashboard-analytics-grid">
        <!-- Popular Items -->
        <?php 
        $popularItems = getPopularFurniture($pdo, 8);
        if (!empty($popularItems)): 
        ?>
        <div class="analytics-card">
            <h3>🔥 Most Popular Items</h3>
            <div class="popular-items-list">
                <?php foreach ($popularItems as $item): ?>
                <?php 
                $cats = $item['categories'] ?? [];
                $catDisplay = !empty($cats) ? $cats[0]['name'] : ($item['category_name'] ?? '');
                if (count($cats) > 1) $catDisplay .= ' +' . (count($cats) - 1);
                ?>
                <a href="/?furniture=<?= $item['id'] ?>" class="popular-item" target="_blank">
                    <img src="<?= e($item['image_url'] ?? '/images/placeholder.svg') ?>" 
                         alt="" onerror="this.src='/images/placeholder.svg'">
                    <div class="popular-item-info">
                        <span class="popular-item-name"><?= e($item['name']) ?></span>
                        <span class="popular-item-meta"><?= e($catDisplay) ?></span>
                    </div>
                    <span class="popular-item-count">❤️ <?= number_format($item['favorite_count']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Category Stats -->
        <?php 
        $categoryStats = getCategoryStats($pdo);
        if (!empty($categoryStats)): 
        ?>
        <div class="analytics-card">
            <h3>📊 Category Performance</h3>
            <div class="category-stats-list">
                <?php foreach (array_slice($categoryStats, 0, 8) as $cat): ?>
                <div class="category-stat">
                    <span class="category-stat-name">
                        <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
                    </span>
                    <span class="category-stat-counts">
                        <span title="Items"><?= number_format($cat['item_count']) ?> items</span>
                        <span title="Favorites" class="category-stat-favorites">❤️ <?= number_format($cat['favorite_count']) ?></span>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
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

function renderFurnitureList(PDO $pdo): void
{
    $currentPage    = max(1, getQueryInt('p', 1));
    $categoryFilter = getQuery('category', null);
    $search         = trim((string) getQuery('q', ''));
    $categories     = getCategories($pdo);

    if ($search !== '') {
        // Admin search across all furniture using server-side search
        $perPage = getSetting('app.items_per_page', 50);
        $result = searchFurniture($pdo, $search, $currentPage, $perPage, null, true);
    } else {
        $perPage = getSetting('app.items_per_page', 50);
        $result = getFurnitureList($pdo, $currentPage, $perPage, $categoryFilter);
    }

    $items      = $result['items'];
    $pagination = $result['pagination'];
    $csrfToken  = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>🪑 Furniture</h1>
        <div class="actions">
            <a href="/admin/?page=furniture&action=add" class="btn btn-primary">+ Add Furniture</a>
        </div>
    </div>
    
    <!-- Filter Bar: Search + Category -->
    <form class="table-filter-bar" method="get">
        <input type="hidden" name="page" value="furniture">
        <input
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="🔍 Search furniture..."
            aria-label="Search furniture">
        <div class="filter-buttons">
            <select name="category" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat['slug']) ?>" <?= $categoryFilter === $cat['slug'] ? 'selected' : '' ?>>
                    <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($categoryFilter || $search !== ''): ?>
            <a href="/admin/?page=furniture" class="btn btn-sm">✕ Clear</a>
            <?php endif; ?>
        </div>
    </form>
    
    <?php if (empty($items)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '🪑',
            'No furniture items',
            'Add furniture items to the catalog.',
            '/admin/?page=furniture&action=add',
            'Add First Furniture'
        ) ?>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table id="furniture-table" class="data-table">
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
                <?php foreach ($items as $item): ?>
                <?php 
                $categories = $item['categories'] ?? [];
                $categoryCount = count($categories);
                ?>
                <tr data-id="<?= $item['id'] ?>">
                    <td><?= $item['id'] ?></td>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td>
                        <?php if ($categoryCount > 0): ?>
                            <?= e($categories[0]['name']) ?>
                            <?php if ($categoryCount > 1): ?>
                                <span class="category-overflow" title="<?= e(implode(', ', array_column($categories, 'name'))) ?>">+<?= $categoryCount - 1 ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= e($item['category_name'] ?? '-') ?>
                        <?php endif; ?>
                    </td>
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
            </tbody>
        </table>
    </div>
    
    <?php 
    $paginationUrl = '/admin/?page=furniture';
    if ($categoryFilter) {
        $paginationUrl .= '&category=' . urlencode($categoryFilter);
    }
    if ($search !== '') {
        $paginationUrl .= '&q=' . urlencode($search);
    }
    echo renderPaginationHtml($pagination, $paginationUrl, 'p'); 
    ?>
    <?php endif; ?>
    <?php
}

function renderFurnitureAdd(PDO $pdo): void
{
    $categories = getCategories($pdo);
    $tagsGrouped = getTagsGrouped($pdo);
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Add Furniture</h1>
        <div class="actions">
            <a href="/admin/?page=furniture" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form form-split" method="POST" data-ajax data-action="/admin/api.php?action=furniture/create" data-redirect="/admin/?page=furniture">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-layout">
            <!-- Left Column: Form Container -->
            <div class="form-layout-main">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required maxlength="255" placeholder="e.g., Black Double Bed">
                    <p class="form-help">The exact prop name used in-game</p>
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
                
                <!-- Duplicate Detection Panel (in left column, below form) -->
                <aside id="duplicate-panel" class="duplicate-panel hidden">
                    <!-- Populated by JavaScript -->
                </aside>
            </div>
            
            <!-- Right Column: Sidebar with separate panels -->
            <div class="form-layout-sidebar">
                <!-- Categories Panel (styled like tags) -->
                <section class="tags-panel categories-panel">
                    <h3 class="tags-panel-header">Categories * <small style="font-weight: normal; opacity: 0.7;">(first selected = primary)</small></h3>
                    <div class="tag-group-section">
                        <div class="checkbox-group">
                            <?php foreach ($categories as $cat): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>">
                                <span><?= e($cat['icon']) ?> <?= e($cat['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                
                <!-- Tags Panel -->
                <section class="tags-panel" id="tags-container">
                    <h3 class="tags-panel-header">Tags</h3>
                    
                    <!-- General Tags -->
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
                    
                    <!-- Category-Specific Tags (loaded dynamically) -->
                    <div id="category-specific-tags"></div>
                </section>
            </div>
        </div>
    </form>
    <?php
}

function renderFurnitureEdit(PDO $pdo, int $id): void
{
    $item = getFurnitureById($pdo, $id);
    if (!$item) {
        echo '<div class="alert alert-error">Furniture not found</div>';
        return;
    }
    
    $categories = getCategories($pdo);
    $tagsGrouped = getTagsGrouped($pdo);
    $itemTagIds = array_column($item['tags'] ?? [], 'id');
    $itemCategoryIds = array_column($item['categories'] ?? [], 'id');
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
    
    <form class="admin-form form-split" method="POST" data-ajax data-action="/admin/api.php?action=furniture/update&id=<?= $id ?>" data-redirect="/admin/?page=furniture">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-layout">
            <!-- Left Column: Form Container -->
            <div class="form-layout-main">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required maxlength="255" value="<?= e($item['name']) ?>">
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
                
                <!-- Duplicate Detection Panel (in left column, below form) -->
                <aside id="duplicate-panel" 
                       class="duplicate-panel hidden"
                       data-exclude-id="<?= $id ?>">
                    <!-- Populated by JavaScript -->
                </aside>
            </div>
            
            <!-- Right Column: Sidebar with separate panels -->
            <div class="form-layout-sidebar">
                <!-- Categories Panel (styled like tags) -->
                <section class="tags-panel categories-panel">
                    <h3 class="tags-panel-header">Categories * <small style="font-weight: normal; opacity: 0.7;">(first selected = primary)</small></h3>
                    <div class="tag-group-section">
                        <div class="checkbox-group">
                            <?php foreach ($categories as $cat): ?>
                            <?php $isChecked = in_array($cat['id'], $itemCategoryIds); ?>
                            <label class="checkbox-item <?= $isChecked ? 'checked' : '' ?>">
                                <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                <span><?= e($cat['icon']) ?> <?= e($cat['name']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                
                <!-- Tags Panel -->
                <section class="tags-panel" id="tags-container">
                    <h3 class="tags-panel-header">Tags</h3>
                    
                    <!-- General Tags -->
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
                    
                    <!-- Category-Specific Tags (loaded dynamically) -->
                    <div id="category-specific-tags"></div>
                </section>
            </div>
        </div>
    </form>
    <?php
}

function renderCategoryList(PDO $pdo): void
{
    $categories = getCategories($pdo);
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>📁 Categories</h1>
        <div class="actions">
            <a href="/admin/?page=categories&action=add" class="btn btn-primary">+ Add Category</a>
        </div>
    </div>
    
    <?php if (empty($categories)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '📁',
            'No categories yet',
            'Create categories to organize furniture items.',
            '/admin/?page=categories&action=add',
            'Add First Category'
        ) ?>
    </div>
    <?php else: ?>
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
    <?php endif; ?>
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

function renderCategoryEdit(PDO $pdo, int $id): void
{
    $category = getCategoryById($pdo, $id);
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

function renderTagGroupList(PDO $pdo): void
{
    $groups = getTagGroups($pdo);
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>📁 Tag Groups</h1>
        <div class="actions">
            <a href="/admin/?page=tags" class="btn">🏷️ Manage Tags</a>
            <a href="/admin/?page=tag-groups&action=add" class="btn btn-primary">+ Add Group</a>
        </div>
    </div>
    
    <?php if (empty($groups)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '📁',
            'No tag groups yet',
            'Create groups to organize your tags by category (Style, Size, Material, etc.).',
            '/admin/?page=tag-groups&action=add',
            'Add First Group'
        ) ?>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table" data-sortable data-reorder-url="/admin/api.php?action=tag-groups/reorder">
            <thead>
                <tr>
                    <th style="width: 40px">⋮⋮</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Color</th>
                    <th>Tags</th>
                    <th style="width: 140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $allTags = getTags($pdo);
                $tagCounts = [];
                foreach ($allTags as $tag) {
                    $gid = $tag['group_id'] ?? 0;
                    $tagCounts[$gid] = ($tagCounts[$gid] ?? 0) + 1;
                }
                foreach ($groups as $group): 
                $isGeneral = !empty($group['is_general']);
                ?>
                <tr data-id="<?= $group['id'] ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td>
                        <span class="group-color-dot" style="background: <?= e($group['color']) ?>"></span>
                        <strong><?= e($group['name']) ?></strong>
                    </td>
                    <td>
                        <?php if ($isGeneral): ?>
                        <span class="badge badge-success">🌐 General</span>
                        <?php else: ?>
                        <span class="badge">🏷️ Category</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="color-preview" style="background: <?= e($group['color']) ?>"></span>
                        <code><?= e($group['color']) ?></code>
                    </td>
                    <td><?= $tagCounts[$group['id']] ?? 0 ?></td>
                    <td class="actions">
                        <a href="/admin/?page=tag-groups&action=edit&id=<?= $group['id'] ?>" class="btn btn-sm">Edit</a>
                        <button class="btn btn-sm btn-danger" 
                                data-delete 
                                data-url="/admin/api.php?action=tag-groups/delete&id=<?= $group['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                data-confirm="Delete this tag group? Tags will become ungrouped.">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

function renderTagGroupAdd(PDO $pdo): void
{
    $categories = getCategories($pdo);
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
        
        <div class="form-group">
            <label>Tag Group Type</label>
            <div class="radio-group">
                <label class="radio-item">
                    <input type="radio" name="is_general" value="1" checked onchange="document.getElementById('category-selector').style.display='none'">
                    <span>🌐 General</span>
                    <small>Appears for all furniture regardless of category</small>
                </label>
                <label class="radio-item">
                    <input type="radio" name="is_general" value="0" onchange="document.getElementById('category-selector').style.display='block'">
                    <span>🏷️ Category-Specific</span>
                    <small>Only appears when specific categories are selected</small>
                </label>
            </div>
        </div>
        
        <div class="form-group" id="category-selector" style="display: none;">
            <label>Link to Categories</label>
            <p class="form-help">Select which categories this tag group should appear for</p>
            <div class="checkbox-grid">
                <?php foreach ($categories as $cat): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>">
                    <span><?= e($cat['icon']) ?> <?= e($cat['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Group</button>
            <a href="/admin/?page=tag-groups" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderTagGroupEdit(PDO $pdo, int $id): void
{
    $group = getTagGroupById($pdo, $id);
    if (!$group) {
        echo '<div class="alert alert-error">Tag group not found</div>';
        return;
    }
    
    $categories = getCategories($pdo);
    $linkedCategories = getCategoriesForTagGroup($pdo, $id);
    $linkedCategoryIds = array_column($linkedCategories, 'id');
    $isGeneral = !empty($group['is_general']);
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
        
        <div class="form-group">
            <label>Tag Group Type</label>
            <div class="alert alert-info" style="margin-bottom: var(--spacing-sm);">
                <?php if ($isGeneral): ?>
                <span class="badge badge-success">🌐 General Tag Group</span>
                <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.875rem;">This tag group appears for all furniture items.</p>
                <?php else: ?>
                <span class="badge">🏷️ Category-Specific Tag Group</span>
                <p style="margin: var(--spacing-xs) 0 0 0; font-size: 0.875rem;">This tag group only appears for selected categories.</p>
                <?php endif; ?>
            </div>
            <p class="form-help">Type cannot be changed after creation. Create a new tag group if needed.</p>
        </div>
        
        <?php if (!$isGeneral): ?>
        <div class="form-group">
            <label>Linked Categories</label>
            <p class="form-help">This tag group appears when these categories are selected</p>
            <div class="checkbox-grid">
                <?php foreach ($categories as $cat): ?>
                <?php $isLinked = in_array($cat['id'], $linkedCategoryIds); ?>
                <label class="checkbox-item <?= $isLinked ? 'checked' : '' ?>">
                    <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>" <?= $isLinked ? 'checked' : '' ?> onchange="toggleCategoryLink(<?= $id ?>, <?= $cat['id'] ?>, this.checked)">
                    <span><?= e($cat['icon']) ?> <?= e($cat['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
        async function toggleCategoryLink(tagGroupId, categoryId, isLinked) {
            const action = isLinked ? 'tag-groups/link-category' : 'tag-groups/unlink-category';
            try {
                const response = await fetch('/admin/api.php?action=' + action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        tag_group_id: tagGroupId,
                        category_id: categoryId
                    })
                });
                const result = await response.json();
                if (result.success) {
                    window.GTAW?.toast(result.message || 'Updated', 'success');
                } else {
                    window.GTAW?.toast(result.error || 'Failed to update', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                window.GTAW?.toast('Network error', 'error');
            }
        }
        </script>
        <?php endif; ?>
        
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

function renderTagList(PDO $pdo): void
{
    $tags = getTags($pdo);
    $groups = getTagGroups($pdo);
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>🏷️ Tags</h1>
        <div class="actions">
            <a href="/admin/?page=tag-groups" class="btn">📁 Manage Groups</a>
            <a href="/admin/?page=tags&action=add" class="btn btn-primary">+ Add Tag</a>
        </div>
    </div>
    
    <?php if (empty($tags)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '🏷️',
            'No tags yet',
            'Create tags to help categorize and filter furniture items.',
            '/admin/?page=tags&action=add',
            'Add First Tag'
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Search Filter -->
    <?php if (count($tags) > 10): ?>
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="tags-table"
               placeholder="🔍 Search tags..."
               aria-label="Search tags">
    </div>
    <?php endif; ?>
    
    <div class="data-table-container">
        <table id="tags-table" class="data-table">
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
    <?php endif; ?>
    <?php
}

function renderTagAdd(PDO $pdo): void
{
    $groups = getTagGroups($pdo);
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

function renderTagEdit(PDO $pdo, int $id): void
{
    $tag = getTagById($pdo, $id);
    if (!$tag) {
        echo '<div class="alert alert-error">Tag not found</div>';
        return;
    }
    
    $groups = getTagGroups($pdo);
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

function renderUserList(PDO $pdo): void
{
    $currentPage = max(1, getQueryInt('p', 1));
    $perPage = getSetting('app.items_per_page', 50);
    $result = getUsers($pdo, $currentPage, $perPage);
    $users = $result['items'];
    $pagination = $result['pagination'];
    ?>
    <div class="admin-header">
        <h1>👥 Users</h1>
    </div>
    
    <?php if (empty($users)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '👥',
            'No users yet',
            'Users will appear here after they log in with GTA World OAuth.',
            null,
            null
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Search Filter -->
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="users-table"
               placeholder="🔍 Search users..."
               aria-label="Search users">
    </div>
    
    <div class="data-table-container">
        <table id="users-table" class="data-table">
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
            </tbody>
        </table>
    </div>
    
    <?= renderPaginationHtml($pagination, '/admin/?page=users', 'p') ?>
    <?php endif; ?>
    <?php
}

// =============================================
// SUBMISSION VIEWS
// =============================================

function renderSubmissionList(PDO $pdo): void
{
    $status = getQuery('status', null);
    $currentPage = max(1, getQueryInt('p', 1));
    
    $result = getSubmissions($pdo, $currentPage, 20, $status);
    $submissions = $result['items'];
    $pagination = $result['pagination'];
    $pendingCount = getPendingSubmissionsCount($pdo);
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>📝 User Submissions</h1>
        <div class="actions">
            <?php if ($pendingCount > 0): ?>
            <span class="badge badge-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">
                <?= $pendingCount ?> pending
            </span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filter Bar: Search + Status -->
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="submissions-table"
               placeholder="🔍 Search submissions..."
               aria-label="Search submissions">
        <div class="filter-buttons">
            <a href="/admin/?page=submissions" class="btn btn-sm <?= !$status ? 'btn-primary' : '' ?>">All</a>
            <a href="/admin/?page=submissions&status=<?= SUBMISSION_STATUS_PENDING ?>" class="btn btn-sm <?= $status === SUBMISSION_STATUS_PENDING ? 'btn-primary' : '' ?>">
                ⏳ Pending
            </a>
            <a href="/admin/?page=submissions&status=<?= SUBMISSION_STATUS_APPROVED ?>" class="btn btn-sm <?= $status === SUBMISSION_STATUS_APPROVED ? 'btn-primary' : '' ?>">
                ✓ Approved
            </a>
            <a href="/admin/?page=submissions&status=<?= SUBMISSION_STATUS_REJECTED ?>" class="btn btn-sm <?= $status === SUBMISSION_STATUS_REJECTED ? 'btn-primary' : '' ?>">
                ✕ Rejected
            </a>
        </div>
    </div>
    
    <?php if (empty($submissions)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '📝',
            'No submissions found',
            $status ? 'No submissions match the selected filter.' : 'User submissions will appear here.',
            null,
            null
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Bulk Actions Bar (hidden by default, shown when items selected) -->
    <div id="bulk-actions-bar" class="bulk-actions-bar" style="display: none;">
        <span id="bulk-selection-count">0 selected</span>
        <div class="bulk-actions-buttons">
            <button class="btn btn-sm btn-success" onclick="Admin.bulkApprove()">✓ Approve Selected</button>
            <button class="btn btn-sm btn-danger" onclick="Admin.bulkReject()">✕ Reject Selected</button>
            <button class="btn btn-sm" onclick="Admin.bulkClearSelection()">Clear Selection</button>
        </div>
    </div>
    
    <div class="data-table-container">
        <table id="submissions-table" class="data-table">
            <thead>
                <tr>
                    <th style="width: 40px">
                        <input type="checkbox" id="bulk-select-all" 
                               onchange="Admin.bulkToggleAll(this.checked)"
                               title="Select all pending">
                    </th>
                    <th style="width: 60px">ID</th>
                    <th style="width: 80px">Type</th>
                    <th>Submitted By</th>
                    <th>Name</th>
                    <th style="width: 100px">Status</th>
                    <th style="width: 140px">Date</th>
                    <th style="width: 180px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub): ?>
                <tr data-id="<?= $sub['id'] ?>">
                    <td>
                        <?php if ($sub['status'] === SUBMISSION_STATUS_PENDING): ?>
                        <input type="checkbox" class="bulk-select-item" 
                               value="<?= $sub['id'] ?>"
                               onchange="Admin.bulkUpdateSelection()">
                        <?php endif; ?>
                    </td>
                    <td><?= $sub['id'] ?></td>
                    <td>
                        <span class="badge"><?= $sub['type'] === SUBMISSION_TYPE_NEW ? '✨ New' : '✏️ Edit' ?></span>
                    </td>
                    <td>
                        <strong><?= e($sub['submitter_username']) ?></strong>
                    </td>
                    <td>
                        <?= e($sub['data']['name'] ?? 'Untitled') ?>
                        <?php if ($sub['type'] === SUBMISSION_TYPE_EDIT && !empty($sub['data']['edit_notes'])): ?>
                        <br><small class="text-muted" title="<?= e($sub['data']['edit_notes']) ?>">📝 <?= e(mb_strimwidth($sub['data']['edit_notes'], 0, 50, '...')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= renderAdminStatusBadge($sub['status']) ?>
                    </td>
                    <td><?= date('M j, Y H:i', strtotime($sub['created_at'])) ?></td>
                    <td class="actions">
                        <a href="/admin/?page=submissions&action=view&id=<?= $sub['id'] ?>" class="btn btn-sm">View</a>
                        <?php if ($sub['status'] === SUBMISSION_STATUS_PENDING): ?>
                        <button class="btn btn-sm btn-success" onclick="Admin.approveSubmission(<?= $sub['id'] ?>)">✓</button>
                        <button class="btn btn-sm btn-danger" onclick="Admin.rejectSubmission(<?= $sub['id'] ?>)">✕</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php 
    $baseUrl = '/admin/?page=submissions' . ($status ? '&status=' . urlencode($status) : '');
    echo renderPaginationHtml($pagination, $baseUrl, 'p'); 
    ?>
    <?php endif; ?>
    <?php
}

function renderSubmissionDetail(PDO $pdo, int $id): void
{
    $submission = getSubmissionById($pdo, $id);
    if (!$submission) {
        echo '<div class="alert alert-error">Submission not found</div>';
        return;
    }
    
    $data = $submission['data'];
    $csrfToken = generateCsrfToken();
    
    // Handle both old (category_id) and new (category_ids) format
    $submittedCategoryIds = $data['category_ids'] ?? (isset($data['category_id']) ? [$data['category_id']] : []);
    $submittedCategories = [];
    foreach ($submittedCategoryIds as $catId) {
        $cat = getCategoryById($pdo, (int)$catId);
        if ($cat) $submittedCategories[] = $cat;
    }
    $submittedCategoryNames = array_column($submittedCategories, 'name');
    
    $originalFurniture = null;
    if ($submission['type'] === SUBMISSION_TYPE_EDIT && $submission['furniture_id']) {
        $originalFurniture = getFurnitureById($pdo, $submission['furniture_id']);
    }
    ?>
    <div class="admin-header">
        <h1>📝 Submission #<?= $id ?></h1>
        <div class="actions">
            <a href="/admin/?page=submissions" class="btn">← Back to List</a>
        </div>
    </div>
    
    <div class="submission-detail">
        <!-- Status Banner -->
        <div class="submission-status-banner status-<?= $submission['status'] ?>">
            <div>
                <?= renderAdminStatusBadge($submission['status']) ?>
                <span class="submission-type badge" style="margin-left: 0.5rem;">
                    <?= $submission['type'] === SUBMISSION_TYPE_NEW ? '✨ New Furniture' : '✏️ Edit Suggestion' ?>
                </span>
            </div>
            <?php if ($submission['status'] === SUBMISSION_STATUS_PENDING): ?>
            <div class="action-buttons">
                <button class="btn btn-success" onclick="Admin.approveSubmission(<?= $id ?>)">
                    ✓ Approve
                </button>
                <button class="btn btn-danger" onclick="Admin.rejectSubmission(<?= $id ?>)">
                    ✕ Reject
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Submission Info -->
        <div class="info-panel">
            <h3>Submission Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Submitted by:</span>
                    <span class="value"><?= e($submission['submitter_username']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Submitted:</span>
                    <span class="value"><?= date('M j, Y g:i A', strtotime($submission['created_at'])) ?></span>
                </div>
                <?php if ($submission['reviewed_at']): ?>
                <div class="info-item">
                    <span class="label">Reviewed by:</span>
                    <span class="value"><?= e($submission['reviewer_username'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Reviewed at:</span>
                    <span class="value"><?= date('M j, Y g:i A', strtotime($submission['reviewed_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Proposed Data -->
        <div class="data-panel">
            <h3><?= $submission['type'] === SUBMISSION_TYPE_EDIT ? 'Proposed Changes' : 'Submitted Data' ?></h3>
            
            <?php if ($submission['type'] === SUBMISSION_TYPE_EDIT && $originalFurniture): ?>
            <!-- Diff View for Edits -->
            <table class="diff-table">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Current</th>
                        <th>Proposed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Compare name
                    $nameChanged = trim($originalFurniture['name'] ?? '') !== trim($data['name'] ?? '');
                    ?>
                    <tr class="<?= $nameChanged ? 'changed' : '' ?>">
                        <td><strong>Name</strong></td>
                        <td><?= e($originalFurniture['name'] ?? '-') ?></td>
                        <td><?= e($data['name'] ?? '-') ?></td>
                    </tr>
                    <?php 
                    // Compare categories - sort both arrays for proper comparison
                    $originalCategoryNames = array_column($originalFurniture['categories'] ?? [], 'name');
                    sort($originalCategoryNames);
                    sort($submittedCategoryNames);
                    $categoriesChanged = $originalCategoryNames !== $submittedCategoryNames;
                    ?>
                    <tr class="<?= $categoriesChanged ? 'changed' : '' ?>">
                        <td><strong>Categories</strong></td>
                        <td><?= !empty($originalCategoryNames) ? e(implode(', ', $originalCategoryNames)) : '-' ?></td>
                        <td><?= !empty($submittedCategoryNames) ? e(implode(', ', $submittedCategoryNames)) : '-' ?></td>
                    </tr>
                    <?php
                    // Compare price - ensure both are integers
                    $originalPrice = (int) ($originalFurniture['price'] ?? 0);
                    $proposedPrice = (int) ($data['price'] ?? 0);
                    $priceChanged = $originalPrice !== $proposedPrice;
                    ?>
                    <tr class="<?= $priceChanged ? 'changed' : '' ?>">
                        <td><strong>Price</strong></td>
                        <td>$<?= number_format($originalPrice) ?></td>
                        <td>$<?= number_format($proposedPrice) ?></td>
                    </tr>
                    <?php
                    // Compare image URLs
                    $originalImage = trim($originalFurniture['image_url'] ?? '');
                    $proposedImage = trim($data['image_url'] ?? '');
                    $imageChanged = $originalImage !== $proposedImage;
                    ?>
                    <tr class="<?= $imageChanged ? 'changed' : '' ?>">
                        <td><strong>Image</strong></td>
                        <td>
                            <?php if ($originalImage): ?>
                            <img src="<?= e($originalImage) ?>" alt="Current" class="thumb">
                            <?php else: ?>
                            <span class="text-muted">No image</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($proposedImage): ?>
                            <img src="<?= e($proposedImage) ?>" alt="Proposed" class="thumb">
                            <?php else: ?>
                            <span class="text-muted">No image</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                    // Compare tags
                    $currentTagIds = array_map('intval', array_column($originalFurniture['tags'] ?? [], 'id'));
                    $proposedTagIds = array_map('intval', $data['tags'] ?? []);
                    sort($currentTagIds);
                    sort($proposedTagIds);
                    $tagsChanged = $currentTagIds !== $proposedTagIds;
                    $proposedTagsRaw = $data['tags'] ?? [];
                    ?>
                    <tr class="<?= $tagsChanged ? 'changed' : '' ?>">
                        <td><strong>Tags</strong></td>
                        <td>
                            <?php if (!empty($originalFurniture['tags'])): ?>
                                <?php foreach ($originalFurniture['tags'] as $tag): ?>
                                    <span class="badge" style="background: <?= e($tag['color']) ?>; margin-right: 0.25rem; margin-bottom: 0.25rem; display: inline-block;"><?= e($tag['name']) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No tags</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($proposedTagsRaw)): ?>
                                <?php foreach ($proposedTagsRaw as $tagId): ?>
                                    <?php 
                                    $tag = getTagById($pdo, (int) $tagId);
                                    if ($tag):
                                    ?>
                                    <span class="badge" style="background: <?= e($tag['color']) ?>; margin-right: 0.25rem; margin-bottom: 0.25rem; display: inline-block;"><?= e($tag['name']) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No tags</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if (!empty($data['edit_notes'])): ?>
            <div class="edit-notes">
                <h4>User Notes</h4>
                <p><?= nl2br(e($data['edit_notes'])) ?></p>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- New Submission View -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Name:</span>
                    <span class="value"><strong><?= e($data['name'] ?? '-') ?></strong></span>
                </div>
                <div class="info-item">
                    <span class="label">Categories:</span>
                    <span class="value">
                        <?php if (!empty($submittedCategories)): ?>
                            <?php foreach ($submittedCategories as $cat): ?>
                                <?= e($cat['icon'] . ' ' . $cat['name']) ?><?= $cat !== end($submittedCategories) ? ', ' : '' ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Price:</span>
                    <span class="value">$<?= number_format($data['price'] ?? 0) ?></span>
                </div>
            </div>
            
            <?php if (!empty($data['image_url'])): ?>
            <div class="image-preview">
                <img src="<?= e($data['image_url']) ?>" alt="Preview" onerror="this.src='/images/placeholder.svg'">
            </div>
            <?php endif; ?>
            
            <?php if (!empty($data['tags'])): ?>
            <div class="tags-preview" style="margin-top: 1rem;">
                <strong>Tags:</strong>
                <?php 
                foreach ($data['tags'] as $tagId):
                    $tag = getTagById($pdo, $tagId);
                    if ($tag):
                ?>
                <span class="badge" style="background: <?= e($tag['color']) ?>"><?= e($tag['name']) ?></span>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($submission['status'] === SUBMISSION_STATUS_REJECTED && $submission['admin_notes']): ?>
        <div class="rejection-notes">
            <h3>Rejection Reason</h3>
            <p><?= nl2br(e($submission['admin_notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    .submission-detail {
        background: var(--bg-elevated);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }
    
    .submission-status-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border-color);
    }
    
    .submission-status-banner .action-buttons {
        display: flex;
        gap: 0.5rem;
    }
    
    .info-panel, .data-panel, .rejection-notes, .edit-notes {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-panel:last-child, .data-panel:last-child, .rejection-notes:last-child {
        border-bottom: none;
    }
    
    .info-panel h3, .data-panel h3 {
        margin: 0 0 1rem 0;
        font-size: 1rem;
        color: var(--text-secondary);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .info-item .label {
        font-size: 0.8rem;
        color: var(--text-tertiary);
    }
    
    .diff-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .diff-table th, .diff-table td {
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        text-align: left;
    }
    
    .diff-table th {
        background: var(--bg-primary);
        font-weight: 600;
    }
    
    .diff-table tr.changed {
        background: rgba(245, 158, 11, 0.1);
    }
    
    .diff-table .thumb {
        max-width: 100px;
        max-height: 75px;
        border-radius: var(--radius-sm);
    }
    
    .image-preview img {
        max-width: 300px;
        max-height: 200px;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        margin-top: 1rem;
    }
    
    .rejection-notes {
        background: rgba(239, 68, 68, 0.05);
    }
    
    .rejection-notes h3 {
        color: var(--error);
    }
    
    .edit-notes {
        background: var(--bg-primary);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .edit-notes h4 {
        margin: 0 0 0.5rem 0;
        font-size: 0.875rem;
    }
    </style>
    <?php
}

function renderAdminStatusBadge(string $status): string
{
    return match($status) {
        SUBMISSION_STATUS_PENDING => '<span class="badge badge-warning">⏳ Pending</span>',
        SUBMISSION_STATUS_APPROVED => '<span class="badge badge-success">✓ Approved</span>',
        SUBMISSION_STATUS_REJECTED => '<span class="badge badge-error">✕ Rejected</span>',
        default => '<span class="badge">' . e($status) . '</span>',
    };
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
"Black Double Bed",seating,1500,"modern,luxury",
"Showerhead Advanced",bathroom,800,"rustic,large",
"Victorian Justice Lamp",lighting,150,"modern,small",</pre>
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

/**
 * Render Settings page
 */
function renderSettings(PDO $pdo): void
{
    $csrfToken = generateCsrfToken();
    $settings = getAllSettingsWithMeta();
    
    // Group settings by prefix
    $groupedSettings = [];
    foreach ($settings as $setting) {
        $parts = explode('.', $setting['key'], 2);
        $group = $parts[0] ?? 'general';
        $groupedSettings[$group][] = $setting;
    }
    
    // Define group metadata
    $groupMeta = [
        'app' => ['title' => '⚙️ General Settings', 'description' => 'Core application settings'],
        'features' => ['title' => '🔧 Feature Toggles', 'description' => 'Enable or disable features'],
        'community' => ['title' => '🌍 Community Settings', 'description' => 'Configure GTA World communities'],
    ];
    ?>
    <div class="admin-header">
        <h1>⚙️ Settings</h1>
    </div>
    
    <!-- Search/Filter Bar -->
    <div class="table-filter-bar" style="margin-bottom: 1.5rem;">
        <input type="search" 
               id="settings-search"
               class="table-search-input" 
               placeholder="🔍 Search settings..."
               aria-label="Search settings">
        <div class="filter-buttons">
            <select id="settings-group-filter" style="width: auto;">
                <option value="">All Groups</option>
                <?php foreach (array_keys($groupedSettings) as $group): ?>
                <option value="<?= e($group) ?>"><?= e($groupMeta[$group]['title'] ?? ucfirst($group)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <form id="settings-form" class="admin-form settings-form" style="max-width: 900px;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <?php foreach ($groupedSettings as $group => $items): ?>
        <?php $meta = $groupMeta[$group] ?? ['title' => ucfirst($group), 'description' => '']; ?>
        <section class="settings-section" data-group="<?= e($group) ?>">
            <h2><?= e($meta['title']) ?></h2>
            <?php if (!empty($meta['description'])): ?>
            <p><?= e($meta['description']) ?></p>
            <?php endif; ?>
            
            <div class="settings-grid">
                <?php foreach ($items as $setting): ?>
                <?php
                // Get validation rules for this setting
                $validationRules = getSettingValidationRules($setting['key'], $setting['type']);
                
                // Get dependencies (settings that must be enabled for this to show)
                $dependencies = getSettingDependencies($setting['key']);
                $dependencyAttrs = '';
                if (!empty($dependencies)) {
                    $dependencyAttrs = ' data-depends-on="' . e(implode(',', $dependencies)) . '"';
                }
                ?>
                <div class="setting-item" data-setting-key="<?= e($setting['key']) ?>" data-setting-label="<?= e(strtolower(formatSettingLabel($setting['key']))) ?>"<?= $dependencyAttrs ?>>
                    <div class="setting-item-label">
                        <label for="setting-<?= e($setting['key']) ?>">
                            <?= e(formatSettingLabel($setting['key'])) ?>
                        </label>
                        <?php if (!empty($setting['description'])): ?>
                        <p><?= e($setting['description']) ?></p>
                        <?php endif; ?>
                        <span class="setting-item-error" id="error-<?= e($setting['key']) ?>" style="display: none;"></span>
                    </div>
                    <div class="setting-item-control">
                        <?php if ($setting['type'] === 'boolean'): ?>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="setting-<?= e($setting['key']) ?>"
                                   name="settings[<?= e($setting['key']) ?>]" 
                                   value="1"
                                   <?= $setting['value'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label"><?= $setting['value'] ? 'Enabled' : 'Disabled' ?></span>
                        </label>
                        <?php elseif ($setting['type'] === 'integer'): ?>
                        <input type="number" 
                               id="setting-<?= e($setting['key']) ?>"
                               name="settings[<?= e($setting['key']) ?>]" 
                               value="<?= e((string)$setting['value']) ?>"
                               class="form-control"
                               min="<?= e((string)($validationRules['min'] ?? '')) ?>"
                               max="<?= e((string)($validationRules['max'] ?? '')) ?>"
                               data-validation='<?= e(json_encode($validationRules)) ?>'>
                        <?php elseif ($setting['is_sensitive']): ?>
                        <input type="password" 
                               id="setting-<?= e($setting['key']) ?>"
                               name="settings[<?= e($setting['key']) ?>]" 
                               value="<?= e((string)$setting['raw_value']) ?>"
                               class="form-control"
                               placeholder="••••••••"
                               data-validation='<?= e(json_encode($validationRules)) ?>'>
                        <?php else: ?>
                        <input type="text" 
                               id="setting-<?= e($setting['key']) ?>"
                               name="settings[<?= e($setting['key']) ?>]" 
                               value="<?= e((string)$setting['raw_value']) ?>"
                               class="form-control"
                               data-validation='<?= e(json_encode($validationRules)) ?>'>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
        
        <div class="form-actions settings-form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                💾 Save Settings
            </button>
        </div>
    </form>
    
    <script>
    // Validation function
    function validateSetting(input) {
        const key = input.name.match(/settings\[(.+)\]/)[1];
        const value = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value;
        const validation = JSON.parse(input.dataset.validation || '{}');
        const errorElement = document.getElementById('error-' + key);
        const settingItem = input.closest('.setting-item');
        
        // Clear previous error
        if (errorElement) {
            errorElement.style.display = 'none';
            errorElement.textContent = '';
        }
        if (settingItem) {
            settingItem.classList.remove('has-error');
        }
        
        // Skip validation for checkboxes
        if (input.type === 'checkbox') {
            return true;
        }
        
        // Integer validation
        if (input.type === 'number') {
            const numValue = parseInt(value, 10);
            if (isNaN(numValue)) {
                showError(key, 'Must be a valid number');
                return false;
            }
            if (validation.min !== undefined && numValue < validation.min) {
                showError(key, `Must be at least ${validation.min}`);
                return false;
            }
            if (validation.max !== undefined && numValue > validation.max) {
                showError(key, `Must be at most ${validation.max}`);
                return false;
            }
        }
        
        // String validation
        if (input.type === 'text' || input.type === 'password') {
            if (validation.maxlength !== undefined && value.length > validation.maxlength) {
                showError(key, `Must be at most ${validation.maxlength} characters`);
                return false;
            }
            if (validation.minlength !== undefined && value.length < validation.minlength) {
                showError(key, `Must be at least ${validation.minlength} characters`);
                return false;
            }
            if (validation.pattern !== undefined) {
                const regex = new RegExp(validation.pattern);
                if (!regex.test(value)) {
                    showError(key, validation.patternMessage || 'Invalid format');
                    return false;
                }
            }
        }
        
        return true;
    }
    
    function showError(key, message) {
        const errorElement = document.getElementById('error-' + key);
        const settingItem = document.querySelector(`[data-setting-key="${key}"]`);
        
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
        if (settingItem) {
            settingItem.classList.add('has-error');
        }
    }
    
    function clearAllErrors() {
        document.querySelectorAll('.setting-item-error').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
        document.querySelectorAll('.setting-item').forEach(el => {
            el.classList.remove('has-error');
        });
    }
    
    // Validate on input
    document.querySelectorAll('#settings-form [name^="settings["]').forEach(input => {
        if (input.type !== 'checkbox') {
            input.addEventListener('blur', function() {
                validateSetting(this);
            });
        }
    });
    
    // Form submission
    document.getElementById('settings-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        clearAllErrors();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Validate all inputs
        let isValid = true;
        document.querySelectorAll('[name^="settings["]').forEach(input => {
            if (!validateSetting(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            Admin.toast('Please fix the errors before saving', 'error');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Saving...';
        
        try {
            // Collect form data
            const formData = new FormData(form);
            const settings = {};
            const errors = {};
            
            // Process all settings inputs
            document.querySelectorAll('[name^="settings["]').forEach(input => {
                const key = input.name.match(/settings\[(.+)\]/)[1];
                if (input.type === 'checkbox') {
                    settings[key] = input.checked ? '1' : '0';
                } else {
                    settings[key] = input.value;
                }
            });
            
            const response = await fetch('/admin/api.php?action=settings/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': formData.get('csrf_token')
                },
                body: JSON.stringify({ settings })
            });
            
            const result = await response.json();
            
            if (result.success) {
                Admin.toast(result.message || 'Settings saved successfully', 'success');
                // Update toggle labels
                document.querySelectorAll('.toggle-switch input').forEach(input => {
                    const label = input.parentElement.querySelector('.toggle-label');
                    if (label) {
                        label.textContent = input.checked ? 'Enabled' : 'Disabled';
                    }
                });
                clearAllErrors();
            } else {
                // Show specific errors if provided
                if (result.errors && Array.isArray(result.errors)) {
                    result.errors.forEach(error => {
                        // Try to extract key from error message
                        const match = error.match(/^([^:]+):\s*(.+)$/);
                        if (match) {
                            showError(match[1], match[2]);
                        }
                    });
                }
                Admin.toast(result.error || 'Failed to save settings', 'error');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            Admin.toast('Network error. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
    // Update toggle labels on change
    document.querySelectorAll('.toggle-switch input').forEach(input => {
        input.addEventListener('change', function() {
            const label = this.parentElement.querySelector('.toggle-label');
            if (label) {
                label.textContent = this.checked ? 'Enabled' : 'Disabled';
            }
            
            // Handle dependencies - show/hide dependent settings
            handleSettingDependencies(this);
        });
    });
    
    // Settings search/filter
    const searchInput = document.getElementById('settings-search');
    const groupFilter = document.getElementById('settings-group-filter');
    
    function filterSettings() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedGroup = groupFilter.value;
        
        document.querySelectorAll('.settings-section').forEach(section => {
            const group = section.dataset.group;
            let sectionVisible = false;
            let visibleCount = 0;
            
            section.querySelectorAll('.setting-item').forEach(item => {
                const key = item.dataset.settingKey;
                const label = item.dataset.settingLabel || '';
                const visible = 
                    (selectedGroup === '' || group === selectedGroup) &&
                    (searchTerm === '' || key.toLowerCase().includes(searchTerm) || label.includes(searchTerm));
                
                item.style.display = visible ? '' : 'none';
                if (visible) {
                    sectionVisible = true;
                    visibleCount++;
                }
            });
            
            section.style.display = sectionVisible ? '' : 'none';
            
            // Update section header with count
            const header = section.querySelector('h2');
            if (header && searchTerm) {
                const originalText = header.textContent;
                const countMatch = originalText.match(/^(.+?)\s*\((\d+)\)$/);
                const baseText = countMatch ? countMatch[1] : originalText;
                header.textContent = `${baseText} (${visibleCount})`;
            } else if (header) {
                const countMatch = header.textContent.match(/^(.+?)\s*\((\d+)\)$/);
                if (countMatch) {
                    header.textContent = countMatch[1];
                }
            }
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterSettings);
    }
    if (groupFilter) {
        groupFilter.addEventListener('change', filterSettings);
    }
    
    // Handle setting dependencies (show/hide based on other settings)
    function handleSettingDependencies(changedInput) {
        const changedKey = changedInput.name.match(/settings\[(.+)\]/)[1];
        const isEnabled = changedInput.type === 'checkbox' ? changedInput.checked : (changedInput.value !== '' && changedInput.value !== '0');
        
        // Find all settings that depend on this one
        document.querySelectorAll('.setting-item[data-depends-on]').forEach(item => {
            const dependsOn = item.dataset.dependsOn.split(',');
            if (dependsOn.includes(changedKey)) {
                // Check if all dependencies are met
                let allMet = true;
                dependsOn.forEach(depKey => {
                    const depInput = document.querySelector(`[name="settings[${depKey}]"]`);
                    if (depInput) {
                        const depEnabled = depInput.type === 'checkbox' ? depInput.checked : (depInput.value !== '' && depInput.value !== '0');
                        if (!depEnabled) {
                            allMet = false;
                        }
                    } else {
                        allMet = false;
                    }
                });
                
                // Show/hide based on dependencies
                item.style.display = allMet ? '' : 'none';
            }
        });
    }
    
    // Initialize dependencies on page load
    document.querySelectorAll('.toggle-switch input').forEach(input => {
        handleSettingDependencies(input);
    });
    </script>
    <?php
}

/**
 * Format a setting key into a human-readable label
 */
function formatSettingLabel(string $key): string
{
    // Remove prefix
    $parts = explode('.', $key);
    array_shift($parts);
    
    // Join and convert to title case
    $label = implode(' ', $parts);
    $label = str_replace('_', ' ', $label);
    $label = ucwords($label);
    
    return $label;
}

/**
 * Get validation rules for a setting
 * 
 * @param string $key Setting key
 * @param string $type Setting type
 * @return array Validation rules
 */
function getSettingValidationRules(string $key, string $type): array
{
    $rules = [];
    
    // Integer-specific rules
    if ($type === 'integer') {
        switch ($key) {
            case 'app.items_per_page':
            case 'app.max_items_per_page':
                $rules['min'] = 1;
                $rules['max'] = 1000;
                break;
        }
    }
    
    // String-specific rules
    if ($type === 'string') {
        switch ($key) {
            case 'app.maintenance_message':
                $rules['maxlength'] = 500;
                break;
        }
    }
    
    return $rules;
}

/**
 * Get dependencies for a setting (other settings that must be enabled for this to show)
 * 
 * @param string $key Setting key
 * @return array Array of setting keys this depends on
 */
function getSettingDependencies(string $key): array
{
    $dependencies = [];
    
    // Maintenance message only shows when maintenance mode is enabled
    if ($key === 'app.maintenance_message') {
        $dependencies[] = 'app.maintenance_mode';
    }
    
    return $dependencies;
}

// =============================================
// SYNONYM MANAGEMENT VIEWS
// =============================================

function renderSynonymList(PDO $pdo): void
{
    $page = max(1, getQueryInt('p', 1));
    $search = getQuery('search', '');
    $perPage = 50;
    
    // Check if synonyms table exists
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'synonyms'");
        $tableExists = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $tableExists = false;
    }
    
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>🔤 Synonyms</h1>
        <div class="actions">
            <a href="/admin/?page=synonyms&action=discover" class="btn">🔮 Auto-Discover</a>
            <a href="/admin/?page=synonyms&action=analytics" class="btn">📊 Analytics</a>
            <a href="/admin/?page=synonyms&action=add" class="btn btn-primary">+ Add Synonym</a>
        </div>
    </div>
    
    <?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Database Migration Required</strong><br>
        The synonyms table doesn't exist yet. Please run the migration:
        <code>migrations/005_add_synonyms_and_search_log.sql</code>
    </div>
    
    <div class="admin-card">
        <h3>🔧 Setup Options</h3>
        <p>Once the migration is run, you can:</p>
        <ul>
            <li><strong>Add manually:</strong> Create synonyms one-by-one</li>
            <li><strong>Track analytics:</strong> See which searches have zero results</li>
            <li><strong>Auto-discover:</strong> Use the auto-discovery feature to find new synonyms</li>
        </ul>
    </div>
    <?php return; endif; ?>
    
    <div class="admin-toolbar">
        <form method="GET" class="toolbar-search">
            <input type="hidden" name="page" value="synonyms">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search synonyms...">
            <button type="submit" class="btn btn-sm">🔍</button>
            <?php if ($search): ?>
            <a href="/admin/?page=synonyms" class="btn btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php
    $result = getSynonymsList($pdo, $page, $perPage, $search ?: null);
    $synonyms = $result['items'];
    $pagination = $result['pagination'];
    
    if (empty($synonyms)):
    ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '🔤',
            $search ? 'No synonyms found' : 'No synonyms yet',
            $search ? 'Try a different search term.' : 'Add synonyms to improve search results. You can add manually or use the auto-discovery feature.',
            $search ? null : '/admin/?page=synonyms&action=add',
            $search ? null : 'Add First Synonym'
        ) ?>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Canonical Term</th>
                    <th>Synonym</th>
                    <th style="width: 100px">Weight</th>
                    <th style="width: 100px">Source</th>
                    <th style="width: 80px">Usage</th>
                    <th style="width: 80px">Active</th>
                    <th style="width: 140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($synonyms as $syn): ?>
                <tr>
                    <td><strong><?= e($syn['canonical']) ?></strong></td>
                    <td><code><?= e($syn['synonym']) ?></code></td>
                    <td>
                        <span class="weight-badge" style="opacity: <?= $syn['weight'] ?>">
                            <?= number_format($syn['weight'] * 100) ?>%
                        </span>
                    </td>
                    <td>
                        <?php 
                        $sourceColors = ['static' => 'secondary', 'admin' => 'primary', 'analytics' => 'success'];
                        $sourceIcons = ['static' => '📄', 'admin' => '👤', 'analytics' => '📊'];
                        ?>
                        <span class="badge badge-<?= $sourceColors[$syn['source']] ?? 'secondary' ?>">
                            <?= $sourceIcons[$syn['source']] ?? '' ?> <?= e($syn['source']) ?>
                        </span>
                    </td>
                    <td><?= number_format($syn['usage_count']) ?></td>
                    <td>
                        <button class="toggle-mini <?= $syn['is_active'] ? 'active' : '' ?>"
                                data-toggle-url="/admin/api.php?action=synonyms/toggle&id=<?= $syn['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                title="<?= $syn['is_active'] ? 'Active - click to disable' : 'Disabled - click to enable' ?>">
                            <?= $syn['is_active'] ? '✓' : '✗' ?>
                        </button>
                    </td>
                    <td class="actions">
                        <a href="/admin/?page=synonyms&action=edit&id=<?= $syn['id'] ?>" class="btn btn-sm">Edit</a>
                        <button class="btn btn-sm btn-danger" 
                                data-delete 
                                data-url="/admin/api.php?action=synonyms/delete&id=<?= $syn['id'] ?>"
                                data-csrf="<?= e($csrfToken) ?>"
                                data-confirm="Delete this synonym?">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="/admin/?page=synonyms&p=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-sm">← Prev</a>
        <?php endif; ?>
        
        <span class="pagination-info">Page <?= $page ?> of <?= $pagination['total_pages'] ?></span>
        
        <?php if ($page < $pagination['total_pages']): ?>
        <a href="/admin/?page=synonyms&p=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-sm">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <script>
    // Toggle synonym active state
    document.querySelectorAll('.toggle-mini[data-toggle-url]').forEach(btn => {
        btn.addEventListener('click', async function() {
            const url = this.dataset.toggleUrl;
            const csrf = this.dataset.csrf;
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.classList.toggle('active');
                    this.textContent = this.classList.contains('active') ? '✓' : '✗';
                    this.title = this.classList.contains('active') ? 'Active - click to disable' : 'Disabled - click to enable';
                }
            } catch (e) {
                console.error('Toggle failed:', e);
            }
        });
    });
    </script>
    <?php
}

function renderSynonymAdd(): void
{
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Add Synonym</h1>
        <div class="actions">
            <a href="/admin/?page=synonyms" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=synonyms/create" data-redirect="/admin/?page=synonyms">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="canonical">Canonical Term *</label>
                <input type="text" id="canonical" name="canonical" required maxlength="100" 
                       placeholder="e.g., sofa" autocomplete="off">
                <p class="form-help">The main/primary term that other words map to</p>
            </div>
            
            <div class="form-group">
                <label for="synonym">Synonym *</label>
                <input type="text" id="synonym" name="synonym" required maxlength="100" 
                       placeholder="e.g., couch" autocomplete="off">
                <p class="form-help">Alternative term that should find the canonical term</p>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="weight">Weight</label>
                <input type="range" id="weight" name="weight" min="0.1" max="1.0" step="0.05" value="0.9"
                       oninput="document.getElementById('weight_display').textContent = Math.round(this.value * 100) + '%'">
                <p class="form-help">Relevance: <span id="weight_display">90%</span> (higher = more relevant)</p>
            </div>
            
            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" checked>
                    Active
                </label>
                <p class="form-help">Inactive synonyms won't be used in searches</p>
            </div>
        </div>
        
        <div class="admin-card" style="background: var(--bg-tertiary); margin-bottom: var(--spacing-md);">
            <h4>💡 Synonym Tips</h4>
            <ul style="margin: 0; padding-left: var(--spacing-md);">
                <li><strong>Common misspellings:</strong> "couch" → "couch" (typos users make)</li>
                <li><strong>Regional variations:</strong> "apartment" → "flat" (UK/US)</li>
                <li><strong>Abbreviations:</strong> "television" → "tv"</li>
                <li><strong>Related terms:</strong> "sofa" → "couch", "settee", "divan"</li>
            </ul>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Synonym</button>
            <a href="/admin/?page=synonyms" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderSynonymEdit(PDO $pdo, int $id): void
{
    $synonym = getSynonymById($pdo, $id);
    if (!$synonym) {
        echo '<div class="alert alert-error">Synonym not found</div>';
        return;
    }
    
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>✏️ Edit Synonym</h1>
        <div class="actions">
            <a href="/admin/?page=synonyms" class="btn">← Back to List</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/admin/api.php?action=synonyms/update&id=<?= $id ?>" data-redirect="/admin/?page=synonyms">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label for="canonical">Canonical Term *</label>
                <input type="text" id="canonical" name="canonical" required maxlength="100" 
                       value="<?= e($synonym['canonical']) ?>">
            </div>
            
            <div class="form-group">
                <label for="synonym">Synonym *</label>
                <input type="text" id="synonym" name="synonym" required maxlength="100" 
                       value="<?= e($synonym['synonym']) ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="weight">Weight</label>
                <input type="range" id="weight" name="weight" min="0.1" max="1.0" step="0.05" 
                       value="<?= $synonym['weight'] ?>"
                       oninput="document.getElementById('weight_display').textContent = Math.round(this.value * 100) + '%'">
                <p class="form-help">Relevance: <span id="weight_display"><?= round($synonym['weight'] * 100) ?>%</span></p>
            </div>
            
            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" <?= $synonym['is_active'] ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
        </div>
        
        <div class="admin-card" style="background: var(--bg-tertiary); margin-bottom: var(--spacing-md);">
            <h4>📊 Usage Stats</h4>
            <p style="margin: 0;">
                <strong>Source:</strong> <?= e($synonym['source']) ?> &nbsp;|&nbsp;
                <strong>Usage count:</strong> <?= number_format($synonym['usage_count']) ?> &nbsp;|&nbsp;
                <strong>Created:</strong> <?= date('M j, Y', strtotime($synonym['created_at'])) ?>
            </p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Synonym</button>
            <a href="/admin/?page=synonyms" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderSearchAnalytics(PDO $pdo): void
{
    $days = getQueryInt('days', 7);
    
    // Check if tables exist
    $tablesExist = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'search_analytics'");
        $tablesExist = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $tablesExist = false;
    }
    ?>
    <div class="admin-header">
        <h1>📊 Search Analytics</h1>
        <div class="actions">
            <a href="/admin/?page=synonyms" class="btn">← Back to Synonyms</a>
        </div>
    </div>
    
    <?php if (!$tablesExist): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Database Migration Required</strong><br>
        The search analytics tables don't exist yet. Please run the migration:
        <code>migrations/005_add_synonyms_and_search_log.sql</code>
    </div>
    <?php return; endif; ?>
    
    <div class="admin-toolbar">
        <form method="GET" class="toolbar-filter">
            <input type="hidden" name="page" value="synonyms">
            <input type="hidden" name="action" value="analytics">
            <label>Time period:</label>
            <select name="days" onchange="this.form.submit()">
                <option value="1" <?= $days === 1 ? 'selected' : '' ?>>Last 24 hours</option>
                <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $days === 90 ? 'selected' : '' ?>>Last 90 days</option>
            </select>
        </form>
    </div>
    
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: var(--spacing-lg);">
        <?php
        // Get total searches
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(search_count), 0) as total 
            FROM search_analytics 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $totalSearches = (int) $stmt->fetchColumn();
        
        // Get unique queries
        $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT query_normalized) 
            FROM search_analytics 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $uniqueQueries = (int) $stmt->fetchColumn();
        
        // Get zero result searches
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(zero_result_count), 0) 
            FROM search_analytics 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $zeroResults = (int) $stmt->fetchColumn();
        ?>
        <div class="stat-card">
            <div class="stat-icon">🔍</div>
            <p class="stat-value"><?= number_format($totalSearches) ?></p>
            <p class="stat-label">Total Searches</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <p class="stat-value"><?= number_format($uniqueQueries) ?></p>
            <p class="stat-label">Unique Queries</p>
        </div>
        
        <div class="stat-card" style="<?= $zeroResults > 0 ? 'border-color: var(--warning);' : '' ?>">
            <div class="stat-icon">⚠️</div>
            <p class="stat-value"><?= number_format($zeroResults) ?></p>
            <p class="stat-label">Zero Results</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <p class="stat-value"><?= $totalSearches > 0 ? round(($zeroResults / $totalSearches) * 100, 1) : 0 ?>%</p>
            <p class="stat-label">Zero Rate</p>
        </div>
    </div>
    
    <div class="analytics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
        <div class="admin-card">
            <h3>🔥 Popular Searches</h3>
            <?php
            $popular = getPopularSearches($pdo, $days, 15);
            if (empty($popular)):
            ?>
            <p class="text-muted">No search data yet</p>
            <?php else: ?>
            <table class="data-table data-table-compact">
                <thead>
                    <tr>
                        <th>Query</th>
                        <th style="width: 80px">Searches</th>
                        <th style="width: 80px">Avg Results</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popular as $row): ?>
                    <tr>
                        <td><code><?= e($row['query_normalized']) ?></code></td>
                        <td><?= number_format($row['total_searches']) ?></td>
                        <td><?= $row['avg_results'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <div class="admin-card">
            <h3>⚠️ Zero Result Searches</h3>
            <p class="text-muted" style="margin-bottom: var(--spacing-sm);">
                These searches found nothing - consider adding synonyms!
            </p>
            <?php
            $zeroResultSearches = getZeroResultSearches($pdo, $days, 15);
            if (empty($zeroResultSearches)):
            ?>
            <p class="text-muted">No zero-result searches 🎉</p>
            <?php else: ?>
            <table class="data-table data-table-compact">
                <thead>
                    <tr>
                        <th>Query</th>
                        <th style="width: 80px">Searches</th>
                        <th style="width: 100px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zeroResultSearches as $row): ?>
                    <tr>
                        <td><code><?= e($row['query_normalized']) ?></code></td>
                        <td><?= number_format($row['zero_searches']) ?></td>
                        <td>
                            <a href="/admin/?page=synonyms&action=add&canonical=<?= urlencode($row['query_normalized']) ?>" 
                               class="btn btn-sm btn-primary">+ Synonym</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderSynonymAutoDiscovery(PDO $pdo): void
{
    $days = getQueryInt('days', 30);
    $csrfToken = generateCsrfToken();
    
    // Check if tables exist
    $tablesExist = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'search_analytics'");
        $tablesExist = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $tablesExist = false;
    }
    ?>
    <div class="admin-header">
        <h1>🔮 Synonym Auto-Discovery</h1>
        <div class="actions">
            <a href="/admin/?page=synonyms" class="btn">← Back to Synonyms</a>
        </div>
    </div>
    
    <?php if (!$tablesExist): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Database Migration Required</strong><br>
        The search analytics tables don't exist yet. Please run the migration:
        <code>migrations/005_add_synonyms_and_search_log.sql</code>
    </div>
    <?php return; endif; ?>
    
    <div class="admin-toolbar">
        <form method="GET" class="toolbar-filter">
            <input type="hidden" name="page" value="synonyms">
            <input type="hidden" name="action" value="discover">
            <label>Analysis period:</label>
            <select name="days" onchange="this.form.submit()">
                <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
                <option value="14" <?= $days === 14 ? 'selected' : '' ?>>Last 14 days</option>
                <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
                <option value="60" <?= $days === 60 ? 'selected' : '' ?>>Last 60 days</option>
                <option value="90" <?= $days === 90 ? 'selected' : '' ?>>Last 90 days</option>
            </select>
        </form>
        
        <button class="btn btn-primary" id="autoCreateBtn" onclick="autoCreateSynonyms()">
            🤖 Auto-Create All (Confidence ≥ 70%)
        </button>
    </div>
    
    <p class="text-muted" style="margin-bottom: var(--spacing-lg);">
        The system analyzes search patterns to suggest new synonyms. These include:
        <strong>fuzzy matches</strong> (typo corrections), 
        <strong>session patterns</strong> (users searching A then B), and
        <strong>zero-result queries</strong> that might need synonyms.
    </p>
    
    <?php
    // Get suggestions
    $suggestions = SynonymAutoDiscovery::analyzeSearchPatterns($pdo, $days);
    
    // Group by type
    $fuzzyMatches = array_filter($suggestions, fn($s) => $s['type'] === 'fuzzy_match');
    $sessionPatterns = array_filter($suggestions, fn($s) => $s['type'] === 'session_pattern');
    $zeroResults = array_filter($suggestions, fn($s) => $s['type'] === 'zero_result');
    ?>
    
    <div class="grid-2" style="gap: var(--spacing-lg);">
        <!-- Fuzzy Matches (Typo Corrections) -->
        <div class="card">
            <h3>✏️ Fuzzy Matches (Typo Corrections)</h3>
            <p class="text-muted" style="margin-bottom: var(--spacing-sm);">
                Terms that look like typos of existing synonyms
            </p>
            <?php if (empty($fuzzyMatches)): ?>
            <p class="text-muted">No fuzzy match suggestions available</p>
            <?php else: ?>
            <table class="data-table data-table-compact">
                <thead>
                    <tr>
                        <th>Typo</th>
                        <th>Suggested Fix</th>
                        <th>Score</th>
                        <th>Searches</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fuzzyMatches as $suggestion): ?>
                    <?php $match = $suggestion['matches'][0] ?? null; ?>
                    <?php if ($match): ?>
                    <tr>
                        <td><code><?= e($suggestion['term']) ?></code></td>
                        <td><code><?= e($match['term']) ?></code></td>
                        <td>
                            <span class="badge <?= $match['score'] >= 0.8 ? 'badge-success' : ($match['score'] >= 0.6 ? 'badge-warning' : 'badge-secondary') ?>">
                                <?= number_format($match['score'] * 100, 0) ?>%
                            </span>
                        </td>
                        <td><?= number_format($suggestion['searches'] ?? 0) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" 
                                    onclick="createSynonym('<?= e($match['term']) ?>', '<?= e($suggestion['term']) ?>', <?= $match['score'] ?>)">
                                + Add
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- Session Patterns -->
        <div class="card">
            <h3>🔗 Session Patterns</h3>
            <p class="text-muted" style="margin-bottom: var(--spacing-sm);">
                Users who searched A often then searched B (refinement patterns)
            </p>
            <?php if (empty($sessionPatterns)): ?>
            <p class="text-muted">No session pattern suggestions available</p>
            <?php else: ?>
            <table class="data-table data-table-compact">
                <thead>
                    <tr>
                        <th>First Search</th>
                        <th>Then Searched</th>
                        <th>Confidence</th>
                        <th>Occurrences</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessionPatterns as $suggestion): ?>
                    <tr>
                        <td><code><?= e($suggestion['term']) ?></code></td>
                        <td><code><?= e($suggestion['related_term']) ?></code></td>
                        <td>
                            <span class="badge <?= $suggestion['confidence'] >= 0.7 ? 'badge-success' : ($suggestion['confidence'] >= 0.5 ? 'badge-warning' : 'badge-secondary') ?>">
                                <?= number_format($suggestion['confidence'] * 100, 0) ?>%
                            </span>
                        </td>
                        <td><?= number_format($suggestion['occurrences'] ?? 0) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" 
                                    onclick="createSynonym('<?= e($suggestion['related_term']) ?>', '<?= e($suggestion['term']) ?>', <?= $suggestion['confidence'] ?>)">
                                + Add
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Zero Result Searches -->
    <div class="card" style="margin-top: var(--spacing-lg);">
        <h3>⚠️ Zero Result Searches</h3>
        <p class="text-muted" style="margin-bottom: var(--spacing-sm);">
            Searches with no results - consider adding synonyms or checking if these are valid terms
        </p>
        <?php if (empty($zeroResults)): ?>
        <p class="text-muted">No zero-result patterns to analyze 🎉</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Query</th>
                    <th>Searches</th>
                    <th>Suggested Synonym</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zeroResults as $suggestion): ?>
                <tr>
                    <td><code><?= e($suggestion['term']) ?></code></td>
                    <td><?= number_format($suggestion['searches'] ?? 0) ?></td>
                    <td>
                        <?php if (!empty($suggestion['suggestion'])): ?>
                        <code><?= e($suggestion['suggestion']) ?></code>
                        <?php else: ?>
                        <span class="text-muted">No suggestion</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($suggestion['suggestion'])): ?>
                        <button class="btn btn-sm btn-primary" 
                                onclick="createSynonym('<?= e($suggestion['suggestion']) ?>', '<?= e($suggestion['term']) ?>', 0.7)">
                            + Add
                        </button>
                        <?php else: ?>
                        <a href="/admin/?page=synonyms&action=add&synonym=<?= urlencode($suggestion['term']) ?>" 
                           class="btn btn-sm">Manual Add</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <script>
    const csrfToken = '<?= e($csrfToken) ?>';
    
    async function createSynonym(canonical, synonym, weight) {
        try {
            const response = await fetch('/admin/api.php?action=synonyms/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    canonical: canonical,
                    synonym: synonym,
                    weight: weight,
                    source: 'analytics'
                })
            });
            
            const data = await response.json();
            if (data.success) {
                window.GTAW.toast('Synonym created successfully', 'success');
                // Remove the row or disable the button
                event.target.closest('tr').style.opacity = '0.5';
                event.target.disabled = true;
                event.target.textContent = '✓ Added';
            } else {
                window.GTAW.toast(data.message || 'Failed to create synonym', 'error');
            }
        } catch (error) {
            console.error('Error creating synonym:', error);
            window.GTAW.toast('An error occurred', 'error');
        }
    }
    
    async function autoCreateSynonyms() {
        if (!confirm('This will automatically create synonyms with confidence ≥ 70%. Continue?')) {
            return;
        }
        
        const btn = document.getElementById('autoCreateBtn');
        btn.disabled = true;
        btn.innerHTML = '⏳ Processing...';
        
        try {
            const response = await fetch('/admin/api.php?action=synonyms/auto-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    days: <?= $days ?>,
                    min_confidence: 0.7
                })
            });
            
            const data = await response.json();
            if (data.success) {
                window.GTAW.toast(data.message, 'success');
                // Reload the page to refresh the list
                setTimeout(() => location.reload(), 1500);
            } else {
                window.GTAW.toast(data.message || 'Failed to auto-create synonyms', 'error');
                btn.disabled = false;
                btn.innerHTML = '🤖 Auto-Create All (Confidence ≥ 70%)';
            }
        } catch (error) {
            console.error('Error auto-creating synonyms:', error);
            window.GTAW.toast('An error occurred', 'error');
            btn.disabled = false;
            btn.innerHTML = '🤖 Auto-Create All (Confidence ≥ 70%)';
        }
    }
    </script>
    <?php
}

