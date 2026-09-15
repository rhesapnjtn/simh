<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Inventory</h2>
            <div class="mr-auto flex flex-wrap items-center gap-3 text-sm">
                <span class="text-gray-500">Item: <b class="text-gray-800">{{ summary.total_items }}</b></span>
                <span class="text-gray-500">Stok menipis: <b class="text-amber-600">{{ summary.low_stock?.length ?? 0 }}</b></span>
                <span class="text-gray-500">Habis: <b class="text-rose-600">{{ summary.out_of_stock?.length ?? 0 }}</b></span>
            </div>
            <el-button v-if="auth.can('inventory.create')" type="primary" :icon="Plus" @click="openForm()">Tambah Item</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama/sku..." clearable style="width: 230px" :prefix-icon="Search" @input="load" />
                <el-input v-model="filters.category" placeholder="Kategori" clearable style="width: 150px" @input="load" />
                <el-select v-model="filters.stock_level" placeholder="Level stok" clearable style="width: 150px" @change="load">
                    <el-option label="Masuk / Normal" value="in" />
                    <el-option label="Menipis" value="low" />
                    <el-option label="Habis" value="out" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="items" stripe class="mt-4">
                <el-table-column prop="name" label="Item" min-width="180">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.sku || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="category" label="Kategori" width="130">
                    <template #default="{ row }">{{ row.category || '-' }}</template>
                </el-table-column>
                <el-table-column label="Stok" width="150">
                    <template #default="{ row }">
                        <span class="font-semibold">{{ row.quantity }} {{ row.unit }}</span>
                        <el-tag v-if="row.stock_status === 'low'" size="small" type="warning" class="ml-2">Menipis</el-tag>
                        <el-tag v-else-if="row.stock_status === 'out'" size="small" type="danger" class="ml-2">Habis</el-tag>
                        <div class="text-xs text-gray-400">Min: {{ row.min_stock }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="location" label="Lokasi" width="110" />
                <el-table-column label="Harga Pokok" width="120" align="right">
                    <template #default="{ row }">{{ formatCurrency(row.cost_price) }}</template>
                </el-table-column>
                <el-table-column label="Aksi" width="210">
                    <template #default="{ row }">
                        <el-button v-if="auth.can('inventory.transfer')" size="small" text type="success" @click="openAdjust(row)">Stok</el-button>
                        <el-button v-if="auth.can('inventory.view')" size="small" text type="info" :icon="List" @click="openTransactions(row)">Riwayat</el-button>
                        <el-button v-if="auth.can('inventory.update')" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button v-if="auth.can('inventory.delete')" size="small" text type="danger" :icon="Delete" @click="remove(row)" />
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Item Stok' : 'Tambah Item Stok'" width="520px">
            <el-form :model="dialog.item" label-position="top">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nama" required>
                        <el-input v-model="dialog.item.name" />
                    </el-form-item>
                    <el-form-item label="SKU">
                        <el-input v-model="dialog.item.sku" />
                    </el-form-item>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Kategori">
                        <el-input v-model="dialog.item.category" />
                    </el-form-item>
                    <el-form-item label="Satuan">
                        <el-input v-model="dialog.item.unit" placeholder="pcs, kg, liter" />
                    </el-form-item>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <el-form-item label="Stok Awal">
                        <el-input-number v-model="dialog.item.quantity" :min="0" :precision="2" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Stok Minimum">
                        <el-input-number v-model="dialog.item.min_stock" :min="0" :precision="2" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Harga Pokok">
                        <el-input-number v-model="dialog.item.cost_price" :min="0" :precision="2" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Lokasi">
                    <el-input v-model="dialog.item.location" placeholder="mis. Gudang utama" />
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

        <el-dialog v-model="adjust.visible" :title="`Transaksi Stok · ${adjust.item?.name ?? ''}`" width="420px">
            <el-form :model="adjust.form" label-position="top">
                <el-form-item label="Tipe" required>
                    <el-radio-group v-model="adjust.form.type">
                        <el-radio-button value="in">Masuk</el-radio-button>
                        <el-radio-button value="out">Keluar</el-radio-button>
                        <el-radio-button value="adjust">Penyesuaian</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="Jumlah" required>
                    <el-input-number v-model="adjust.form.quantity" :min="0.01" :precision="2" class="w-full" />
                </el-form-item>
                <el-form-item label="Referensi">
                    <el-input v-model="adjust.form.reference" placeholder="mis. PO-260915-XXXX, order F&B" />
                </el-form-item>
                <el-form-item label="Catatan">
                    <el-input v-model="adjust.form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="adjust.visible = false">Batal</el-button>
                <el-button type="primary" :loading="adjust.saving" @click="saveAdjust">Simpan</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="history.visible" :title="`Riwayat · ${history.item?.name ?? ''}`" size="420px">
            <div v-loading="history.loading" class="space-y-3">
                <div v-for="t in history.data" :key="t.id" class="rounded-lg border border-slate-100 p-3">
                    <div class="flex items-center justify-between">
                        <el-tag size="small" :type="t.type === 'in' ? 'success' : t.type === 'out' ? 'danger' : 'warning'">
                            {{ t.type === 'in' ? 'Masuk' : t.type === 'out' ? 'Keluar' : 'Penyesuaian' }}
                        </el-tag>
                        <span class="text-sm font-semibold text-gray-800">{{ t.quantity }}</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">{{ t.transaction_at }} · {{ t.user || '-' }}</div>
                    <div v-if="t.reference || t.notes" class="mt-1 text-xs text-gray-400">{{ t.reference }} {{ t.notes }}</div>
                </div>
                <el-empty v-if="!history.loading && history.data.length === 0" description="Belum ada transaksi" />
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Edit, Delete, List } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { formatCurrency } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const items = ref([]);
const loading = ref(true);
const summary = reactive({ total_items: 0, low_stock: [], out_of_stock: [] });
const filters = reactive({ search: '', category: '', stock_level: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });
const adjust = reactive({ visible: false, saving: false, item: null, form: { type: 'in', quantity: 1, reference: '', notes: '' } });
const history = reactive({ visible: false, loading: false, item: null, data: [] });

