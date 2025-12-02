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
     * Get CSRF token from meta tag or hidden input
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        const input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value;
        return null;
    },

    /**
     * Initialize dashboard
     */
    init() {
        this.bindEvents();
        this.initThemeToggle();
        this.initForms();
        this.initCollectionReordering();
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
     * Initialize theme toggle - same as admin
     */
    initThemeToggle() {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('gtaw_theme', newTheme);
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
                
                // Disable submit button
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
                        this.showToast(result.message || 'Saved successfully', 'success');
                        if (redirect) {
                            setTimeout(() => {
                                window.location.href = redirect;
                            }, 500);
                        }
                    } else {
                        this.showToast(result.error || 'An error occurred', 'error');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = submitBtn.dataset.originalText || 'Save';
                        }
                    }
                } catch (error) {
                    console.error('Form submission error:', error);
                    this.showToast('Network error. Please try again.', 'error');
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
     */
    copyCommand(name) {
        const command = `/sf ${name}`;
        navigator.clipboard.writeText(command).then(() => {
            this.showToast('Copied: ' + command, 'success');
        }).catch(() => {
            // Fallback
            const input = document.createElement('input');
            input.value = command;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            this.showToast('Copied: ' + command, 'success');
        });
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
                this.showToast('Removed from favorites', 'success');
            } else {
                this.showToast(result.error || 'Failed to remove', 'error');
            }
        } catch (error) {
            console.error('Remove favorite error:', error);
            this.showToast('Network error', 'error');
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
                this.showToast('No favorites to export', 'info');
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
            
            this.showToast('Exported ' + result.data.length + ' items', 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.showToast('Export failed', 'error');
        }
    },

    /**
     * Open collection picker modal
     */
    async addToCollection(furnitureId) {
        this.state.currentFurnitureId = furnitureId;
        
        // Fetch user's collections
        try {
            const response = await fetch('/dashboard/api.php?action=collections');
            const result = await response.json();
            
            if (!result.success) {
                this.showToast(result.error || 'Failed to load collections', 'error');
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
                            <button onclick="Dashboard.toggleCollectionItem(${col.id})" 
                                    class="btn ${containsIds.includes(col.id) ? 'btn-primary' : ''}"
                                    style="justify-content: space-between; width: 100%;"
                                    data-collection-id="${col.id}">
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
            this.showToast('Failed to load collections', 'error');
        }
    },

    /**
     * Toggle item in collection
     */
    async toggleCollectionItem(collectionId) {
        const furnitureId = this.state.currentFurnitureId;
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
                    span.textContent = isInCollection ? 'Add' : '✓ Added';
                }
                this.showToast(isInCollection ? 'Removed from collection' : 'Added to collection', 'success');
            } else {
                this.showToast(result.error || 'Failed to update collection', 'error');
            }
        } catch (error) {
            console.error('Toggle collection error:', error);
            this.showToast('Network error', 'error');
        }
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
                this.showToast('Collection deleted', 'success');
            } else {
                this.showToast(result.error || 'Failed to delete', 'error');
            }
        } catch (error) {
            console.error('Delete collection error:', error);
            this.showToast('Network error', 'error');
        }
    },

    /**
     * Share collection link - FIXED URL format
     */
    shareCollection(id, slug, username) {
        // Use query parameter format: /collection.php?user=username&slug=slug
        const url = `${window.location.origin}/collection.php?user=${encodeURIComponent(username)}&slug=${encodeURIComponent(slug)}`;
        
        navigator.clipboard.writeText(url).then(() => {
            this.showToast('Collection link copied!', 'success');
        }).catch(() => {
            // Fallback
            prompt('Share this link:', url);
        });
    },

    /**
     * Export collection
     */
    async exportCollection(collectionId) {
        try {
            const response = await fetch(`/dashboard/api.php?action=collections/items&id=${collectionId}`);
            const result = await response.json();
            
            if (!result.success || !result.data.length) {
                this.showToast('No items in collection to export', 'info');
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
            
            this.showToast('Exported ' + result.data.length + ' items', 'success');
        } catch (error) {
            console.error('Export error:', error);
            this.showToast('Export failed', 'error');
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
                this.showToast('Removed from collection', 'success');
            } else {
                this.showToast(result.error || 'Failed to remove', 'error');
            }
        } catch (error) {
            console.error('Remove from collection error:', error);
            this.showToast('Network error', 'error');
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
                this.showToast('Submission cancelled', 'success');
                window.location.href = '/dashboard/?page=submissions';
            } else {
                this.showToast(result.error || 'Failed to cancel', 'error');
            }
        } catch (error) {
            console.error('Cancel submission error:', error);
            this.showToast('Network error', 'error');
        }
    },

    /**
     * Show modal - uses admin styling
     */
    showModal(title, content) {
        // Remove existing modal if any
        const existing = document.getElementById('dashboard-modal');
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.id = 'dashboard-modal';
        modal.className = 'modal-overlay active';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h2>${this.escapeHtml(title)}</h2>
                    <button class="modal-close" onclick="Dashboard.closeModal()">&times;</button>
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
        const modal = document.getElementById('dashboard-modal');
        if (modal) modal.remove();
        this.state.currentFurnitureId = null;
    },

    /**
     * Show toast notification - same as admin
     */
    showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
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
                this.showToast('Items reordered successfully');
            } else {
                this.showToast(result.error || 'Failed to reorder items', 'error');
            }
        } catch (error) {
            console.error('Error reordering items:', error);
            this.showToast('Failed to reorder items', 'error');
        }
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => Dashboard.init());
