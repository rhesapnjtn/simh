import axios from 'axios';

const http = window.axios ?? axios;

http.defaults.withCredentials = true;

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const url = error?.config?.url ?? '';

        if ((status === 401 || status === 419) && !url.includes('/login') && url !== '/me') {
            if (!window.__simh_auth_redirecting) {
                window.__simh_auth_redirecting = true;
                const redirect = window.location.pathname + window.location.search;
                window.location.replace(`/login?redirect=${encodeURIComponent(redirect)}`);
            }
        }

        return Promise.reject(error);
    }
);

export function getErrorMessage(error, fallback = 'Terjadi kesalahan, silakan coba lagi.') {
    const data = error?.response?.data;
    if (data?.message) {
        return data.message;
    }
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first)) {
            return first[0];
        }
        if (first) {
            return first;
        }
    }
    if (error?.message) {
        return error.message;
    }
    return fallback;
}

export default http;