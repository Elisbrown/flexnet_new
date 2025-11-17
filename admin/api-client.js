/**
 * FlexNet Admin API Client
 * Handles all API calls for admin interface
 */

class APIClient {
    constructor() {
        this.baseURL = 'http://localhost:3000/api';
        this.token = localStorage.getItem('admin_token');
    }

    /**
     * Generic fetch wrapper with JWT authentication
     */
    async request(method, endpoint, data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // LOCATIONS
    async getLocations(params = {}) {
        const { page = 1, limit = 10, sort_by, order, region, status, search } = params;
        const query = new URLSearchParams({ page, limit });
        if (sort_by) query.append('sort_by', sort_by);
        if (order) query.append('order', order);
        if (region) query.append('region', region);
        if (status) query.append('status', status);
        if (search) query.append('search', search);
        return this.request('GET', `/admin/locations?${query.toString()}`);
    }

    async getLocation(id) {
        return this.request('GET', `/admin/locations/${id}`);
    }

    async createLocation(data) {
        return this.request('POST', '/admin/locations', data);
    }

    async updateLocation(id, data) {
        return this.request('PUT', `/admin/locations/${id}`, data);
    }

    async deleteLocation(id) {
        return this.request('DELETE', `/admin/locations/${id}`);
    }

    // HOUSEHOLDS
    async getHouseholds(params = {}) {
        const { location_id, page = 1, limit = 10, status, search, sort_by, order } = params;
        const query = new URLSearchParams({ page, limit });
        if (location_id) query.append('location_id', location_id);
        if (status) query.append('status', status);
        if (search) query.append('search', search);
        if (sort_by) query.append('sort_by', sort_by);
        if (order) query.append('order', order);
        return this.request('GET', `/admin/households?${query.toString()}`);
    }

    async getHousehold(id) {
        return this.request('GET', `/admin/households/${id}`);
    }

    async getHouseholdDetails(id) {
        return this.getHousehold(id);
    }

    async createHousehold(data) {
        return this.request('POST', '/admin/households', data);
    }

    async updateHousehold(id, data) {
        return this.request('PUT', `/admin/households/${id}`, data);
    }

    async resetHouseholdPin(id) {
        return this.request('POST', `/admin/households/${id}/reset-pin`, {});
    }

    async applySubscriptionAction(id, data) {
        return this.request('POST', `/admin/households/${id}/subscription-action`, data);
    }

    async deleteHousehold(id) {
        return this.request('DELETE', `/admin/households/${id}`);
    }

    // PAYMENTS
    async getPayments(params = {}) {
        const { page = 1, limit = 10, status, search, channel, sort_by, order } = params;
        const query = new URLSearchParams({ page, limit });
        if (status) query.append('status', status);
        if (search) query.append('search', search);
        if (channel) query.append('channel', channel);
        if (sort_by) query.append('sort_by', sort_by);
        if (order) query.append('order', order);
        return this.request('GET', `/admin/payments?${query.toString()}`);
    }

    async getPayment(id) {
        return this.request('GET', `/admin/payments/${id}`);
    }

    async decidePayment(id, data) {
        return this.request('POST', `/admin/payments/${id}/decision`, data);
    }

    // ADMINS
    async getAdmins() {
        return this.request('GET', '/admin/admins');
    }

    async createAdmin(data) {
        return this.request('POST', '/admin/admins', data);
    }

    async updateAdmin(id, data) {
        return this.request('PUT', `/admin/admins/${id}`, data);
    }

    async deleteAdmin(id) {
        return this.request('DELETE', `/admin/admins/${id}`);
    }

    // ROLES
    async getRoles() {
        return this.request('GET', '/admin/roles');
    }

    async getAdminRoles(adminId) {
        return this.request('GET', `/admin/admins/${adminId}/roles`);
    }

    async assignRoles(adminId, roleIds) {
        return this.request('POST', `/admin/admins/${adminId}/roles`, { role_ids: roleIds });
    }

    async removeRole(adminId, roleId) {
        return this.request('DELETE', `/admin/admins/${adminId}/roles/${roleId}`);
    }

    // LOGS
    async getLogs(page = 1, limit = 50) {
        return this.request('GET', `/admin/logs?page=${page}&limit=${limit}`);
    }

    // FAQS
    async getFaqs() {
        return this.request('GET', '/admin/faqs');
    }

    async createFaq(data) {
        return this.request('POST', '/admin/faqs', data);
    }

    async updateFaq(id, data) {
        return this.request('PUT', `/admin/faqs/${id}`, data);
    }

    async deleteFaq(id) {
        return this.request('DELETE', `/admin/faqs/${id}`);
    }

    // SUPPORT TICKETS
    async getSupportTickets(page = 1, limit = 50, filters = {}) {
        const params = new URLSearchParams({ page, limit });
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.append(key, value);
            }
        });
        return this.request('GET', `/admin/support-tickets?${params.toString()}`);
    }

    async createSupportTicket(data) {
        return this.request('POST', '/admin/support-tickets', data);
    }

    async updateSupportTicket(id, data) {
        return this.request('PUT', `/admin/support-tickets/${id}`, data);
    }

    async addSupportReply(id, message) {
        return this.request('POST', `/admin/support-tickets/${id}/replies`, { message });
    }

    async getSupportMessages(id) {
        return this.request('GET', `/admin/support-tickets/${id}/messages`);
    }

    // LOGOUT
    async logout() {
        try {
            await this.request('POST', '/admin/logout', {});
        } finally {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_data');
            window.location.href = '/admin/login.html';
        }
    }

    /**
     * Check if token is still valid
     */
    isAuthenticated() {
        return !!this.token;
    }

    /**
     * Update token after login
     */
    setToken(token) {
        this.token = token;
        localStorage.setItem('admin_token', token);
    }
}

