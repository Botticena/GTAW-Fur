/**
 * GTAW Furniture Catalog - Admin Panel JavaScript
 */

const Admin = {
    /**
     * CSRF token for API requests
     */
    csrfToken: null,

    /**
     * Selected items for batch operations
     */
    selectedItems: new Set(),

    /**
     * Initialize admin panel
     */
    init() {
        this.csrfToken = this.getCsrfToken();
        this.bindEvents();
        this.initForms();
        this.initBatchOperations();
        this.initDragAndDrop();
        this.initImagePreview();
        this.initColorInputs();
        this.initDeleteButtons();
    },

    /**
     * Get CSRF token from hidden input or meta tag
     */
    getCsrfToken() {
        // Try hidden input first
        const input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value;
        
        // Try meta tag
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        
        return null;
    },

    /**
     * Bind global event listeners
     */
    bindEvents() {
        // Confirm delete actions
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', (e) => {
                const message = el.dataset.confirm || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        // Handle form submissions via AJAX
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', (e) => this.handleAjaxForm(e));
        });

        // File upload preview
        document.querySelectorAll('.file-upload input[type="file"]').forEach(input => {
            input.addEventListener('change', (e) => this.handleFileSelect(e));
        });

        // Tag checkbox styling
        document.querySelectorAll('.checkbox-item input').forEach(input => {
            input.addEventListener('change', (e) => {
                e.target.closest('.checkbox-item').classList.toggle('checked', e.target.checked);
            });
        });
    },

    /**
     * Initialize form enhancements
     */
    initForms() {
        // Initialize checkbox states
        document.querySelectorAll('.checkbox-item input:checked').forEach(input => {
            input.closest('.checkbox-item').classList.add('checked');
        });
    },

    /**
     * Initialize batch operations
     */
    initBatchOperations() {
        const selectAll = document.getElementById('select-all');
        const batchControls = document.getElementById('batch-controls');
        
        if (!selectAll || !batchControls) return;

        // Select all checkbox
        selectAll.addEventListener('change', (e) => {
            const checkboxes = document.querySelectorAll('.row-select');
            checkboxes.forEach(cb => {
                cb.checked = e.target.checked;
                if (e.target.checked) {
                    this.selectedItems.add(cb.dataset.id);
                } else {
                    this.selectedItems.delete(cb.dataset.id);
                }
            });
            this.updateBatchControls();
        });

        // Individual row checkboxes
        document.querySelectorAll('.row-select').forEach(cb => {
            cb.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.selectedItems.add(e.target.dataset.id);
                } else {
                    this.selectedItems.delete(e.target.dataset.id);
                }
                this.updateBatchControls();
            });
        });
    },

    /**
     * Update batch controls visibility and count
     */
    updateBatchControls() {
        const controls = document.getElementById('batch-controls');
        const countEl = document.getElementById('selected-count');
        
        if (!controls) return;
        
        if (this.selectedItems.size > 0) {
            controls.classList.add('visible');
            if (countEl) {
                countEl.innerHTML = `<strong>${this.selectedItems.size}</strong> item${this.selectedItems.size > 1 ? 's' : ''} selected`;
            }
        } else {
            controls.classList.remove('visible');
        }
    },

    /**
     * Delete selected items
     */
    async batchDelete(type) {
        if (this.selectedItems.size === 0) return;
        
        const count = this.selectedItems.size;
        if (!confirm(`Are you sure you want to delete ${count} item${count > 1 ? 's' : ''}?`)) {
            return;
        }

        try {
            const ids = Array.from(this.selectedItems);
            const result = await this.api(`${type}/batch-delete`, {
                method: 'POST',
                body: { ids }
            });

            if (result.success) {
                this.toast(`${count} item${count > 1 ? 's' : ''} deleted`, 'success');
                // Remove rows
                ids.forEach(id => {
                    document.querySelector(`tr[data-id="${id}"]`)?.remove();
                });
                this.selectedItems.clear();
                this.updateBatchControls();
            } else {
                this.toast(result.error || 'Failed to delete items', 'error');
            }
        } catch (error) {
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Initialize drag and drop for sortable tables
     */
    initDragAndDrop() {
        const sortableTables = document.querySelectorAll('[data-sortable]');
        
        sortableTables.forEach(table => {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const type = table.dataset.sortable || '';
            const reorderUrl = table.dataset.reorderUrl || null;
            let draggedRow = null;

            tbody.querySelectorAll('tr').forEach(row => {
                row.classList.add('sortable-row');
                row.draggable = true;

                row.addEventListener('dragstart', (e) => {
                    draggedRow = row;
                    row.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', row.dataset.id);
                });

                row.addEventListener('dragend', () => {
                    row.classList.remove('dragging');
                    tbody.querySelectorAll('.drag-over').forEach(r => r.classList.remove('drag-over'));
                });

                row.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (row !== draggedRow) {
                        row.classList.add('drag-over');
                    }
                });

                row.addEventListener('dragleave', () => {
                    row.classList.remove('drag-over');
                });

                row.addEventListener('drop', (e) => {
                    e.preventDefault();
                    row.classList.remove('drag-over');
                    
                    if (draggedRow && row !== draggedRow) {
                        const rect = row.getBoundingClientRect();
                        const midY = rect.top + rect.height / 2;
                        
                        if (e.clientY < midY) {
                            tbody.insertBefore(draggedRow, row);
                        } else {
                            tbody.insertBefore(draggedRow, row.nextSibling);
                        }
                        
                        // If we have a direct reorder URL, save immediately
                        if (reorderUrl) {
                            this.saveOrderDirect(table, reorderUrl);
                        } else if (type) {
                            this.showOrderChanged(type);
                        }
                    }
                });
            });
        });
    },

    /**
     * Save order directly via URL (for tag groups etc.)
     */
    async saveOrderDirect(table, url) {
        const rows = table.querySelectorAll('tbody tr');
        const order = Array.from(rows).map((row, index) => ({
            id: parseInt(row.dataset.id),
            order: index + 1
        }));

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken || ''
                },
                body: JSON.stringify({ 
                    order,
                    csrf_token: this.csrfToken 
                }),
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (result.success) {
                this.toast('Order saved', 'success');
            } else {
                this.toast(result.error || 'Failed to save order', 'error');
            }
        } catch (error) {
            console.error('Reorder error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Show order changed indicator
     */
    showOrderChanged(type) {
        const indicator = document.getElementById('order-changed');
        if (indicator) {
            indicator.classList.add('visible');
            indicator.dataset.type = type;
        }
    },

    /**
     * Save the new order
     */
    async saveOrder() {
        const indicator = document.getElementById('order-changed');
        const type = indicator?.dataset.type;
        if (!type) return;

        const table = document.querySelector(`[data-sortable="${type}"]`);
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');
        const order = Array.from(rows).map((row, index) => ({
            id: parseInt(row.dataset.id),
            order: index + 1
        }));

        try {
            const result = await this.api(`${type}/reorder`, {
                method: 'POST',
                body: { order }
            });

            if (result.success) {
                this.toast('Order saved successfully', 'success');
                indicator.classList.remove('visible');
            } else {
                this.toast(result.error || 'Failed to save order', 'error');
            }
        } catch (error) {
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Handle AJAX form submission
     */
    async handleAjaxForm(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn?.textContent;

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
            }

            const formData = new FormData(form);
            const action = form.dataset.action || form.action;
            const method = form.method?.toUpperCase() || 'POST';

            let response;
            if (method === 'GET') {
                const params = new URLSearchParams(formData);
                response = await fetch(`${action}?${params}`);
            } else {
                // Convert FormData to JSON for API
                const data = {};
                formData.forEach((value, key) => {
                    if (key.endsWith('[]')) {
                        const arrayKey = key.slice(0, -2);
                        if (!data[arrayKey]) data[arrayKey] = [];
                        data[arrayKey].push(value);
                    } else {
                        data[key] = value;
                    }
                });

                // Ensure CSRF token is included
                if (this.csrfToken && !data.csrf_token) {
                    data.csrf_token = this.csrfToken;
                }

                response = await fetch(action, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken || ''
                    },
                    body: JSON.stringify(data),
                    credentials: 'same-origin'
                });
            }

            const result = await response.json();

            if (result.success) {
                this.toast(result.message || 'Success!', 'success');
                
                // Handle redirect
                if (form.dataset.redirect) {
                    setTimeout(() => {
                        window.location.href = form.dataset.redirect;
                    }, 500);
                }
                
                // Handle reload
                if (form.dataset.reload) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            } else {
                this.toast(result.error || 'An error occurred', 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.toast('Network error. Please try again.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    },

    /**
     * Handle file selection
     */
    handleFileSelect(e) {
        const input = e.target;
        const container = input.closest('.file-upload');
        const file = input.files[0];

        if (file && container) {
            const label = container.querySelector('p');
            if (label) {
                label.textContent = file.name;
            }
        }
    },

    /**
     * API helper
     */
    async api(action, options = {}) {
        const url = new URL('/admin/api.php', window.location.origin);
        url.searchParams.set('action', action);

        if (options.id) {
            url.searchParams.set('id', options.id);
        }

        const fetchOptions = {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: {}
        };

        // Add CSRF token for non-GET requests
        if (options.method && options.method !== 'GET') {
            if (this.csrfToken) {
                fetchOptions.headers['X-CSRF-Token'] = this.csrfToken;
            }
        }

        if (options.body) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            // Include CSRF token in body as well
            const bodyWithCsrf = { ...options.body };
            if (this.csrfToken) {
                bodyWithCsrf.csrf_token = this.csrfToken;
            }
            fetchOptions.body = JSON.stringify(bodyWithCsrf);
        } else if (options.method && options.method !== 'GET' && this.csrfToken) {
            // For requests without body, still send CSRF token
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify({ csrf_token: this.csrfToken });
        }

        const response = await fetch(url, fetchOptions);
        return response.json();
    },

    /**
     * Delete item with confirmation
     */
    async deleteItem(type, id, name) {
        if (!confirm(`Are you sure you want to delete "${name}"?`)) {
            return;
        }

        try {
            const result = await this.api(`${type}/delete`, {
                method: 'POST',
                id: id
            });

            if (result.success) {
                this.toast(result.message || 'Deleted successfully', 'success');
                
                // Remove row from table
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.remove();
                }
            } else {
                this.toast(result.error || 'Failed to delete', 'error');
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Ban user
     */
    async banUser(id, username) {
        const reason = prompt(`Enter ban reason for ${username}:`, '');
        if (reason === null) return; // Cancelled

        try {
            const result = await this.api('users/ban', {
                method: 'POST',
                id: id,
                body: { reason }
            });

            if (result.success) {
                this.toast('User banned successfully', 'success');
                window.location.reload();
            } else {
                this.toast(result.error || 'Failed to ban user', 'error');
            }
        } catch (error) {
            console.error('Ban error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Unban user
     */
    async unbanUser(id) {
        if (!confirm('Are you sure you want to unban this user?')) {
            return;
        }

        try {
            const result = await this.api('users/unban', {
                method: 'POST',
                id: id
            });

            if (result.success) {
                this.toast('User unbanned successfully', 'success');
                window.location.reload();
            } else {
                this.toast(result.error || 'Failed to unban user', 'error');
            }
        } catch (error) {
            console.error('Unban error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Approve submission
     */
    async approveSubmission(id) {
        if (!confirm('Approve this submission and create/update the furniture?')) {
            return;
        }

        try {
            const result = await this.api('submissions/approve', {
                method: 'POST',
                id: id
            });

            if (result.success) {
                this.toast(result.message || 'Submission approved', 'success');
                window.location.reload();
            } else {
                this.toast(result.error || 'Failed to approve submission', 'error');
            }
        } catch (error) {
            console.error('Approve error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Reject submission
     */
    async rejectSubmission(id) {
        const notes = prompt('Enter reason for rejection (optional):');
        if (notes === null) return; // Cancelled

        try {
            const result = await this.api('submissions/reject', {
                method: 'POST',
                id: id,
                body: { notes }
            });

            if (result.success) {
                this.toast('Submission rejected', 'success');
                window.location.reload();
            } else {
                this.toast(result.error || 'Failed to reject submission', 'error');
            }
        } catch (error) {
            console.error('Reject error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Import CSV
     */
    async importCsv() {
        const textarea = document.getElementById('csv-content');
        const fileInput = document.getElementById('csv-file');
        let csvContent = '';

        // Try file first, then textarea
        if (fileInput?.files[0]) {
            csvContent = await this.readFile(fileInput.files[0]);
        } else if (textarea?.value) {
            csvContent = textarea.value;
        }

        if (!csvContent.trim()) {
            this.toast('Please provide CSV content or upload a file', 'error');
            return;
        }

        try {
            const result = await this.api('import', {
                method: 'POST',
                body: { csv: csvContent }
            });

            if (result.success) {
                this.toast(result.message || 'Import completed', 'success');
                setTimeout(() => {
                    window.location.href = '/admin/?page=furniture';
                }, 1000);
            } else {
                this.toast(result.error || 'Import failed', 'error');
            }
        } catch (error) {
            console.error('Import error:', error);
            this.toast('Network error. Please try again.', 'error');
        }
    },

    /**
     * Read file contents
     */
    readFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = reject;
            reader.readAsText(file);
        });
    },

    /**
     * Export data
     */
    exportData() {
        window.location.href = '/admin/api.php?action=export';
    },

    /**
     * Show modal
     */
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    },

    /**
     * Hide modal
     */
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    },

    /**
     * Show toast notification
     */
    toast(message, type = 'info') {
        const container = document.getElementById('toast-container') || this.createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    /**
     * Create toast container if it doesn't exist
     */
    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
        return container;
    },

    /**
     * Initialize live image preview for URL inputs
     */
    initImagePreview() {
        const imageUrlInput = document.getElementById('image_url');
        const previewImg = document.getElementById('preview-img');
        
        if (!imageUrlInput || !previewImg) return;
        
        // Debounce timer
        let debounceTimer = null;
        
        const updatePreview = (url) => {
            if (!url) {
                previewImg.src = '/images/placeholder.svg';
                return;
            }
            
            // Handle relative paths
            if (url.startsWith('/')) {
                previewImg.src = url;
            } else if (url.startsWith('http://') || url.startsWith('https://')) {
                previewImg.src = url;
            } else {
                previewImg.src = '/images/placeholder.svg';
            }
        };
        
        imageUrlInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                updatePreview(e.target.value.trim());
            }, 300);
        });
        
        // Handle image load errors
        previewImg.addEventListener('error', () => {
            previewImg.src = '/images/placeholder.svg';
        });
    },

    /**
     * Initialize color input sync (color picker + text input)
     */
    initColorInputs() {
        const colorInput = document.getElementById('color');
        const colorText = document.getElementById('color_text');
        
        if (!colorInput || !colorText) return;
        
        // Sync color picker to text input
        colorInput.addEventListener('input', (e) => {
            colorText.value = e.target.value.toUpperCase();
        });
        
        // Sync text input to color picker
        colorText.addEventListener('input', (e) => {
            let value = e.target.value.trim();
            
            // Add # if missing
            if (!value.startsWith('#')) {
                value = '#' + value;
            }
            
            // Validate hex color
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                colorInput.value = value;
                colorText.value = value.toUpperCase();
            }
        });
        
        // Format on blur
        colorText.addEventListener('blur', (e) => {
            let value = e.target.value.trim();
            if (!value.startsWith('#')) {
                value = '#' + value;
            }
            if (/^#[0-9A-Fa-f]{6}$/i.test(value)) {
                colorText.value = value.toUpperCase();
            } else {
                // Reset to color picker value if invalid
                colorText.value = colorInput.value.toUpperCase();
            }
        });
    },

    /**
     * Initialize data-delete buttons (for inline delete actions)
     */
    initDeleteButtons() {
        document.querySelectorAll('[data-delete]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                
                const url = btn.dataset.url;
                const csrf = btn.dataset.csrf || this.csrfToken;
                const confirmMsg = btn.dataset.confirm || 'Are you sure you want to delete this item?';
                
                if (!confirm(confirmMsg)) return;
                
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ csrf_token: csrf }),
                        credentials: 'same-origin'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.toast(result.message || 'Deleted successfully', 'success');
                        // Remove the row
                        const row = btn.closest('tr');
                        if (row) row.remove();
                    } else {
                        this.toast(result.error || 'Failed to delete', 'error');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    this.toast('Network error. Please try again.', 'error');
                }
            });
        });
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Admin.init());
} else {
    Admin.init();
}

