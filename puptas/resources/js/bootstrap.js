import axios from 'axios';
import { useErrorStore } from './Composables/useErrorStore';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Get CSRF token and set it for axios
let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error(
        'CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token'
    );
}

window.axios.interceptors.response.use(
    response => response,
    error => {
        const { setError } = useErrorStore()
        if (error.response) {
            const message = error.response.data?.message
                ?? 'An unexpected error occurred. Please try again.'
            setError(message)
        } else {
            setError('Unable to connect. Please check your connection and try again.')
        }
        return Promise.reject(error)
    }
)