// Create global API client instance
const api = new APIClient();

/**
 * Utility functions for form handling
 */
const FormUtils = {
    /**
     * Show success message
     */
    showSuccess(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.insertBefore(alert, document.body.firstChild);
        setTimeout(() => alert.remove(), 3000);
    },

    /**
     * Show error message
     */
    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.insertBefore(alert, document.body.firstChild);
        setTimeout(() => alert.remove(), 5000);
    },

    /**
     * Get form data as object
     */
    getFormData(formId) {
        const form = document.getElementById(formId);
        if (!form) return null;
        
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (value === 'on' || value === 'true') {
                data[key] = true;
            } else if (value === 'false') {
                data[key] = false;
            } else if (!isNaN(value) && value !== '') {
                data[key] = parseInt(value);
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },

    /**
     * Populate form with data
     */
    populateForm(formId, data) {
        const form = document.getElementById(formId);
        if (!form) return;

        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = !!data[key];
                } else {
                    field.value = data[key] || '';
                }
            }
        });
    },

    /**
     * Clear form
     */
    clearForm(formId) {
        const form = document.getElementById(formId);
        if (form) form.reset();
    },

    /**
     * Disable form during submission
     */
    disableForm(formId, disabled = true) {
        const form = document.getElementById(formId);
        if (!form) return;

        const inputs = form.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => input.disabled = disabled);
    },

    /**
     * Build query parameters
     */
    buildQuery(obj) {
        return Object.keys(obj)
            .filter(key => obj[key] != null && obj[key] !== '')
            .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(obj[key])}`)
            .join('&');
    }
};

/**
 * Data Table Helper
 */
class DataTable {
    constructor(tableId, columns) {
        this.table = document.getElementById(tableId);
        this.columns = columns;
        this.data = [];
        this.currentPage = 1;
        this.limit = 10;
    }

    /**
     * Render table with data
     */
    render(data = []) {
        this.data = data;
        const tbody = this.table.querySelector('tbody');
        
        if (!tbody) {
            console.error('Table body not found');
            return;
        }

        // Clear existing rows
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${this.columns.length}" class="text-center text-muted">No data available</td></tr>`;
            return;
        }

        // Add rows
        data.forEach(row => {
            const tr = document.createElement('tr');
            this.columns.forEach(col => {
                const td = document.createElement('td');
                
                if (col.render) {
                    td.innerHTML = col.render(row[col.field], row);
                } else if (col.field === 'actions') {
                    td.innerHTML = row.actions || '';
                } else {
                    td.textContent = row[col.field] || '-';
                }
                
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    /**
     * Add action buttons to row
     */
    addActions(row, actions) {
        const buttons = actions.map(action => 
            `<button class="btn btn-sm btn-${action.class || 'primary'}" onclick="${action.onclick}">${action.label}</button>`
        ).join(' ');
        row.actions = buttons;
        return row;
    }
}

export { APIClient, FormUtils, DataTable };
