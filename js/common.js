/**
 * GTAW Furniture Catalog - Shared Frontend Utilities
 *
 * Exposes a unified set of helpers on the global GTAW namespace so that
 * app.js, dashboard.js, admin.js, and collection.php can share common
 * functionality without duplicating implementations.
 * 
 * Available methods:
 * - getCsrfToken()         - Get CSRF token from meta tag or hidden input
 * - escapeHtml(text)       - Escape HTML to prevent XSS
 * - toast(msg, type)       - Show toast notification
 * - showModal(...)         - Show modal dialog
 * - closeModal(id)         - Close modal dialog
 * - copyToClipboard(text)  - Copy text with fallback for older browsers
 * - copyCommand(name)      - Copy /sf command and show toast
 * - toggleTheme(toast)     - Toggle dark/light theme
 * - debounce(fn, delay)    - Debounce function calls
 * 
 * Available modules:
 * - tableSearch.init()               - Client-side table filtering
 * - duplicateDetection.init(options) - Furniture duplicate detection
 * - imagePreview.init(options)       - Live image URL preview
 * - collectionPicker.open(id)        - Add to collection modal
 */

window.GTAW = (function () {
    // =========================================
    // CONSTANTS
    // =========================================
    
    /**
     * Default debounce delay in milliseconds
     * Used for search input, form validation, and other user-triggered events
     */
    const DEFAULT_DEBOUNCE_DELAY = 300;
    
    /**
     * Toast notification display duration in milliseconds
     */
    const TOAST_DISPLAY_DURATION = 3000;
    
    /**
     * Toast animation duration in milliseconds
     */
    const TOAST_ANIMATION_DURATION = 300;
    
    // =========================================
    // CSRF & SECURITY
    // =========================================
    
    /**
     * Get CSRF token from meta tag or hidden input
     * @returns {string|null} CSRF token or null if not found
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : null;
    }

    /**
     * Escape HTML to prevent XSS attacks
     * @param {*} text - Text to escape
     * @returns {string} Escaped HTML string
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =========================================
    // TOAST NOTIFICATIONS
    // =========================================

    /**
     * Get or create toast container
     * @returns {HTMLElement} Toast container element
     */
    function ensureToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Show toast notification
     * @param {string} message - Message to display
     * @param {string} type - Type: 'success', 'error', 'warning', 'info'
     */
    function toast(message, type = 'info') {
        const container = ensureToastContainer();
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${escapeHtml(message)}</span>
        `;

        container.appendChild(el);

        setTimeout(() => {
            el.classList.add('hiding');
            setTimeout(() => el.remove(), TOAST_ANIMATION_DURATION);
        }, TOAST_DISPLAY_DURATION);
    }

    // =========================================
    // MODAL DIALOGS
    // =========================================

    /**
     * Show modal dialog
     * @param {string} id - Modal ID (optional, defaults to 'gtaw-modal')
     * @param {string} title - Modal title
     * @param {string} content - Modal body HTML content
     * @param {function} onCloseCallback - Optional callback when modal closes
     */
    function showModal(id, title, content, onCloseCallback) {
        const modalId = id || 'gtaw-modal';
        const existing = document.getElementById(modalId);
        if (existing) {
            existing.remove();
        }

        const overlay = document.createElement('div');
        overlay.id = modalId;
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h2>${escapeHtml(title)}</h2>
                    <button class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        `;

        const close = () => {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 150);
            if (typeof onCloseCallback === 'function') {
                onCloseCallback();
            }
        };

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                close();
            }
        });
        overlay.querySelector('.modal-close')?.addEventListener('click', close);

        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                close();
                document.removeEventListener('keydown', escHandler);
            }
        });

        document.body.appendChild(overlay);
    }

    /**
     * Close modal dialog
     * @param {string} id - Modal ID (optional, defaults to 'gtaw-modal')
     */
    function closeModal(id) {
        const modalId = id || 'gtaw-modal';
        const overlay = document.getElementById(modalId);
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 150);
        }
    }

    // =========================================
    // CLIPBOARD UTILITIES
    // =========================================

    /**
     * Copy text to clipboard with fallback for older browsers
     * @param {string} text - Text to copy
     * @returns {Promise<boolean>} Promise resolving to success status
     */
    function copyToClipboard(text) {
        // Modern Clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text)
                .then(() => true)
                .catch(() => fallbackCopy(text));
        }
        // Fallback for older browsers
        return Promise.resolve(fallbackCopy(text));
    }

    /**
     * Fallback copy method using execCommand
     * @param {string} text - Text to copy
     * @returns {boolean} Success status
     */
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0';
        textarea.setAttribute('readonly', ''); // Prevent mobile keyboard
        document.body.appendChild(textarea);
        
        let success = false;
        try {
            textarea.select();
            textarea.setSelectionRange(0, text.length); // For mobile
            success = document.execCommand('copy');
        } catch (err) {
            console.error('Fallback copy failed:', err);
        }
        
        document.body.removeChild(textarea);
        return success;
    }

    /**
     * Copy /sf furniture command and show toast notification
     * @param {string} name - Furniture name
     */
    function copyCommand(name) {
        const command = `/sf ${name}`;
        copyToClipboard(command).then((success) => {
            if (success) {
                toast(`Copied: ${command}`, 'success');
            } else {
                toast('Failed to copy command', 'error');
            }
        });
    }

    // =========================================
    // THEME MANAGEMENT
    // =========================================

    /**
     * Toggle between light and dark themes
     * @param {boolean} showToast - Whether to show toast notification (default: true)
     */
    function toggleTheme(showToastNotification = true) {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme') || 'dark';
        const next = current === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', next);
        localStorage.setItem('gtaw_theme', next);
        
        if (showToastNotification) {
            toast(`Switched to ${next} mode`, 'info');
        }
    }

    // =========================================
    // UTILITY FUNCTIONS
    // =========================================

    /**
     * Debounce function calls
     * @param {function} fn - Function to debounce
     * @param {number} delay - Delay in milliseconds (default: DEFAULT_DEBOUNCE_DELAY)
     * @returns {function} Debounced function
     */
    function debounce(fn, delay = DEFAULT_DEBOUNCE_DELAY) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => {
                fn.apply(this, args);
            }, delay);
        };
    }

    // =========================================
    // TABLE SEARCH MODULE
    // =========================================

    /**
     * Table Search Module
     * Provides client-side filtering for data tables.
     * Usage: Add class "table-search-input" to an input and data-table="tableId" attribute.
     */
    const tableSearch = {
        /**
         * Initialize search inputs for tables
         */
        init() {
            document.querySelectorAll('.table-search-input').forEach(input => {
                const tableId = input.dataset.table;
                const table = document.getElementById(tableId);
                
                if (!table) return;
                
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                
                const noResultsRow = this.createNoResultsRow(table);
                
                // Debounced filter function
                const filterRows = debounce(() => {
                    const query = input.value.toLowerCase().trim();
                    let visibleCount = 0;
                    
                    tbody.querySelectorAll('tr:not(.no-results-row)').forEach(row => {
                        const text = row.textContent.toLowerCase();
                        const matches = query === '' || text.includes(query);
                        row.style.display = matches ? '' : 'none';
                        if (matches) visibleCount++;
                    });
                    
                    // Show/hide no results message
                    noResultsRow.style.display = visibleCount === 0 && query !== '' ? '' : 'none';
                }, 150);
                
                input.addEventListener('input', filterRows);
                input.addEventListener('search', filterRows);
            });
        },
        
        /**
         * Create a "no results" row for empty search results
         * @param {HTMLTableElement} table - The table element
         * @returns {HTMLTableRowElement} The created row element
         */
        createNoResultsRow(table) {
            const tbody = table.querySelector('tbody');
            const colCount = table.querySelectorAll('thead th').length || 6;
            
            const row = document.createElement('tr');
            row.className = 'no-results-row';
            row.style.display = 'none';
            row.innerHTML = `
                <td colspan="${colCount}" style="text-align: center; padding: var(--spacing-lg); color: var(--text-muted);">
                    No items match your search
                </td>
            `;
            tbody.appendChild(row);
            
            return row;
        }
    };

    // =========================================
    // DUPLICATE DETECTION MODULE
    // =========================================

    /**
     * Duplicate Detection Module
     * Shows potential duplicate items when adding/editing furniture.
     * Usage: Requires #duplicate-panel, #name input, and optionally category checkboxes.
     * 
     * @param {Object} options - Configuration options
     * @param {string} options.editLinkPrefix - URL prefix for edit links (default: '/admin/?page=furniture&action=edit&id=')
     * @param {string} options.editLinkText - Text for edit button (default: 'Edit')
     * @param {string} options.hintText - Hint text shown in panel (default: 'Consider editing the existing item instead of creating a duplicate.')
     */
    const duplicateDetection = {
        panel: null,
        nameInput: null,
        categorySelect: null,
        excludeId: null,
        debouncedCheck: null,
        options: {
            editLinkPrefix: '/admin/?page=furniture&action=edit&id=',
            editLinkText: 'Edit',
            hintText: 'Consider editing the existing item instead of creating a duplicate.'
        },
        
        /**
         * Initialize duplicate detection for a form
         * @param {Object} customOptions - Optional custom configuration
         */
        init(customOptions = {}) {
            this.panel = document.getElementById('duplicate-panel');
            this.nameInput = document.getElementById('name');
            // Support both old single select and new checkbox array
            this.categorySelect = document.getElementById('category_id');
            this.categoryCheckboxes = document.querySelectorAll('input[name="category_ids[]"]');
            
            if (!this.panel || !this.nameInput) return;
            
            // Merge custom options
            this.options = { ...this.options, ...customOptions };
            
            // Get exclude ID for edit forms (from data attribute)
            this.excludeId = this.panel.dataset.excludeId || null;
            
            // Create debounced check function
            this.debouncedCheck = debounce(() => this.check(), 400);
            
            // Bind events
            this.nameInput.addEventListener('input', () => this.debouncedCheck());
            this.nameInput.addEventListener('blur', () => this.check());
            
            // Listen for category changes (single select or checkboxes)
            if (this.categorySelect) {
                this.categorySelect.addEventListener('change', () => this.check());
            }
            this.categoryCheckboxes?.forEach(cb => {
                cb.addEventListener('change', () => this.check());
            });
            
            // Initial check if name has value (for edit forms)
            if (this.nameInput.value.trim().length >= 3) {
                this.check();
            }
        },
        
        /**
         * Check for potential duplicates via API
         */
        async check() {
            const name = this.nameInput.value.trim();
            
            // Require minimum 3 characters for meaningful matching
            if (name.length < 3) {
                this.hidePanel();
                return;
            }
            
            const params = new URLSearchParams({ name });
            
            // Get category ID - support both single select and checkbox array
            if (this.categorySelect?.value) {
                params.set('category_id', this.categorySelect.value);
            } else if (this.categoryCheckboxes?.length > 0) {
                const firstChecked = Array.from(this.categoryCheckboxes).find(cb => cb.checked);
                if (firstChecked) {
                    params.set('category_id', firstChecked.value);
                }
            }
            
            if (this.excludeId) {
                params.set('exclude_id', this.excludeId);
            }
            
            try {
                const response = await fetch(`/api.php?action=furniture/check-duplicates&${params}`);
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    this.showPanel(result.data);
                } else {
                    this.hidePanel();
                }
            } catch (error) {
                console.error('Duplicate check failed:', error);
                this.hidePanel();
            }
        },
        
        /**
         * Show panel with potential duplicates
         * @param {Array} matches - Array of matching furniture items
         */
        showPanel(matches) {
            if (!this.panel) return;
            
            const items = matches.map(m => `
                <div class="item-card-mini duplicate-item">
                    <img src="${m.image_url || '/images/placeholder.svg'}" 
                         alt="" 
                         onerror="this.src='/images/placeholder.svg'">
                    <div class="item-card-mini-info">
                        <div class="item-card-mini-name" title="${escapeHtml(m.name)}">
                            ${escapeHtml(m.name)}
                        </div>
                        <div class="item-card-mini-meta">
                            ${escapeHtml(m.categories?.[0]?.name || m.category_name || '')} • $${Number(m.price).toLocaleString()}
                        </div>
                    </div>
                    <div class="item-card-mini-actions">
                        <a href="/?furniture=${m.id}" target="_blank" class="btn btn-sm">View</a>
                        <a href="${this.options.editLinkPrefix}${m.id}" 
                           class="btn btn-sm btn-secondary">${escapeHtml(this.options.editLinkText)}</a>
                    </div>
                </div>
            `).join('');
            
            this.panel.innerHTML = `
                <div class="duplicate-panel-header">
                    <span>⚠️</span>
                    <span>Possible Duplicates Found</span>
                </div>
                <p class="duplicate-panel-hint">
                    Found ${matches.length} similar item${matches.length > 1 ? 's' : ''}. 
                    ${escapeHtml(this.options.hintText)}
                </p>
                ${items}
            `;
            
            this.panel.classList.remove('hidden');
        },
        
        /**
         * Hide the duplicate panel
         */
        hidePanel() {
            if (this.panel) {
                this.panel.classList.add('hidden');
            }
        },
        
        /**
         * Reset the module state (for re-initialization)
         */
        reset() {
            this.panel = null;
            this.nameInput = null;
            this.categorySelect = null;
            this.categoryCheckboxes = null;
            this.excludeId = null;
            this.debouncedCheck = null;
        }
    };

    // =========================================
    // IMAGE PREVIEW MODULE
    // =========================================

    /**
     * Image Preview Module
     * Live preview for image URL inputs with debouncing.
     * Usage: Call GTAW.imagePreview.init() after DOM ready.
     * 
     * Requires:
     * - Input with id="image_url"
     * - Img element with id="preview-img"
     */
    const imagePreview = {
        input: null,
        previewImg: null,
        debounceTimer: null,
        
        /**
         * Initialize image preview for URL inputs
         * @param {Object} options - Optional configuration
         * @param {string} options.inputId - ID of the URL input (default: 'image_url')
         * @param {string} options.previewId - ID of the preview img (default: 'preview-img')
         * @param {string} options.placeholder - Placeholder image path (default: '/images/placeholder.svg')
         * @param {number} options.debounceMs - Debounce delay in ms (default: DEFAULT_DEBOUNCE_DELAY)
         */
        init(options = {}) {
            const inputId = options.inputId || 'image_url';
            const previewId = options.previewId || 'preview-img';
            this.placeholder = options.placeholder || '/images/placeholder.svg';
            this.debounceMs = options.debounceMs || DEFAULT_DEBOUNCE_DELAY;
            
            this.input = document.getElementById(inputId);
            this.previewImg = document.getElementById(previewId);
            
            if (!this.input || !this.previewImg) return;
            
            // Bind input event with debouncing
            this.input.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.updatePreview(e.target.value.trim());
                }, this.debounceMs);
            });
            
            this.previewImg.addEventListener('error', () => {
                this.previewImg.src = this.placeholder;
            });
        },
        
        /**
         * Update preview image
         * @param {string} url - Image URL
         */
        updatePreview(url) {
            if (!this.previewImg) return;
            
            if (!url) {
                this.previewImg.src = this.placeholder;
                return;
            }
            
            if (url.startsWith('/') || url.startsWith('http://') || url.startsWith('https://')) {
                this.previewImg.src = url;
            } else {
                this.previewImg.src = this.placeholder;
            }
        },
        
        /**
         * Reset the module state
         */
        reset() {
            this.input = null;
            this.previewImg = null;
            clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        }
    };

    // =========================================
    // COLLECTION PICKER MODULE
    // =========================================

    /**
     * Collection Picker Module
     * Modal for adding furniture items to user collections.
     * Shared between app.js and dashboard.js.
     * 
     * Usage: GTAW.collectionPicker.open(furnitureId)
     */
    const collectionPicker = {
        currentFurnitureId: null,
        modalId: 'collection-picker-modal',
        
        /**
         * Open collection picker modal for a furniture item
         * @param {number} furnitureId - ID of the furniture item
         */
        async open(furnitureId) {
            this.currentFurnitureId = furnitureId;
            
            try {
                const response = await fetch('/dashboard/api.php?action=collections');
                const result = await response.json();
                
                if (!result.success) {
                    toast(result.error || 'Failed to load collections', 'error');
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
                    const containsResponse = await fetch(`/dashboard/api.php?action=collections/contains&furniture_id=${furnitureId}`);
                    const containsResult = await containsResponse.json();
                    const containsIds = containsResult.success ? containsResult.data : [];
                    
                    modalBody = `
                        <div class="collection-picker-list">
                            ${collections.map(col => `
                                <button onclick="GTAW.collectionPicker.toggle(${col.id})" 
                                        class="btn collection-picker-btn ${containsIds.includes(col.id) ? 'btn-primary' : ''}"
                                        data-collection-id="${col.id}"
                                        data-item-count="${col.item_count}">
                                    <span class="collection-picker-name">${escapeHtml(col.name)}</span>
                                    <span class="collection-picker-status">${containsIds.includes(col.id) ? '✓ Added' : col.item_count + ' items'}</span>
                                </button>
                            `).join('')}
                        </div>
                        <div class="collection-picker-footer">
                            <a href="/dashboard/?page=collections&action=add" class="btn btn-sm">+ New Collection</a>
                        </div>
                    `;
                }
                
                showModal(this.modalId, 'Add to Collection', modalBody, () => {
                    this.currentFurnitureId = null;
                });
            } catch (error) {
                console.error('Collection picker error:', error);
                toast('Failed to load collections', 'error');
            }
        },
        
        /**
         * Toggle item in collection
         * @param {number} collectionId - Collection ID
         */
        async toggle(collectionId) {
            const furnitureId = this.currentFurnitureId;
            if (!furnitureId) return;
            
            const button = document.querySelector(`button[data-collection-id="${collectionId}"]`);
            if (!button) return;
            
            const isInCollection = button.classList.contains('btn-primary');
            const csrfToken = getCsrfToken();
            
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
                    const statusSpan = button.querySelector('.collection-picker-status');
                    if (statusSpan) {
                        const itemCount = button.dataset.itemCount || '0';
                        statusSpan.textContent = isInCollection ? itemCount + ' items' : '✓ Added';
                    }
                    toast(isInCollection ? 'Removed from collection' : 'Added to collection', 'success');
                } else {
                    toast(result.error || 'Failed to update collection', 'error');
                }
            } catch (error) {
                console.error('Toggle collection error:', error);
                toast('Network error', 'error');
            }
        },
        
        /**
         * Close the collection picker modal
         */
        close() {
            closeModal(this.modalId);
            this.currentFurnitureId = null;
        }
    };

    // =========================================
    // PUBLIC API
    // =========================================

    return {
        // Security
        getCsrfToken,
        escapeHtml,
        
        // Notifications
        toast,
        
        // Modals
        showModal,
        closeModal,
        
        // Clipboard
        copyToClipboard,
        copyCommand,
        
        // Theme
        toggleTheme,
        
        // Utilities
        debounce,
        
        // Modules
        tableSearch,
        duplicateDetection,
        imagePreview,
        collectionPicker
    };
})();


