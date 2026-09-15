<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Housekeeping</h2>
            <el-button type="primary" :icon="Plus" @click="openForm()">Buat Tugas</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-select v-model="filters.status" placeholder="Semua status" clearable style="width: 170px" @change="load">
                    <el-option v-for="(meta, key) in housekeepingStatus" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.room_id" placeholder="Semua kamar" clearable filterable style="width: 160px" @change="load">
                    <el-option v-for="r in rooms" :key="r.id" :label="r.room_number" :value="r.id" />
                </el-select>
                <div class="ml-auto flex flex-wrap items-center gap-3 text-sm">
                    <span class="text-gray-500">Menunggu: <b class="text-amber-600">{{ counts.pending }}</b></span>
                    <span class="text-gray-500">Dikerjakan: <b class="text-sky-600">{{ counts.in_progress }}</b></span>
                    <span class="text-gray-500">Selesai: <b class="text-emerald-600">{{ counts.completed }}</b></span>
                </div>
            </div>

            <el-table v-loading="loading" :data="tasks" stripe class="mt-4">
                <el-table-column label="Kamar" width="110">
                    <template #default="{ row }">
                        <div class="font-semibold text-gray-800">{{ row.room?.room_number }}</div>
                        <div class="text-xs text-gray-400">Lt. {{ row.room?.floor }} · {{ row.room?.room_type }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Tugas" min-width="170">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ housekeepingTaskLabel[row.task_type] }}</div>
                        <div class="text-xs text-gray-400">{{ row.notes || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Prioritas" width="110">
                    <template #default="{ row }">
                        <el-tag size="small" :type="priorityType(row.priority)">{{ row.priority }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Petugas" width="130">
                    <template #default="{ row }">{{ row.assignee || '-' }}</template>
                </el-table-column>
                <el-table-column label="Jadwal" prop="scheduled_date" width="110" />
                <el-table-column label="Status" width="130">
                    <template #default="{ row }">
                        <el-tag :type="housekeepingStatus[row.status].type">{{ housekeepingStatus[row.status].label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="230">
                    <template #default="{ row }">
                        <template v-if="row.status === 'pending'">
                            <el-button size="small" type="warning" text @click="changeStatus(row, 'in_progress')">Mulai</el-button>
                            <el-button size="small" type="danger" text @click="changeStatus(row, 'cancelled')">Batal</el-button>
                        </template>
                        <template v-if="row.status === 'in_progress'">
                            <el-button size="small" type="success" text @click="changeStatus(row, 'completed')">Selesai</el-button>
                        </template>
                        <el-button size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button size="small" text type="danger" :icon="Delete" @click="remove(row)" />
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Tugas' : 'Buat Tugas Housekeeping'" width="500px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Kamar" required>
                    <el-select v-model="dialog.item.room_id" class="w-full" filterable :disabled="Boolean(dialog.item.id)">
                        <el-option v-for="r in rooms" :key="r.id" :label="r.room_number" :value="r.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Jenis Tugas" required>
                    <el-select v-model="dialog.item.task_type" class="w-full">
                        <el-option v-for="(label, key) in housekeepingTaskLabel" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Prioritas">
                        <el-select v-model="dialog.item.priority" class="w-full">
                            <el-option v-for="p in ['low', 'medium', 'high']" :key="p" :label="p" :value="p" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Jadwal">
                        <el-date-picker v-model="dialog.item.scheduled_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item v-if="users.length" label="Petugas">
                    <el-select v-model="dialog.item.assigned_to" class="w-full" clearable placeholder="Tugaskan ke staf">
                        <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
                    </el-select>
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
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { housekeepingStatus, housekeepingTaskLabel } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const tasks = ref([]);
const rooms = ref([]);
const users = ref([]);
const loading = ref(true);
const counts = reactive({ pending: 0, in_progress: 0, completed: 0 });
const filters = reactive({ status: '', room_id: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 15 });

const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(async () => {
    await Promise.all([
        load(),
        http.get('/rooms').then((r) => (rooms.value = r.data.data)),
        http.get('/users').catch(() => ({ data: { data: [] } })).then((r) => (users.value = r.data.data)),
    ]);
});

useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/housekeeping', {
            params: {
                status: filters.status || undefined,
                room_id: filters.room_id || undefined,
                page: pagination.current_page,
            },
        });
        tasks.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;

        const statuses = await http.get('/housekeeping', { params: { page: 1 } }).then((r) => r.data.data);
        counts.pending = statuses.filter((t) => t.status === 'pending').length;
        counts.in_progress = statuses.filter((t) => t.status === 'in_progress').length;
        counts.completed = statuses.filter((t) => t.status === 'completed').length;
    } catch (err) {
        if (err !== 'cancel') ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function priorityType(priority) {
    return { low: 'info', medium: 'warning', high: 'danger' }[priority] ?? 'info';
}

function openForm(item) {
    dialog.item = item
        ? JSON.parse(JSON.stringify(item))
        : {
              room_id: rooms.value[0]?.id ?? null,
              task_type: 'cleaning',
              priority: 'medium',
              status: 'pending',
              assigned_to: null,
              notes: '',
              scheduled_date: '',
          };
    dialog.visible = true;
}

async function save() {
    if (!dialog.item.room_id) {
        ElMessage.warning('Pilih kamar.');
        return;
    }
    dialog.saving = true;
    try {
        const payload = { ...dialog.item };
        payload.assigned_to = payload.assigned_to || null;
        payload.scheduled_date = payload.scheduled_date || null;
        delete payload.room;
        delete payload.assignee;

        if (dialog.item.id) {
            await http.put(`/housekeeping/${dialog.item.id}`, payload);
            ElMessage.success('Tugas diperbarui.');
        } else {
            await http.post('/housekeeping', payload);
            ElMessage.success('Tugas housekeeping dibuat.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function changeStatus(task, status) {
    try {
        await http.post(`/housekeeping/${task.id}/status`, { status });
        ElMessage.success(`Status menjadi ${housekeepingStatus[status].label}.`);
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}

async function remove(task) {
    try {
        await ElMessageBox.confirm('Hapus tugas ini?', 'Konfirmasi', { type: 'warning' });
        await http.delete(`/housekeeping/${task.id}`);
        ElMessage.success('Tugas dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>