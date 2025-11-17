/**
 * FlexNet User API Client
 * Handles all API calls for user interface with JWT authentication
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
    async changePin(oldPin, newPin) {
        return this.request('POST', '/user/change-pin', { 
            old_pin: oldPin, 
            new_pin: newPin 
        });
    }

    // PAYMENT
    async getDefaultPlan() {
        return this.request('GET', '/user/plan');
    }

    async initiatePayment(phoneNumber, paymentMethod, amount, planId) {
        return this.request('POST', '/payments/initiate', {
            phone_number: phoneNumber,
            payment_method: paymentMethod,
            amount: amount,
            plan_id: planId
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

    /**
     * Initialize auth check on page load
     */
    initAuthCheck() {
        if (!this.isAuthenticated()) {
            localStorage.removeItem('user_token');
            localStorage.removeItem('user_data');
            window.location.href = '/user/login.html';
        }
    }
}

// Create global API client instance
const api = new UserAPIClient();

// Auto-check authentication on protected pages (excluding login and onboarding)
document.addEventListener('DOMContentLoaded', function () {
    const path = window.location.pathname;
    if (!path.includes('/user/login.html') && !path.includes('/user/onboarding.html')) {
        api.initAuthCheck();
    }
});

/**
 * Utility functions for form handling
 */
const FormUtils = {
    /**
     * Get form data as object
     */
    getFormData(formId) {
        const form = document.getElementById(formId);
        if (!form) return {};
        const formData = new FormData(form);
        return Object.fromEntries(formData);
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
