<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Manajemen Kamar</h2>
            <el-button type="primary" :icon="Plus" @click="openForm()">Tambah Kamar</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nomor kamar..." clearable style="width: 220px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.status" placeholder="Semua status" clearable style="width: 180px" @change="load">
                    <el-option v-for="(meta, key) in roomStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.room_type_id" placeholder="Semua tipe" clearable style="width: 200px" @change="load">
                    <el-option v-for="rt in roomTypes" :key="rt.id" :label="rt.name" :value="rt.id" />
                </el-select>

                <el-radio-group v-model="viewMode" class="ml-auto">
                    <el-radio-button value="grid"><el-icon><Grid /></el-icon></el-radio-button>
                    <el-radio-button value="table"><el-icon><Tickets /></el-icon></el-radio-button>
                </el-radio-group>
            </div>

            <div v-if="loading" class="mt-4">
                <el-skeleton :rows="6" animated />
            </div>

            <template v-else>
                <div v-if="viewMode === 'grid'" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <div
                        v-for="room in rooms"
                        :key="room.id"
                        class="rounded-xl border p-4 transition"
                        :class="statusCardClass(room.status)"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-lg font-bold text-gray-800">{{ room.room_number }}</div>
                                <div class="text-xs text-gray-500">Lantai {{ room.floor }}</div>
                            </div>
                            <el-tag size="small" :type="roomStatusMeta[room.status].type">{{ roomStatusMeta[room.status].label }}</el-tag>
                        </div>
                        <div class="mt-3 text-sm font-medium text-gray-700">{{ room.room_type?.name }}</div>
                        <div class="text-xs text-gray-500">{{ formatCurrency(room.room_type?.base_rate) }}/malam</div>
                        <div class="mt-3 flex gap-2">
                            <el-dropdown trigger="click" @command="(cmd) => changeStatus(room, cmd)">
                                <el-button size="small" :type="roomStatusMeta[room.status].type === 'danger' ? 'warning' : 'primary'" plain>
                                    Ubah Status
                                    <el-icon class="ml-1"><ArrowDown /></el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="(meta, key) in roomStatusMeta" :key="key" :command="key" :disabled="room.status === key">
                                            {{ meta.label }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-button size="small" :icon="Edit" @click="openForm(room)" />
                            <el-button size="small" type="danger" :icon="Delete" plain @click="remove(room)" />
                        </div>
                    </div>
                </div>

                <el-table v-else class="mt-4" :data="rooms" stripe>
                    <el-table-column prop="room_number" label="No. Kamar" width="110" />
                    <el-table-column label="Lantai" prop="floor" width="80" />
                    <el-table-column label="Tipe Kamar">
                        <template #default="{ row }">
                            <div class="font-medium text-gray-700">{{ row.room_type?.name }}</div>
                            <div class="text-xs text-gray-400">{{ formatCurrency(row.room_type?.base_rate) }}/malam</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="Status" width="140">
                        <template #default="{ row }">
                            <el-tag :type="roomStatusMeta[row.status].type">{{ roomStatusMeta[row.status].label }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Aksi" width="180">
                        <template #default="{ row }">
                            <el-dropdown trigger="click" @command="(cmd) => changeStatus(row, cmd)">
                                <el-button size="small" text type="primary">Ubah Status</el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="(meta, key) in roomStatusMeta" :key="key" :command="key" :disabled="row.status === key">
                                            {{ meta.label }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
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
            </template>
        </div>

        <el-dialog v-model="dialog.visible" :title="dialog.room?.id ? 'Edit Kamar' : 'Tambah Kamar'" width="480px">
            <el-form :model="dialog.room" label-position="top">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nomor Kamar" required>
                        <el-input v-model="dialog.room.room_number" placeholder="cth: 101" />
                    </el-form-item>
                    <el-form-item label="Lantai" required>
                        <el-input-number v-model="dialog.room.floor" :min="0" :max="99" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Tipe Kamar" required>
                    <el-select v-model="dialog.room.room_type_id" class="w-full" placeholder="Pilih tipe kamar">
                        <el-option v-for="rt in roomTypes" :key="rt.id" :label="`${rt.name} (${formatCurrency(rt.base_rate)})`" :value="rt.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Status">
                    <el-select v-model="dialog.room.status" class="w-full">
                        <el-option v-for="(meta, key) in roomStatusMeta" :key="key" :label="meta.label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Catatan">
                    <el-input v-model="dialog.room.notes" type="textarea" :rows="2" />
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
import { Plus, Search, Edit, Delete, Grid, Tickets } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { formatCurrency, roomStatusMeta } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const rooms = ref([]);
const roomTypes = ref([]);
const loading = ref(true);
const viewMode = ref('grid');
const filters = reactive({ search: '', status: '', room_type_id: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });

const dialog = reactive({
    visible: false,
    saving: false,
    room: null,
});

onMounted(async () => {
    const [types] = await Promise.all([http.get('/room-types/all'), load()]);
    roomTypes.value = types.data;
});

useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/rooms', {
            params: {
                search: filters.search || undefined,
                status: filters.status || undefined,
                room_type_id: filters.room_type_id || undefined,
                page: pagination.current_page,
            },
        });
        rooms.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function statusCardClass(status) {
    const map = {
        available: 'border-emerald-200 bg-emerald-50',
        occupied: 'border-rose-200 bg-rose-50',
        housekeeping: 'border-amber-200 bg-amber-50',
        maintenance: 'border-gray-200 bg-gray-50',
    };
    return map[status];
}

function openForm(room) {
    dialog.room = room
        ? JSON.parse(JSON.stringify(room))
        : { room_number: '', floor: 1, room_type_id: roomTypes.value[0]?.id ?? null, status: 'available', notes: '' };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        if (dialog.room.id) {
            await http.put(`/rooms/${dialog.room.id}`, dialog.room);
            ElMessage.success('Kamar diperbarui.');
        } else {
            await http.post('/rooms', dialog.room);
            ElMessage.success('Kamar ditambahkan.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function changeStatus(room, status) {
    try {
        await http.post(`/rooms/${room.id}/status`, { status });
        ElMessage.success(`Status kamar ${room.room_number} diubah menjadi ${roomStatusMeta[status].label}.`);
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}

async function remove(room) {
    try {
        await ElMessageBox.confirm(`Hapus kamar ${room.room_number}?`, 'Konfirmasi', {
            type: 'warning',
            confirmButtonText: 'Hapus',
            cancelButtonText: 'Batal',
        });
        await http.delete(`/rooms/${room.id}`);
        ElMessage.success('Kamar dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') {
            ElMessage.error(getErrorMessage(err));
        }
    }
}
</script>