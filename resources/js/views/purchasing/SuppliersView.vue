<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Supplier</h2>
            <el-button v-if="auth.can('suppliers.manage')" type="primary" :icon="Plus" @click="openForm()">Tambah Supplier</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama/kategori..." clearable style="width: 260px" :prefix-icon="Search" @input="load" />
            </div>

            <el-table v-loading="loading" :data="suppliers" stripe class="mt-4">
                <el-table-column prop="name" label="Nama" min-width="170">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.category || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="contact_person" label="Kontak" width="150">
                    <template #default="{ row }">{{ row.contact_person || '-' }}</template>
                </el-table-column>
                <el-table-column prop="phone" label="Telepon" width="140" />
                <el-table-column prop="email" label="Email" min-width="170" />
                <el-table-column label="Aktif" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="160">
                    <template #default="{ row }">
                        <el-button v-if="auth.can('suppliers.manage')" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button v-if="auth.can('suppliers.manage')" size="small" text type="danger" :icon="Delete" @click="remove(row)" />
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Supplier' : 'Tambah Supplier'" width="520px">
            <el-form :model="dialog.item" label-position="top">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nama" required>
                        <el-input v-model="dialog.item.name" />
                    </el-form-item>
                    <el-form-item label="Kategori">
                        <el-input v-model="dialog.item.category" placeholder="mis. Makanan, Laundry, SPA" />
                    </el-form-item>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nama Kontak">
                        <el-input v-model="dialog.item.contact_person" />
                    </el-form-item>
                    <el-form-item label="Telepon">
                        <el-input v-model="dialog.item.phone" />
                    </el-form-item>
                </div>
                <el-form-item label="Email">
                    <el-input v-model="dialog.item.email" type="email" />
                </el-form-item>
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
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Edit, Delete } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const suppliers = ref([]);
const loading = ref(true);
const filters = reactive({ search: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/suppliers', { params: { search: filters.search || undefined, page: pagination.current_page } });
        suppliers.value = res.data.data;
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
    dialog.item = item ? JSON.parse(JSON.stringify(item)) : { name: '', category: '', contact_person: '', phone: '', email: '', address: '', notes: '', is_active: true };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        payload.is_active = true;
        if (payload.id) {
            await http.put(`/suppliers/${payload.id}`, payload);
            ElMessage.success('Supplier diperbarui.');
        } else {
            await http.post('/suppliers', payload);
            ElMessage.success('Supplier ditambahkan.');
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
        await ElMessageBox.confirm(`Hapus supplier "${item.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/suppliers/${item.id}`);
        ElMessage.success('Supplier dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>