<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Tipe Kamar</h2>
            <el-button type="primary" :icon="Plus" @click="openForm()">Tambah Tipe</el-button>
        </div>

        <div class="card p-4">
            <div class="flex gap-3">
                <el-input v-model="filters.search" placeholder="Cari tipe kamar..." clearable style="width: 240px" :prefix-icon="Search" @input="load" />
            </div>

            <el-table v-loading="loading" :data="roomTypes" stripe class="mt-4">
                <el-table-column label="Nama" min-width="160">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.slug }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="capacity" label="Kapasitas" width="100" align="center" />
                <el-table-column label="Tarif Dasar" width="140">
                    <template #default="{ row }">{{ formatCurrency(row.base_rate) }}</template>
                </el-table-column>
                <el-table-column label="Orang Tambahan" width="140">
                    <template #default="{ row }">{{ row.extra_person_rate > 0 ? formatCurrency(row.extra_person_rate) : '-' }}</template>
                </el-table-column>
                <el-table-column label="Fasilitas" min-width="220">
                    <template #default="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <el-tag v-for="a in row.amenities.slice(0, 4)" :key="a" size="small" type="info">{{ a }}</el-tag>
                            <el-tag v-if="row.amenities.length > 4" size="small">+{{ row.amenities.length - 4 }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Jml Kamar" prop="rooms_count" width="90" align="center" />
                <el-table-column label="Aktif" width="80" align="center">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" @change="(v) => toggleActive(row, v)" />
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="150">
                    <template #default="{ row }">
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Tipe Kamar' : 'Tambah Tipe Kamar'" width="560px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Nama Tipe" required>
                    <el-input v-model="dialog.item.name" placeholder="cth: Deluxe Room" />
                </el-form-item>
                <div class="grid grid-cols-3 gap-4">
                    <el-form-item label="Tarif Dasar" required>
                        <el-input-number v-model="dialog.item.base_rate" :min="0" :step="50000" class="w-full" :precision="0" />
                    </el-form-item>
                    <el-form-item label="Tarif Ekstra Orang">
                        <el-input-number v-model="dialog.item.extra_person_rate" :min="0" :step="25000" class="w-full" :precision="0" />
                    </el-form-item>
                    <el-form-item label="Kapasitas" required>
                        <el-input-number v-model="dialog.item.capacity" :min="1" :max="20" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Deskripsi">
                    <el-input v-model="dialog.item.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="Fasilitas" hint="Tekan Enter untuk menambah">
                    <el-select
                        v-model="dialog.item.amenities"
                        multiple
                        filterable
                        allow-create
                        default-first-option
                        placeholder="Ketik lalu tekan Enter"
                        class="w-full"
                    />
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
import { formatCurrency } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const roomTypes = ref([]);
const loading = ref(true);
const filters = reactive({ search: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 10 });

const dialog = reactive({
    visible: false,
    saving: false,
    item: null,
});

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/room-types', {
            params: { search: filters.search || undefined, page: pagination.current_page },
        });
        roomTypes.value = res.data.data;
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
        : { name: '', description: '', base_rate: 500000, extra_person_rate: 100000, capacity: 2, is_active: true, amenities: [] };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = { ...dialog.item, is_active: dialog.item.is_active ?? true };
        payload.amenities = (dialog.item.amenities ?? []).map((a) => String(a).trim()).filter(Boolean);

        if (dialog.item.id) {
            await http.put(`/room-types/${dialog.item.id}`, payload);
            ElMessage.success('Tipe kamar diperbarui.');
        } else {
            await http.post('/room-types', payload);
            ElMessage.success('Tipe kamar ditambahkan.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function toggleActive(item, value) {
    try {
        await http.put(`/room-types/${item.id}`, { ...item, is_active: value });
        ElMessage.success(value ? 'Tipe diaktifkan.' : 'Tipe dinonaktifkan.');
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
        await load();
    }
}

async function remove(item) {
    try {
        await ElMessageBox.confirm(`Hapus tipe kamar "${item.name}"?`, 'Konfirmasi', {
            type: 'warning',
            confirmButtonText: 'Hapus',
            cancelButtonText: 'Batal',
        });
        await http.delete(`/room-types/${item.id}`);
        ElMessage.success('Tipe kamar dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') {
            ElMessage.error(getErrorMessage(err));
        }
    }
}
</script>