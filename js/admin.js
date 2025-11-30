/**
 * GTAW Furniture Catalog - Admin Panel JavaScript
 */

const Admin = {
    /**
     * Initialize admin panel
     */
    init() {
        this.bindEvents();
        this.initForms();
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

                response = await fetch(action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
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

        if (options.body) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(options.body);
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

