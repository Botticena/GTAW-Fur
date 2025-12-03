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
                            submitBtn.textContent = submitBtn.dataset.originalText || 'Save';
                        }
                    }
                } catch (error) {
                    console.error('Form submission error:', error);
                    this.toast('Network error. Please try again.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalText || 'Save';
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
                this.toast('Removed from favorites', 'success');
            } else {
                this.toast(result.error || 'Failed to remove', 'error');
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
                this.toast('No favorites to export', 'info');
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
            
            this.toast('Exported ' + result.data.length + ' items', 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.toast('Export failed', 'error');
        }
    },

    /**
     * Clear all favorites with confirmation
     */
    async clearAllFavorites(count) {
        if (count === 0) {
            this.toast('No favorites to clear', 'info');
            return;
        }
        
        if (!confirm(`Remove ALL ${count} favorites? This cannot be undone.`)) {
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
                this.toast(`Cleared ${result.data.count} favorites`, 'success');
                // Reload page to show empty state
                window.location.reload();
            } else {
                this.toast(result.error || 'Failed to clear favorites', 'error');
            }
        } catch (error) {
            console.error('Clear favorites error:', error);
            this.toast('Network error', 'error');
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
        if (!confirm(`Delete collection "${name}"? This cannot be undone.`)) return;
        
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
                this.toast('Collection deleted', 'success');
            } else {
                this.toast(result.error || 'Failed to delete', 'error');
            }
        } catch (error) {
            console.error('Delete collection error:', error);
            this.toast('Network error', 'error');
        }
    },

    /**
     * Share collection link - FIXED URL format
     */
    shareCollection(id, slug, username) {
        // Use query parameter format: /collection.php?user=username&slug=slug
        const url = `${window.location.origin}/collection.php?user=${encodeURIComponent(username)}&slug=${encodeURIComponent(slug)}`;
        
        navigator.clipboard.writeText(url).then(() => {
                this.toast('Collection link copied!', 'success');
        }).catch(() => {
            // Fallback
            prompt('Share this link:', url);
        });
    },

    /**
     * Duplicate a collection
     */
    async duplicateCollection(id, name) {
        if (!confirm(`Create a copy of "${name}"?`)) {
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
                this.toast(`Collection duplicated: ${result.data.name}`, 'success');
                // Redirect to edit the new collection
                window.location.href = `/dashboard/?page=collections&action=edit&id=${result.data.id}`;
            } else {
                this.toast(result.error || 'Failed to duplicate collection', 'error');
            }
        } catch (error) {
            console.error('Duplicate collection error:', error);
            this.toast('Network error', 'error');
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
            this.toast('No items in collection to export', 'info');
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
            
            this.toast('Exported ' + result.data.length + ' items', 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.toast('Export failed', 'error');
        }
    },

    /**
     * Remove item from collection
     */
    async removeFromCollection(collectionId, furnitureId) {
        if (!confirm('Remove this item from the collection?')) return;
        
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
                this.toast('Removed from collection', 'success');
            } else {
                this.toast(result.error || 'Failed to remove', 'error');
            }
        } catch (error) {
            console.error('Remove from collection error:', error);
            this.toast('Network error', 'error');
        }
    },

    /**
     * Cancel submission
     */
    async cancelSubmission(id) {
        if (!confirm('Cancel this submission? This cannot be undone.')) return;
        
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
                this.toast('Submission cancelled', 'success');
                window.location.href = '/dashboard/?page=submissions';
            } else {
                this.toast(result.error || 'Failed to cancel', 'error');
            }
        } catch (error) {
            console.error('Cancel submission error:', error);
            this.toast('Network error', 'error');
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
                this.toast('Items reordered successfully');
            } else {
                this.toast(result.error || 'Failed to reorder items', 'error');
            }
        } catch (error) {
            console.error('Error reordering items:', error);
            this.toast('Failed to reorder items', 'error');
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
};

document.addEventListener('DOMContentLoaded', () => Dashboard.init());
