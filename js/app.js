/**
 * GTAW Furniture Catalog - Frontend Application
 * Vanilla JavaScript, no dependencies
 */

const App = {
    // Application state
    state: {
        furniture: [],
        categories: [],
        tags: [],
        favorites: new Set(),
        filters: {
            category: null,
            tags: [],
            search: '',
            sort: 'name',
            order: 'asc'
        },
        pagination: {
            page: 1,
            perPage: 24,
            total: 0,
            totalPages: 0
        },
        user: null,
        loading: false
    },

    // DOM element cache
    elements: {},

    /**
     * Initialize the application
     */
    async init() {
        this.cacheElements();
        this.bindEvents();

        // Load initial data in parallel
        await Promise.all([
            this.loadCategories(),
            this.loadTags(),
            this.checkAuth()
        ]);

        // Parse URL parameters and load furniture
        this.parseUrlParams();
        await this.loadFurniture();
    },

    /**
     * Cache frequently accessed DOM elements
     */
    cacheElements() {
        this.elements = {
            grid: document.getElementById('furniture-grid'),
            searchInput: document.getElementById('search-input'),
            categorySelect: document.getElementById('category-filter'),
            sortSelect: document.getElementById('sort-filter'),
            tagContainer: document.getElementById('active-tags'),
            pagination: document.getElementById('pagination'),
            loginBtn: document.getElementById('login-btn'),
            userInfo: document.getElementById('user-info'),
            loadingOverlay: document.getElementById('loading'),
            toastContainer: document.getElementById('toast-container')
        };
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Search with debounce
        let searchTimeout;
        this.elements.searchInput?.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.state.pagination.page = 1;
                this.loadFurniture();
                this.updateUrl();
            }, 300);
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
            this.loadFurniture();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Press '/' to focus search
            if (e.key === '/' && !this.isInputFocused()) {
                e.preventDefault();
                this.elements.searchInput?.focus();
            }

            // Press Escape to blur search
            if (e.key === 'Escape' && document.activeElement === this.elements.searchInput) {
                this.elements.searchInput.blur();
            }
        });

        // Delegate click events on grid
        this.elements.grid?.addEventListener('click', (e) => {
            const copyBtn = e.target.closest('.btn-copy');
            const favBtn = e.target.closest('.btn-favorite');

            if (copyBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.copyCommand(copyBtn.dataset.name);
            } else if (favBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.toggleFavorite(parseInt(favBtn.dataset.id, 10));
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

        const fetchOptions = {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: {}
        };

        if (options.body) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(options.body);
        }

        const response = await fetch(url, fetchOptions);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'API request failed');
        }

        return data;
    },

    /**
     * Load categories for filter dropdown
     */
    async loadCategories() {
        try {
            const { data } = await this.api('categories');
            this.state.categories = data;
            this.renderCategoryFilter();
        } catch (error) {
            console.error('Failed to load categories:', error);
        }
    },

    /**
     * Load tags for filter
     */
    async loadTags() {
        try {
            const { data } = await this.api('tags');
            this.state.tags = data;
        } catch (error) {
            console.error('Failed to load tags:', error);
        }
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

        try {
            const action = this.state.filters.search ? 'furniture/search' : 'furniture';
            const params = {
                page: this.state.pagination.page,
                per_page: this.state.pagination.perPage,
                category: this.state.filters.category,
                tags: this.state.filters.tags.join(','),
                sort: this.state.filters.sort,
                order: this.state.filters.order
            };

            if (this.state.filters.search) {
                params.q = this.state.filters.search;
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
        const btn = this.elements.grid?.querySelector(`.btn-favorite[data-id="${furnitureId}"]`);
        if (btn) {
            const isFav = this.state.favorites.has(furnitureId);
            btn.classList.toggle('active', isFav);
            btn.innerHTML = isFav ? '❤️' : '🤍';
            btn.setAttribute('title', isFav ? 'Remove from favorites' : 'Add to favorites');
        }
    },

    /**
     * Render the furniture grid
     */
    render() {
        if (!this.elements.grid) return;

        if (this.state.furniture.length === 0) {
            this.elements.grid.innerHTML = `
                <div class="empty-state">
                    <div class="icon">🔍</div>
                    <h3>No furniture found</h3>
                    <p>Try adjusting your search or filters</p>
                </div>
            `;
            this.renderPagination();
            return;
        }

        this.elements.grid.innerHTML = this.state.furniture
            .map(item => this.renderCard(item))
            .join('');

        this.renderPagination();
    },

    /**
     * Render a single furniture card
     */
    renderCard(item) {
        const isFav = this.state.favorites.has(item.id);
        const tags = (item.tags || []).slice(0, 3);
        const imageUrl = item.image_url || '/images/placeholder.svg';

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
                    <div class="tags">
                        ${tags.map(tag => `
                            <span class="tag" style="--tag-color: ${tag.color}">
                                ${this.escapeHtml(tag.name)}
                            </span>
                        `).join('')}
                    </div>
                    <div class="actions">
                        <button 
                            class="btn-copy" 
                            data-name="${this.escapeHtml(item.name)}"
                            title="Copy /sf command"
                        >
                            📋
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
     * Render pagination controls
     */
    renderPagination() {
        if (!this.elements.pagination) return;

        const { page, totalPages, total } = this.state.pagination;

        if (totalPages <= 1) {
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
            <span class="page-info">Page ${page} of ${totalPages} (${total} items)</span>
            <button 
                ${page >= totalPages ? 'disabled' : ''} 
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
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

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
     * Show toast notification
     */
    toast(message, type = 'info') {
        const container = this.elements.toastContainer;
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);

        // Remove after delay
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    /**
     * Parse URL parameters on page load
     */
    parseUrlParams() {
        const params = new URLSearchParams(window.location.search);

        if (params.has('category')) {
            this.state.filters.category = params.get('category');
            if (this.elements.categorySelect) {
                this.elements.categorySelect.value = params.get('category');
            }
        }

        if (params.has('search')) {
            this.state.filters.search = params.get('search');
            if (this.elements.searchInput) {
                this.elements.searchInput.value = params.get('search');
            }
        }

        if (params.has('page')) {
            this.state.pagination.page = Math.max(1, parseInt(params.get('page'), 10) || 1);
        }
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

        const url = params.toString() ? `?${params.toString()}` : window.location.pathname;
        window.history.replaceState({}, '', url);
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

