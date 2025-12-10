/**
 * GTAW Furniture Catalog - Dashboard JavaScript
 * 
 * Handles user dashboard interactions.
 * Uses same toast system as admin panel.
 */

const Dashboard = {
    // State
    state: {
        collections: [],
        currentFurnitureId: null,
    },

    /**
     * Get CSRF token from shared helper
     */
    getCsrfToken() {
        return window.GTAW ? window.GTAW.getCsrfToken() : null;
    },

    /**
     * Initialize dashboard
     */
    init() {
        this.bindEvents();
        this.initThemeToggle();
        this.initForms();
        this.initCollectionReordering();
        this.initCategoryTagSync();
        this.recentlyViewed.init();
        
        window.GTAW.tableSearch.init();
        window.GTAW.imagePreview.init();
        window.GTAW.duplicateDetection.init({
            editLinkPrefix: '/dashboard/?page=submissions&action=new&furniture_id=',
            editLinkText: 'Suggest Edit',
            hintText: 'Consider suggesting an edit instead of creating a duplicate.'
        });
    },

    /**
     * Bind global event listeners
     */
    bindEvents() {
        // Close modals on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Close modals on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    },

    /**
     * Initialize theme toggle
     * Delegates to shared GTAW.toggleTheme() for consistent behavior
     */
    initThemeToggle() {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            // Use shared utility (no toast on dashboard to match original behavior)
            window.GTAW.toggleTheme(false);
        });
    },

    /**
     * Initialize AJAX forms
     */
    initForms() {
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const action = form.dataset.action;
                const redirect = form.dataset.redirect;
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving...';
                }
                
                try {
                    const response = await fetch(action, {
                        method: 'POST',
                        body: formData,
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.toast(result.message || 'Saved successfully', 'success');
                        if (redirect) {
                            setTimeout(() => {
                                window.location.href = redirect;
                            }, 500);
                        }
                    } else {
                        this.toast(result.error || 'An error occurred', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.originalText || window.GTAW.__('form.save');
                }
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.toast(window.GTAW.__('error.network_retry'), 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText || window.GTAW.__('form.save');
            }
        }
            });
            
            // Store original button text
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.dataset.originalText = submitBtn.textContent;
            }
        });
    },

    /**
     * Copy furniture command
     * Delegates to shared GTAW.copyCommand() for consistent behavior
     */
    copyCommand(name) {
        window.GTAW.copyCommand(name);
    },

    /**
     * Remove item from favorites
     */
    async removeFavorite(furnitureId) {
        if (!confirm('Remove this item from favorites?')) return;
        
        const csrfToken = this.getCsrfToken();
        try {
            const response = await fetch('/api.php?action=favorites', {
                method: 'DELETE',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify({ 
                    furniture_id: furnitureId,
                    csrf_token: csrfToken
                }),
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Remove row from DOM
                const row = document.querySelector(`tr[data-id="${furnitureId}"]`);
                if (row) {
                    row.remove();
                }
                this.toast(window.GTAW.__('favorites.removed'), 'success');
            } else {
                this.toast(result.error || window.GTAW.__('error.generic'), 'error');
            }
        } catch (error) {
            console.error('Remove favorite error:', error);
            this.toast('Network error', 'error');
        }
    },

    /**
     * Export favorites
     */
    async exportFavorites() {
        try {
            const response = await fetch('/api.php?action=favorites');
            const result = await response.json();
            
            if (!result.success || !result.data.length) {
                this.toast(window.GTAW.__('favorites.nothing_to_export'), 'info');
                return;
            }
            
            const commands = result.data.map(item => `/sf ${item.name}`).join('\n');
            
            // Create download
            const blob = new Blob([commands], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'favorites-commands.txt';
            a.click();
            URL.revokeObjectURL(url);
            
            this.toast(window.GTAW.__('favorites.exported', { count: result.data.length }), 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.toast(window.GTAW.__('error.generic'), 'error');
        }
    },

    /**
     * Clear all favorites with confirmation
     */
    async clearAllFavorites(count) {
        if (count === 0) {
            this.toast(window.GTAW.__('favorites.nothing_to_clear'), 'info');
            return;
        }
        
        if (!confirm(window.GTAW.__('favorites.confirm_clear', { count }))) {
            return;
        }
        
        try {
            const response = await fetch('/api.php?action=favorites/clear', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.toast(window.GTAW.__('favorites.cleared', { count: result.data.count }), 'success');
                // Reload page to show empty state
                window.location.reload();
            } else {
                this.toast(result.error || window.GTAW.__('error.generic'), 'error');
            }
        } catch (error) {
            console.error('Clear favorites error:', error);
            this.toast(window.GTAW.__('error.network'), 'error');
        }
    },

    /**
     * Open collection picker modal
     * Delegates to shared GTAW.collectionPicker module
     */
    addToCollection(furnitureId) {
        window.GTAW.collectionPicker.open(furnitureId);
    },

    /**
     * Delete collection
     */
    async deleteCollection(id, name) {
        if (!confirm(window.GTAW.__('collections.confirm_delete', { name }))) return;
        
        const csrfToken = this.getCsrfToken();
        try {
            const response = await fetch(`/dashboard/api.php?action=collections/delete&id=${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify({
                    csrf_token: csrfToken
                }),
            });
            
            const result = await response.json();
            
            if (result.success) {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) row.remove();
                this.toast(window.GTAW.__('collections.deleted'), 'success');
            } else {
                this.toast(result.error || window.GTAW.__('error.generic'), 'error');
            }
        } catch (error) {
            console.error('Delete collection error:', error);
            this.toast(window.GTAW.__('error.network'), 'error');
        }
    },

    /**
     * Share collection link - FIXED URL format
     */
    shareCollection(id, slug, username) {
        // Use query parameter format: /collection.php?user=username&slug=slug
        const url = `${window.location.origin}/collection.php?user=${encodeURIComponent(username)}&slug=${encodeURIComponent(slug)}`;
        
        navigator.clipboard.writeText(url).then(() => {
                this.toast(window.GTAW.__('collections.link_copied'), 'success');
        }).catch(() => {
            // Fallback
            prompt('Share this link:', url);
        });
    },

    /**
     * Duplicate a collection
     */
    async duplicateCollection(id, name) {
        if (!confirm(window.GTAW.__('collections.confirm_duplicate', { name }))) {
            return;
        }
        
        try {
            const response = await fetch('/dashboard/api.php?action=collections/duplicate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({ collection_id: id })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.toast(window.GTAW.__('collections.duplicated', { name: result.data.name }), 'success');
                // Redirect to edit the new collection
                window.location.href = `/dashboard/?page=collections&action=edit&id=${result.data.id}`;
            } else {
                this.toast(result.error || window.GTAW.__('error.generic'), 'error');
            }
        } catch (error) {
            console.error('Duplicate collection error:', error);
            this.toast(window.GTAW.__('error.network'), 'error');
        }
    },

    /**
     * Export collection
     */
    async exportCollection(collectionId) {
        try {
            const response = await fetch(`/dashboard/api.php?action=collections/items&id=${collectionId}`);
            const result = await response.json();
            
            if (!result.success || !result.data.length) {
                this.toast(window.GTAW.__('collections.nothing_to_export'), 'info');
                return;
            }
            
            const commands = result.data.map(item => `/sf ${item.name}`).join('\n');
            
            const blob = new Blob([commands], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'collection-commands.txt';
            a.click();
            URL.revokeObjectURL(url);
            
            this.toast(window.GTAW.__('favorites.exported', { count: result.data.length }), 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.toast(window.GTAW.__('favorites.export_failed'), 'error');
        }
    },

    /**
     * Remove item from collection
     */
    async removeFromCollection(collectionId, furnitureId) {
        if (!confirm(window.GTAW.__('collections.confirm_remove_item'))) return;
        
        const csrfToken = this.getCsrfToken();
        try {
            const response = await fetch('/dashboard/api.php?action=collections/remove-item', {
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
                const row = document.querySelector(`tr[data-id="${furnitureId}"]`);
                if (row) row.remove();
                this.toast(window.GTAW.__('collections.removed'), 'success');
            } else {
                this.toast(result.error || window.GTAW.__('error.generic'), 'error');
            }
        } catch (error) {
            console.error('Remove from collection error:', error);
            this.toast(window.GTAW.__('error.network'), 'error');
        }
    },

    /**
     * Cancel submission
     */
    async cancelSubmission(id) {
        if (!confirm(window.GTAW.__('submissions.confirm_cancel'))) return;
        
        const csrfToken = this.getCsrfToken();
        try {
            const response = await fetch(`/dashboard/api.php?action=submissions/cancel&id=${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify({
                    csrf_token: csrfToken
                }),
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.toast(window.GTAW.__('submissions.cancelled'), 'success');
                window.location.href = '/dashboard/?page=submissions';
            } else {
                this.toast(result.error || window.GTAW.__('error.generic'), 'error');
            }
        } catch (error) {
            console.error('Cancel submission error:', error);
            this.toast(window.GTAW.__('error.network'), 'error');
        }
    },

    /**
     * Show modal - uses admin styling
     */
    showModal(title, content) {
        if (window.GTAW) {
            window.GTAW.showModal('dashboard-modal', title, content, () => {
                this.state.currentFurnitureId = null;
            });
        }
    },

    /**
     * Close modal
     */
    closeModal() {
        if (window.GTAW) {
            window.GTAW.closeModal('dashboard-modal');
        }
        this.state.currentFurnitureId = null;
    },

    /**
     * Show toast notification
     * Delegates to shared GTAW.toast() for consistent behavior
     */
    toast(message, type = 'success') {
        window.GTAW.toast(message, type);
    },

    /**
     * Initialize collection item reordering (drag-and-drop)
     */
    initCollectionReordering() {
        const sortableTables = document.querySelectorAll('table[data-sortable][data-collection-id]');
        
        sortableTables.forEach(table => {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const collectionId = parseInt(table.dataset.collectionId);
            let draggedRow = null;
            
            // Make rows draggable
            tbody.querySelectorAll('tr').forEach(row => {
                row.draggable = true;
                row.style.cursor = 'move';
                
                row.addEventListener('dragstart', (e) => {
                    draggedRow = row;
                    row.style.opacity = '0.5';
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', row.innerHTML);
                });
                
                row.addEventListener('dragend', () => {
                    row.style.opacity = '1';
                    tbody.querySelectorAll('tr').forEach(r => {
                        r.classList.remove('drag-over');
                    });
                });
                
                row.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    
                    const afterElement = this.getDragAfterElement(tbody, e.clientY);
                    const dragging = row === draggedRow;
                    
                    if (afterElement == null && !dragging) {
                        tbody.appendChild(draggedRow);
                    } else if (afterElement && !dragging) {
                        tbody.insertBefore(draggedRow, afterElement);
                    }
                });
                
                row.addEventListener('dragenter', (e) => {
                    e.preventDefault();
                    if (row !== draggedRow) {
                        row.classList.add('drag-over');
                    }
                });
                
                row.addEventListener('dragleave', () => {
                    row.classList.remove('drag-over');
                });
                
                row.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    row.classList.remove('drag-over');
                    
                    if (draggedRow && draggedRow !== row) {
                        const newOrder = Array.from(tbody.querySelectorAll('tr')).map((tr, index) => ({
                            id: parseInt(tr.dataset.id),
                            order: index
                        }));
                        
                        await this.reorderCollectionItems(collectionId, newOrder);
                    }
                });
            });
        });
    },
    
    /**
     * Get element after which to insert dragged element
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('tr:not(.dragging)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },
    
    /**
     * Reorder collection items via API
     */
    async reorderCollectionItems(collectionId, order) {
        try {
            const response = await fetch('/dashboard/api.php?action=collections/reorder-items', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({
                    collection_id: collectionId,
                    order: order
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.toast(window.GTAW.__('collections.reordered'));
            } else {
                this.toast(result.error || window.GTAW.__('collections.reorder_failed'), 'error');
            }
        } catch (error) {
            console.error('Error reordering items:', error);
            this.toast(window.GTAW.__('collections.reorder_failed'), 'error');
        }
    },

    // =========================================
    // RECENTLY VIEWED MODULE
    // =========================================
    
    /**
     * Recently Viewed Module
     * Displays recently viewed furniture items on the overview page
     */
    recentlyViewed: {
        section: null,
        grid: null,
        storageKey: 'gtaw_recently_viewed',
        maxDisplay: 15,
        
        /**
         * Initialize recently viewed section
         */
        init() {
            this.section = document.getElementById('recently-viewed-section');
            this.grid = document.getElementById('recently-viewed-grid');
            
            if (!this.section || !this.grid) return;
            
            this.load();
        },
        
        /**
         * Load and display recently viewed items
         */
        async load() {
            const ids = this.getIds();
            
            if (ids.length === 0) return;
            
            // Limit to maxDisplay items
            const displayIds = ids.slice(0, this.maxDisplay);
            
            try {
                const response = await fetch(`/api.php?action=furniture/batch&ids=${displayIds.join(',')}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    this.render(result.data);
                }
            } catch (error) {
                console.error('Failed to load recently viewed:', error);
            }
        },
        
        /**
         * Get recently viewed IDs from localStorage
         */
        getIds() {
            try {
                return JSON.parse(localStorage.getItem(this.storageKey) || '[]');
            } catch {
                return [];
            }
        },
        
        /**
         * Render recently viewed items
         */
        render(items) {
            const escapeHtml = window.GTAW?.escapeHtml || (t => String(t ?? ''));
            
            const html = items.map(item => `
                <a href="/?furniture=${item.id}" class="recently-viewed-item">
                    <img src="${item.image_url || '/images/placeholder.svg'}" 
                         alt="" 
                         onerror="this.src='/images/placeholder.svg'">
                    <div class="recently-viewed-item-info">
                        <div class="recently-viewed-item-name" title="${escapeHtml(item.name)}">
                            ${escapeHtml(item.name)}
                        </div>
                        <div class="recently-viewed-item-meta">
                            ${escapeHtml(item.categories?.[0]?.name || item.category_name || '')} • $${Number(item.price).toLocaleString()}
                        </div>
                    </div>
                </a>
            `).join('');
            
            this.grid.innerHTML = html;
            this.section.style.display = 'block';
        }
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        return window.GTAW ? window.GTAW.escapeHtml(text) : String(text ?? '');
    },

    /**
     * Initialize category-tag synchronization for submission forms
     * When category selection changes, loads category-specific tags
     */
    initCategoryTagSync() {
        const categoryCheckboxes = document.querySelectorAll('input[name="category_ids[]"]');
        const tagsContainer = document.getElementById('tags-container');
        
        if (!categoryCheckboxes.length || !tagsContainer) return;
        
        // Store currently selected tag IDs
        this.selectedTagIds = new Set();
        document.querySelectorAll('input[name="tags[]"]:checked').forEach(cb => {
            this.selectedTagIds.add(cb.value);
        });
        
        // Watch for category changes
        categoryCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.loadCategorySpecificTags();
            });
        });
        
        // Initial load if categories are selected
        const selectedCategories = Array.from(categoryCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
            
        if (selectedCategories.length > 0) {
            this.loadCategorySpecificTags();
        }
    },

    /**
     * Load and render category-specific tags
     */
    async loadCategorySpecificTags() {
        const categoryCheckboxes = document.querySelectorAll('input[name="category_ids[]"]:checked');
        const specificContainer = document.getElementById('category-specific-tags');
        
        if (!specificContainer) return;
        
        const categoryIds = Array.from(categoryCheckboxes).map(cb => cb.value);
        
        if (categoryIds.length === 0) {
            specificContainer.innerHTML = '';
            return;
        }
        
        try {
            const response = await fetch(`/api.php?action=tags/for-categories&category_ids=${categoryIds.join(',')}`);
            const result = await response.json();
            
            if (!result.success) {
                console.error('Failed to load category-specific tags');
                return;
            }
            
            const categorySpecificGroups = result.data?.category_specific?.groups || [];
            
            if (categorySpecificGroups.length === 0) {
                specificContainer.innerHTML = '';
                return;
            }
            
            // Render category-specific tag groups
            // Use same structure as regular tag groups for consistency
            let html = '<h4>Category-Specific Tags</h4>';
            
            categorySpecificGroups.forEach(group => {
                if (!group.tags || group.tags.length === 0) return;
                
                html += `
                    <div class="tag-group-section" data-group-id="${group.id}">
                        <h4>
                            <span class="group-color-dot" style="background: ${this.escapeHtml(group.color)}"></span>
                            ${this.escapeHtml(group.name)}
                        </h4>
                        <div class="checkbox-group">
                `;
                
                group.tags.forEach(tag => {
                    const isChecked = this.selectedTagIds.has(String(tag.id));
                    html += `
                        <label class="checkbox-item${isChecked ? ' checked' : ''}">
                            <input type="checkbox" name="tags[]" value="${tag.id}"${isChecked ? ' checked' : ''}>
                            <span>${this.escapeHtml(tag.name)}</span>
                        </label>
                    `;
                });
                
                html += '</div></div>';
            });
            
            specificContainer.innerHTML = html;
            
            // Re-bind checkbox styling events
            specificContainer.querySelectorAll('.checkbox-item input').forEach(input => {
                input.addEventListener('change', (e) => {
                    e.target.closest('.checkbox-item').classList.toggle('checked', e.target.checked);
                    // Track selection
                    if (e.target.checked) {
                        this.selectedTagIds.add(e.target.value);
                    } else {
                        this.selectedTagIds.delete(e.target.value);
                    }
                });
            });
            
        } catch (error) {
            console.error('Error loading category-specific tags:', error);
        }
    },
};

document.addEventListener('DOMContentLoaded', () => Dashboard.init());
