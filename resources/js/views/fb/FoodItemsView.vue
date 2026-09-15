<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Menu F&B</h2>
            <el-button v-if="auth.can('fb.create')" type="primary" :icon="Plus" @click="openForm()">Tambah Menu</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari menu..." clearable style="width: 240px" :prefix-icon="Search" @input="load" />
                <el-input v-model="filters.category" placeholder="Kategori" clearable style="width: 170px" @input="load" />
            </div>

            <el-table v-loading="loading" :data="items" stripe class="mt-4">
                <el-table-column prop="name" label="Nama Menu" min-width="180" />
                <el-table-column prop="category" label="Kategori" width="140">
                    <template #default="{ row }">
                        <el-tag size="small" type="info">{{ row.category || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Harga" width="130" align="right">
                    <template #default="{ row }">
                        <span class="font-semibold text-gray-800">{{ formatCurrency(row.price) }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="description" label="Deskripsi" min-width="200" show-overflow-tooltip />
                <el-table-column label="Aktif" width="90" align="center">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" @change="(v) => toggleActive(row, v)" />
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="150">
                    <template #default="{ row }">
                        <el-button v-if="auth.can('fb.update')" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button v-if="auth.can('fb.delete')" size="small" text type="danger" :icon="Delete" @click="remove(row)" />
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Menu' : 'Tambah Menu'" width="480px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Nama Menu" required>
                    <el-input v-model="dialog.item.name" />
                </el-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Kategori">
                        <el-input v-model="dialog.item.category" placeholder="mis. Makanan, Minuman" />
                    </el-form-item>
                    <el-form-item label="Harga" required>
                        <el-input-number v-model="dialog.item.price" :min="0" :precision="2" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Deskripsi">
                    <el-input v-model="dialog.item.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="Aktif">
                    <el-switch v-model="dialog.item.is_active" />
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
const items = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', category: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/food-items', {
            params: {
                search: filters.search || undefined,
                category: filters.category || undefined,
                page: pagination.current_page,
            },
        });
        items.value = res.data.data;
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
    dialog.item = item ? JSON.parse(JSON.stringify(item)) : { name: '', category: '', price: 0, description: '', is_active: true };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        if (payload.id) {
            await http.put(`/food-items/${payload.id}`, payload);
            ElMessage.success('Menu diperbarui.');
        } else {
            await http.post('/food-items', payload);
            ElMessage.success('Menu ditambahkan.');
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
        await http.put(`/food-items/${item.id}`, { ...item, is_active: value });
        ElMessage.success(value ? 'Menu diaktifkan.' : 'Menu dinonaktifkan.');
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
        await load();
    }
}

async function remove(item) {
    try {
        await ElMessageBox.confirm(`Hapus menu "${item.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/food-items/${item.id}`);
        ElMessage.success('Menu dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>