import axios from 'axios';

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
}

// Pakai cookie XSRF-TOKEN (di-refresh Laravel pada setiap respons)
// sehingga token selalu segar meskipun session di-regenerate saat login.
const http = axios.create({
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) {
        config.headers['X-XSRF-TOKEN'] = xsrf;
    }
    delete config.headers['X-CSRF-TOKEN'];
    return config;
});

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status;

        if (status === 401) {
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }

        // CSRF habis (mis. halaman lama dibuka terlalu lama) → refresh satu kali
        // lewat GET / agar session + cookie XSRF-TOKEN baru, lalu ulangi request.
        if (status === 419 && !error.config?.__csrfRetried) {
            const config = { ...error.config, __csrfRetried: true };
            try {
                await http.get('/');
                return http.request(config);
            } catch (e) {
                // Fallback: teruskan error asli jika refresh gagal.
            }
        }

        return Promise.reject(error);
    }
);

window.axios = http;