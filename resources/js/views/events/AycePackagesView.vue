<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Paket AYCE</h2>
            <el-button v-if="auth.can('ayce.manage')" type="primary" :icon="Plus" @click="openForm()">Tambah Paket</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama paket..." clearable style="width: 260px" :prefix-icon="Search" @input="load" />
            </div>

            <el-table v-loading="loading" :data="packages" stripe class="mt-4">
                <el-table-column prop="name" label="Nama Paket" min-width="180">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.name }}</div>
                        <div v-if="row.includes" class="text-xs text-gray-400">{{ row.includes }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Harga/Orang" width="150" align="right">
                    <template #default="{ row }">
                        <span class="tabular-nums">{{ formatCurrency(row.price_per_pax) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Min/Max Pax" width="130">
                    <template #default="{ row }">{{ row.min_pax }} — {{ row.max_pax ?? '∞' }}</template>
                </el-table-column>
                <el-table-column label="Durasi (mnt)" width="110" align="right">
                    <template #default="{ row }">{{ row.duration_minutes }}</template>
                </el-table-column>
                <el-table-column label="Status" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column v-if="auth.can('ayce.manage')" label="Aksi" width="160">
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Paket' : 'Tambah Paket'" width="560px">
            <el-form :model="dialog.item" label-position="top">
                <el-form-item label="Nama Paket" required>
                    <el-input v-model="dialog.item.name" />
                </el-form-item>
                <div class="grid grid-cols-3 gap-4">
                    <el-form-item label="Harga/Orang (Rp)" required>
                        <el-input-number v-model="dialog.item.price_per_pax" :min="0" :step="10000" :precision="2" controls-position="right" style="width: 100%" />
                    </el-form-item>
                    <el-form-item label="Min Pax">
                        <el-input-number v-model="dialog.item.min_pax" :min="0" controls-position="right" style="width: 100%" />
                    </el-form-item>
                    <el-form-item label="Max Pax">
                        <el-input-number v-model="dialog.item.max_pax" :min="0" controls-position="right" style="width: 100%" />
                    </el-form-item>
                </div>
                <el-form-item label="Durasi (menit)">
                    <el-input-number v-model="dialog.item.duration_minutes" :min="30" :step="30" :max="1440" controls-position="right" style="width: 100%" />
                </el-form-item>
                <el-form-item label="Include">
                    <el-input v-model="dialog.item.includes" placeholder="mis. Steak, seafood, dessert, minuman" />
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
const packages = ref([]);
const loading = ref(true);
const filters = reactive({ search: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/ayce-packages', { params: { search: filters.search || undefined, page: pagination.current_page } });
        packages.value = res.data.data;
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
        : { name: '', price_per_pax: 150000, min_pax: 30, max_pax: 200, duration_minutes: 120, includes: '', description: '', is_active: true };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        if (payload.id) {
            await http.put(`/ayce-packages/${payload.id}`, payload);
            ElMessage.success('Paket diperbarui.');
        } else {
            await http.post('/ayce-packages', payload);
            ElMessage.success('Paket ditambahkan.');
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
        await ElMessageBox.confirm(`Hapus paket "${item.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/ayce-packages/${item.id}`);
        ElMessage.success('Paket dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>