<?php
/**
 * GTAW Furniture Catalog - User Dashboard
 * 
 * Personal dashboard for logged-in users to manage their
 * favorites, collections, and submissions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/collections.php';
require_once __DIR__ . '/../includes/submissions.php';

// Require user authentication
requireAuth();

// Get current user
$currentUser = getCurrentUser();
$userId = (int) $_SESSION['user_id'];

// Get database connection
try {
    $pdo = getDb();
} catch (RuntimeException $e) {
    throw new RuntimeException('Database connection not available');
}

// Get current page
$page = getQuery('page', 'overview');
$action = getQuery('action', 'list');
$id = getQueryInt('id', 0);

// Messages
$success = getQuery('success', null);
$error = getQuery('error', null);

// Page-specific data
$pageTitle = 'Dashboard';

// Include header
require_once __DIR__ . '/../templates/dashboard/header.php';
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
    case 'overview':
        renderOverview($pdo, $userId);
        break;
    
    case 'favorites':
        renderFavorites($pdo, $userId);
        break;
    
    case 'collections':
        if ($action === 'view' && $id > 0) {
            renderCollectionView($pdo, $userId, $id);
        } elseif ($action === 'add') {
            renderCollectionAdd($pdo, $userId);
        } elseif ($action === 'edit' && $id > 0) {
            renderCollectionEdit($pdo, $userId, $id);
        } else {
            renderCollectionsList($pdo, $userId);
        }
        break;
    
    case 'submissions':
        if ($action === 'new') {
            renderSubmissionNew($pdo);
        } elseif ($action === 'edit' && $id > 0) {
            renderSubmissionEdit($pdo, $userId, $id);
        } elseif ($action === 'view' && $id > 0) {
            renderSubmissionView($pdo, $userId, $id);
        } else {
            renderSubmissionsList($pdo, $userId);
        }
        break;
    
    default:
        renderOverview($pdo, $userId);
}
?>

<?php require_once __DIR__ . '/../templates/dashboard/footer.php'; ?>

<?php
// =============================================
// VIEW FUNCTIONS
// =============================================

function renderOverview(PDO $pdo, int $userId): void
{
    $favoritesCount = countUserFavorites($pdo, $userId);
    $collections = getUserCollections($pdo, $userId);
    $submissionsResult = getUserSubmissions($pdo, $userId, 1, 5);
    ?>
    <div class="admin-header">
        <h1>📊 Overview</h1>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">❤️</div>
            <p class="stat-value"><?= number_format($favoritesCount) ?></p>
            <p class="stat-label">Favorites</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📁</div>
            <p class="stat-value"><?= number_format(count($collections)) ?></p>
            <p class="stat-label">Collections</p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <p class="stat-value"><?= number_format($submissionsResult['pagination']['total']) ?></p>
            <p class="stat-label">Submissions</p>
        </div>
    </div>
    
    <h2>Quick Actions</h2>
    <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-xl);">
        <a href="/dashboard/?page=collections&action=add" class="btn btn-primary">➕ Create Collection</a>
        <a href="/dashboard/?page=submissions&action=new" class="btn btn-primary">📝 Submit Furniture</a>
        <a href="/" class="btn">🔍 Browse Catalog</a>
    </div>
    <?php
}

function renderFavorites(PDO $pdo, int $userId): void
{
    $favorites = getUserFavorites($pdo, $userId);
    ?>
    <div class="admin-header">
        <h1>❤️ My Favorites</h1>
        <div class="actions">
            <?php if (!empty($favorites)): ?>
            <button class="btn" onclick="Dashboard.exportFavorites()">📥 Export</button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($favorites)): ?>
    <div class="data-table-container" style="padding: var(--spacing-xl); text-align: center;">
        <p style="font-size: 3rem; margin-bottom: var(--spacing-md);">❤️</p>
        <h3>No favorites yet</h3>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">Browse the catalog and click the heart icon to add items to your favorites.</p>
        <a href="/" class="btn btn-primary">Browse Catalog</a>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th style="width: 80px;">Price</th>
                    <th style="width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($favorites as $item): ?>
                <tr data-id="<?= $item['id'] ?>">
                    <td>
                        <img src="<?= e($item['image_url'] ?? '/images/placeholder.svg') ?>" 
                             alt="<?= e($item['name']) ?>" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);"
                             onerror="this.src='/images/placeholder.svg'">
                    </td>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td><?= e($item['category_name']) ?></td>
                    <td>$<?= number_format($item['price']) ?></td>
                    <td class="actions">
                        <button class="btn btn-sm" onclick="Dashboard.copyCommand('<?= e(addslashes($item['name'])) ?>')">📋 Copy</button>
                        <button class="btn btn-sm" onclick="Dashboard.addToCollection(<?= $item['id'] ?>)">📁</button>
                        <button class="btn btn-sm btn-danger" onclick="Dashboard.removeFavorite(<?= $item['id'] ?>)">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

function renderCollectionsList(PDO $pdo, int $userId): void
{
    $collections = getUserCollections($pdo, $userId);
    ?>
    <div class="admin-header">
        <h1>📁 My Collections</h1>
        <div class="actions">
            <a href="/dashboard/?page=collections&action=add" class="btn btn-primary">+ Create Collection</a>
        </div>
    </div>
    
    <?php if (empty($collections)): ?>
    <div class="data-table-container" style="padding: var(--spacing-xl); text-align: center;">
        <p style="font-size: 3rem; margin-bottom: var(--spacing-md);">📁</p>
        <h3>No collections yet</h3>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">Create collections to organize your furniture items into shareable lists.</p>
        <a href="/dashboard/?page=collections&action=add" class="btn btn-primary">Create Your First Collection</a>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th style="width: 80px;">Items</th>
                    <th style="width: 80px;">Visibility</th>
                    <th style="width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $col): ?>
                <tr data-id="<?= $col['id'] ?>">
                    <td><strong><?= e($col['name']) ?></strong></td>
                    <td><?= e($col['description'] ?: '-') ?></td>
                    <td><?= number_format($col['item_count']) ?></td>
                    <td>
                        <span class="badge <?= $col['is_public'] ? 'badge-success' : '' ?>">
                            <?= $col['is_public'] ? '🌐 Public' : '🔒 Private' ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="/dashboard/?page=collections&action=view&id=<?= $col['id'] ?>" class="btn btn-sm">View</a>
                        <a href="/dashboard/?page=collections&action=edit&id=<?= $col['id'] ?>" class="btn btn-sm">Edit</a>
                        <?php if ($col['is_public']): ?>
                        <button class="btn btn-sm" onclick="Dashboard.shareCollection(<?= $col['id'] ?>, '<?= e($col['slug']) ?>', '<?= e($col['owner_username'] ?? $_SESSION['username'] ?? '') ?>')">🔗</button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-danger" onclick="Dashboard.deleteCollection(<?= $col['id'] ?>, '<?= e(addslashes($col['name'])) ?>')">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

function renderCollectionView(PDO $pdo, int $userId, int $id): void
{
    $currentUser = getCurrentUser();
    $collection = getCollectionById($pdo, $id);
    if (!$collection || $collection['user_id'] !== $userId) {
        echo '<div class="alert alert-error">Collection not found</div>';
        return;
    }
    
    $items = getCollectionItems($pdo, $id);
    ?>
    <div class="admin-header">
        <h1>📁 <?= e($collection['name']) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=collections" class="btn">← Back</a>
            <?php if ($collection['is_public']): ?>
            <button class="btn" onclick="Dashboard.shareCollection(<?= $id ?>, '<?= e($collection['slug']) ?>', '<?= e($collection['owner_username'] ?? $_SESSION['username'] ?? '') ?>')">🔗 Share</button>
            <?php endif; ?>
            <button class="btn" onclick="Dashboard.exportCollection(<?= $id ?>)">📥 Export</button>
        </div>
    </div>
    
    <?php if ($collection['description']): ?>
    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);"><?= e($collection['description']) ?></p>
    <?php endif; ?>
    
    <?php if (empty($items)): ?>
    <div class="data-table-container" style="padding: var(--spacing-xl); text-align: center;">
        <p style="font-size: 3rem; margin-bottom: var(--spacing-md);">📦</p>
        <h3>Collection is empty</h3>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">Browse the catalog and add items to this collection.</p>
        <a href="/" class="btn btn-primary">Browse Catalog</a>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table" data-sortable data-collection-id="<?= $id ?>">
            <thead>
                <tr>
                    <th style="width: 40px;">⋮⋮</th>
                    <th style="width: 60px;">Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th style="width: 80px;">Price</th>
                    <th style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr data-id="<?= $item['id'] ?>" data-sort-order="<?= $item['sort_order'] ?? $index ?>">
                    <td class="drag-handle" style="cursor: move; text-align: center; color: var(--text-muted);" title="Drag to reorder">⋮⋮</td>
                    <td>
                        <img src="<?= e($item['image_url'] ?? '/images/placeholder.svg') ?>" 
                             alt="<?= e($item['name']) ?>" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);"
                             onerror="this.src='/images/placeholder.svg'">
                    </td>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td><?= e($item['category_name']) ?></td>
                    <td>$<?= number_format($item['price']) ?></td>
                    <td class="actions">
                        <button class="btn btn-sm" onclick="Dashboard.copyCommand('<?= e(addslashes($item['name'])) ?>')">📋 Copy</button>
                        <button class="btn btn-sm btn-danger" onclick="Dashboard.removeFromCollection(<?= $id ?>, <?= $item['id'] ?>)">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

function renderCollectionAdd(PDO $pdo, int $userId): void
{
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>➕ Create Collection</h1>
        <div class="actions">
            <a href="/dashboard/?page=collections" class="btn">← Back</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/dashboard/api.php?action=collections/create" data-redirect="/dashboard/?page=collections">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Collection Name *</label>
            <input type="text" id="name" name="name" required maxlength="100" placeholder="e.g., Modern Living Room">
        </div>
        
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3" maxlength="500" placeholder="Describe this collection..."></textarea>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label" style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer;">
                <input type="checkbox" name="is_public" value="1" checked style="width: auto;">
                <span>Make this collection public (shareable)</span>
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Collection</button>
            <a href="/dashboard/?page=collections" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderCollectionEdit(PDO $pdo, int $userId, int $id): void
{
    $collection = getCollectionById($pdo, $id);
    if (!$collection || $collection['user_id'] !== $userId) {
        echo '<div class="alert alert-error">Collection not found</div>';
        return;
    }
    
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>✏️ Edit Collection</h1>
        <div class="actions">
            <a href="/dashboard/?page=collections" class="btn">← Back</a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/dashboard/api.php?action=collections/update&id=<?= $id ?>" data-redirect="/dashboard/?page=collections">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name">Collection Name *</label>
            <input type="text" id="name" name="name" required maxlength="100" value="<?= e($collection['name']) ?>">
        </div>
        
        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3" maxlength="500"><?= e($collection['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label" style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer;">
                <input type="checkbox" name="is_public" value="1" <?= $collection['is_public'] ? 'checked' : '' ?> style="width: auto;">
                <span>Make this collection public (shareable)</span>
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/dashboard/?page=collections" class="btn">Cancel</a>
        </div>
    </form>
    <?php
}

function renderSubmissionsList(PDO $pdo, int $userId): void
{
    $result = getUserSubmissions($pdo, $userId);
    ?>
    <div class="admin-header">
        <h1>📝 My Submissions</h1>
        <div class="actions">
            <a href="/dashboard/?page=submissions&action=new" class="btn btn-primary">+ Submit Furniture</a>
        </div>
    </div>
    
    <?php if (empty($result['items'])): ?>
    <div class="data-table-container" style="padding: var(--spacing-xl); text-align: center;">
        <p style="font-size: 3rem; margin-bottom: var(--spacing-md);">📝</p>
        <h3>No submissions yet</h3>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">Submit new furniture to add to the catalog, or suggest edits to existing items.</p>
        <a href="/dashboard/?page=submissions&action=new" class="btn btn-primary">Submit Furniture</a>
    </div>
    <?php else: ?>
    <div class="data-table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Type</th>
                    <th>Name</th>
                    <th style="width: 100px;">Status</th>
                    <th style="width: 120px;">Submitted</th>
                    <th style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['items'] as $sub): ?>
                <tr>
                    <td><span class="badge"><?= $sub['type'] === SUBMISSION_TYPE_NEW ? '✨ New' : '✏️ Edit' ?></span></td>
                    <td>
                        <strong><?= e($sub['data']['name'] ?? 'Untitled') ?></strong>
                        <?php if ($sub['type'] === SUBMISSION_TYPE_EDIT && !empty($sub['furniture_name'])): ?>
                        <br><small style="color: var(--text-muted);">Editing: <?= e($sub['furniture_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= renderStatusBadge($sub['status']) ?>
                        <?php if ($sub['status'] === SUBMISSION_STATUS_REJECTED && !empty($sub['admin_notes'])): ?>
                        <br><small style="color: var(--text-muted);" title="<?= e($sub['admin_notes']) ?>">💬 Feedback</small>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($sub['created_at'])) ?></td>
                    <td class="actions">
                        <a href="/dashboard/?page=submissions&action=view&id=<?= $sub['id'] ?>" class="btn btn-sm">View</a>
                        <?php if ($sub['status'] === SUBMISSION_STATUS_PENDING): ?>
                        <button class="btn btn-sm btn-danger" onclick="Dashboard.cancelSubmission(<?= $sub['id'] ?>)">Cancel</button>
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

function renderSubmissionNew(PDO $pdo): void
{
    $categories = getCategories($pdo);
    $tagsGrouped = getTagsGrouped($pdo);
    $csrfToken = generateCsrfToken();
    
    // Check if editing existing furniture (pre-fill)
    $furnitureId = getQueryInt('furniture_id', 0);
    $furniture = $furnitureId > 0 ? getFurnitureById($pdo, $furnitureId) : null;
    $isEdit = $furniture !== null;
    ?>
    <div class="admin-header">
        <h1><?= $isEdit ? '✏️ Suggest Edit' : '➕ Submit New Furniture' ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=submissions" class="btn">← Back</a>
        </div>
    </div>
    
    <div class="alert alert-info">
        <?php if ($isEdit): ?>
        <strong>Editing:</strong> <?= e($furniture['name']) ?><br>
        Your suggested changes will be reviewed by an administrator before being applied.
        <?php else: ?>
        <strong>Note:</strong> Your submission will be reviewed by an administrator before being added to the catalog.
        <?php endif; ?>
    </div>
    
    <form class="admin-form two-column" method="POST" data-ajax 
          data-action="/dashboard/api.php?action=submissions/create<?= $isEdit ? '&furniture_id=' . $furnitureId : '' ?>" 
          data-redirect="/dashboard/?page=submissions&success=Submission+received">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="type" value="edit">
        <?php endif; ?>
        
        <!-- Left Column: Main Fields -->
        <div class="form-column-left">
            <div class="form-group">
                <label for="name">Furniture Name *</label>
                <input type="text" id="name" name="name" required maxlength="255" 
                       value="<?= e($furniture['name'] ?? '') ?>"
                       placeholder="e.g., prop_sofa_modern_01">
                <p class="form-help">The exact prop name used in-game</p>
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($furniture['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" min="0" value="<?= $furniture['price'] ?? 250 ?>">
                <p class="form-help">Default is $250 (most common price in-game)</p>
            </div>
            
            <div class="form-group">
                <label for="image_url">Image URL</label>
                <input type="text" id="image_url" name="image_url" 
                       value="<?= e($furniture['image_url'] ?? '') ?>"
                       placeholder="https://... or /images/...">
                <p class="form-help">URL to an image of the furniture (will be processed and converted)</p>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="form-group">
                <label for="edit_notes">Edit Notes (optional)</label>
                <textarea id="edit_notes" name="edit_notes" rows="3" 
                          placeholder="Explain what you're changing and why..."></textarea>
            </div>
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Submit Edit' : 'Submit Furniture' ?></button>
                <a href="/dashboard/?page=submissions" class="btn">Cancel</a>
            </div>
        </div>
        
        <!-- Right Column: Tags -->
        <div class="form-column-right">
            <label class="tags-column-label">Tags</label>
            
            <?php 
            $itemTagIds = $furniture ? array_column($furniture['tags'] ?? [], 'id') : [];
            foreach ($tagsGrouped['groups'] as $group): 
            ?>
            <?php if (!empty($group['tags'])): ?>
            <div class="tag-group-section">
                <h4>
                    <span class="group-color-dot" style="background: <?= e($group['color']) ?>"></span>
                    <?= e($group['name']) ?>
                </h4>
                <div class="checkbox-group">
                    <?php foreach ($group['tags'] as $tag): ?>
                    <label class="checkbox-item <?= in_array($tag['id'], $itemTagIds) ? 'checked' : '' ?>">
                        <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" 
                               <?= in_array($tag['id'], $itemTagIds) ? 'checked' : '' ?>>
                        <span><?= e($tag['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </form>
    <?php
}

function renderSubmissionEdit(PDO $pdo, int $userId, int $id): void
{
    $submission = getSubmissionById($pdo, $id);
    if (!$submission || $submission['user_id'] !== $userId) {
        echo '<div class="alert alert-error">Submission not found</div>';
        return;
    }
    
    if ($submission['status'] !== SUBMISSION_STATUS_PENDING) {
        echo '<div class="alert alert-error">Cannot edit a ' . e($submission['status']) . ' submission</div>';
        return;
    }
    
    renderSubmissionView($pdo, $userId, $id);
}

function renderSubmissionView(PDO $pdo, int $userId, int $id): void
{
    $submission = getSubmissionById($pdo, $id);
    if (!$submission || $submission['user_id'] !== $userId) {
        echo '<div class="alert alert-error">Submission not found</div>';
        return;
    }
    
    $data = $submission['data'];
    $category = getCategoryById($pdo, $data['category_id'] ?? 0);
    ?>
    <div class="admin-header">
        <h1>📝 Submission Details</h1>
        <div class="actions">
            <a href="/dashboard/?page=submissions" class="btn">← Back</a>
        </div>
    </div>
    
    <div class="admin-form" style="max-width: 700px;">
        <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--bg-elevated); border-radius: var(--radius-md);">
            <?= renderStatusBadge($submission['status']) ?>
            <?php if (!empty($submission['reviewed_at'])): ?>
            <span style="color: var(--text-muted); margin-left: var(--spacing-md); font-size: 0.875rem;">
                Reviewed on <?= date('M j, Y', strtotime($submission['reviewed_at'])) ?>
            </span>
            <?php endif; ?>
        </div>
        
        <?php if ($submission['status'] === SUBMISSION_STATUS_REJECTED && !empty($submission['admin_notes'])): ?>
        <div class="alert alert-error">
            <strong>Feedback from reviewer:</strong><br>
            <?= e($submission['admin_notes']) ?>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label>Type</label>
            <p><?= $submission['type'] === SUBMISSION_TYPE_NEW ? '✨ New Furniture' : '✏️ Edit Suggestion' ?></p>
        </div>
        
        <?php if ($submission['type'] === SUBMISSION_TYPE_EDIT && !empty($submission['furniture_name'])): ?>
        <div class="form-group">
            <label>Editing</label>
            <p><?= e($submission['furniture_name']) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label>Name</label>
            <p><strong><?= e($data['name'] ?? '-') ?></strong></p>
        </div>
        
        <div class="form-group">
            <label>Category</label>
            <p><?= $category ? e($category['icon'] . ' ' . $category['name']) : '-' ?></p>
        </div>
        
        <div class="form-group">
            <label>Price</label>
            <p>$<?= number_format($data['price'] ?? 0) ?></p>
        </div>
        
        <?php if (!empty($data['image_url'])): ?>
        <div class="form-group">
            <label>Image</label>
            <div class="image-preview">
                <img src="<?= e($data['image_url']) ?>" alt="Preview">
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label>Submitted</label>
            <p><?= date('M j, Y g:i A', strtotime($submission['created_at'])) ?></p>
        </div>
        
        <?php if ($submission['status'] === SUBMISSION_STATUS_PENDING): ?>
        <div class="form-actions">
            <button class="btn btn-danger" onclick="Dashboard.cancelSubmission(<?= $submission['id'] ?>)">
                Cancel Submission
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// =============================================
// HELPER FUNCTIONS
// =============================================

function renderStatusBadge(string $status): string
{
    return match($status) {
        SUBMISSION_STATUS_PENDING => '<span class="badge badge-warning">⏳ Pending</span>',
        SUBMISSION_STATUS_APPROVED => '<span class="badge badge-success">✓ Approved</span>',
        SUBMISSION_STATUS_REJECTED => '<span class="badge badge-error">✕ Rejected</span>',
        default => '<span class="badge">' . e($status) . '</span>',
    };
}
