<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Venue Event</h2>
            <el-button v-if="auth.can('venues.manage')" type="primary" :icon="Plus" @click="openForm()">Tambah Venue</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama venue..." clearable style="width: 260px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.is_active" clearable placeholder="Status" style="width: 140px" @change="load">
                    <el-option label="Aktif" :value="1" />
                    <el-option label="Nonaktif" :value="0" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="venues" stripe class="mt-4">
                <el-table-column prop="name" label="Nama" min-width="180">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.name }}</div>
                        <div v-if="row.facilities?.length" class="text-xs text-gray-400">{{ row.facilities.join(' · ') }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Kapasitas" width="150">
                    <template #default="{ row }">
                        <span>Duduk {{ row.capacity_seated }}</span>
                        <span class="text-gray-400"> · Berdiri {{ row.capacity_standing }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Harga/Hari" width="140" align="right">
                    <template #default="{ row }">
                        <span class="tabular-nums">{{ formatCurrency(row.base_rate) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Status" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column v-if="auth.can('venues.manage')" label="Aksi" width="160">
                    <template #default="{ row }">
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Venue' : 'Tambah Venue'" width="560px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Nama Venue" required>
                    <el-input v-model="dialog.item.name" />
                </el-form-item>
                <div class="grid grid-cols-3 gap-4">
                    <el-form-item label="Kapasitas Duduk">
                        <el-input-number v-model="dialog.item.capacity_seated" :min="0" controls-position="right" style="width: 100%" />
                    </el-form-item>
                    <el-form-item label="Kapasitas Berdiri">
                        <el-input-number v-model="dialog.item.capacity_standing" :min="0" controls-position="right" style="width: 100%" />
                    </el-form-item>
                    <el-form-item label="Harga/Hari (Rp)" required>
                        <el-input-number v-model="dialog.item.base_rate" :min="0" :step="50000" :precision="2" controls-position="right" style="width: 100%" />
                    </el-form-item>
                </div>
                <el-form-item label="Fasilitas">
                    <el-select v-model="dialog.item.facilities" multiple filterable allow-create default-first-option placeholder="Ketik fasilitas lalu Enter" style="width: 100%">
                        <el-option v-for="f in ['AC', 'Sound System', 'Proyektor', 'Panggung', 'Dekorasi', 'Catering', 'Meja & Kursi', 'Parkir']" :key="f" :label="f" :value="f" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Deskripsi">
                    <el-input v-model="dialog.item.description" type="textarea" :rows="3" />
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
import { Plus, Search, Edit, Delete } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { formatCurrency } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const venues = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', is_active: undefined });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/event-venues', {
            params: { search: filters.search || undefined, is_active: filters.is_active, page: pagination.current_page },
        });
        venues.value = res.data.data;
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
        : { name: '', capacity_seated: 50, capacity_standing: 100, base_rate: 0, facilities: [], description: '', is_active: true };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        if (payload.id) {
            await http.put(`/event-venues/${payload.id}`, payload);
            ElMessage.success('Venue diperbarui.');
        } else {
            await http.post('/event-venues', payload);
            ElMessage.success('Venue ditambahkan.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function remove(item) {
    try {
        await ElMessageBox.confirm(`Hapus venue "${item.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/event-venues/${item.id}`);
        ElMessage.success('Venue dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>