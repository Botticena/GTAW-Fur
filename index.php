<?php
/**
 * GTAW Furniture Catalog - Main Page
 * 
 * The primary catalog view where users browse furniture.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';

// Check if database is configured
$isConfigured = Database::isConfigured();

// Set page title
$pageTitle = __('nav.browse');

// Include header
require_once __DIR__ . '/templates/header.php';
?>

<?php if (!$isConfigured): ?>
    <div class="container" style="padding-top: 4rem; text-align: center;">
        <article>
            <header>
                <h1>🛠️ <?= e(__('setup.required')) ?></h1>
            </header>
            <p><?= e(__('setup.not_configured')) ?></p>
            <footer>
                <a href="/admin/login.php" role="button"><?= e(__('setup.go_to_admin')) ?></a>
            </footer>
        </article>
    </div>
<?php else: ?>
    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <div class="search-container">
                <span class="search-spinner"></span>
                <input 
                    type="search" 
                    id="search-input" 
                    placeholder="<?= e(__('search.placeholder')) ?>" 
                    aria-label="<?= e(__('search.placeholder')) ?>"
                    autocomplete="off"
                >
            </div>
            <p class="search-hint">
                <?= e(__('search.hint')) ?>
            </p>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="filters-section">
        <div class="container">
            <div class="filters">
                <div class="filter-group">
                    <label for="category-filter"><?= e(__('filter.category')) ?></label>
                    <select id="category-filter" aria-label="<?= e(__('filter.category')) ?>">
                        <option value=""><?= e(__('filter.all_categories')) ?></option>
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort-filter"><?= e(__('filter.sort')) ?></label>
                    <select id="sort-filter" aria-label="<?= e(__('filter.sort')) ?>">
                        <option value="name-asc"><?= e(__('filter.sort.name_asc')) ?></option>
                        <option value="name-desc"><?= e(__('filter.sort.name_desc')) ?></option>
                        <option value="price-asc"><?= e(__('filter.sort.price_asc')) ?></option>
                        <option value="price-desc"><?= e(__('filter.sort.price_desc')) ?></option>
                        <option value="newest-desc"><?= e(__('filter.sort.newest')) ?></option>
                    </select>
                </div>
                
                <?php if ($currentUser): ?>
                <button type="button" id="favorites-only" class="btn-favorites-filter" aria-pressed="false">
                    ❤️ <?= e(__('filter.favorites_only')) ?>
                </button>
                <?php endif; ?>
                
                <button type="button" id="clear-filters" class="btn-clear-filters" style="display: none;">
                    <?= e(__('filter.clear_all')) ?>
                </button>
            </div>
            
            <!-- Grouped Tag Filters -->
            <div id="tag-filters-container" class="tag-filters-container" aria-label="<?= e(__('submissions.tags')) ?>">
                <!-- Populated by JavaScript with grouped tag sections -->
            </div>
            
            <!-- Active Tags Display -->
            <div id="active-tags" class="active-tags" style="display: none;">
                <span class="active-tags-label"><?= e(__('filter.active')) ?></span>
                <div id="active-tags-list" class="active-tags-list">
                    <!-- Shows selected tags -->
                </div>
            </div>
        </div>
    </section>

    <!-- Furniture Grid Section -->
    <section class="furniture-section">
        <div class="container">
            <div id="furniture-grid" class="furniture-grid">
                <!-- Populated by JavaScript -->
                <div class="empty-state">
                    <div class="icon">⏳</div>
                    <h3><?= e(__('empty.loading')) ?></h3>
                    <p><?= e(__('empty.please_wait')) ?></p>
                </div>
            </div>
            
            <div id="pagination" class="pagination">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
