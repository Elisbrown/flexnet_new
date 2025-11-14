/**
 * FlexNet User API Client
 * Handles all API calls for user interface
 */

class UserAPIClient {
    constructor() {
        this.baseURL = 'http://localhost:3000/api';
        this.token = localStorage.getItem('user_token');
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

    // USER PROFILE
    async getUserProfile() {
        return this.request('GET', '/user/profile');
    }

    async updateUserProfile(data) {
        return this.request('PUT', '/user/profile', data);
    }

    // USER SUBSCRIPTIONS
    async getUserSubscriptions() {
        return this.request('GET', '/user/subscriptions');
    }

    // USER BILLING
    async getUserBilling() {
        return this.request('GET', '/user/billing');
    }

    // USER PIN CHANGE
    async changePin(currentPin, newPin) {
        return this.request('POST', '/user/change-pin', { 
            current_pin: currentPin, 
            new_pin: newPin 
        });
    }

    // PAYMENT
    async initiatePayment(phoneNumber, amount) {
        return this.request('POST', '/payments/initiate', { 
            phone_number: phoneNumber,
            amount: amount
        });
    }

    async getPaymentStatus(transactionId) {
        return this.request('GET', `/payments/status/${transactionId}`);
    }

    // LOGOUT
    async logout() {
        try {
            await this.request('POST', '/user/logout', {});
        } finally {
            localStorage.removeItem('user_token');
            localStorage.removeItem('user_data');
            window.location.href = '/user/login.html';
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
        localStorage.setItem('user_token', token);
    }

    /**
     * Get stored user data
     */
    getUserData() {
        const data = localStorage.getItem('user_data');
        return data ? JSON.parse(data) : null;
    }

    /**
     * Store user data
     */
    setUserData(data) {
        localStorage.setItem('user_data', JSON.stringify(data));
    }
}

// Create global API client instance
const api = new UserAPIClient();

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
    }
};
