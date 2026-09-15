import { defineStore } from 'pinia';
import http from '../api/http';
import { getErrorMessage } from '../api/http';
import { roleMeta } from '../utils/helpers';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        permissions: [],
        loading: false,
        initialized: false,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.user),
        isAdmin: (state) => ['admin', 'super_admin'].includes(state.user?.role),
        isManager: (state) => ['manager', 'general_manager', 'supervisor'].includes(state.user?.role),
        canViewReports: (state) => state.permissions.includes('reports.view') || state.permissions.includes('*'),
        roleLabel: (state) => roleMeta[state.user?.role]?.label ?? '-',
    },
    actions: {
        async fetchUser() {
            try {
                const { data } = await http.get('/me');
                this.user = data.user;
                this.permissions = data.permissions ?? [];
            } catch (error) {
                this.user = null;
                this.permissions = [];
                throw error;
            } finally {
                this.initialized = true;
            }
        },
        async login(payload) {
            this.loading = true;
            try {
                const { data } = await http.post('/login', payload);
                this.user = data.user;
                this.permissions = data.permissions ?? [];
                return data;
            } catch (error) {
                throw new Error(getErrorMessage(error, 'Gagal login.'));
            } finally {
                this.loading = false;
            }
        },
        async logout() {
            try {
                await http.post('/logout');
            } finally {
                this.user = null;
                this.permissions = [];
            }
        },
        can(permission) {
            return this.permissions.includes('*') || this.permissions.includes(permission);
        },
    },
});