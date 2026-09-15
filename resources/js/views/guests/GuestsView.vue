<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Data Tamu</h2>
            <el-button type="primary" :icon="Plus" @click="openForm()">Tambah Tamu</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama, email, telepon, ID..." clearable style="width: 280px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.is_vip" placeholder="Semua status" clearable style="width: 160px" @change="load">
                    <el-option label="VIP" :value="true" />
                    <el-option label="Reguler" :value="false" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="guests" stripe class="mt-4">
                <el-table-column label="Nama" min-width="180">
                    <template #default="{ row }">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                                {{ initials(row.name) }}
                            </div>
                            <div>
                                <div class="font-medium text-gray-800">
                                    {{ row.name }}
                                    <el-tag v-if="row.is_vip" size="small" type="danger" effect="dark" class="ml-1">VIP</el-tag>
                                </div>
                                <div class="text-xs text-gray-400">{{ row.email || '-' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="phone" label="Telepon" width="140" />
                <el-table-column label="Identitas" min-width="160">
                    <template #default="{ row }">{{ row.id_type }}: {{ row.id_number || '-' }}</template>
                </el-table-column>
                <el-table-column prop="nationality" label="Kebangsaan" width="130" />
                <el-table-column prop="reservations_count" label="Reservasi" width="90" align="center" />
                <el-table-column label="Aksi" width="200">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" :icon="View" @click="openDetail(row)">Detail</el-button>
                        <el-button size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button size="small" text type="danger" :icon="Delete" @click="remove(row)">Hapus</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-if="pagination.total > pagination.per_page"
                    background
                    layout="prev, pager, next"
                    :total="pagination.total"
                    :page-size="pagination.per_page"
                    :current-page="pagination.current_page"
                    @current-change="(page) => { pagination.current_page = page; load(); }"
                />
            </div>
        </div>

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Tamu' : 'Tambah Tamu'" width="640px">
            <el-form :model="dialog.item" label-position="top">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nama Depan" required>
                        <el-input v-model="dialog.item.first_name" />
                    </el-form-item>
                    <el-form-item label="Nama Belakang" required>
                        <el-input v-model="dialog.item.last_name" />
                    </el-form-item>
                    <el-form-item label="Email">
                        <el-input v-model="dialog.item.email" />
                    </el-form-item>
                    <el-form-item label="Telepon">
                        <el-input v-model="dialog.item.phone" placeholder="08xx-xxxx-xxxx" />
                    </el-form-item>
                    <el-form-item label="Jenis Identitas">
                        <el-select v-model="dialog.item.id_type" class="w-full">
                            <el-option v-for="t in ['KTP', 'Passport', 'SIM', 'Lainnya']" :key="t" :label="t" :value="t" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Nomor Identitas">
                        <el-input v-model="dialog.item.id_number" />
                    </el-form-item>
                    <el-form-item label="Kebangsaan">
                        <el-input v-model="dialog.item.nationality" />
                    </el-form-item>
                    <el-form-item label="Tamu VIP">
                        <el-switch v-model="dialog.item.is_vip" />
                    </el-form-item>
                </div>
                <el-form-item label="Alamat">
                    <el-input v-model="dialog.item.address" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="Catatan">
                    <el-input v-model="dialog.item.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialog.visible = false">Batal</el-button>
                <el-button type="primary" :loading="dialog.saving" @click="save">Simpan</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="drawer.visible" title="Detail Tamu" size="480px">
            <template v-if="drawer.item">
                <div class="mb-6 flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-sky-100 text-xl font-bold text-sky-700">
                        {{ initials(drawer.item.name) }}
                    </div>
                    <div>
                        <div class="text-lg font-bold text-gray-800">
                            {{ drawer.item.name }}
                            <el-tag v-if="drawer.item.is_vip" size="small" type="danger" effect="dark">VIP</el-tag>
                        </div>
                        <div class="text-sm text-gray-500">{{ drawer.item.email || '-' }}</div>
                        <div class="text-sm text-gray-500">{{ drawer.item.phone || '-' }}</div>
                    </div>
                </div>

                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item label="Jenis Identitas">{{ drawer.item.id_type }}</el-descriptions-item>
                    <el-descriptions-item label="No. Identitas">{{ drawer.item.id_number || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="Kebangsaan">{{ drawer.item.nationality || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="Alamat">{{ drawer.item.address || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="Total Reservasi">{{ drawer.item.reservations_count }}</el-descriptions-item>
                </el-descriptions>

                <div class="mt-6 mb-2 font-semibold text-gray-800">Riwayat Reservasi</div>
                <div v-loading="drawer.loading" class="space-y-3">
                    <el-empty v-if="!drawer.loading && !drawer.history.length" description="Belum ada reservasi" :image-size="60" />
                    <div v-for="r in drawer.history" :key="r.id" class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium text-gray-800">{{ r.code }}</div>
                            <el-tag size="small" :type="reservationStatus[r.status].type">{{ reservationStatus[r.status].label }}</el-tag>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ r.check_in_date }} → {{ r.check_out_date }} · Kamar {{ r.room || '-' }}
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-700">{{ formatCurrency(r.total_amount) }}</span>
                            <el-tag size="small" :type="paymentStatusMeta[r.payment_status].type">{{ paymentStatusMeta[r.payment_status].label }}</el-tag>
                        </div>
                    </div>
                </div>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Edit, Delete, View } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { formatCurrency, paymentStatusMeta, reservationStatus } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const guests = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', is_vip: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 10 });

const dialog = reactive({ visible: false, saving: false, item: null });
const drawer = reactive({ visible: false, loading: false, item: null, history: [] });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/guests', {
            params: {
                search: filters.search || undefined,
                is_vip: filters.is_vip === '' ? undefined : filters.is_vip,
                page: pagination.current_page,
            },
        });
        guests.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function initials(name) {
    return (name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

function openForm(item) {
    dialog.item = item
        ? JSON.parse(JSON.stringify(item))
        : {
              first_name: '',
              last_name: '',
              email: '',
              phone: '',
              id_type: 'KTP',
              id_number: '',
              address: '',
              nationality: 'Indonesia',
              is_vip: false,
              notes: '',
          };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        if (dialog.item.id) {
            await http.put(`/guests/${dialog.item.id}`, dialog.item);
            ElMessage.success('Data tamu diperbarui.');
        } else {
            await http.post('/guests', dialog.item);
            ElMessage.success('Tamu ditambahkan.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function remove(guest) {
    try {
        await ElMessageBox.confirm(`Hapus tamu "${guest.name}"?`, 'Konfirmasi', {
            type: 'warning',
            confirmButtonText: 'Hapus',
            cancelButtonText: 'Batal',
        });
        await http.delete(`/guests/${guest.id}`);
        ElMessage.success('Tamu dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') {
            ElMessage.error(getErrorMessage(err));
        }
    }
}

async function openDetail(guest) {
    drawer.item = guest;
    drawer.history = [];
    drawer.visible = true;
    drawer.loading = true;
    try {
        const [detail, history] = await Promise.all([
            http.get(`/guests/${guest.id}`),
            http.get(`/guests/${guest.id}/reservations`),
        ]);
        drawer.item = detail.data;
        drawer.history = history.data;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        drawer.loading = false;
    }
}
</script>