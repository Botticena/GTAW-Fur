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

$currentUser = getCurrentUser();
$userId = (int) $_SESSION['user_id'];

try {
    $pdo = getDb();
} catch (RuntimeException $e) {
    throw new RuntimeException('Database connection not available');
}
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
        // Check if submissions feature is enabled
        if (!isFeatureEnabled('submissions_enabled')) {
            echo '<div class="alert alert-error">' . e(__('submissions.disabled')) . '</div>';
            break;
        }
        
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
    $favorites = getUserFavorites($pdo, $userId);
    $favoritesCount = count($favorites);
    $collections = getUserCollections($pdo, $userId);
    $submissionsResult = getUserSubmissions($pdo, $userId, 1, 100);
    $submissions = $submissionsResult['items'];
    
    // Submission stats
    $pendingCount = count(array_filter($submissions, fn($s) => $s['status'] === SUBMISSION_STATUS_PENDING));
    $approvedCount = count(array_filter($submissions, fn($s) => $s['status'] === SUBMISSION_STATUS_APPROVED));
    ?>
    <div class="admin-header">
        <h1>📊 <?= e(__('dashboard.overview')) ?></h1>
    </div>
    
    <div class="stats-grid">
        <!-- Primary Stats -->
        <div class="stat-card">
            <div class="stat-icon">❤️</div>
            <p class="stat-value"><?= number_format($favoritesCount) ?></p>
            <p class="stat-label"><?= e(__('dashboard.favorites')) ?></p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📁</div>
            <p class="stat-value"><?= number_format(count($collections)) ?></p>
            <p class="stat-label"><?= e(__('dashboard.collections')) ?></p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <p class="stat-value"><?= number_format(count($submissions)) ?></p>
            <p class="stat-label"><?= e(__('dashboard.submissions')) ?></p>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <p class="stat-value"><?= number_format($pendingCount) ?></p>
            <p class="stat-label"><?= e(__('dashboard.pending_review')) ?></p>
        </div>
    </div>
    
    <h2><?= e(__('dashboard.quick_actions')) ?></h2>
    <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); margin-bottom: var(--spacing-xl);">
        <a href="/dashboard/?page=collections&action=add" class="btn btn-primary">➕ <?= e(__('collections.create')) ?></a>
        <a href="/dashboard/?page=submissions&action=new" class="btn btn-primary">📝 <?= e(__('submissions.submit')) ?></a>
        <a href="/" class="btn">🔍 <?= e(__('dashboard.browse')) ?></a>
    </div>
    
    <!-- Recently Viewed Section (populated by JS) -->
    <div id="recently-viewed-section" class="recently-viewed-section" style="display: none;">
        <h2><?= e(__('dashboard.recently_viewed')) ?></h2>
        <div id="recently-viewed-grid" class="recently-viewed-grid">
            <!-- Populated by JS -->
        </div>
    </div>
    <?php
}

/**
 * Compute insights from user's favorites
 */
