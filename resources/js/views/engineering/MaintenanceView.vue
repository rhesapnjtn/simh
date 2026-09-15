<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Maintenance</h2>
            <el-button v-if="auth.can('engineering.create')" type="primary" :icon="Plus" @click="openForm()">Buat Laporan</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-select v-model="filters.status" placeholder="Semua status" clearable style="width: 160px" @change="load">
                    <el-option v-for="(meta, key) in maintenanceStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.category" placeholder="Semua kategori" clearable style="width: 180px" @change="load">
                    <el-option v-for="(label, key) in maintenanceCategoryLabel" :key="key" :label="label" :value="key" />
                </el-select>
                <el-select v-model="filters.room_id" placeholder="Semua kamar" clearable filterable style="width: 160px" @change="load">
                    <el-option v-for="r in rooms" :key="r.id" :label="r.room_number" :value="r.id" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="requests" stripe class="mt-4">
                <el-table-column label="Laporan" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.title }}</div>
                        <div class="text-xs text-gray-400">{{ row.description || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Kamar" width="90">
                    <template #default="{ row }">{{ row.room || '-' }}</template>
                </el-table-column>
                <el-table-column label="Kategori" width="130">
                    <template #default="{ row }">
                        <el-tag size="small" type="info">{{ maintenanceCategoryLabel[row.category] }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Prioritas" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" :type="priorityType(row.priority)">{{ row.priority }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Petugas" width="120">
                    <template #default="{ row }">{{ row.assignee || '-' }}</template>
                </el-table-column>
                <el-table-column label="Biaya" width="110" align="right">
                    <template #default="{ row }">{{ row.cost ? formatCurrency(row.cost) : '-' }}</template>
                </el-table-column>
                <el-table-column label="Status" width="120">
                    <template #default="{ row }">
                        <el-tag :type="maintenanceStatusMeta[row.status].type">{{ maintenanceStatusMeta[row.status].label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="250">
                    <template #default="{ row }">
                        <template v-if="row.status === 'pending'">
                            <el-button v-if="auth.can('engineering.status')" size="small" type="warning" text @click="openStatus(row, 'in_progress')">Mulai</el-button>
                        </template>
                        <template v-if="row.status === 'in_progress'">
                            <el-button v-if="auth.can('engineering.status')" size="small" type="danger" text @click="openStatus(row, 'on_hold')">Tunda</el-button>
                            <el-button v-if="auth.can('engineering.status')" size="small" type="success" text @click="complete(row)">Selesai</el-button>
                        </template>
                        <template v-if="row.status === 'on_hold'">
                            <el-button v-if="auth.can('engineering.status')" size="small" type="warning" text @click="openStatus(row, 'in_progress')">Lanjut</el-button>
                        </template>
                        <el-button v-if="auth.can('engineering.update') && !['completed', 'cancelled'].includes(row.status)" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button v-if="auth.can('engineering.delete')" size="small" text type="danger" :icon="Delete" @click="remove(row)" />
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Laporan' : 'Buat Laporan Maintenance'" width="560px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Judul" required>
                    <el-input v-model="dialog.item.title" placeholder="mis. AC kamar tidak dingin" />
                </el-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Kamar">
                        <el-select v-model="dialog.item.room_id" clearable filterable class="w-full">
                            <el-option v-for="r in rooms" :key="r.id" :label="r.room_number" :value="r.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Kategori">
                        <el-select v-model="dialog.item.category" class="w-full">
                            <el-option v-for="(label, key) in maintenanceCategoryLabel" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Prioritas">
                        <el-select v-model="dialog.item.priority" class="w-full">
                            <el-option v-for="p in ['low', 'medium', 'high', 'urgent']" :key="p" :label="p" :value="p" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Tugaskan ke">
                        <el-select v-model="dialog.item.assigned_to" clearable filterable class="w-full">
                            <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
                        </el-select>
                    </el-form-item>
                </div>
                <el-form-item label="Deskripsi">
                    <el-input v-model="dialog.item.description" type="textarea" :rows="2" />
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

        <el-dialog v-model="completeDialog.visible" title="Selesaikan Maintenance" width="380px">
            <el-form label-position="top">
                <el-form-item label="Biaya (Rp)">
                    <el-input-number v-model="completeDialog.cost" :min="0" :precision="2" class="w-full" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="completeDialog.visible = false">Batal</el-button>
                <el-button type="success" :loading="completeDialog.saving" @click="submitComplete">Selesai</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { formatCurrency, maintenanceCategoryLabel, maintenanceStatusMeta } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const requests = ref([]);
const rooms = ref([]);
const users = ref([]);
const loading = ref(true);
const filters = reactive({ status: '', category: '', room_id: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });
const completeDialog = reactive({ visible: false, saving: false, cost: 0, item: null });

onMounted(async () => {
    await Promise.all([
        load(),
        http.get('/rooms').then((r) => (rooms.value = r.data.data)),
        http.get('/users').catch(() => ({ data: { data: [] } })).then((r) => (users.value = r.data.data)),
    ]);
});

useAutoRefresh(load);

function priorityType(p) {
    return { low: 'info', medium: '', high: 'warning', urgent: 'danger' }[p] ?? 'info';
}

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/maintenance', {
            params: {
                status: filters.status || undefined,
                category: filters.category || undefined,
                room_id: filters.room_id || undefined,
                page: pagination.current_page,
            },
        });
        requests.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function openForm(item) {
    dialog.item = item
        ? JSON.parse(JSON.stringify(item))
        : { room_id: null, title: '', category: 'other', priority: 'medium', assigned_to: null, description: '', notes: '' };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        delete payload.room;
        delete payload.assignee;
        delete payload.requester;
        payload.assigned_to = payload.assigned_to || null;
        if (payload.id) {
            await http.put(`/maintenance/${payload.id}`, payload);
            ElMessage.success('Laporan diperbarui.');
        } else {
            await http.post('/maintenance', payload);
            ElMessage.success('Laporan maintenance dibuat.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function openStatus(item, status) {
    try {
        await http.post(`/maintenance/${item.id}/status`, { status });
        ElMessage.success(`Status menjadi ${maintenanceStatusMeta[status].label}.`);
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}

function complete(item) {
    completeDialog.item = item;
    completeDialog.cost = Number(item.cost ?? 0);
    completeDialog.visible = true;
}

async function submitComplete() {
    completeDialog.saving = true;
    try {
        await http.post(`/maintenance/${completeDialog.item.id}/status`, { status: 'completed', cost: completeDialog.cost });
        ElMessage.success('Permintaan maintenance selesai.');
        completeDialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        completeDialog.saving = false;
    }
}

async function remove(item) {
    try {
        await ElMessageBox.confirm(`Hapus laporan "${item.title}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/maintenance/${item.id}`);
        ElMessage.success('Laporan dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>