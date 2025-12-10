<?php
/**
 * GTAW Furniture Catalog - Public Collection View
 * 
 * Displays a user's public collection (shareable link).
 * URL format: /collection.php?user=username&slug=collection-slug
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/collections.php';

/**
 * Render 404 error page for collection not found
 * 
 * @return never Exits after rendering error page
 */
function renderCollectionNotFound(): never
{
    http_response_code(404);
    $pageTitle = 'Collection Not Found';
    require_once __DIR__ . '/templates/header.php';
    ?>
    <div class="container" style="padding-top: 4rem; text-align: center;">
        <p style="font-size: 4rem; margin-bottom: var(--spacing-md);">📁</p>
        <h2>Collection Not Found</h2>
        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">The collection you're looking for doesn't exist or is private.</p>
        <a href="/" class="btn btn-primary">Browse Catalog</a>
    </div>
    <?php
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

$username = getQuery('user', '');
$slug = getQuery('slug', '');

if (empty($username) || empty($slug)) {
    renderCollectionNotFound();
}

try {
    $pdo = getDb();
} catch (RuntimeException $e) {
    throw new RuntimeException('Database connection not available', 0, $e);
}

// Check if public collections feature is enabled
if (!isFeatureEnabled('collections_public')) {
    renderCollectionNotFound();
}

$collection = getPublicCollection($pdo, $username, $slug);

if (!$collection) {
    renderCollectionNotFound();
}

$items = getCollectionItems($pdo, $collection['id']);
$currentUser = getCurrentUser();
$userFavoriteIds = $currentUser ? getUserFavoriteIds($pdo, $currentUser['id']) : [];

$pageTitle = $collection['name'] . ' - Collection by ' . $collection['owner_username'];
require_once __DIR__ . '/templates/header.php';
?>

<!-- Collection Header -->
<section class="collection-header-section">
    <div class="container">
        
        <div class="collection-header-content">
            <div class="collection-header-info">
                <span class="collection-badge">📁 Public Collection</span>
                <h1><?= e($collection['name']) ?></h1>
                <?php if ($collection['description']): ?>
                <p class="collection-description"><?= e($collection['description']) ?></p>
                <?php endif; ?>
                <div class="collection-meta">
                    <span class="meta-item">
                        <strong><?= e($collection['owner_username']) ?></strong>
                    </span>
                    <span class="meta-separator">•</span>
                    <span class="meta-item"><?= count($items) ?> items</span>
                    <span class="meta-separator">•</span>
                    <span class="meta-item"><?= date('M j, Y', strtotime($collection['created_at'])) ?></span>
                </div>
            </div>
            
            <div class="collection-header-actions">
                <button class="btn" onclick="CollectionPage.exportCommands()">
                    📥 Export Commands
                </button>
                <button class="btn" onclick="CollectionPage.shareCollection()">
                    🔗 Share
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Collection Items -->
<section class="furniture-section">
    <div class="container">
        <?php if (empty($items)): ?>
        <div style="text-align: center; padding: var(--spacing-xl);">
            <p style="font-size: 4rem; margin-bottom: var(--spacing-md);">📦</p>
            <h3>This collection is empty</h3>
            <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">No items have been added to this collection yet.</p>
            <a href="/" class="btn btn-primary">Browse Catalog</a>
        </div>
        <?php else: ?>
        <div class="furniture-grid" id="collection-grid">
            <?php foreach ($items as $item): 
                $isFavorited = in_array($item['id'], $userFavoriteIds);
                echo renderFurnitureCard($item, $isFavorited, (bool) $currentUser);
            endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
/* Collection Header Section */
.collection-header-section {
    border-bottom: 1px solid var(--border-color);
    padding: var(--spacing-lg) 0 var(--spacing-xl);
}

.collection-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--spacing-xl);
}

.collection-header-info {
    flex: 1;
}

.collection-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-xs) var(--spacing-sm);
    background: var(--primary);
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
}

.collection-header-info h1 {
    font-size: 2rem;
    margin: 0 0 var(--spacing-sm) 0;
    color: var(--text-primary);
}

.collection-description {
    color: var(--text-secondary);
    margin: 0 0 var(--spacing-md) 0;
    max-width: 600px;
}

.collection-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    color: var(--text-muted);
    font-size: 0.875rem;
}

