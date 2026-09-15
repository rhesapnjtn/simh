import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guest: true, title: 'Login' },
    },
    {
        path: '/',
        component: () => import('../layouts/MainLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { title: 'Dashboard', permission: 'dashboard.view' } },
            { path: 'rooms', name: 'rooms', component: () => import('../views/rooms/RoomsView.vue'), meta: { title: 'Manajemen Kamar', permission: 'rooms.view' } },
            { path: 'room-types', name: 'room-types', component: () => import('../views/rooms/RoomTypesView.vue'), meta: { title: 'Tipe Kamar', permission: 'room_types.view' } },
            { path: 'guests', name: 'guests', component: () => import('../views/guests/GuestsView.vue'), meta: { title: 'Tamu', permission: 'guests.view' } },
            {
                path: 'reservations',
                name: 'reservations',
                component: () => import('../views/reservations/ReservationsView.vue'),
                meta: { title: 'Reservasi', permission: 'reservations.view', permissionOr: 'reservations.own' },
            },
            {
                path: 'reservations/create',
                name: 'reservations-create',
                component: () => import('../views/reservations/ReservationFormView.vue'),
                meta: { title: 'Reservasi Baru', permission: 'reservations.create' },
            },
            {
                path: 'reservations/:id/edit',
                name: 'reservations-edit',
                component: () => import('../views/reservations/ReservationFormView.vue'),
                meta: { title: 'Ubah Reservasi', permission: 'reservations.create', permissionOr: 'reservations.update' },
            },
            {
                path: 'reservations/:id',
                name: 'reservations-detail',
                component: () => import('../views/reservations/ReservationDetailView.vue'),
                meta: { title: 'Detail Reservasi', permission: 'reservations.view', permissionOr: 'reservations.own' },
            },
            { path: 'payments', name: 'payments', component: () => import('../views/payments/PaymentsView.vue'), meta: { title: 'Pembayaran', permission: 'payments.view' } },
            { path: 'housekeeping', name: 'housekeeping', component: () => import('../views/housekeeping/HousekeepingView.vue'), meta: { title: 'Housekeeping', permission: 'housekeeping.view' } },
            { path: 'reports', name: 'reports', component: () => import('../views/reports/ReportsView.vue'), meta: { title: 'Laporan', permission: 'reports.view' } },
            {
                path: 'users',
                name: 'users',
                component: () => import('../views/users/UsersView.vue'),
                meta: { title: 'Pengguna', permission: 'users.manage' },
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../views/settings/SettingsView.vue'),
                meta: { title: 'Pengaturan', permission: 'settings.manage' },
            },
            {
                path: 'logs',
                name: 'logs',
                component: () => import('../views/logs/ActivityLogsView.vue'),
                meta: { title: 'Log Aktivitas', permission: 'logs.view' },
            },
            { path: 'food-items', name: 'food-items', component: () => import('../views/fb/FoodItemsView.vue'), meta: { title: 'Menu F&B', permission: 'fb.view' } },
            { path: 'food-orders', name: 'food-orders', component: () => import('../views/fb/FoodOrdersView.vue'), meta: { title: 'Order F&B', permission: 'fb.view' } },
            { path: 'purchase-orders', name: 'purchase-orders', component: () => import('../views/purchasing/PurchaseOrdersView.vue'), meta: { title: 'Purchase Order', permission: 'purchasing.view' } },
            { path: 'suppliers', name: 'suppliers', component: () => import('../views/purchasing/SuppliersView.vue'), meta: { title: 'Supplier', permission: 'suppliers.view' } },
            { path: 'inventory', name: 'inventory', component: () => import('../views/inventory/InventoryView.vue'), meta: { title: 'Inventory', permission: 'inventory.view' } },
            { path: 'maintenance', name: 'maintenance', component: () => import('../views/engineering/MaintenanceView.vue'), meta: { title: 'Maintenance', permission: 'engineering.view' } },
            { path: 'employees', name: 'employees', component: () => import('../views/hrd/EmployeesView.vue'), meta: { title: 'Karyawan', permission: 'hr.view' } },
            { path: 'events', name: 'events', component: () => import('../views/events/EventsView.vue'), meta: { title: 'Event', permission: 'events.view' } },
            {
                path: 'events/create',
                name: 'events-create',
                component: () => import('../views/events/EventFormView.vue'),
                meta: { title: 'Event Baru', permission: 'events.create' },
            },
            {
                path: 'events/:id/edit',
                name: 'events-edit',
                component: () => import('../views/events/EventFormView.vue'),
                meta: { title: 'Ubah Event', permission: 'events.create', permissionOr: 'events.update' },
            },
            {
                path: 'events/:id',
                name: 'events-detail',
                component: () => import('../views/events/EventDetailView.vue'),
                meta: { title: 'Detail Event', permission: 'events.view' },
            },
            { path: 'event-venues', name: 'event-venues', component: () => import('../views/events/EventVenuesView.vue'), meta: { title: 'Venue Event', permission: 'venues.manage' } },
            { path: 'ayce-packages', name: 'ayce-packages', component: () => import('../views/events/AycePackagesView.vue'), meta: { title: 'Paket AYCE', permission: 'ayce.manage' } },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        try {
            await auth.fetchUser();
        } catch (error) {
            auth.user = null;
        }
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (auth.isAuthenticated && to.meta.permission) {
        const allowed = auth.can(to.meta.permission) || (to.meta.permissionOr && auth.can(to.meta.permissionOr));
        if (!allowed) {
            return { name: 'dashboard' };
        }
    }

    if (auth.isAuthenticated && to.meta.roles) {
        const allowed = to.meta.roles.includes(auth.user?.role);
        if (!allowed) {
            return { name: 'dashboard' };
        }
    }

    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} · SIMH` : 'SIMH';
});

export default router;