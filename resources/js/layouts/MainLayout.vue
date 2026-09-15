<template>
    <div class="flex h-screen overflow-hidden bg-slate-50 text-slate-800 antialiased">
        <!-- Backdrop mobile -->
        <transition name="fade">
            <div
                v-if="isMobile && drawerOpen"
                class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
                @click="drawerOpen = false"
            />
        </transition>

        <!-- Sidebar -->
        <aside
            class="flex shrink-0 flex-col border-r border-slate-200 bg-white transition-[width,transform] duration-300 ease-in-out"
            :class="isMobile ? 'fixed inset-y-0 left-0 z-50 lg:hidden' : 'relative z-30 hidden lg:flex'"
            :style="isMobile ? { width: '264px', transform: drawerOpen ? 'translateX(0)' : 'translateX(-100%)' } : { width: collapsed ? '76px' : '256px' }"
        >
            <!-- Brand -->
            <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-4" :class="collapsed && !isMobile ? 'justify-center px-0' : ''">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-sky-700 text-lg font-bold text-white shadow-sm shadow-sky-600/30">
                    S
                </div>
                <div v-if="showLabels" class="min-w-0 leading-tight">
                    <div class="truncate text-[15px] font-bold tracking-tight text-slate-800">SIMH Hotel</div>
                    <div class="truncate text-[11px] font-medium text-slate-400">Management System</div>
                </div>
            </div>

            <!-- Navigation -->
            <el-scrollbar class="flex-1" view-class="py-3">
                <nav class="flex flex-col gap-0.5 px-3">
                    <template v-for="item in visibleNav" :key="item.to">
                        <div
                            v-if="item.section"
                            class="flex items-center py-2 pl-3 pr-2"
                            :class="showLabels ? 'mt-3 justify-start' : 'mx-auto mt-3 justify-center'"
                        >
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ item.section }}</span>
                            <span v-if="!showLabels" class="h-px w-5 rounded bg-slate-200"></span>
                        </div>
                        <router-link
                            v-else
                            :to="item.to"
                            :exact="item.to === '/'"
                            class="sidebar-link"
                            :class="!showLabels ? 'sidebar-link--square' : ''"
                            :title="collapsed ? item.label : undefined"
                            @click="drawerOpen = false"
                        >
                            <el-icon :size="19" class="shrink-0"><component :is="item.icon" /></el-icon>
                            <span v-if="showLabels" class="flex-1 truncate">{{ item.label }}</span>
                            <span v-if="item.badge && showLabels" class="sidebar-badge">{{ item.badge }}</span>
                        </router-link>
                    </template>
                </nav>
            </el-scrollbar>

            <!-- Sidebar footer -->
            <div class="border-t border-slate-100 p-3">
                <button
                    type="button"
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    @click="isMobile ? (drawerOpen = false) : (collapsed = !collapsed)"
                >
                    <el-icon :size="18">
                        <component :is="isMobile ? Close : collapsed ? Expand : Fold" />
                    </el-icon>
                    <span v-if="!isMobile && showLabels">{{ collapsed ? 'Perluas' : 'Ciutkan' }}</span>
                </button>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Header -->
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur-md sm:h-16 sm:px-6">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    @click="isMobile ? (drawerOpen = true) : (collapsed = !collapsed)"
                >
                    <el-icon :size="20">
                        <component :is="isMobile ? Expand : collapsed ? Expand : Fold" />
                    </el-icon>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold tracking-tight text-slate-800 sm:text-lg">{{ pageTitle }}</h1>
                    <p class="hidden truncate text-xs text-slate-400 sm:block">{{ todayLabel }}</p>
                </div>

                <!-- Auto-refresh toggle -->
                <el-tooltip :content="ui.autoRefresh ? 'Auto-refresh aktif · tiap 30 detik' : 'Auto-refresh nonaktif'" placement="bottom">
                    <button
                        type="button"
                        class="relative flex h-9 w-9 items-center justify-center rounded-lg border transition"
                        :class="ui.autoRefresh ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300'"
                        @click="ui.setAutoRefresh(!ui.autoRefresh)"
                    >
                        <el-icon :size="17"><Refresh /></el-icon>
                        <span v-if="ui.autoRefresh" class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    </button>
                </el-tooltip>

                <div class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500 md:flex">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>{{ auth.roleLabel }}</span>
                </div>

                <el-dropdown trigger="click" @command="onCommand">
                    <button type="button" class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-2 transition hover:border-slate-300 sm:pr-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-sky-700 text-xs font-bold uppercase text-white">{{ initials }}</span>
                        <span class="hidden text-left leading-tight sm:block">
                            <span class="block max-w-[120px] truncate text-xs font-semibold text-slate-800">{{ auth.user?.name }}</span>
                        </span>
                        <el-icon class="text-slate-400" :size="14"><ArrowDown /></el-icon>
                    </button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="logout" divided>
                                <el-icon><SwitchButton /></el-icon> Keluar
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <router-view v-slot="{ Component }">
                    <transition name="page" mode="out-in">
                        <component :is="Component" />
                    </transition>
                </router-view>
            </main>

            <footer class="hidden shrink-0 border-t border-slate-200 bg-white/60 px-6 py-2 text-center text-[11px] text-slate-400 sm:block">
                © {{ year }} SIMH Hotel Management System · Dibangun dengan Laravel & Vue
            </footer>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import {
    ArrowDown, Avatar, Box, Brush, Calendar, Close, CollectionTag, DataAnalysis, Document, Dish, Expand,
    Fold, Food, Goods, Notebook, Odometer, OfficeBuilding, Refresh, Setting, ShoppingTrolley, Suitcase, SwitchButton,
    Tools, User, UserFilled, Wallet,
} from '@element-plus/icons-vue';
import { useAuthStore } from '../stores/auth';
import { ui } from '../stores/ui';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const mq = window.matchMedia('(min-width: 1024px)');
const isMobile = ref(!mq.matches);
const drawerOpen = ref(false);
const collapsed = ref(localStorage.getItem('simh.sidebar') === 'collapsed');

