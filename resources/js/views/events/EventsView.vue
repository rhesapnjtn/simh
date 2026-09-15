<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Event</h2>
            <el-button v-if="auth.can('events.create')" type="primary" :icon="Plus" @click="$router.push({ name: 'events-create' })">Jadwalkan Event</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar flex-wrap gap-3">
                <el-input v-model="filters.search" placeholder="Cari kode/nama/kontak..." clearable style="width: 230px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.status" clearable placeholder="Status" style="width: 150px" @change="load">
                    <el-option v-for="(meta, key) in eventStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.event_type" clearable placeholder="Tipe Event" style="width: 170px" @change="load">
                    <el-option v-for="(label, key) in eventTypeLabel" :key="key" :label="label" :value="key" />
                </el-select>
                <el-select v-model="filters.payment_status" clearable placeholder="Pembayaran" style="width: 150px" @change="load">
                    <el-option v-for="(meta, key) in paymentStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="events" stripe class="mt-4">
                <el-table-column prop="code" label="Kode" width="120">
                    <template #default="{ row }">
                        <span class="font-mono text-xs font-semibold text-gray-700">{{ row.code }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Event" min-width="220">
                    <template #default="{ row }">
                        <router-link :to="{ name: 'events-detail', params: { id: row.id } }" class="font-medium text-sky-600 hover:underline">{{ row.title }}</router-link>
                        <div class="text-xs text-gray-400">{{ row.event_type_label }} · {{ row.pax }} pax</div>
                    </template>
                </el-table-column>
                <el-table-column label="Venue" width="140">
                    <template #default="{ row }">{{ row.venue?.name || 'Paket AYCE' }}</template>
                </el-table-column>
                <el-table-column label="Tanggal" min-width="170">
                    <template #default="{ row }">
                        {{ row.start_date }} <span v-if="row.days > 1" class="text-gray-400">s/d {{ row.end_date }}</span>
                        <div class="text-xs text-gray-400">{{ row.days }} hari</div>
                    </template>
                </el-table-column>
                <el-table-column label="Total" width="130" align="right">
                    <template #default="{ row }">
                        <span class="tabular-nums font-medium">{{ formatCurrency(row.total_amount) }}</span>
                        <div class="text-xs font-normal text-gray-400">bayar {{ formatCurrency(row.paid_amount) }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Status" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="eventStatusMeta[row.status]?.type">{{ eventStatusMeta[row.status]?.label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Pembayaran" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="paymentStatusMeta[row.payment_status]?.type">{{ paymentStatusMeta[row.payment_status]?.label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="" width="60" align="center">
                    <template #default="{ row }">
                        <el-button size="small" text :icon="ArrowRight" @click="$router.push({ name: 'events-detail', params: { id: row.id } })" />
                    </template>
                </el-table-column>
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
import { onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Search, ArrowRight } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { formatCurrency, eventStatusMeta, eventTypeLabel, paymentStatusMeta } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const events = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', status: undefined, event_type: undefined, payment_status: undefined });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/events', {
            params: {
                search: filters.search || undefined,
                status: filters.status || undefined,
                event_type: filters.event_type || undefined,
                payment_status: filters.payment_status || undefined,
                page: pagination.current_page,
            },
        });
        events.value = res.data.data;
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