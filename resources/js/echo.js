import Echo from 'laravel-echo';
import axios from 'axios';

// Set up axios defaults for CSRF and authentication
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.baseURL = import.meta.env.VITE_API_URL || 'http://loe-mini-engine-api.test';
window.axios.defaults.withCredentials = true; // Enable cookies for session auth

// Get CSRF token from meta tag or cookie
const getCSRFToken = () => {
    const metaToken = document.head.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.content;
    }

    // Try to get from cookie
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(value);
        }
    }
    return null;
};

const csrfToken = getCSRFToken();
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],

    // Authentication endpoint
    authEndpoint: `${import.meta.env.VITE_API_URL || 'http://loe-mini-engine-api.test'}/api/broadcasting/auth`,

    // Authentication configuration - supports both session and token auth
    auth: {
        headers: () => {
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
            };

            // Add CSRF token if available (for session auth)
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            // Add Bearer token if available (for API auth)
            const authToken = localStorage.getItem('auth_token');
            if (authToken) {
                headers['Authorization'] = `Bearer ${authToken}`;
            }

            return headers;
        },
    },

    // Use axios for HTTP requests with credentials
    client: window.axios,
});
