import { reactive } from 'vue';

const saved = localStorage.getItem('simh.autoRefresh');

export const ui = reactive({
    autoRefresh: saved !== '0',
    setAutoRefresh(value) {
        this.autoRefresh = value;
        localStorage.setItem('simh.autoRefresh', value ? '1' : '0');
    },
});