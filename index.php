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
global $pdo;
$isConfigured = $pdo !== null;

// Set page title
$pageTitle = 'Browse Furniture';

// Include header
require_once __DIR__ . '/templates/header.php';
?>

<?php if (!$isConfigured): ?>
    <div class="container" style="padding-top: 4rem; text-align: center;">
        <article>
            <header>
                <h1>🛠️ Setup Required</h1>
            </header>
            <p>The application is not configured yet.</p>
            <footer>
                <a href="/admin/login.php" role="button">Go to Admin Panel</a>
            </footer>
        </article>
    </div>
<?php else: ?>
    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <div class="search-container">
                <input 
                    type="search" 
                    id="search-input" 
                    placeholder="Search furniture, categories, or tags..." 
                    aria-label="Search furniture"
                    autocomplete="off"
                >
            </div>
            <p class="search-hint">
                Press <kbd>/</kbd> to focus search • <kbd>C</kbd> to copy command • Click image to zoom
            </p>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="filters-section">
        <div class="container">
            <div class="filters">
                <div class="filter-group">
                    <label for="category-filter">Category:</label>
                    <select id="category-filter" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort-filter">Sort by:</label>
                    <select id="sort-filter" aria-label="Sort furniture">
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="price-asc">Price (Low to High)</option>
                        <option value="price-desc">Price (High to Low)</option>
                        <option value="newest-desc">Newest First</option>
                    </select>
                </div>
            </div>
            
            <!-- Tag Filters -->
            <div class="filters" style="margin-top: var(--spacing-sm);">
                <span class="tag-filters-label">Tags:</span>
                <div id="tag-filters" class="tag-filters" aria-label="Filter by tags">
                    <!-- Populated by JavaScript -->
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
                    <h3>Loading furniture...</h3>
                    <p>Please wait</p>
                </div>
            </div>
            
            <div id="pagination" class="pagination">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>

