import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import dayjs from 'dayjs';
import 'dayjs/locale/id';

import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

dayjs.locale('id');

const app = createApp(App);

for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(createPinia());
app.use(router);
app.use(ElementPlus, { locale: undefined });

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;

    const auth = useAuthStore();
    auth.initialized = false;
    auth.fetchUser().catch(() => {
        auth.user = null;
        auth.permissions = [];
        if (router.currentRoute.value.name !== 'login') {
            router.replace({ name: 'login' });
        }
    });
});

app.mount('#app');