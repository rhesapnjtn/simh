<template>
    <div class="space-y-4">
        <h2 class="page-title">Log Aktivitas</h2>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari deskripsi kegiatan..." clearable style="width: 280px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.action" placeholder="Semua jenis" clearable style="width: 180px" @change="load">
                    <el-option v-for="a in actions" :key="a" :value="a" :label="actionLabel(a)" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="logs" stripe class="mt-4">
                <el-table-column prop="created_at" label="Waktu" width="175" />
                <el-table-column label="Aksi" width="130">
                    <template #default="{ row }">
                        <el-tag size="small" :type="actionType(row.action)">{{ actionLabel(row.action) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="description" label="Deskripsi" min-width="280" />
                <el-table-column prop="user" label="Pengguna" width="140" />
                <el-table-column prop="ip_address" label="IP" width="130" />
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
import { Search } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const logs = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', action: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 20 });

const actions = ['login', 'logout', 'create', 'update', 'delete', 'confirm', 'check_in', 'check_out', 'cancel', 'no_show', 'assign', 'status', 'payment', 'charge', 'void'];

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/logs', {
            params: {
                search: filters.search || undefined,
                action: filters.action || undefined,
                page: pagination.current_page,
            },
        });
        logs.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function actionLabel(action) {
    const map = {
        login: 'Login',
        logout: 'Logout',
        create: 'Buat',
        update: 'Ubah',
        delete: 'Hapus',
        confirm: 'Konfirmasi',
        check_in: 'Check-in',
        check_out: 'Check-out',
        cancel: 'Batal',
        no_show: 'No-Show',
        assign: 'Tugaskan',
        status: 'Status',
        payment: 'Bayar',
        charge: 'Biaya',
        void: 'Batal Biaya',
    };
    return map[action] ?? action;
}

function actionType(action) {
    const map = {
        login: 'success',
        logout: 'info',
        create: 'success',
        update: 'warning',
        delete: 'danger',
        confirm: 'warning',
        check_in: 'success',
        check_out: 'primary',
        cancel: 'danger',
        no_show: 'danger',
        assign: 'warning',
        status: 'info',
        payment: 'success',
        charge: 'warning',
        void: 'danger',
    };
    return map[action] ?? 'info';
}
</script>