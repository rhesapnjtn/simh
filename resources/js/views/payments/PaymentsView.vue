<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Transaksi Pembayaran</h2>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari kode reservasi/tamu/referensi..." clearable style="width: 260px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.method" placeholder="Semua metode" clearable style="width: 180px" @change="load">
                    <el-option v-for="(label, key) in methodsLabel" :key="key" :label="label" :value="key" />
                </el-select>
                <el-date-picker v-model="filters.from" type="date" placeholder="Dari" value-format="YYYY-MM-DD" style="width: 150px" @change="load" />
                <el-date-picker v-model="filters.to" type="date" placeholder="Sampai" value-format="YYYY-MM-DD" style="width: 150px" @change="load" />
                <div class="ml-auto flex items-center gap-2 text-sm">
                    <span class="text-gray-500">Total di filter:</span>
                    <span class="text-lg font-bold text-emerald-600">{{ formatCurrency(displayTotal) }}</span>
                </div>
            </div>

            <el-table v-loading="loading" :data="payments" stripe class="mt-4">
                <el-table-column label="Waktu" prop="paid_at" width="160" />
                <el-table-column label="Reservasi" width="160">
                    <template #default="{ row }">
                        <router-link :to="{ name: 'reservations-detail', params: { id: row.reservation_id } }" class="text-sky-600 hover:underline">
                            {{ row.code }}
                        </router-link>
                    </template>
                </el-table-column>
                <el-table-column prop="guest" label="Tamu" min-width="160" />
                <el-table-column label="Metode" width="150">
                    <template #default="{ row }">
                        <el-tag size="small" type="info">{{ methodsLabel[row.method] }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reference" label="Referensi" min-width="120" />
                <el-table-column label="Jumlah" width="140" align="right">
                    <template #default="{ row }">
                        <span class="font-medium text-emerald-600">{{ formatCurrency(row.amount) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="user" label="Petugas" width="120" />
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-if="pagination.total > pagination.per_page"
                    background
                    layout="prev, pager, next, total"
                    :total="pagination.total"
                    :page-size="pagination.per_page"
                    :current-page="pagination.current_page"
                    @current-change="(page) => { pagination.current_page = page; load(); }"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { formatCurrency, methodsLabel } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const payments = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', method: '', from: '', to: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 15 });

const displayTotal = computed(() => payments.value.reduce((a, p) => a + Number(p.amount), 0));

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/payments', {
            params: {
                search: filters.search || undefined,
                method: filters.method || undefined,
                from: filters.from || undefined,
                to: filters.to || undefined,
                page: pagination.current_page,
            },
        });
        payments.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}
</script>