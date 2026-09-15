<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Manajemen Pengguna</h2>
            <el-button type="primary" :icon="Plus" @click="openForm()">Tambah Pengguna</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama/email..." clearable style="width: 260px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.role" placeholder="Semua peran" clearable style="width: 180px" @change="load">
                    <el-option v-for="(label, key) in roleLabel" :key="key" :label="label" :value="key" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="users" stripe class="mt-4">
                <el-table-column label="Nama" min-width="170">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">
                            {{ row.name }}
                            <el-tag v-if="row.id === auth.user?.id" size="small" type="success">Anda</el-tag>
                        </div>
                        <div class="text-xs text-gray-400">{{ row.email }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="phone" label="Telepon" width="140" />
                <el-table-column label="Peran" width="130">
                    <template #default="{ row }">
                        <el-tag :type="roleTag(row.role)">{{ roleLabel[row.role] }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aktif" width="90" align="center">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" :disabled="row.id === auth.user?.id" @change="(v) => toggleActive(row, v)" />
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="Dibuat" width="120" />
                <el-table-column label="Aksi" width="160">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button size="small" text type="danger" :icon="Delete" :disabled="row.id === auth.user?.id" @click="remove(row)">Hapus</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </div>

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Pengguna' : 'Tambah Pengguna'" width="480px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Nama" required>
                    <el-input v-model="dialog.item.name" />
                </el-form-item>
                <el-form-item label="Email" required>
                    <el-input v-model="dialog.item.email" type="email" />
                </el-form-item>
                <el-form-item label="Telepon">
                    <el-input v-model="dialog.item.phone" />
                </el-form-item>
                <el-form-item label="Peran" required>
                    <el-select v-model="dialog.item.role" class="w-full">
                        <el-option v-for="(label, key) in roleLabel" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="dialog.item?.id ? 'Password (kosongkan jika tetap)' : 'Password'" required="!dialog.item?.id">
                    <el-input v-model="dialog.item.password" type="password" show-password />
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
import { roleLabel, roleMeta } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const users = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', role: '' });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/users', {
            params: {
                search: filters.search || undefined,
                role: filters.role || undefined,
            },
        });
        users.value = res.data.data;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function roleTag(role) {
    return roleMeta[role]?.type ?? 'info';
}

function openForm(user) {
    dialog.item = user
        ? JSON.parse(JSON.stringify(user))
        : { name: '', email: '', phone: '', role: 'receptionist', password: '', is_active: true };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        if (dialog.item.id) {
            await http.put(`/users/${dialog.item.id}`, dialog.item);
            ElMessage.success('Pengguna diperbarui.');
        } else {
            await http.post('/users', dialog.item);
            ElMessage.success('Pengguna ditambahkan.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function toggleActive(user, value) {
    try {
        await http.put(`/users/${user.id}`, { ...user, is_active: value });
        ElMessage.success(value ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.');
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
        await load();
    }
}

async function remove(user) {
    try {
        await ElMessageBox.confirm(`Hapus pengguna "${user.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/users/${user.id}`);
        ElMessage.success('Pengguna dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>