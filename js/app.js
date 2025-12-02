/**
 * GTAW Furniture Catalog - Frontend Application
 * Vanilla JavaScript, no dependencies
 * 
 * Features:
 * - Furniture browsing with search, filter, and sort
 * - Image lightbox with navigation
 * - Tag filtering
 * - Favorites management
 * - URL state persistence
 */

const App = {
    // Application state
    state: {
        furniture: [],
        categories: [],
        tags: [],        // flat list
        tagGroups: [],   // grouped structure { groups: [...], ungrouped: [...] }
        favorites: new Set(),
        filters: {
            category: null,
            tags: [],
            search: '',
            sort: 'name',
            order: 'asc',
            favoritesOnly: false
        },
        currentFurnitureForCollection: null,
        pagination: {
            page: 1,
            per_page: 24,
            total: 0,
            total_pages: 0
        },
        user: null,
        loading: false,
        searching: false,
        lightbox: {
            isOpen: false,
            currentIndex: -1
        }
    },

    // Cache settings (TTL in milliseconds)
    // Note: Cache keys are versioned to invalidate when structure changes
    cacheConfig: {
        categories: { key: 'gtaw_categories_v2', ttl: 5 * 60 * 1000 }, // 5 minutes
        tags: { key: 'gtaw_tags_grouped_v2', ttl: 5 * 60 * 1000 }     // Changed from flat to grouped
    },

    // DOM element cache
    elements: {},

    /**
     * Initialize the application
     */
    async init() {
        this.cacheElements();
        this.initTheme();
        this.bindEvents();
        this.bindLightboxEvents();
        this.showSkeletonLoading();

        // Parse URL parameters first (before loading data)
        this.parseUrlParams();

        // Load initial data in parallel
        await Promise.all([
            this.loadCategories(),
            this.loadTags(),
            this.checkAuth()
        ]);

        // Load furniture with current filters
        await this.loadFurniture();
    },

    /**
     * Initialize theme from localStorage or system preference
     */
    initTheme() {
        const saved = localStorage.getItem('gtaw_theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = saved || (prefersDark ? 'dark' : 'dark'); // Default to dark
        document.documentElement.setAttribute('data-theme', theme);
    },

    /**
     * Toggle between light and dark themes
     */
    toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('gtaw_theme', next);
        this.toast(`Switched to ${next} mode`, 'info');
    },

    /**
     * Show skeleton loading cards
     */
    showSkeletonLoading() {
        if (!this.elements.grid) return;
        
        const skeletonCount = 12;
        const skeletons = Array(skeletonCount).fill(0).map(() => `
            <div class="skeleton-card">
                <div class="skeleton-image skeleton"></div>
                <div class="skeleton-body">
                    <div class="skeleton-title skeleton"></div>
                    <div class="skeleton-meta skeleton"></div>
                    <div class="skeleton-tags">
                        <div class="skeleton-tag skeleton"></div>
                        <div class="skeleton-tag skeleton"></div>
                    </div>
                    <div class="skeleton-actions">
                        <div class="skeleton-btn skeleton"></div>
                        <div class="skeleton-btn-small skeleton"></div>
                    </div>
                </div>
            </div>
        `).join('');
        
        this.elements.grid.innerHTML = skeletons;
    },

    /**
     * Cache frequently accessed DOM elements
     */
    cacheElements() {
        this.elements = {
            grid: document.getElementById('furniture-grid'),
            searchInput: document.getElementById('search-input'),
            searchContainer: document.querySelector('.search-container'),
            categorySelect: document.getElementById('category-filter'),
            sortSelect: document.getElementById('sort-filter'),
            favoritesOnlyBtn: document.getElementById('favorites-only'),
            tagFiltersContainer: document.getElementById('tag-filters-container'),
            activeTags: document.getElementById('active-tags'),
            activeTagsList: document.getElementById('active-tags-list'),
            clearFiltersBtn: document.getElementById('clear-filters'),
            pagination: document.getElementById('pagination'),
            loadingOverlay: document.getElementById('loading'),
            toastContainer: document.getElementById('toast-container'),
            themeToggle: document.getElementById('theme-toggle'),
            // Lightbox elements
            lightbox: document.getElementById('lightbox'),
            lightboxImage: document.getElementById('lightbox-image'),
            lightboxTitle: document.getElementById('lightbox-title'),
            lightboxMeta: document.getElementById('lightbox-meta'),
            lightboxCopy: document.getElementById('lightbox-copy'),
            lightboxFavorite: document.getElementById('lightbox-favorite'),
            lightboxEdit: document.getElementById('lightbox-edit'),
            lightboxShare: document.getElementById('lightbox-share'),
            lightboxAddCollection: document.getElementById('lightbox-add-collection'),
            lightboxSuggestEdit: document.getElementById('lightbox-suggest-edit'),
            lightboxClose: document.querySelector('.lightbox-close'),
            lightboxPrev: document.querySelector('.lightbox-nav.prev'),
            lightboxNext: document.querySelector('.lightbox-nav.next')
        };
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Search with debounce and visual feedback
        let searchTimeout;
        this.elements.searchInput?.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            
            // Show searching state
            this.elements.searchContainer?.classList.add('searching');
            
            searchTimeout = setTimeout(() => {
                this.elements.searchContainer?.classList.remove('searching');
                this.state.filters.search = e.target.value.trim();
                this.state.pagination.page = 1;
                this.loadFurniture();
                this.updateUrl();
            }, 300);
        });

        // Theme toggle
        this.elements.themeToggle?.addEventListener('click', () => {
            this.toggleTheme();
        });

        // Category filter
        this.elements.categorySelect?.addEventListener('change', (e) => {
            this.state.filters.category = e.target.value || null;
            this.state.pagination.page = 1;
            this.loadFurniture();
            this.updateUrl();
        });

        // Sort filter
        this.elements.sortSelect?.addEventListener('change', (e) => {
            const [sort, order] = e.target.value.split('-');
            this.state.filters.sort = sort || 'name';
            this.state.filters.order = order || 'asc';
            this.state.pagination.page = 1;
            this.loadFurniture();
            this.updateUrl();
        });

        // Favorites only filter (button toggle)
        this.elements.favoritesOnlyBtn?.addEventListener('click', () => {
            this.state.filters.favoritesOnly = !this.state.filters.favoritesOnly;
            this.elements.favoritesOnlyBtn.classList.toggle('active', this.state.filters.favoritesOnly);
            this.elements.favoritesOnlyBtn.setAttribute('aria-pressed', this.state.filters.favoritesOnly);
            this.state.pagination.page = 1;
            this.loadFurniture();
            this.updateUrl();
        });

        // Clear all filters button
        this.elements.clearFiltersBtn?.addEventListener('click', () => {
            this.clearAllFilters();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Press '/' to focus search
            if (e.key === '/' && !this.isInputFocused() && !this.state.lightbox.isOpen) {
                e.preventDefault();
                this.elements.searchInput?.focus();
            }

            // Press Escape to blur search or close lightbox
            if (e.key === 'Escape') {
                if (this.state.lightbox.isOpen) {
                    this.closeLightbox();
                } else if (document.activeElement === this.elements.searchInput) {
                    this.elements.searchInput.blur();
                }
            }

            // Lightbox navigation
            if (this.state.lightbox.isOpen) {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.lightboxPrev();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.lightboxNext();
                } else if (e.key === 'c' || e.key === 'C') {
                    // Copy command from lightbox
                    const name = this.elements.lightboxCopy?.dataset.name;
                    if (name) this.copyCommand(name);
                }
            }

            // Grid navigation with arrow keys
            if (!this.isInputFocused() && !this.state.lightbox.isOpen) {
                const focused = document.activeElement;
                const card = focused?.closest('.furniture-card');
                
                if (card && (e.key === 'ArrowUp' || e.key === 'ArrowDown' || 
                    e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
                    e.preventDefault();
                    this.navigateGrid(card, e.key);
                }
            }
        });

        // Delegate click events on grid
        this.elements.grid?.addEventListener('click', (e) => {
            const copyBtn = e.target.closest('.btn-copy');
            const favBtn = e.target.closest('.btn-favorite');
            const cardImage = e.target.closest('.card-image');

            if (copyBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.copyCommand(copyBtn.dataset.name);
            } else if (favBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.toggleFavorite(parseInt(favBtn.dataset.id, 10));
            } else if (cardImage) {
                const card = cardImage.closest('.furniture-card');
                if (card) {
                    e.preventDefault();
                    this.openLightbox(parseInt(card.dataset.id, 10));
                }
            }
        });

        // Keyboard shortcuts on cards
        this.elements.grid?.addEventListener('keydown', (e) => {
            const card = e.target.closest('.furniture-card');
            if (!card) return;

            // Press 'C' to copy command
            if (e.key === 'c' || e.key === 'C') {
                const name = card.querySelector('.btn-copy')?.dataset.name;
                if (name) {
                    this.copyCommand(name);
                }
            }

            // Press 'F' to toggle favorite
            if (e.key === 'f' || e.key === 'F') {
                const id = parseInt(card.dataset.id, 10);
                if (id) {
                    this.toggleFavorite(id);
                }
            }

            // Press Enter or Space to open lightbox
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const id = parseInt(card.dataset.id, 10);
                if (id) {
                    this.openLightbox(id);
                }
            }
        });

        // Browser back/forward navigation
        window.addEventListener('popstate', () => {
            this.parseUrlParams();
            this.loadFurniture();
            this.syncFiltersToUI();
        });
    },

    /**
     * Bind lightbox-specific events
     */
    bindLightboxEvents() {
        // Don't bind lightbox events on collection pages (they have their own handler)
        if (window.location.pathname === '/collection.php' || window.location.pathname.includes('/collection.php')) {
            return;
        }
        
        // Close button
        this.elements.lightboxClose?.addEventListener('click', () => {
            this.closeLightbox();
        });

        // Click outside to close
        this.elements.lightbox?.addEventListener('click', (e) => {
            if (e.target === this.elements.lightbox) {
                this.closeLightbox();
            }
        });

        // Navigation buttons
        this.elements.lightboxPrev?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.lightboxPrev();
        });

        this.elements.lightboxNext?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.lightboxNext();
        });

        // Copy button in lightbox
        this.elements.lightboxCopy?.addEventListener('click', (e) => {
            e.stopPropagation();
            const name = this.elements.lightboxCopy.dataset.name;
            if (name) {
                this.copyCommand(name);
            }
        });

        // Favorite button in lightbox
        this.elements.lightboxFavorite?.addEventListener('click', (e) => {
            e.stopPropagation();
            const furnitureId = parseInt(this.elements.lightboxFavorite.dataset.id, 10);
            if (furnitureId) {
                this.toggleFavorite(furnitureId);
                this.updateLightboxFavoriteButton(furnitureId);
            }
        });

        // Edit button in lightbox (prevent default, just set href dynamically)
        this.elements.lightboxEdit?.addEventListener('click', (e) => {
            e.stopPropagation();
            // Link will navigate naturally via href
        });

        // Add to Collection button in lightbox
        this.elements.lightboxAddCollection?.addEventListener('click', (e) => {
            e.stopPropagation();
            const item = this.state.furniture[this.state.lightbox.currentIndex];
            if (item) {
                this.openCollectionModal(item.id);
            }
        });

        // Suggest Edit button in lightbox
        this.elements.lightboxSuggestEdit?.addEventListener('click', (e) => {
            e.stopPropagation();
            const item = this.state.furniture[this.state.lightbox.currentIndex];
            if (item) {
                // Navigate to suggest edit page
                window.location.href = `/dashboard/?page=submissions&action=new&furniture_id=${item.id}`;
            }
        });


        // Share button in lightbox
        this.elements.lightboxShare?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.shareFurniture();
        });

        // Prevent clicks on content from closing
        this.elements.lightbox?.querySelector('.lightbox-content')?.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    },

    /**
     * Check if an input element is focused
     */
    isInputFocused() {
        const active = document.activeElement;
        return active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable);
    },

    /**
     * Navigate grid using arrow keys
     */
    navigateGrid(currentCard, direction) {
        const cards = Array.from(this.elements.grid?.querySelectorAll('.furniture-card') || []);
        const currentIndex = cards.indexOf(currentCard);
        if (currentIndex === -1) return;

        // Calculate grid columns
        const gridStyle = window.getComputedStyle(this.elements.grid);
        const columns = gridStyle.gridTemplateColumns.split(' ').length;

        let nextIndex;
        switch (direction) {
            case 'ArrowLeft':
                nextIndex = currentIndex > 0 ? currentIndex - 1 : currentIndex;
                break;
            case 'ArrowRight':
                nextIndex = currentIndex < cards.length - 1 ? currentIndex + 1 : currentIndex;
                break;
            case 'ArrowUp':
                nextIndex = currentIndex >= columns ? currentIndex - columns : currentIndex;
                break;
            case 'ArrowDown':
                nextIndex = currentIndex + columns < cards.length ? currentIndex + columns : currentIndex;
                break;
            default:
                return;
        }

        if (nextIndex !== currentIndex && cards[nextIndex]) {
            cards[nextIndex].focus();
            cards[nextIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    },

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : null;
    },

    /**
     * API helper
     */
    async api(action, options = {}) {
        const url = new URL('/api.php', window.location.origin);
        url.searchParams.set('action', action);

        if (options.params) {
            Object.entries(options.params).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    url.searchParams.set(key, value);
                }
            });
        }

        const method = options.method || 'GET';
        const fetchOptions = {
            method: method,
            credentials: 'same-origin',
            headers: {}
        };

        // Include CSRF token for state-changing operations
        const csrfToken = this.getCsrfToken();
        if (csrfToken && (method === 'POST' || method === 'DELETE' || method === 'PUT' || method === 'PATCH')) {
            fetchOptions.headers['X-CSRF-Token'] = csrfToken;
        }

        if (options.body) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            const body = { ...options.body };
            // Also include CSRF token in body for compatibility
            if (csrfToken && (method === 'POST' || method === 'DELETE' || method === 'PUT' || method === 'PATCH')) {
                body.csrf_token = csrfToken;
            }
            fetchOptions.body = JSON.stringify(body);
        }

        const response = await fetch(url, fetchOptions);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'API request failed');
        }

        return data;
    },

    /**
     * Get cached data or fetch fresh
     */
    getCached(key) {
        try {
            const cached = localStorage.getItem(key);
            if (!cached) return null;
            
            const { data, timestamp, ttl } = JSON.parse(cached);
            if (Date.now() - timestamp > ttl) {
                localStorage.removeItem(key);
                return null;
            }
            return data;
        } catch {
            return null;
        }
    },

    /**
     * Set cached data with TTL
     */
    setCache(key, data, ttl) {
        try {
            localStorage.setItem(key, JSON.stringify({
                data,
                timestamp: Date.now(),
                ttl
            }));
        } catch {
            // Storage quota exceeded or disabled
        }
    },

    /**
     * Load categories for filter dropdown (with caching)
     */
    async loadCategories() {
        try {
            const { key, ttl } = this.cacheConfig.categories;
            
            // Try cache first
            const cached = this.getCached(key);
            if (cached) {
                this.state.categories = cached;
                this.renderCategoryFilter();
                return;
            }
            
            const { data } = await this.api('categories');
            this.state.categories = data;
            this.setCache(key, data, ttl);
            this.renderCategoryFilter();
        } catch (error) {
            console.error('Failed to load categories:', error);
        }
    },

    /**
     * Load tags for filter (with caching)
     * Now loads grouped structure: { groups: [...], ungrouped: [...] }
     */
    async loadTags() {
        try {
            const { key, ttl } = this.cacheConfig.tags;
            
            // Try cache first (validate it's the new grouped format)
            const cached = this.getCached(key);
            if (cached && (Array.isArray(cached.groups) || Array.isArray(cached.ungrouped))) {
                this.state.tagGroups = cached;
                this.state.tags = this.flattenTags(cached);
                this.renderTagFilters();
                return;
            }
            
            const { data } = await this.api('tags');
            this.state.tagGroups = data;
            this.state.tags = this.flattenTags(data);
            this.setCache(key, data, ttl);
            this.renderTagFilters();
        } catch (error) {
            console.error('Failed to load tags:', error);
        }
    },

    /**
     * Flatten grouped tags into a simple array
     */
    flattenTags(groupedData) {
        const tags = [];
        if (groupedData.groups) {
            groupedData.groups.forEach(group => {
                if (group.tags) {
                    tags.push(...group.tags);
                }
            });
        }
        if (groupedData.ungrouped) {
            tags.push(...groupedData.ungrouped);
        }
        return tags;
    },

    /**
     * Check if user is logged in
     */
    async checkAuth() {
        try {
            const { data } = await this.api('user');
            this.state.user = data;
            await this.loadUserFavorites();
        } catch (error) {
            // Not logged in, that's fine
            this.state.user = null;
        }
    },

    /**
     * Load user's favorites
     */
    async loadUserFavorites() {
        if (!this.state.user) return;

        try {
            const { data } = await this.api('favorites');
            this.state.favorites = new Set(data.map(f => f.id));
        } catch (error) {
            console.error('Failed to load favorites:', error);
        }
    },

    /**
     * Load furniture items
     */
    async loadFurniture() {
        this.setLoading(true);
        
        // Add loading class to grid for transition effect
        this.elements.grid?.classList.add('loading');

        try {
            const action = this.state.filters.search ? 'furniture/search' : 'furniture';
            const params = {
                page: this.state.pagination.page,
                per_page: this.state.pagination.per_page,
                category: this.state.filters.category,
                tags: this.state.filters.tags.join(','),
                sort: this.state.filters.sort,
                order: this.state.filters.order
            };

            if (this.state.filters.search) {
                params.q = this.state.filters.search;
            }

            if (this.state.filters.favoritesOnly) {
                params.favorites_only = '1';
            }

            const { data, pagination } = await this.api(action, { params });

            this.state.furniture = data;
            this.state.pagination = { ...this.state.pagination, ...pagination };

            this.render();
        } catch (error) {
            console.error('Failed to load furniture:', error);
            this.toast('Failed to load furniture', 'error');
        } finally {
            this.setLoading(false);
            // Remove loading class after a brief delay for smooth transition
            setTimeout(() => {
                this.elements.grid?.classList.remove('loading');
            }, 50);
        }
    },

    /**
     * Copy /sf command to clipboard
     */
    async copyCommand(name) {
        const command = `/sf ${name}`;

        try {
            await navigator.clipboard.writeText(command);
            this.toast(`Copied: ${command}`, 'success');
        } catch (error) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = command;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.toast(`Copied: ${command}`, 'success');
        }
    },

    /**
     * Toggle favorite status
     */
    async toggleFavorite(furnitureId) {
        if (!this.state.user) {
            this.toast('Login to save favorites', 'info');
            return;
        }

        const isFavorite = this.state.favorites.has(furnitureId);

        // Optimistic update
        if (isFavorite) {
            this.state.favorites.delete(furnitureId);
        } else {
            this.state.favorites.add(furnitureId);
        }
        this.updateFavoriteButton(furnitureId);

        try {
            await this.api('favorites', {
                method: isFavorite ? 'DELETE' : 'POST',
                body: { furniture_id: furnitureId }
            });

            this.toast(isFavorite ? 'Removed from favorites' : 'Added to favorites', 'success');
        } catch (error) {
            // Revert on error
            if (isFavorite) {
                this.state.favorites.add(furnitureId);
            } else {
                this.state.favorites.delete(furnitureId);
            }
            this.updateFavoriteButton(furnitureId);
            this.toast('Failed to update favorites', 'error');
        }
    },

    /**
     * Update a single favorite button without re-rendering
     */
    updateFavoriteButton(furnitureId) {
        // Update card button
        const btn = this.elements.grid?.querySelector(`.btn-favorite[data-id="${furnitureId}"]`);
        if (btn) {
            const isFav = this.state.favorites.has(furnitureId);
            btn.classList.toggle('active', isFav);
            btn.innerHTML = isFav ? '❤️' : '🤍';
            btn.setAttribute('title', isFav ? 'Remove from favorites' : 'Add to favorites');
        }
        
        // Also update lightbox button if open and showing this item
        if (this.state.lightbox.isOpen) {
            const currentItem = this.state.furniture[this.state.lightbox.currentIndex];
            if (currentItem && currentItem.id === furnitureId) {
                this.updateLightboxFavoriteButton(furnitureId);
            }
        }
    },

    // =========================================
    // LIGHTBOX METHODS
    // =========================================

    /**
     * Open lightbox for a furniture item
     */
    openLightbox(furnitureId) {
        const index = this.state.furniture.findIndex(f => f.id === furnitureId);
        if (index === -1) return;

        this.state.lightbox.isOpen = true;
        this.state.lightbox.currentIndex = index;
        
        this.updateLightboxContent();
        
        this.elements.lightbox?.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus the lightbox for keyboard navigation
        this.elements.lightbox?.focus();
    },

    /**
     * Close lightbox
     */
    closeLightbox() {
        this.state.lightbox.isOpen = false;
        this.state.lightbox.currentIndex = -1;
        
        this.elements.lightbox?.classList.remove('active');
        document.body.style.overflow = '';
    },

    /**
     * Navigate to previous item in lightbox
     */
    lightboxPrev() {
        if (this.state.lightbox.currentIndex > 0) {
            this.state.lightbox.currentIndex--;
            this.updateLightboxContent();
        }
    },

    /**
     * Navigate to next item in lightbox
     */
    lightboxNext() {
        if (this.state.lightbox.currentIndex < this.state.furniture.length - 1) {
            this.state.lightbox.currentIndex++;
            this.updateLightboxContent();
        }
    },

    /**
     * Update lightbox content for current item
     */
    updateLightboxContent() {
        const index = this.state.lightbox.currentIndex;
        const item = this.state.furniture[index];
        
        if (!item) return;

        // Update image
        const imageUrl = item.image_url || '/images/placeholder.svg';
        if (this.elements.lightboxImage) {
            this.elements.lightboxImage.src = imageUrl;
            this.elements.lightboxImage.alt = item.name;
        }

        // Update title
        if (this.elements.lightboxTitle) {
            this.elements.lightboxTitle.textContent = item.name;
        }

        // Update meta
        if (this.elements.lightboxMeta) {
            this.elements.lightboxMeta.textContent = `${item.category_name} • $${this.formatNumber(item.price)}`;
        }

        // Update copy button
        if (this.elements.lightboxCopy) {
            this.elements.lightboxCopy.dataset.name = item.name;
        }

        // Update favorite button
        if (this.elements.lightboxFavorite) {
            this.elements.lightboxFavorite.dataset.id = item.id;
            this.updateLightboxFavoriteButton(item.id);
        }

        // Update edit button link (admin)
        if (this.elements.lightboxEdit) {
            this.elements.lightboxEdit.href = `/admin/?page=furniture&action=edit&id=${item.id}`;
        }

        // Update suggest edit link (user)
        if (this.elements.lightboxSuggestEdit) {
            this.elements.lightboxSuggestEdit.href = `/dashboard/?page=submissions&action=new&furniture_id=${item.id}`;
        }

        // Update navigation buttons
        if (this.elements.lightboxPrev) {
            this.elements.lightboxPrev.disabled = index === 0;
        }
        if (this.elements.lightboxNext) {
            this.elements.lightboxNext.disabled = index === this.state.furniture.length - 1;
        }
    },

    /**
     * Update lightbox favorite button state
     */
    updateLightboxFavoriteButton(furnitureId) {
        if (!this.elements.lightboxFavorite) return;
        
        const isFav = this.state.favorites.has(furnitureId);
        this.elements.lightboxFavorite.classList.toggle('active', isFav);
        this.elements.lightboxFavorite.innerHTML = isFav ? '❤️' : '🤍';
        this.elements.lightboxFavorite.setAttribute('title', isFav ? 'Remove from favorites' : 'Add to favorites');
        this.elements.lightboxFavorite.setAttribute('aria-label', isFav ? 'Remove from favorites' : 'Add to favorites');
    },

    /**
     * Show modal - matching dashboard styling
     */
    showModal(title, content) {
        // Remove existing modal if any
        const existing = document.getElementById('app-modal');
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.id = 'app-modal';
        modal.className = 'modal-overlay active';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h2>${this.escapeHtml(title)}</h2>
                    <button class="modal-close" onclick="App.closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        `;
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal();
            }
        });
        
        document.body.appendChild(modal);
    },

    /**
     * Close modal
     */
    closeModal() {
        const modal = document.getElementById('app-modal');
        if (modal) modal.remove();
        this.state.currentFurnitureForCollection = null;
    },

    /**
     * Open collection modal
     */
    async openCollectionModal(furnitureId) {
        this.state.currentFurnitureForCollection = furnitureId;
        
        // Fetch user's collections
        try {
            const response = await fetch('/dashboard/api.php?action=collections');
            const result = await response.json();
            
            if (!result.success) {
                this.toast(result.error || 'Failed to load collections', 'error');
                return;
            }
            
            const collections = result.data;
            let modalBody;
            
            if (collections.length === 0) {
                modalBody = `
                    <p style="margin-bottom: var(--spacing-md);">You haven't created any collections yet.</p>
                    <a href="/dashboard/?page=collections&action=add" class="btn btn-primary">Create Collection</a>
                `;
            } else {
                // Check which collections contain this item
                const containsResponse = await fetch(`/dashboard/api.php?action=collections/contains&furniture_id=${furnitureId}`);
                const containsResult = await containsResponse.json();
                const containsIds = containsResult.success ? containsResult.data : [];
                
                modalBody = `
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        ${collections.map(col => `
                            <button onclick="App.toggleCollectionItem(${col.id})" 
                                    class="btn ${containsIds.includes(col.id) ? 'btn-primary' : ''}"
                                    style="justify-content: space-between; width: 100%;"
                                    data-collection-id="${col.id}"
                                    data-item-count="${col.item_count}">
                                <span>${this.escapeHtml(col.name)}</span>
                                <span style="opacity: 0.7; font-size: 0.875rem;">${containsIds.includes(col.id) ? '✓ Added' : col.item_count + ' items'}</span>
                            </button>
                        `).join('')}
                    </div>
                    <div style="margin-top: var(--spacing-lg); padding-top: var(--spacing-md); border-top: 1px solid var(--border-color);">
                        <a href="/dashboard/?page=collections&action=add" class="btn btn-sm">+ New Collection</a>
                    </div>
                `;
            }
            
            // Create or update modal
            this.showModal('Add to Collection', modalBody);
        } catch (error) {
            console.error('Load collections error:', error);
            this.toast('Failed to load collections', 'error');
        }
    },

    /**
     * Close collection modal (alias for consistency)
     */
    closeCollectionModal() {
        this.closeModal();
    },

    /**
     * Toggle item in collection
     */
    async toggleCollectionItem(collectionId) {
        const furnitureId = this.state.currentFurnitureForCollection;
        if (!furnitureId) return;
        
        const button = document.querySelector(`button[data-collection-id="${collectionId}"]`);
        const isInCollection = button.classList.contains('btn-primary');
        
        const csrfToken = this.getCsrfToken();
        try {
            const action = isInCollection ? 'collections/remove-item' : 'collections/add-item';
            const response = await fetch(`/dashboard/api.php?action=${action}`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify({
                    collection_id: collectionId,
                    furniture_id: furnitureId,
                    csrf_token: csrfToken
                }),
            });
            
            const result = await response.json();
            
            if (result.success) {
                button.classList.toggle('btn-primary');
                const span = button.querySelector('span:last-child');
                if (span) {
                    // Match dashboard behavior: show item count when removing
                    const itemCount = button.dataset.itemCount || '0';
                    span.textContent = isInCollection ? itemCount + ' items' : '✓ Added';
                }
                this.toast(isInCollection ? 'Removed from collection' : 'Added to collection', 'success');
            } else {
                this.toast(result.error || 'Failed to update collection', 'error');
            }
        } catch (error) {
            console.error('Toggle collection error:', error);
            this.toast('Network error', 'error');
        }
    },

    /**
     * Escape HTML for safe insertion
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Share current furniture item (copy deep link)
     */
    async shareFurniture() {
        const item = this.state.furniture[this.state.lightbox.currentIndex];
        if (!item) return;

        const url = new URL(window.location.origin);
        url.searchParams.set('furniture', item.id);
        
        try {
            await navigator.clipboard.writeText(url.toString());
            this.toast('Link copied to clipboard!', 'success');
        } catch {
            // Fallback
            const input = document.createElement('input');
            input.value = url.toString();
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            this.toast('Link copied to clipboard!', 'success');
        }
    },


    // =========================================
    // RENDER METHODS
    // =========================================

    /**
     * Render the furniture grid
     */
    render() {
        if (!this.elements.grid) return;

        if (this.state.furniture.length === 0) {
            const hasFilters = this.state.filters.search || 
                              this.state.filters.category || 
                              this.state.filters.tags.length > 0;
            
            this.elements.grid.innerHTML = `
                <div class="empty-state">
                    <div class="icon">${hasFilters ? '🔍' : '🪑'}</div>
                    <h3>${hasFilters ? 'No furniture found' : 'Welcome!'}</h3>
                    <p>${hasFilters ? 'Try adjusting your search or filters' : 'Start browsing furniture items'}</p>
                    ${hasFilters ? `
                        <div class="suggestion">
                            <button class="suggestion-btn" onclick="App.clearAllFilters()">Clear all filters</button>
                        </div>
                    ` : ''}
                </div>
            `;
            this.renderPagination();
            return;
        }

        this.elements.grid.innerHTML = this.state.furniture
            .map(item => this.renderCard(item))
            .join('');

        this.renderPagination();
        
        // Check for furniture param in URL (deep link)
        this.handleDeepLink();
    },

    /**
     * Handle deep link to specific furniture
     */
    handleDeepLink() {
        const params = new URLSearchParams(window.location.search);
        const furnitureId = params.get('furniture');
        if (furnitureId) {
            const id = parseInt(furnitureId, 10);
            // Remove from URL to prevent re-opening
            params.delete('furniture');
            const newUrl = params.toString() ? `?${params}` : window.location.pathname;
            window.history.replaceState({}, '', newUrl);
            // Open lightbox
            setTimeout(() => this.openLightbox(id), 100);
        }
    },

    /**
     * Clear all filters
     */
    clearAllFilters() {
        this.state.filters = {
            category: null,
            tags: [],
            search: '',
            sort: 'name',
            order: 'asc',
            favoritesOnly: false
        };
        this.state.pagination.page = 1;
        
        // Reset UI
        if (this.elements.searchInput) this.elements.searchInput.value = '';
        if (this.elements.categorySelect) this.elements.categorySelect.value = '';
        if (this.elements.sortSelect) this.elements.sortSelect.value = 'name-asc';
        if (this.elements.favoritesOnlyBtn) {
            this.elements.favoritesOnlyBtn.classList.remove('active');
            this.elements.favoritesOnlyBtn.setAttribute('aria-pressed', 'false');
        }
        
        this.renderTagFilters();
        this.updateActiveTagsDisplay();
        this.loadFurniture();
        this.updateUrl();
    },

    /**
     * Render a single furniture card
     */
    renderCard(item) {
        const isFav = this.state.favorites.has(item.id);
        const allTags = item.tags || [];
        const imageUrl = item.image_url || '/images/placeholder.svg';

        // Calculate how many tags fit based on character length
        // Available width is ~180px, avg char ~7px + padding ~16px per tag
        const maxChars = 28; // Approximate character budget for tags row
        let charCount = 0;
        let visibleTags = [];
        
        for (const tag of allTags) {
            const tagChars = tag.name.length + 2; // +2 for padding estimate
            if (charCount + tagChars <= maxChars || visibleTags.length === 0) {
                visibleTags.push(tag);
                charCount += tagChars;
            } else {
                break;
            }
        }

        const extraCount = allTags.length - visibleTags.length;
        const tagsHtml = visibleTags.map(tag => `
            <span class="tag" style="--tag-color: ${tag.color}">
                ${this.escapeHtml(tag.name)}
            </span>
        `).join('') + (extraCount > 0 ? `<span class="tag-more">+${extraCount}</span>` : '');

        return `
            <article class="furniture-card" data-id="${item.id}" tabindex="0">
                <div class="card-image">
                    <img 
                        src="${this.escapeHtml(imageUrl)}" 
                        alt="${this.escapeHtml(item.name)}"
                        loading="lazy"
                        onerror="this.src='/images/placeholder.svg'"
                    >
                </div>
                <div class="card-body">
                    <h3 title="${this.escapeHtml(item.name)}">${this.escapeHtml(item.name)}</h3>
                    <p class="meta">
                        <span class="category">${this.escapeHtml(item.category_name)}</span>
                        <span class="separator">•</span>
                        <span class="price">$${this.formatNumber(item.price)}</span>
                    </p>
                    <div class="tags">${tagsHtml}</div>
                    <div class="actions">
                        <button 
                            class="btn-copy" 
                            data-name="${this.escapeHtml(item.name)}"
                            title="Copy /sf command"
                        >
                            <span class="btn-icon">📋</span>
                            <span class="btn-text">Copy</span>
                        </button>
                        <button 
                            class="btn-favorite ${isFav ? 'active' : ''}" 
                            data-id="${item.id}"
                            title="${isFav ? 'Remove from favorites' : 'Add to favorites'}"
                            aria-label="${isFav ? 'Remove from favorites' : 'Add to favorites'}"
                        >
                            ${isFav ? '❤️' : '🤍'}
                        </button>
                    </div>
                </div>
            </article>
        `;
    },

    /**
     * Render category filter dropdown
     */
    renderCategoryFilter() {
        if (!this.elements.categorySelect) return;

        const options = this.state.categories.map(cat =>
            `<option value="${cat.slug}">${cat.icon} ${this.escapeHtml(cat.name)} (${cat.item_count})</option>`
        );

        this.elements.categorySelect.innerHTML = `
            <option value="">All Categories</option>
            ${options.join('')}
        `;

        // Restore selected value if any
        if (this.state.filters.category) {
            this.elements.categorySelect.value = this.state.filters.category;
        }
    },

    /**
     * Render grouped tag filter UI as dropdowns
     */
    renderTagFilters() {
        const container = this.elements.tagFiltersContainer;
        if (!container) return;

        const groupedData = this.state.tagGroups;
        if (!groupedData || (!groupedData.groups?.length && !groupedData.ungrouped?.length)) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Render each group as a dropdown
        if (groupedData.groups) {
            groupedData.groups.forEach(group => {
                if (!group.tags || group.tags.length === 0) return;
                html += this.renderTagGroupDropdown(group);
            });
        }

        // Render ungrouped tags if any
        if (groupedData.ungrouped && groupedData.ungrouped.length > 0) {
            html += this.renderTagGroupDropdown({
                slug: 'ungrouped',
                name: 'Other',
                color: '#6b7280',
                tags: groupedData.ungrouped
            });
        }

        container.innerHTML = html;
        
        // Bind dropdown events
        this.bindTagDropdownEvents();

        // Update active tags display and clear button
        this.updateActiveTagsDisplay();
    },

    /**
     * Render a single tag group dropdown
     */
    renderTagGroupDropdown(group) {
        const selectedInGroup = group.tags.filter(t => 
            this.state.filters.tags.includes(t.slug)
        ).length;
        
        const tagCheckboxes = group.tags.map(tag => {
            const isChecked = this.state.filters.tags.includes(tag.slug);
            return `
                <label class="tag-checkbox-item" style="--tag-color: ${tag.color}">
                    <input type="checkbox" data-slug="${tag.slug}" ${isChecked ? 'checked' : ''}>
                    <span class="tag-color-dot"></span>
                    <span class="tag-name">${this.escapeHtml(tag.name)}</span>
                </label>
            `;
        }).join('');

        return `
            <div class="tag-group-dropdown" data-group="${group.slug}">
                <div class="tag-group-trigger">
                    <span class="group-color" style="background: ${group.color}"></span>
                    <span class="group-name">${this.escapeHtml(group.name)}</span>
                    ${selectedInGroup > 0 ? `<span class="group-count">${selectedInGroup}</span>` : ''}
                    <span class="group-arrow">▼</span>
                </div>
                <div class="tag-group-panel">
                    <div class="tag-group-panel-header">
                        <span class="tag-group-panel-title">${this.escapeHtml(group.name)}</span>
                        <button class="tag-group-panel-clear" data-group="${group.slug}">Clear</button>
                    </div>
                    <div class="tag-group-tags">
                        ${tagCheckboxes}
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Bind event handlers for tag dropdowns
     */
    bindTagDropdownEvents() {
        const container = this.elements.tagFiltersContainer;
        if (!container) return;

        // Toggle dropdown on trigger click
        container.querySelectorAll('.tag-group-trigger').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = trigger.closest('.tag-group-dropdown');
                const wasOpen = dropdown.classList.contains('open');
                
                // Close all other dropdowns
                container.querySelectorAll('.tag-group-dropdown.open').forEach(d => {
                    d.classList.remove('open');
                });
                
                // Toggle this one
                if (!wasOpen) {
                    dropdown.classList.add('open');
                }
            });
        });

        // Handle checkbox changes
        container.querySelectorAll('.tag-checkbox-item input').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                this.toggleTagFilter(checkbox.dataset.slug);
            });
        });

        // Handle clear buttons
        container.querySelectorAll('.tag-group-panel-clear').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.clearGroupTags(btn.dataset.group);
            });
        });

        // Close dropdowns when clicking outside (only bind once)
        if (!this._tagDropdownClickBound) {
            this._tagDropdownClickBound = true;
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.tag-group-dropdown')) {
                    document.querySelectorAll('.tag-group-dropdown.open').forEach(d => {
                        d.classList.remove('open');
                    });
                }
            });
        }
    },

    /**
     * Clear all tags in a specific group
     */
    clearGroupTags(groupSlug) {
        const groupedData = this.state.tagGroups;
        if (!groupedData) return;

        let groupTags = [];
        if (groupSlug === 'ungrouped') {
            groupTags = groupedData.ungrouped || [];
        } else {
            const group = groupedData.groups?.find(g => g.slug === groupSlug);
            groupTags = group?.tags || [];
        }

        // Remove all tags from this group from the filter
        groupTags.forEach(tag => {
            const index = this.state.filters.tags.indexOf(tag.slug);
            if (index !== -1) {
                this.state.filters.tags.splice(index, 1);
            }
        });

        this.state.pagination.page = 1;
        this.renderTagFilters();
        this.updateActiveTagsDisplay();
        this.loadFurniture();
        this.updateUrl();
    },

    /**
     * Toggle a single tag filter
     */
    toggleTagFilter(slug) {
        const index = this.state.filters.tags.indexOf(slug);
        
        if (index === -1) {
            this.state.filters.tags.push(slug);
        } else {
            this.state.filters.tags.splice(index, 1);
        }
        
        // Update checkbox state in dropdown
        const checkbox = document.querySelector(`.tag-checkbox-item input[data-slug="${slug}"]`);
        if (checkbox) {
            checkbox.checked = index === -1;
        }
        
        // Update group count badges
        this.updateGroupCounts();
        
        // Update active tags display
        this.updateActiveTagsDisplay();
        
        this.state.pagination.page = 1;
        this.loadFurniture();
        this.updateUrl();
    },

    /**
     * Update the selected count badges on dropdown triggers
     */
    updateGroupCounts() {
        const groupedData = this.state.tagGroups;
        if (!groupedData) return;

        const updateGroupBadge = (groupSlug, tags) => {
            const selectedCount = tags?.filter(t => 
                this.state.filters.tags.includes(t.slug)
            ).length || 0;
            
            const dropdown = document.querySelector(`.tag-group-dropdown[data-group="${groupSlug}"]`);
            if (!dropdown) return;
            
            const trigger = dropdown.querySelector('.tag-group-trigger');
            let countEl = trigger.querySelector('.group-count');
            
            if (selectedCount > 0) {
                if (!countEl) {
                    // Create count badge if doesn't exist
                    countEl = document.createElement('span');
                    countEl.className = 'group-count';
                    trigger.insertBefore(countEl, trigger.querySelector('.group-arrow'));
                }
                countEl.textContent = selectedCount;
            } else if (countEl) {
                countEl.remove();
            }
        };

        if (groupedData.groups) {
            groupedData.groups.forEach(group => {
                updateGroupBadge(group.slug, group.tags);
            });
        }

        if (groupedData.ungrouped) {
            updateGroupBadge('ungrouped', groupedData.ungrouped);
        }
    },

    /**
     * Update the active tags bar display
     */
    updateActiveTagsDisplay() {
        const activeTags = this.elements.activeTags;
        const activeTagsList = this.elements.activeTagsList;
        const clearBtn = this.elements.clearFiltersBtn;
        
        const hasFilters = this.state.filters.tags.length > 0 || 
                          this.state.filters.category || 
                          this.state.filters.search;
        
        // Show/hide clear button
        if (clearBtn) {
            clearBtn.style.display = hasFilters ? 'block' : 'none';
        }
        
        // Show/hide active tags bar
        if (!activeTags || !activeTagsList) return;
        
        if (this.state.filters.tags.length === 0) {
            activeTags.style.display = 'none';
            return;
        }
        
        activeTags.style.display = 'flex';
        
        // Build active tag pills
        const pills = this.state.filters.tags.map(slug => {
            const tag = this.state.tags.find(t => t.slug === slug);
            if (!tag) return '';
            
            return `
                <span class="active-tag" style="--tag-color: ${tag.color}">
                    ${this.escapeHtml(tag.name)}
                    <span class="remove-tag" onclick="App.toggleTagFilter('${slug}')" title="Remove">×</span>
                </span>
            `;
        }).join('');
        
        activeTagsList.innerHTML = pills;
    },

    /**
     * Clear all tag filters
     */
    clearTagFilters() {
        this.state.filters.tags = [];
        this.state.pagination.page = 1;
        this.renderTagFilters();
        this.updateActiveTagsDisplay();
        this.loadFurniture();
        this.updateUrl();
    },

    /**
     * Render pagination controls
     */
    renderPagination() {
        if (!this.elements.pagination) return;

        const { page, total_pages, total } = this.state.pagination;

        if (total_pages <= 1) {
            this.elements.pagination.innerHTML = total > 0 
                ? `<span class="page-info">${total} item${total === 1 ? '' : 's'}</span>`
                : '';
            return;
        }

        this.elements.pagination.innerHTML = `
            <button 
                ${page <= 1 ? 'disabled' : ''} 
                onclick="App.goToPage(${page - 1})"
                aria-label="Previous page"
            >
                ← Previous
            </button>
            <span class="page-info">Page ${page} of ${total_pages} (${total} items)</span>
            <button 
                ${page >= total_pages ? 'disabled' : ''} 
                onclick="App.goToPage(${page + 1})"
                aria-label="Next page"
            >
                Next →
            </button>
        `;
    },

    /**
     * Go to specific page
     */
    goToPage(page) {
        this.state.pagination.page = page;
        this.loadFurniture();
        this.updateUrl();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    // =========================================
    // URL STATE MANAGEMENT
    // =========================================

    /**
     * Parse URL parameters on page load
     */
    parseUrlParams() {
        const params = new URLSearchParams(window.location.search);

        // Category
        if (params.has('category')) {
            this.state.filters.category = params.get('category');
        } else {
            this.state.filters.category = null;
        }

        // Search
        if (params.has('search')) {
            this.state.filters.search = params.get('search');
        } else {
            this.state.filters.search = '';
        }

        // Tags
        if (params.has('tags')) {
            this.state.filters.tags = params.get('tags').split(',').filter(t => t);
        } else {
            this.state.filters.tags = [];
        }

        // Page
        if (params.has('page')) {
            this.state.pagination.page = Math.max(1, parseInt(params.get('page'), 10) || 1);
        } else {
            this.state.pagination.page = 1;
        }

        // Sort
        if (params.has('sort')) {
            const sortValue = params.get('sort');
            const [sort, order] = sortValue.split('-');
            this.state.filters.sort = sort || 'name';
            this.state.filters.order = order || 'asc';
        }

        // Favorites only
        if (params.has('favorites')) {
            this.state.filters.favoritesOnly = params.get('favorites') === '1';
        } else {
            this.state.filters.favoritesOnly = false;
        }
    },

    /**
     * Sync UI elements to current filter state
     */
    syncFiltersToUI() {
        // Category select
        if (this.elements.categorySelect) {
            this.elements.categorySelect.value = this.state.filters.category || '';
        }

        // Search input
        if (this.elements.searchInput) {
            this.elements.searchInput.value = this.state.filters.search || '';
        }

        // Sort select
        if (this.elements.sortSelect) {
            this.elements.sortSelect.value = `${this.state.filters.sort}-${this.state.filters.order}`;
        }

        // Favorites only button
        if (this.elements.favoritesOnlyBtn) {
            this.elements.favoritesOnlyBtn.classList.toggle('active', this.state.filters.favoritesOnly);
            this.elements.favoritesOnlyBtn.setAttribute('aria-pressed', this.state.filters.favoritesOnly);
        }

        // Tag filters
        this.renderTagFilters();
        this.updateActiveTagsDisplay();
    },

    /**
     * Update URL with current filters (without reload)
     */
    updateUrl() {
        const params = new URLSearchParams();

        if (this.state.filters.category) {
            params.set('category', this.state.filters.category);
        }

        if (this.state.filters.search) {
            params.set('search', this.state.filters.search);
        }

        if (this.state.filters.tags.length > 0) {
            params.set('tags', this.state.filters.tags.join(','));
        }

        if (this.state.pagination.page > 1) {
            params.set('page', this.state.pagination.page.toString());
        }

        // Only include sort if not default
        if (this.state.filters.sort !== 'name' || this.state.filters.order !== 'asc') {
            params.set('sort', `${this.state.filters.sort}-${this.state.filters.order}`);
        }

        // Favorites only
        if (this.state.filters.favoritesOnly) {
            params.set('favorites', '1');
        }

        const url = params.toString() ? `?${params.toString()}` : window.location.pathname;
        window.history.pushState({}, '', url);
    },

    // =========================================
    // UTILITY METHODS
    // =========================================

    /**
     * Show loading overlay
     */
    setLoading(loading) {
        this.state.loading = loading;
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.classList.toggle('active', loading);
            this.elements.loadingOverlay.setAttribute('aria-hidden', String(!loading));
        }
    },

    /**
     * Show toast notification with icon
     */
    toast(message, type = 'info') {
        const container = this.elements.toastContainer;
        if (!container) return;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${this.escapeHtml(message)}</span>
        `;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);

        // Remove after delay
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return num.toLocaleString();
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => App.init());
} else {
    App.init();
}