onMounted(async () => {
    await Promise.all([load(), loadSummary()]);
});

useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/inventory-items', {
            params: {
                search: filters.search || undefined,
                category: filters.category || undefined,
                stock_level: filters.stock_level || undefined,
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

async function loadSummary() {
    try {
        const res = await http.get('/inventory-items/summaries');
        summary.total_items = res.data.total_items;
        summary.low_stock = res.data.low_stock;
        summary.out_of_stock = res.data.out_of_stock;
    } catch (err) {
        /* abaikan */
    }
}

function openForm(item) {
    dialog.item = item
        ? JSON.parse(JSON.stringify(item))
        : { name: '', sku: '', category: '', unit: 'pcs', quantity: 0, min_stock: 0, cost_price: 0, location: '', notes: '', is_active: true };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        if (payload.id) {
            await http.put(`/inventory-items/${payload.id}`, payload);
            ElMessage.success('Item diperbarui.');
        } else {
            await http.post('/inventory-items', payload);
            ElMessage.success('Item ditambahkan.');
        }
        dialog.visible = false;
        await Promise.all([load(), loadSummary()]);
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

function openAdjust(item) {
    adjust.item = item;
    adjust.form = { type: 'in', quantity: 1, reference: '', notes: '' };
    adjust.visible = true;
}

async function saveAdjust() {
    adjust.saving = true;
    try {
        await http.post(`/inventory-items/${adjust.item.id}/adjust`, adjust.form);
        ElMessage.success('Transaksi stok disimpan.');
        adjust.visible = false;
        await Promise.all([load(), loadSummary()]);
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        adjust.saving = false;
    }
}

async function openTransactions(item) {
    history.item = item;
    history.data = [];
    history.visible = true;
    history.loading = true;
    try {
        const res = await http.get(`/inventory-items/${item.id}/transactions`);
        history.data = res.data.data;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        history.loading = false;
    }
}

async function remove(item) {
    try {
        await ElMessageBox.confirm(`Hapus item "${item.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/inventory-items/${item.id}`);
        ElMessage.success('Item dihapus.');
        await Promise.all([load(), loadSummary()]);
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>