function renderFavorites(PDO $pdo, int $userId): void
{
    $favorites = getUserFavorites($pdo, $userId);
    ?>
    <div class="admin-header">
        <h1>❤️ <?= e(__('favorites.title')) ?></h1>
        <div class="actions">
            <?php if (!empty($favorites)): ?>
            <button class="btn" onclick="Dashboard.exportFavorites()">📥 <?= e(__('favorites.export')) ?></button>
            <button class="btn btn-danger" onclick="Dashboard.clearAllFavorites(<?= count($favorites) ?>)">🗑️ <?= e(__('favorites.clear_all')) ?></button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($favorites)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '❤️',
            __('favorites.empty'),
            __('favorites.empty_hint'),
            '/',
            __('dashboard.browse')
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Search Filter -->
    <?php if (count($favorites) > 5): ?>
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="favorites-table"
               placeholder="🔍 <?= e(__('search.search_favorites')) ?>"
               aria-label="<?= e(__('search.search_favorites')) ?>">
    </div>
    <?php endif; ?>
    
    <div class="data-table-container">
        <table id="favorites-table" class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px;"><?= e(__('table.image')) ?></th>
                    <th><?= e(__('table.name')) ?></th>
                    <th><?= e(__('table.category')) ?></th>
                    <th style="width: 80px;"><?= e(__('table.price')) ?></th>
                    <th style="width: 200px;"><?= e(__('table.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($favorites as $item): ?>
                <?php 
                $cats = $item['categories'] ?? [];
                $catDisplay = !empty($cats) ? $cats[0]['name'] : ($item['category_name'] ?? '');
                if (count($cats) > 1) $catDisplay .= ' +' . (count($cats) - 1);
                ?>
                <tr data-id="<?= $item['id'] ?>">
                    <td>
                        <img src="<?= e($item['image_url'] ?? '/images/placeholder.svg') ?>" 
                             alt="<?= e($item['name']) ?>" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);"
                             onerror="this.src='/images/placeholder.svg'">
                    </td>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td><?= e($catDisplay) ?></td>
                    <td>$<?= number_format($item['price']) ?></td>
                    <td class="actions">
                        <button class="btn btn-sm" onclick="Dashboard.copyCommand('<?= e(addslashes($item['name'])) ?>')">📋 <?= e(__('card.copy')) ?></button>
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
        <h1>📁 <?= e(__('collections.title')) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=collections&action=add" class="btn btn-primary">+ <?= e(__('collections.create')) ?></a>
        </div>
    </div>
    
    <?php if (empty($collections)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '📁',
            __('collections.empty'),
            __('collections.empty_hint'),
            '/dashboard/?page=collections&action=add',
            __('collections.create_first')
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Search Filter -->
    <?php if (count($collections) > 5): ?>
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="collections-table"
               placeholder="🔍 <?= e(__('search.search_collections')) ?>"
               aria-label="<?= e(__('search.search_collections')) ?>">
    </div>
    <?php endif; ?>
    
    <div class="data-table-container">
        <table id="collections-table" class="data-table">
            <thead>
                <tr>
                    <th><?= e(__('table.name')) ?></th>
                    <th><?= e(__('table.description')) ?></th>
                    <th style="width: 80px;"><?= e(__('collections.items')) ?></th>
                    <th style="width: 80px;"><?= e(__('collections.visibility')) ?></th>
                    <th style="width: 200px;"><?= e(__('table.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $col): ?>
                <tr data-id="<?= $col['id'] ?>">
                    <td><strong><?= e($col['name']) ?></strong></td>
                    <td><?= e($col['description'] ?: '-') ?></td>
                    <td><?= number_format($col['item_count']) ?></td>
                    <td>
                        <?php if (isFeatureEnabled('collections_public')): ?>
                        <span class="badge <?= $col['is_public'] ? 'badge-success' : '' ?>">
                            <?= $col['is_public'] ? e(__('collections.public')) : e(__('collections.private')) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge">
                            <?= e(__('collections.private')) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="/dashboard/?page=collections&action=view&id=<?= $col['id'] ?>" class="btn btn-sm"><?= e(__('collections.view')) ?></a>
                        <a href="/dashboard/?page=collections&action=edit&id=<?= $col['id'] ?>" class="btn btn-sm"><?= e(__('collections.edit')) ?></a>
                        <button class="btn btn-sm" onclick="Dashboard.duplicateCollection(<?= $col['id'] ?>, '<?= e(addslashes($col['name'])) ?>')" title="<?= e(__('collections.duplicate')) ?>">📋</button>
                        <?php if ($col['is_public'] && isFeatureEnabled('collections_public')): ?>
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
        echo '<div class="alert alert-error">' . e(__('collections.not_found')) . '</div>';
        return;
    }
    
    $items = getCollectionItems($pdo, $id);
    ?>
    <div class="admin-header">
        <h1>📁 <?= e($collection['name']) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=collections" class="btn"><?= e(__('collections.back')) ?></a>
            <?php if ($collection['is_public'] && isFeatureEnabled('collections_public')): ?>
            <button class="btn" onclick="Dashboard.shareCollection(<?= $id ?>, '<?= e($collection['slug']) ?>', '<?= e($collection['owner_username'] ?? $_SESSION['username'] ?? '') ?>')">🔗 <?= e(__('collections.share')) ?></button>
            <?php endif; ?>
            <button class="btn" onclick="Dashboard.exportCollection(<?= $id ?>)">📥 <?= e(__('collections.export')) ?></button>
        </div>
    </div>
    
    <?php if ($collection['description']): ?>
    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);"><?= e($collection['description']) ?></p>
    <?php endif; ?>
    
    <?php if (empty($items)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '📦',
            __('collections.collection_empty'),
            __('collections.collection_empty_hint'),
            '/',
            __('dashboard.browse')
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Search Filter -->
    <?php if (count($items) > 5): ?>
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="collection-items-table"
               placeholder="🔍 <?= e(__('search.search_items')) ?>"
               aria-label="<?= e(__('search.search_items')) ?>">
    </div>
    <?php endif; ?>
    
    <div class="data-table-container">
        <table id="collection-items-table" class="data-table" data-sortable data-collection-id="<?= $id ?>">
            <thead>
                <tr>
                    <th style="width: 40px;">⋮⋮</th>
                    <th style="width: 60px;"><?= e(__('table.image')) ?></th>
                    <th><?= e(__('table.name')) ?></th>
                    <th><?= e(__('table.category')) ?></th>
                    <th style="width: 80px;"><?= e(__('table.price')) ?></th>
                    <th style="width: 140px;"><?= e(__('table.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <?php 
                $cats = $item['categories'] ?? [];
                $catDisplay = !empty($cats) ? $cats[0]['name'] : ($item['category_name'] ?? '');
                if (count($cats) > 1) $catDisplay .= ' +' . (count($cats) - 1);
                ?>
                <tr data-id="<?= $item['id'] ?>" data-sort-order="<?= $item['sort_order'] ?? $index ?>">
                    <td class="drag-handle" style="cursor: move; text-align: center; color: var(--text-muted);" title="<?= e(__('table.drag_reorder')) ?>">⋮⋮</td>
                    <td>
                        <img src="<?= e($item['image_url'] ?? '/images/placeholder.svg') ?>" 
                             alt="<?= e($item['name']) ?>" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm);"
                             onerror="this.src='/images/placeholder.svg'">
                    </td>
                    <td><strong><?= e($item['name']) ?></strong></td>
                    <td><?= e($catDisplay) ?></td>
                    <td>$<?= number_format($item['price']) ?></td>
                    <td class="actions">
                        <button class="btn btn-sm" onclick="Dashboard.copyCommand('<?= e(addslashes($item['name'])) ?>')">📋 <?= e(__('card.copy')) ?></button>
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
        <h1>➕ <?= e(__('collections.create_title')) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=collections" class="btn"><?= e(__('collections.back')) ?></a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/dashboard/api.php?action=collections/create" data-redirect="/dashboard/?page=collections">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name"><?= e(__('collections.name')) ?> *</label>
            <input type="text" id="name" name="name" required maxlength="100" placeholder="<?= e(__('collections.name_placeholder')) ?>">
        </div>
        
        <div class="form-group">
            <label for="description"><?= e(__('collections.description_optional')) ?></label>
            <textarea id="description" name="description" rows="3" maxlength="500" placeholder="<?= e(__('collections.description_placeholder')) ?>"></textarea>
        </div>
        
        <?php if (isFeatureEnabled('collections_public')): ?>
        <div class="form-group">
            <label class="checkbox-label" style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer;">
                <input type="checkbox" name="is_public" value="1" checked style="width: auto;">
                <span><?= e(__('collections.make_public')) ?></span>
            </label>
        </div>
        <?php else: ?>
        <input type="hidden" name="is_public" value="0">
        <div class="alert alert-info" style="margin-top: 1rem;">
            <small>⚠️ <?= e(__('collections.public_disabled')) ?> <?= e(__('collections.will_be_private')) ?></small>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(__('collections.create')) ?></button>
            <a href="/dashboard/?page=collections" class="btn"><?= e(__('form.cancel')) ?></a>
        </div>
    </form>
    <?php
}

function renderCollectionEdit(PDO $pdo, int $userId, int $id): void
{
    $collection = getCollectionById($pdo, $id);
    if (!$collection || $collection['user_id'] !== $userId) {
        echo '<div class="alert alert-error">' . e(__('collections.not_found')) . '</div>';
        return;
    }
    
    $csrfToken = generateCsrfToken();
    ?>
    <div class="admin-header">
        <h1>✏️ <?= e(__('collections.edit_title')) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=collections" class="btn"><?= e(__('collections.back')) ?></a>
        </div>
    </div>
    
    <form class="admin-form" method="POST" data-ajax data-action="/dashboard/api.php?action=collections/update&id=<?= $id ?>" data-redirect="/dashboard/?page=collections">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        
        <div class="form-group">
            <label for="name"><?= e(__('collections.name')) ?> *</label>
            <input type="text" id="name" name="name" required maxlength="100" value="<?= e($collection['name']) ?>">
        </div>
        
        <div class="form-group">
            <label for="description"><?= e(__('collections.description_optional')) ?></label>
            <textarea id="description" name="description" rows="3" maxlength="500"><?= e($collection['description'] ?? '') ?></textarea>
        </div>
        
        <?php if (isFeatureEnabled('collections_public')): ?>
        <div class="form-group">
            <label class="checkbox-label" style="display: flex; align-items: center; gap: var(--spacing-sm); cursor: pointer;">
                <input type="checkbox" name="is_public" value="1" <?= $collection['is_public'] ? 'checked' : '' ?> style="width: auto;">
                <span><?= e(__('collections.make_public')) ?></span>
            </label>
        </div>
        <?php else: ?>
        <input type="hidden" name="is_public" value="0">
        <div class="alert alert-info" style="margin-top: 1rem;">
            <small>⚠️ <?= e(__('collections.public_disabled')) ?><?php if ($collection['is_public']): ?> <?= e(__('collections.currently_public_warning')) ?><?php else: ?> <?= e(__('collections.will_be_private')) ?><?php endif; ?></small>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e(__('collections.save')) ?></button>
            <a href="/dashboard/?page=collections" class="btn"><?= e(__('form.cancel')) ?></a>
        </div>
    </form>
    <?php
}

function renderSubmissionsList(PDO $pdo, int $userId): void
{
    $result = getUserSubmissions($pdo, $userId);
    $submissions = $result['items'];
    ?>
    <div class="admin-header">
        <h1>📝 <?= e(__('submissions.title')) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=submissions&action=new" class="btn btn-primary">+ <?= e(__('submissions.submit')) ?></a>
        </div>
    </div>
    
    <?php if (empty($submissions)): ?>
    <div class="data-table-container">
        <?= renderEmptyState(
            '📝',
            __('submissions.empty'),
            __('submissions.empty_hint'),
            '/dashboard/?page=submissions&action=new',
            __('submissions.submit')
        ) ?>
    </div>
    <?php else: ?>
    
    <!-- Search Filter -->
    <?php if (count($submissions) > 5): ?>
    <div class="table-filter-bar">
        <input type="search" 
               class="table-search-input" 
               data-table="submissions-table"
               placeholder="🔍 <?= e(__('search.search_submissions')) ?>"
               aria-label="<?= e(__('search.search_submissions')) ?>">
    </div>
    <?php endif; ?>
    
    <div class="data-table-container">
        <table id="submissions-table" class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;"><?= e(__('submissions.type')) ?></th>
                    <th><?= e(__('table.name')) ?></th>
                    <th style="width: 100px;"><?= e(__('submissions.status')) ?></th>
                    <th style="width: 120px;"><?= e(__('submissions.submitted')) ?></th>
                    <th style="width: 140px;"><?= e(__('table.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub): ?>
                <tr>
                    <td><span class="badge"><?= $sub['type'] === SUBMISSION_TYPE_NEW ? e(__('submissions.type_new')) : e(__('submissions.type_edit')) ?></span></td>
                    <td>
                        <strong><?= e($sub['data']['name'] ?? 'Untitled') ?></strong>
                        <?php if ($sub['type'] === SUBMISSION_TYPE_EDIT && !empty($sub['data']['edit_notes'])): ?>
                        <br><small style="color: var(--text-muted);" title="<?= e($sub['data']['edit_notes']) ?>">📝 <?= e(mb_strimwidth($sub['data']['edit_notes'], 0, 50, '...')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= renderStatusBadge($sub['status']) ?>
                        <?php if ($sub['status'] === SUBMISSION_STATUS_REJECTED && !empty($sub['admin_notes'])): ?>
                        <br><small style="color: var(--text-muted);" title="<?= e($sub['admin_notes']) ?>">💬 <?= e(__('submissions.feedback')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($sub['created_at'])) ?></td>
                    <td class="actions">
                        <a href="/dashboard/?page=submissions&action=view&id=<?= $sub['id'] ?>" class="btn btn-sm"><?= e(__('submissions.view')) ?></a>
                        <?php if ($sub['status'] === SUBMISSION_STATUS_PENDING): ?>
                        <button class="btn btn-sm btn-danger" onclick="Dashboard.cancelSubmission(<?= $sub['id'] ?>)"><?= e(__('submissions.cancel')) ?></button>
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
    
    $furnitureId = getQueryInt('furniture_id', 0);
    $furniture = $furnitureId > 0 ? getFurnitureById($pdo, $furnitureId) : null;
    $isEdit = $furniture !== null;
    $itemCategoryIds = $furniture ? array_column($furniture['categories'] ?? [], 'id') : [];
    ?>
    <div class="admin-header">
        <h1><?= $isEdit ? '✏️ ' . e(__('submissions.suggest_edit')) : '➕ ' . e(__('submissions.submit_new')) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=submissions" class="btn"><?= e(__('collections.back')) ?></a>
        </div>
    </div>
    
    <div class="alert alert-info">
        <?php if ($isEdit): ?>
        <strong><?= e(__('submissions.editing')) ?></strong> <?= e($furniture['name']) ?><br>
        <?= e(__('submissions.editing_note')) ?>
        <?php else: ?>
        <strong>Note:</strong> <?= e(__('submissions.new_note')) ?>
        <?php endif; ?>
    </div>
    
    <form class="admin-form form-split" method="POST" data-ajax 
          data-action="/dashboard/api.php?action=submissions/create<?= $isEdit ? '&furniture_id=' . $furnitureId : '' ?>" 
          data-redirect="/dashboard/?page=submissions&success=Submission+received">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="type" value="edit">
        <?php endif; ?>
        
        <div class="form-layout">
            <!-- Left Column: Form Container -->
            <div class="form-layout-main">
                <div class="form-group">
                    <label for="name"><?= e(__('submissions.furniture_name')) ?> *</label>
                    <input type="text" id="name" name="name" required maxlength="255" 
                           value="<?= e($furniture['name'] ?? '') ?>"
                           placeholder="<?= e(__('submissions.furniture_name_placeholder')) ?>">
                    <p class="form-help"><?= e(__('submissions.furniture_name_help')) ?></p>
                </div>
                
                <div class="form-group">
                    <label for="price"><?= e(__('submissions.price')) ?></label>
                    <input type="number" id="price" name="price" min="0" value="<?= $furniture['price'] ?? 250 ?>">
                    <p class="form-help"><?= e(__('submissions.price_help')) ?></p>
                </div>
                
                <div class="form-group">
                    <label for="image_url"><?= e(__('submissions.image_url')) ?></label>
                    <input type="text" id="image_url" name="image_url" 
                           value="<?= e($furniture['image_url'] ?? '') ?>"
                           placeholder="<?= e(__('submissions.image_url_placeholder')) ?>">
                    <p class="form-help"><?= e(__('submissions.image_url_help')) ?></p>
                    <div class="image-preview" id="image-preview">
                        <img src="<?= e($furniture['image_url'] ?? '/images/placeholder.svg') ?>" 
                             alt="Preview" id="preview-img" 
                             onerror="this.src='/images/placeholder.svg'">
                    </div>
                </div>
                
                <?php if ($isEdit): ?>
                <div class="form-group">
                    <label for="edit_notes"><?= e(__('submissions.edit_notes')) ?></label>
                    <textarea id="edit_notes" name="edit_notes" rows="3" 
                              placeholder="<?= e(__('submissions.edit_notes_placeholder')) ?>"></textarea>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? e(__('submissions.submit_edit')) : e(__('submissions.submit')) ?></button>
                    <a href="/dashboard/?page=submissions" class="btn"><?= e(__('form.cancel')) ?></a>
                </div>
                
                <!-- Duplicate Detection Panel (in left column, below form) -->
                <aside id="duplicate-panel" 
                       class="duplicate-panel hidden"
                       data-exclude-id="<?= $furnitureId ?? '' ?>">
                    <!-- Populated by JavaScript -->
                </aside>
            </div>
            
            <!-- Right Column: Sidebar with separate panels -->
            <div class="form-layout-sidebar">
                <!-- Categories Panel (styled like tags) -->
                <section class="tags-panel categories-panel">
                    <h3 class="tags-panel-header"><?= e(__('submissions.categories')) ?> * <small style="font-weight: normal; opacity: 0.7;"><?= e(__('submissions.categories_help')) ?></small></h3>
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
                    <h3 class="tags-panel-header"><?= e(__('submissions.tags')) ?></h3>
                    
                    <!-- General Tags -->
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
                    
                    <!-- Category-Specific Tags (loaded dynamically) -->
                    <div id="category-specific-tags"></div>
                </section>
            </div>
        </div>
    </form>
    <?php
}

function renderSubmissionEdit(PDO $pdo, int $userId, int $id): void
{
    $submission = getSubmissionById($pdo, $id);
    if (!$submission || $submission['user_id'] !== $userId) {
        echo '<div class="alert alert-error">' . e(__('submissions.not_found')) . '</div>';
        return;
    }
    
    if ($submission['status'] !== SUBMISSION_STATUS_PENDING) {
        echo '<div class="alert alert-error">' . e(__('submissions.cannot_edit', ['status' => $submission['status']])) . '</div>';
        return;
    }
    
    renderSubmissionView($pdo, $userId, $id);
}

function renderSubmissionView(PDO $pdo, int $userId, int $id): void
{
    $submission = getSubmissionById($pdo, $id);
    if (!$submission || $submission['user_id'] !== $userId) {
        echo '<div class="alert alert-error">' . e(__('submissions.not_found')) . '</div>';
        return;
    }
    
    $data = $submission['data'];
    // Handle both old (category_id) and new (category_ids) format
    $submittedCategoryIds = $data['category_ids'] ?? (isset($data['category_id']) ? [$data['category_id']] : []);
    $submittedCategories = [];
    foreach ($submittedCategoryIds as $catId) {
        $cat = getCategoryById($pdo, (int)$catId);
        if ($cat) $submittedCategories[] = $cat;
    }
    ?>
    <div class="admin-header">
        <h1>📝 <?= e(__('submissions.details')) ?></h1>
        <div class="actions">
            <a href="/dashboard/?page=submissions" class="btn"><?= e(__('collections.back')) ?></a>
        </div>
    </div>
    
    <div class="admin-form" style="max-width: 700px;">
        <div style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--bg-elevated); border-radius: var(--radius-md);">
            <?= renderStatusBadge($submission['status']) ?>
            <?php if (!empty($submission['reviewed_at'])): ?>
            <span style="color: var(--text-muted); margin-left: var(--spacing-md); font-size: 0.875rem;">
                <?= e(__('submissions.reviewed_on', ['date' => date('M j, Y', strtotime($submission['reviewed_at']))])) ?>
            </span>
            <?php endif; ?>
        </div>
        
        <?php if ($submission['status'] === SUBMISSION_STATUS_REJECTED && !empty($submission['admin_notes'])): ?>
        <div class="alert alert-error">
            <strong><?= e(__('submissions.feedback')) ?></strong><br>
            <?= e($submission['admin_notes']) ?>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label><?= e(__('submissions.type')) ?></label>
            <p><?= $submission['type'] === SUBMISSION_TYPE_NEW ? e(__('submissions.type_new')) . ' Furniture' : e(__('submissions.type_edit')) ?></p>
        </div>
        
        <?php if ($submission['type'] === SUBMISSION_TYPE_EDIT && !empty($submission['furniture_name'])): ?>
        <div class="form-group">
            <label><?= e(__('submissions.editing')) ?></label>
            <p><?= e($submission['furniture_name']) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label><?= e(__('table.name')) ?></label>
            <p><strong><?= e($data['name'] ?? '-') ?></strong></p>
        </div>
        
        <div class="form-group">
            <label><?= e(__('submissions.categories')) ?></label>
            <p>
                <?php if (!empty($submittedCategories)): ?>
                    <?php foreach ($submittedCategories as $cat): ?>
                        <?= e($cat['icon'] . ' ' . $cat['name']) ?><?= $cat !== end($submittedCategories) ? ', ' : '' ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </p>
        </div>
        
        <div class="form-group">
            <label><?= e(__('submissions.price')) ?></label>
            <p>$<?= number_format($data['price'] ?? 0) ?></p>
        </div>
        
        <?php if (!empty($data['image_url'])): ?>
        <div class="form-group">
            <label><?= e(__('table.image')) ?></label>
            <div class="image-preview">
                <img src="<?= e($data['image_url']) ?>" alt="Preview">
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label><?= e(__('submissions.submitted')) ?></label>
            <p><?= date('M j, Y g:i A', strtotime($submission['created_at'])) ?></p>
        </div>
        
        <?php if ($submission['status'] === SUBMISSION_STATUS_PENDING): ?>
        <div class="form-actions">
            <button class="btn btn-danger" onclick="Dashboard.cancelSubmission(<?= $submission['id'] ?>)">
                <?= e(__('submissions.cancel')) ?>
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
        SUBMISSION_STATUS_PENDING => '<span class="badge badge-warning">' . e(__('submissions.status_pending')) . '</span>',
        SUBMISSION_STATUS_APPROVED => '<span class="badge badge-success">' . e(__('submissions.status_approved')) . '</span>',
        SUBMISSION_STATUS_REJECTED => '<span class="badge badge-error">' . e(__('submissions.status_rejected')) . '</span>',
        default => '<span class="badge">' . e($status) . '</span>',
    };
}