.meta-separator {
    opacity: 0.5;
}

.collection-header-actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-shrink: 0;
}

/* Override Pico CSS default button styles for collection header buttons */
.collection-header-actions button.btn,
.collection-header-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: var(--spacing-xs);
    padding: 0 var(--spacing-lg) !important;
    height: 38px !important;
    background: var(--bg-elevated) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: var(--radius-sm) !important;
    color: var(--text-primary) !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
    box-sizing: border-box;
    line-height: 1;
    margin: 0 !important;
    font-family: inherit;
    width: auto !important;
    min-width: auto !important;
}

.collection-header-actions button.btn:hover,
.collection-header-actions .btn:hover {
    border-color: var(--primary) !important;
    color: var(--primary) !important;
    background: var(--bg-elevated) !important;
}

.collection-header-actions button.btn:active,
.collection-header-actions .btn:active {
    transform: scale(0.98);
}

.collection-header-actions button.btn.btn-primary,
.collection-header-actions .btn.btn-primary {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: white !important;
}

.collection-header-actions button.btn.btn-primary:hover,
.collection-header-actions .btn.btn-primary:hover {
    background: var(--primary-hover) !important;
    border-color: var(--primary-hover) !important;
    color: white !important;
}

@media (max-width: 768px) {
    .collection-header-content {
        flex-direction: column;
    }
    
    .collection-header-actions {
        width: 100%;
    }
    
    .collection-header-actions .btn {
        flex: 1;
    }
}

</style>

