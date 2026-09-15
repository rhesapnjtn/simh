<template>
    <div>
        <el-skeleton v-if="loading" :rows="10" animated class="mt-2" />

        <div v-else-if="data" class="space-y-6">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatCard label="Tingkat Hunian Hari Ini" :value="`${stats.occupancy_today}%`" icon="TrendCharts" value-class="text-sky-600" />
                <StatCard label="Pendapatan Hari Ini" :value="formatCurrency(stats.revenue_today)" icon="Wallet" value-class="text-emerald-600" />
                <StatCard label="Pendapatan Bulan Ini" :value="formatCurrency(stats.revenue_month)" icon="Money" value-class="text-emerald-600" />
                <StatCard label="Reservasi Aktif" :value="stats.active_reservations" icon="Calendar" />
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <StatCard label="Total Kamar" :value="stats.rooms" icon="OfficeBuilding" hint="Semua kamar hotel" />
                <StatCard label="Kamar Tersedia" :value="stats.rooms_available" icon="CircleCheck" value-class="text-emerald-600" />
                <StatCard label="Kamar Terisi" :value="stats.rooms_occupied" icon="House" value-class="text-rose-600" />
                <StatCard label="Kedatangan Hari Ini" :value="stats.arrivals_today" icon="TopRight" />
                <StatCard label="Check-out Hari Ini" :value="stats.departures_today" icon="BottomLeft" value-class="text-amber-600" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Pendapatan 14 Hari Terakhir</h3>
                        <span class="text-sm text-gray-400">{{ formatCurrency(revenueTotal) }}</span>
                    </div>
                    <BarChart :items="data.revenue_chart" color="#0ea5e9" format="currency" />
                </div>

                <div class="card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Tingkat Hunian 14 Hari Terakhir</h3>
                        <span class="text-sm text-gray-400">rata-rata {{ avgOccupancy }}%</span>
                    </div>
                    <BarChart :items="data.occupancy_chart" color="#10b981" suffix="%" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <el-card shadow="never">
                    <template #header>
                        <span class="font-semibold text-gray-800">Kedatangan Mendatang</span>
                    </template>
                    <el-empty v-if="!data.upcoming_arrivals.length" description="Tidak ada kedatangan" :image-size="60" />
                    <div v-else class="space-y-3">
                        <div v-for="item in data.upcoming_arrivals" :key="item.id" class="flex items-center justify-between border-b border-gray-50 pb-3 last:border-0">
                            <div>
                                <div class="text-sm font-medium text-gray-800">{{ item.guest }}</div>
                                <div class="text-xs text-gray-400">{{ item.code }} · {{ item.check_in_date }} → {{ item.check_out_date }}</div>
                                <div class="text-xs text-gray-500">{{ item.room || 'Kamar belum dipilih' }}</div>
                            </div>
                            <el-tag size="small" type="warning">{{ item.check_in_date }}</el-tag>
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header>
                        <span class="font-semibold text-gray-800">Tamu In-House</span>
                    </template>
                    <el-empty v-if="!data.in_house.length" description="Tidak ada tamu" :image-size="60" />
                    <div v-else class="space-y-3">
                        <div v-for="item in data.in_house" :key="item.id" class="flex items-center justify-between border-b border-gray-50 pb-3 last:border-0">
                            <div>
                                <div class="text-sm font-medium text-gray-800">
                                    {{ item.guest }}
                                    <el-tag v-if="item.balance > 0" size="small" type="danger" class="ml-1">Saldo {{ formatCurrency(item.balance) }}</el-tag>
                                </div>
                                <div class="text-xs text-gray-400">{{ item.code }} · Kamar {{ item.room }} · Check-out {{ item.check_out_date }}</div>
                            </div>
                            <router-link :to="{ name: 'reservations-detail', params: { id: item.id } }">
                                <el-button size="small" text type="primary">Detail</el-button>
                            </router-link>
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header>
                        <span class="font-semibold text-gray-800">Status Reservasi</span>
                    </template>
                    <div class="space-y-3">
                        <div v-for="(total, status) in data.status_breakdown" :key="status" class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-sm text-gray-600">
                                <el-tag size="small" :type="reservationStatus[status].type">{{ reservationStatus[status].label }}</el-tag>
                            </span>
                            <span class="font-semibold text-gray-800">{{ total }}</span>
                        </div>
                        <div class="mt-4 border-t border-gray-100 pt-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Total Reservasi</span>
                                <span class="font-bold text-gray-800">{{ statusTotal }}</span>
                            </div>
                        </div>
                    </div>
                </el-card>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import http, { getErrorMessage } from '../api/http';
import StatCard from '../components/StatCard.vue';
import BarChart from '../components/BarChart.vue';
import { formatCurrency, reservationStatus } from '../utils/helpers';
import { useAutoRefresh } from '../composables/useAutoRefresh';

const loading = ref(true);
const error = ref('');
const data = ref(null);

const stats = computed(() => data.value?.stats ?? {});
const revenueTotal = computed(() => (data.value?.revenue_chart ?? []).reduce((a, b) => a + b.value, 0));
const avgOccupancy = computed(() => {
    const values = data.value?.occupancy_chart ?? [];
    return values.length ? (values.reduce((a, b) => a + b.value, 0) / values.length).toFixed(1) : 0;
});
const statusTotal = computed(() => Object.values(data.value?.status_breakdown ?? {}).reduce((a, b) => a + Number(b), 0));

async function load() {
    if (!data.value) loading.value = true;
    try {
        const res = await http.get('/dashboard');
        data.value = res.data;
    } catch (err) {
        error.value = getErrorMessage(err);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
useAutoRefresh(load);
</script>