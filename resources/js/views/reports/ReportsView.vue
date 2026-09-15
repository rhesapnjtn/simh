<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Laporan</h2>
            <el-button :icon="Download" @click="exportCSV">Export CSV</el-button>
        </div>

        <div class="card p-4">
<div class="toolbar">
                <el-radio-group v-model="tab">
                    <el-radio-button value="revenue">Pendapatan</el-radio-button>
                    <el-radio-button value="occupancy">Tingkat Hunian</el-radio-button>
                </el-radio-group>

                <el-date-picker
                    v-model="range"
                    type="daterange"
                    range-separator="sampai"
                    start-placeholder="Mulai"
                    end-placeholder="Selesai"
                    value-format="YYYY-MM-DD"
                />
                <el-button type="primary" :icon="Search" @click="load">Tampilkan</el-button>
            </div>
        </div>

        <el-skeleton v-if="loading" :rows="8" animated />

        <template v-else-if="tab === 'revenue' && revenue">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatCard label="Total Pendapatan" :value="formatCurrency(revenue.total)" icon="Wallet" value-class="text-emerald-600" />
                <StatCard label="Jumlah Transaksi" :value="revenue.count" icon="Tickets" />
                <StatCard label="Rata-rata/Transaksi" :value="formatCurrency(revenue.count ? revenue.total / revenue.count : 0)" icon="DataLine" />
                <StatCard label="Metode Terbanyak" :value="topMethod" icon="CreditCard" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="card p-5 lg:col-span-2">
                    <h3 class="mb-4 font-semibold text-gray-800">Pendapatan Harian</h3>
                    <BarChart :items="revenue.daily" color="#0ea5e9" format="currency" />
                    <div class="mt-4 overflow-x-auto">
                        <el-table :data="revenue.daily" size="small" max-height="320" stripe>
                            <el-table-column prop="date" label="Tanggal" width="120" />
                            <el-table-column prop="label" label="Hari" width="100" />
                            <el-table-column label="Pendapatan" align="right">
                                <template #default="{ row }">
                                    <span class="font-medium">{{ formatCurrency(row.value) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>

                <div class="space-y-5">
                    <el-card shadow="never">
                        <template #header><span class="font-semibold">Berdasarkan Metode</span></template>
                        <div v-for="(total, method) in revenue.by_method" :key="method" class="mb-3 flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ methodsLabel[method] }}</span>
                            <span class="font-semibold">{{ formatCurrency(total) }}</span>
                        </div>
                    </el-card>

                    <el-card shadow="never">
                        <template #header><span class="font-semibold">Petugas Terbaik</span></template>
                        <div v-for="u in revenue.by_user" :key="u.name" class="mb-3 flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ u.name }}</span>
                            <span class="font-semibold">{{ formatCurrency(u.total) }}</span>
                        </div>
                    </el-card>

                    <el-card shadow="never">
                        <template #header><span class="font-semibold">Tamu dengan Transaksi Terbanyak</span></template>
                        <div v-for="g in revenue.top_guests" :key="g.name" class="mb-3 flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ g.name }} <span class="text-xs text-gray-400">({{ g.count }}x)</span></span>
                            <span class="font-semibold">{{ formatCurrency(g.total) }}</span>
                        </div>
                    </el-card>
                </div>
            </div>
        </template>

        <template v-else-if="tab === 'occupancy' && occupancy">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatCard label="Rata-rata Hunian" :value="`${occupancy.average_occupancy}%`" icon="TrendCharts" value-class="text-sky-600" />
                <StatCard label="Total Kamar" :value="occupancy.total_rooms" icon="OfficeBuilding" />
                <StatCard label="Room-Night Tersedia" :value="occupancy.total_available_nights" icon="Calendar" />
                <StatCard label="Room-Night Terpakai" :value="occupancy.total_occupied_nights" icon="House" value-class="text-rose-600" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="card p-5 lg:col-span-2">
                    <h3 class="mb-4 font-semibold text-gray-800">Hunian Harian (%)</h3>
                    <BarChart :items="occupancy.daily" color="#10b981" suffix="%" />
                    <div class="mt-4 overflow-x-auto">
                        <el-table :data="occupancy.daily" size="small" max-height="320" stripe>
                            <el-table-column prop="date" label="Tanggal" width="120" />
                            <el-table-column prop="available" label="Tersedia" align="center" width="90" />
                            <el-table-column prop="occupied" label="Terisi" align="center" width="90" />
                            <el-table-column label="Tingkat Hunian" align="center">
                                <template #default="{ row }">
                                    <el-progress :percentage="row.rate" :stroke-width="8" />
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Hunian per Tipe Kamar</span></template>
                    <div v-for="type in occupancy.by_type" :key="type.name" class="mb-4">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-700">{{ type.name }}</span>
                            <span class="text-gray-500">{{ type.rate }}%</span>
                        </div>
                        <el-progress :percentage="type.rate" :stroke-width="10" />
                        <div class="mt-1 text-xs text-gray-400">{{ type.occupied_nights }} / {{ type.available_nights }} room-night</div>
                    </div>
                </el-card>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { Download, Search } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import StatCard from '../../components/StatCard.vue';
import BarChart from '../../components/BarChart.vue';
import { formatCurrency, methodsLabel, today } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const tab = ref('revenue');
const loading = ref(false);
const revenue = ref(null);
const occupancy = ref(null);

function monthRange() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return [fmt(first), fmt(last)];
}

const [fromDefault, toDefault] = monthRange();
const range = ref([fromDefault, toDefault]);

const topMethod = computed(() => {
    const byMethod = revenue.value?.by_method ?? {};
    const entries = Object.entries(byMethod);
    return entries.length ? methodsLabel[entries.sort((a, b) => b[1] - a[1])[0][0]] : '-';
});

onMounted(load);

useAutoRefresh(load);

async function load() {
    if (!range.value || range.value.length !== 2) {
        ElMessage.warning('Pilih rentang tanggal terlebih dahulu.');
        return;
    }
    loading.value = !revenue.value && !occupancy.value;
    try {
        const params = { from: range.value[0], to: range.value[1] };
        if (tab.value === 'revenue') {
            const res = await http.get('/reports/revenue', { params });
            revenue.value = res.data;
        } else {
            const res = await http.get('/reports/occupancy', { params });
            occupancy.value = res.data;
        }
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function exportCSV() {
    if (!range.value || range.value.length !== 2) return;
    const params = new URLSearchParams({
        from: range.value[0],
        to: range.value[1],
        type: tab.value,
    });
    const link = document.createElement('a');
    link.href = `/reports/export?${params.toString()}`;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    link.remove();
}
</script>