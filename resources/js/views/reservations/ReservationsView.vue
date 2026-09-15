<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Reservasi</h2>
            <el-button type="primary" :icon="Plus" v-if="auth.can('reservations.create')" @click="router.push({ name: 'reservations-create' })">Reservasi Baru</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari kode/tamu..." clearable style="width: 240px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.status" placeholder="Semua status" clearable style="width: 170px" @change="load">
                    <el-option v-for="(meta, key) in reservationStatus" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.payment_status" placeholder="Status bayar" clearable style="width: 160px" @change="load">
                    <el-option v-for="(meta, key) in paymentStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-date-picker
                    v-model="filters.from"
                    type="date"
                    placeholder="Dari tanggal"
                    value-format="YYYY-MM-DD"
                    style="width: 160px"
                    @change="load"
                />
                <el-date-picker
                    v-model="filters.to"
                    type="date"
                    placeholder="Sampai tanggal"
                    value-format="YYYY-MM-DD"
                    style="width: 160px"
                    @change="load"
                />
            </div>

            <el-table v-loading="loading" :data="reservations" stripe class="mt-4">
                <el-table-column label="Kode" width="150">
                    <template #default="{ row }">
                        <router-link :to="{ name: 'reservations-detail', params: { id: row.id } }" class="font-semibold text-sky-600 hover:underline">
                            {{ row.code }}
                        </router-link>
                        <el-tag v-if="row.source !== 'walk_in'" size="small" type="info" class="ml-1">{{ sourceLabel[row.source] }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Tamu" min-width="170">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.guest?.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.guest?.phone || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Kamar" width="100">
                    <template #default="{ row }">
                        <span>{{ row.room?.room_number || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Menginap" min-width="180">
                    <template #default="{ row }">
                        <div class="text-xs text-gray-600">{{ row.check_in_date }}</div>
                        <div class="text-xs text-gray-400">→ {{ row.check_out_date }} ({{ row.nights }} malam)</div>
                    </template>
                </el-table-column>
                <el-table-column label="Total" width="120" align="right">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ formatCurrency(row.total_amount) }}</div>
                        <div class="text-xs" :class="row.balance > 0 ? 'text-rose-500' : 'text-emerald-500'">
                            {{ row.balance > 0 ? `Saldo ${formatCurrency(row.balance)}` : 'Lunas' }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Status" width="120">
                    <template #default="{ row }">
                        <el-tag :type="reservationStatus[row.status].type">{{ reservationStatus[row.status].label }}</el-tag>
                        <div class="mt-1">
                            <el-tag size="small" :type="paymentStatusMeta[row.payment_status].type">{{ paymentStatusMeta[row.payment_status].label }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="190" fixed="right">
                    <template #default="{ row }">
                        <router-link :to="{ name: 'reservations-detail', params: { id: row.id } }">
                            <el-button size="small" text type="primary" :icon="View">Detail</el-button>
                        </router-link>
                        <template v-if="row.status === 'pending' && auth.can('reservations.confirm')">
                            <el-button size="small" text type="warning" @click="confirmReservation(row)">Konfirmasi</el-button>
                        </template>
                        <template v-if="['confirmed', 'pending'].includes(row.status) && (auth.can('reservations.checkin') || auth.can('reservations.no_show') || auth.can('reservations.cancel'))">
                            <el-button v-if="auth.can('reservations.checkin')" size="small" text type="success" @click="checkInAction(row)">Check-in</el-button>
                            <el-dropdown v-if="auth.can('reservations.no_show') || auth.can('reservations.cancel')" trigger="click" @command="(cmd) => onCommand(cmd, row)">
                                <el-button size="small" text type="danger">Lainnya</el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="no-show" :disabled="!canNoShow(row)">Tandai No-Show</el-dropdown-item>
                                        <el-dropdown-item command="cancel">Batalkan</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                        <template v-if="row.status === 'checked_in' && auth.can('reservations.checkout')">
                            <el-button size="small" text type="danger" @click="checkOutAction(row)">Check-out</el-button>
                        </template>
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
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, View } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { formatCurrency, paymentStatusMeta, reservationStatus, sourceLabel, today } from '../../utils/helpers';
import { useAuthStore } from '../../stores/auth';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const router = useRouter();
const reservations = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', status: '', payment_status: '', from: '', to: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const params = {
            search: filters.search || undefined,
            status: filters.status || undefined,
            payment_status: filters.payment_status || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            page: pagination.current_page,
        };
        const res = await http.get('/reservations', { params });
        reservations.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function canNoShow(row) {
    return row.check_in_date < today() && ['pending', 'confirmed'].includes(row.status);
}

async function confirmReservation(row) {
    try {
        await http.post(`/reservations/${row.id}/confirm`);
        ElMessage.success('Reservasi dikonfirmasi.');
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}

async function checkInAction(row) {
    try {
        const { value } = await ElMessageBox.prompt(
            `Check-in ${row.code} untuk ${row.guest?.name}? Jumlah malam default ${row.nights}.`,
            'Check-in',
            {
                confirmButtonText: 'Check-in',
                cancelButtonText: 'Batal',
                inputValue: row.nights,
                inputPlaceholder: 'Jumlah malam (opsional)',
                inputValidator: (v) => v === '' || (Number(v) >= 1 && Number.isInteger(Number(v))),
            }
        );
        const payload = value ? { actual_nights: Number(value) } : {};
        const res = await http.post(`/reservations/${row.id}/check-in`, payload);
        ElMessage.success('Check-in berhasil.');
        router.push({ name: 'reservations-detail', params: { id: res.data.data.id } });
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') {
            ElMessage.error(getErrorMessage(err));
        }
    }
}

async function checkOutAction(row) {
    try {
        const { value } = await ElMessageBox.prompt(
            `Check-out ${row.code}? Jika masih ada saldo, ketikkan "YA" untuk memaksa.`,
            'Check-out',
            {
                confirmButtonText: 'Check-out',
                cancelButtonText: 'Batal',
                inputPlaceholder: 'opsional: YA',
            }
        );
        const payload = value === 'YA' ? { force: true } : {};
        const res = await http.post(`/reservations/${row.id}/check-out`, payload);
        ElMessage.success('Check-out berhasil.');
        router.push({ name: 'reservations-detail', params: { id: res.data.data.id } });
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') {
            ElMessage.error(getErrorMessage(err));
        }
    }
}

async function onCommand(command, row) {
    if (command === 'no-show') {
        try {
            await ElMessageBox.confirm(`Tandai ${row.code} sebagai no-show?`, 'Konfirmasi', { type: 'warning' });
            await http.post(`/reservations/${row.id}/no-show`);
            ElMessage.success('Ditandai no-show.');
        } catch (err) {
            if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
        }
    }

    if (command === 'cancel') {
        try {
            const { value } = await ElMessageBox.prompt('Alasan pembatalan (opsional):', 'Batalkan Reservasi', {
                confirmButtonText: 'Batalkan',
                cancelButtonText: 'Batal',
                inputPlaceholder: 'cth: Tamu membatalkan',
            });
            const res = await http.post(`/reservations/${row.id}/cancel`, { reason: value ?? '' });
            if (res.data.data.balance > 0 && row.paid_amount > 0) {
                ElMessage.warning(`Reservasi dibatalkan. Sisa pembayaran ${formatCurrency(res.data.data.balance)} perlu tindak lanjut.`);
            } else {
                ElMessage.success('Reservasi dibatalkan.');
            }
        } catch (err) {
            if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
        }
        await load();
    }
}
</script>