const year = new Date().getFullYear();

const pageTitle = computed(() => route.meta.title ?? 'Dashboard');
const showLabels = computed(() => !isMobile.value ? !collapsed.value : true);
const todayLabel = computed(() =>
    new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

const initials = computed(() => {
    const name = auth.user?.name ?? '?';
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
});

const allNav = [
    { to: '/', label: 'Dashboard', icon: Odometer, permission: 'dashboard.view' },
    { to: '/reservations', label: 'Reservasi', icon: Calendar, permission: ['reservations.view', 'reservations.own'] },
    { to: '/guests', label: 'Tamu', icon: User, permission: 'guests.view' },
    { to: '/rooms', label: 'Manajemen Kamar', icon: OfficeBuilding, permission: 'rooms.view' },
    { to: '/room-types', label: 'Tipe Kamar', icon: CollectionTag, permission: 'room_types.view' },
    { to: '/payments', label: 'Pembayaran', icon: Wallet, permission: 'payments.view' },
    { to: '/housekeeping', label: 'Housekeeping', icon: Brush, permission: 'housekeeping.view' },
    { to: '/reports', label: 'Laporan', icon: DataAnalysis, permission: 'reports.view' },
    { section: 'F&B', permission: 'fb.view' },
    { to: '/food-orders', label: 'Order F&B', icon: Dish, permission: 'fb.view' },
    { to: '/food-items', label: 'Menu F&B', icon: Notebook, permission: 'fb.view' },
    { section: 'Purchasing & Inventory', permission: 'purchasing.view' },
    { to: '/purchase-orders', label: 'Purchase Order', icon: Goods, permission: 'purchasing.view' },
    { to: '/suppliers', label: 'Supplier', icon: Avatar, permission: 'suppliers.view' },
    { to: '/inventory', label: 'Inventory', icon: Box, permission: 'inventory.view' },
    { section: 'Operasional', permission: 'engineering.view' },
    { to: '/maintenance', label: 'Maintenance', icon: Tools, permission: 'engineering.view' },
    { to: '/employees', label: 'Karyawan', icon: Suitcase, permission: 'hr.view' },
    { section: 'Event & Venue', permission: 'events.view' },
    { to: '/events', label: 'Event', icon: Calendar, permission: 'events.view' },
    { to: '/event-venues', label: 'Venue Event', icon: OfficeBuilding, permission: 'venues.manage' },
    { to: '/ayce-packages', label: 'Paket AYCE', icon: Food, permission: 'ayce.manage' },
    { section: 'Monitoring', permission: 'logs.view' },
    { to: '/logs', label: 'Log Aktivitas', icon: Document, permission: 'logs.view' },
    { section: 'Administrasi', permission: 'users.manage' },
    { to: '/users', label: 'Pengguna', icon: UserFilled, permission: 'users.manage' },
    { to: '/settings', label: 'Pengaturan', icon: Setting, permission: 'settings.manage' },
];

function navAllowed(item) {
    if (!item.permission) return true;
    const perms = Array.isArray(item.permission) ? item.permission : [item.permission];
    return perms.some((p) => auth.can(p));
}

const visibleNav = computed(() => allNav.filter(navAllowed));

function onMqChange(e) {
    isMobile.value = !e.matches;
    if (!isMobile.value) drawerOpen.value = false;
}

watch(
    () => route.fullPath,
    () => {
        if (isMobile.value) drawerOpen.value = false;
    }
);

onMounted(() => {
    mq.addEventListener('change', onMqChange);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('focus', onWindowFocus);
});
onBeforeUnmount(() => {
    mq.removeEventListener('change', onMqChange);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('focus', onWindowFocus);
});

let lastAuthCheck = 0;

function revalidateAuth() {
    const now = Date.now();
    if (now - lastAuthCheck < 30000) return;
    lastAuthCheck = now;

    auth.fetchUser().catch(() => {
        if (router.currentRoute.value.name !== 'login') {
            router.replace({ name: 'login' });
        }
    });
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') revalidateAuth();
}

function onWindowFocus() {
    revalidateAuth();
}

async function onCommand(command) {
    if (command === 'logout') {
        try {
            await ElMessageBox.confirm('Apakah Anda yakin ingin keluar?', 'Keluar', {
                confirmButtonText: 'Keluar',
                cancelButtonText: 'Batal',
                type: 'warning',
                confirmButtonClass: 'el-button--danger',
            });
        } catch (error) {
            return;
        }
        await auth.logout();
        router.replace({ name: 'login' });
    }
}
</script>