<script>
// Collection page specific functionality
const CollectionPage = {
    items: <?= json_encode(array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'categories' => $item['categories'] ?? [],
            'category_name' => $item['category_name'] ?? ($item['categories'][0]['name'] ?? ''),
            'price' => $item['price'],
            'image_url' => $item['image_url'] ?? '/images/placeholder.svg',
        ];
    }, $items)) ?>,
    
    collectionName: <?= json_encode($collection['name']) ?>,
    currentIndex: 0,
    isLoggedIn: <?= json_encode($currentUser !== null) ?>,
    
    // Lightbox elements
    lightbox: null,
    lightboxImage: null,
    lightboxTitle: null,
    lightboxMeta: null,
    
    
    init() {
        if (typeof App !== 'undefined') {
            App.state.furniture = this.items;
        }
        
        this.cacheLightboxElements();
        this.bindEvents();
    },
    
    cacheLightboxElements() {
        this.lightbox = document.getElementById('lightbox');
        this.lightboxImage = document.getElementById('lightbox-image');
        this.lightboxTitle = document.getElementById('lightbox-title');
        this.lightboxMeta = document.getElementById('lightbox-meta');
    },
    
    bindEvents() {
        // Copy buttons
        document.querySelectorAll('#collection-grid .btn-copy').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const name = btn.dataset.name;
                this.copyCommand(name);
            });
        });
        
        // Card image clicks for lightbox
        document.querySelectorAll('#collection-grid .card-image').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target.closest('.btn-favorite')) return;
                
                const card = el.closest('.furniture-card');
                const id = parseInt(card.dataset.id, 10);
                this.openLightbox(id);
            });
        });
        
        // Favorite buttons (in card actions section)
        document.querySelectorAll('#collection-grid .actions .btn-favorite').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = parseInt(btn.dataset.id, 10);
                this.toggleFavorite(id, btn);
            });
        });
        
        // Lightbox close
        document.querySelector('.lightbox-close')?.addEventListener('click', () => this.closeLightbox());
        
        // Lightbox navigation
        document.querySelector('.lightbox-nav.prev')?.addEventListener('click', () => this.navigateLightbox(-1));
        document.querySelector('.lightbox-nav.next')?.addEventListener('click', () => this.navigateLightbox(1));
        
        // Lightbox backdrop click
        this.lightbox?.addEventListener('click', (e) => {
            if (e.target === this.lightbox) this.closeLightbox();
        });
        
        // Lightbox keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!this.lightbox?.classList.contains('active')) return;
            if (e.key === 'Escape') this.closeLightbox();
            if (e.key === 'ArrowLeft') this.navigateLightbox(-1);
            if (e.key === 'ArrowRight') this.navigateLightbox(1);
        });
        
        // Lightbox copy button
        document.getElementById('lightbox-copy')?.addEventListener('click', () => {
            const item = this.items[this.currentIndex];
            if (item) this.copyCommand(item.name);
        });
        
        // Lightbox favorite button - prevent duplicate handlers from app.js
        const lightboxFavoriteBtn = document.getElementById('lightbox-favorite');
        if (lightboxFavoriteBtn) {
            // Clone and replace to remove all existing event listeners
            const newBtn = lightboxFavoriteBtn.cloneNode(true);
            lightboxFavoriteBtn.parentNode.replaceChild(newBtn, lightboxFavoriteBtn);
            
            let favoriteToggleInProgress = false;
            newBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                
                // Prevent double-clicks
                if (favoriteToggleInProgress) return;
                
                const item = this.items[this.currentIndex];
                if (item) {
                    favoriteToggleInProgress = true;
                    this.toggleFavorite(item.id, newBtn).finally(() => {
                        setTimeout(() => {
                            favoriteToggleInProgress = false;
                        }, 500);
                    });
                }
            });
        }
        
        // Lightbox add to collection button
        const lightboxCollectionBtn = document.getElementById('lightbox-add-collection');
        if (lightboxCollectionBtn) {
            // Clone and replace to remove all existing event listeners
            const newBtn = lightboxCollectionBtn.cloneNode(true);
            lightboxCollectionBtn.parentNode.replaceChild(newBtn, lightboxCollectionBtn);
            
            newBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const item = this.items[this.currentIndex];
                if (!item) return;
                
                if (!this.isLoggedIn) {
                    this.toast('Login to add items to collections', 'info');
                    return;
                }
                
                // Use App's modal if available, otherwise wait for it
                if (typeof App !== 'undefined' && App.openCollectionModal) {
                    App.openCollectionModal(item.id);
                } else {
                    // Wait a bit for App to load (script is at end of page)
                    setTimeout(() => {
                        if (typeof App !== 'undefined' && App.openCollectionModal) {
                            App.openCollectionModal(item.id);
                        } else {
                            this.toast('Feature loading, please try again', 'info');
                        }
                    }, 100);
                }
            });
        }
        
        // Lightbox suggest edit button
        const lightboxSuggestBtn = document.getElementById('lightbox-suggest-edit');
        if (lightboxSuggestBtn) {
            // Clone and replace to remove all existing event listeners
            const newBtn = lightboxSuggestBtn.cloneNode(true);
            lightboxSuggestBtn.parentNode.replaceChild(newBtn, lightboxSuggestBtn);
            
            newBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const item = this.items[this.currentIndex];
                if (item) {
                    window.location.href = `/dashboard/?page=submissions&action=new&furniture_id=${item.id}`;
                }
            });
        }
        
        // Lightbox share button
        const lightboxShareBtn = document.getElementById('lightbox-share');
        if (lightboxShareBtn) {
            // Clone and replace to remove all existing event listeners
            const newBtn = lightboxShareBtn.cloneNode(true);
            lightboxShareBtn.parentNode.replaceChild(newBtn, lightboxShareBtn);
            
            newBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const item = this.items[this.currentIndex];
                if (item && typeof App !== 'undefined' && App.shareFurniture) {
                    App.shareFurniture();
                }
            });
        }
    },
    
    openLightbox(id) {
        const index = this.items.findIndex(item => item.id === id);
        if (index === -1) return;
        
        this.currentIndex = index;
        this.showLightboxItem();
        this.lightbox?.classList.add('active');
        document.body.style.overflow = 'hidden';
    },
    
    closeLightbox() {
        this.lightbox?.classList.remove('active');
        document.body.style.overflow = '';
    },
    
    navigateLightbox(direction) {
        this.currentIndex = (this.currentIndex + direction + this.items.length) % this.items.length;
        this.showLightboxItem();
    },
    
    showLightboxItem() {
        const item = this.items[this.currentIndex];
        if (!item) return;
        
        if (this.lightboxImage) {
            this.lightboxImage.src = item.image_url;
            this.lightboxImage.alt = item.name;
        }
        if (this.lightboxTitle) {
            this.lightboxTitle.textContent = item.name;
        }
        if (this.lightboxMeta) {
            // Show all categories
            const categoryText = item.categories?.length > 0 
                ? item.categories.map(c => c.name).join(', ')
                : item.category_name || '';
            this.lightboxMeta.textContent = `${categoryText} • $${item.price.toLocaleString()}`;
        }
        
        const favBtn = document.getElementById('lightbox-favorite');
        if (favBtn && typeof App !== 'undefined') {
            const isFav = App.state.favorites?.has(item.id);
            favBtn.innerHTML = isFav ? '❤️' : '🤍';
            favBtn.classList.toggle('active', isFav);
            favBtn.dataset.id = item.id;
        }
        
        const prevBtn = document.querySelector('.lightbox-nav.prev');
        const nextBtn = document.querySelector('.lightbox-nav.next');
        if (prevBtn) prevBtn.style.display = this.items.length > 1 ? '' : 'none';
        if (nextBtn) nextBtn.style.display = this.items.length > 1 ? '' : 'none';
    },
    
    async toggleFavorite(id, btn) {
        if (btn?.disabled) return;
        if (btn) btn.disabled = true;
        
        if (!this.isLoggedIn) {
            if (btn) btn.disabled = false;
            this.toast('Login to save favorites', 'info');
            return;
        }
        
        const isFavorite = btn?.classList.contains('active');
        
        if (btn) {
            btn.classList.toggle('active');
            btn.innerHTML = isFavorite ? '🤍' : '❤️';
        }
        
        const card = btn?.closest('.furniture-card');
        if (card) {
            const cardBtn = card.querySelector('.actions .btn-favorite');
            if (cardBtn && cardBtn !== btn) {
                cardBtn.classList.toggle('active');
                cardBtn.innerHTML = isFavorite ? '🤍' : '❤️';
            }
        } else {
            const cardBtn = document.querySelector(`#collection-grid .btn-favorite[data-id="${id}"]`);
            if (cardBtn && cardBtn !== btn) {
                cardBtn.classList.toggle('active');
                cardBtn.innerHTML = isFavorite ? '🤍' : '❤️';
            }
        }
        
        if (typeof App !== 'undefined' && App.state.favorites) {
            if (isFavorite) {
                App.state.favorites.delete(id);
            } else {
                App.state.favorites.add(id);
            }
        }
        
        const csrfToken = window.GTAW ? window.GTAW.getCsrfToken() : '';
        
        try {
            await fetch('/api.php?action=favorites', {
                method: isFavorite ? 'DELETE' : 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ 
                    furniture_id: id,
                    csrf_token: csrfToken
                })
            });
            this.toast(isFavorite ? 'Removed from favorites' : 'Added to favorites', 'success');
        } catch (error) {
            if (btn) {
                btn.classList.toggle('active');
                btn.innerHTML = isFavorite ? '❤️' : '🤍';
            }
            if (cardBtn && cardBtn !== btn) {
                cardBtn.classList.toggle('active');
                cardBtn.innerHTML = isFavorite ? '❤️' : '🤍';
            }
            this.toast('Failed to update favorite', 'error');
        } finally {
            if (btn) {
                setTimeout(() => {
                    btn.disabled = false;
                }, 300);
            }
        }
    },
    
    /**
     * Copy /sf command to clipboard
     * Delegates to shared GTAW.copyCommand() for consistent behavior
     */
    copyCommand(name) {
        window.GTAW.copyCommand(name);
    },
    
    exportCommands() {
        const commands = this.items.map(item => '/sf ' + item.name).join('\n');
        const blob = new Blob([commands], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = this.collectionName.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-commands.txt';
        a.click();
        URL.revokeObjectURL(url);
        this.toast('Exported ' + this.items.length + ' commands', 'success');
    },
    
    /**
     * Share collection by copying URL to clipboard
     * Uses shared GTAW.copyToClipboard() for consistent behavior
     */
    shareCollection() {
        const url = window.location.href;
        window.GTAW.copyToClipboard(url).then((success) => {
            if (success) {
                this.toast('Collection link copied!', 'success');
            } else {
                prompt('Share this link:', url);
            }
        });
    },
    
    /**
     * Show toast notification
     * Delegates to shared GTAW.toast() for consistent behavior
     */
    toast(message, type = 'info') {
        window.GTAW.toast(message, type);
    }
};

document.addEventListener('DOMContentLoaded', () => CollectionPage.init());
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
