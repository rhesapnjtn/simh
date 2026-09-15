<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Order F&B</h2>
            <el-button v-if="auth.can('fb.create')" type="primary" :icon="Plus" @click="openForm()">Buat Order</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari kode order..." clearable style="width: 220px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.status" placeholder="Semua status" clearable style="width: 160px" @change="load">
                    <el-option v-for="(meta, key) in orderStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.order_type" placeholder="Tipe order" clearable style="width: 150px" @change="load">
                    <el-option v-for="(label, key) in orderTypeLabel" :key="key" :label="label" :value="key" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="orders" stripe class="mt-4">
                <el-table-column type="expand">
                    <template #default="{ row }">
                        <div class="px-6 py-3">
                            <table class="w-full max-w-xl text-sm">
                                <tbody>
                                    <tr v-for="it in row.items" :key="it.id" class="border-b border-slate-100">
                                        <td class="py-1.5">{{ it.item_name }}</td>
                                        <td class="py-1.5 text-center">{{ it.quantity }}×</td>
                                        <td class="py-1.5 text-right">{{ formatCurrency(it.unit_price) }}</td>
                                        <td class="py-1.5 text-right font-medium">{{ formatCurrency(it.amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="pt-2 text-xs text-gray-400">Pajak</td>
                                        <td colspan="2" class="pt-2 text-xs text-right text-gray-400">{{ formatCurrency(row.tax) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="mt-2 font-semibold text-gray-800">Total: {{ formatCurrency(row.total_amount) }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Kode" width="160">
                    <template #default="{ row }">
                        <span class="font-semibold text-sky-600">{{ row.code }}</span>
                        <div class="text-xs text-gray-400">{{ orderTypeLabel[row.order_type] }} · {{ row.ordered_at }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Lokasi" width="130">
                    <template #default="{ row }">{{ row.table_no ? `Meja ${row.table_no}` : (row.room ? `Kamar ${row.room}` : '-') }}</template>
                </el-table-column>
                <el-table-column label="Total" width="120" align="right">
                    <template #default="{ row }">
                        <span class="font-semibold text-gray-800">{{ formatCurrency(row.total_amount) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Status" width="120">
                    <template #default="{ row }">
                        <el-tag :type="orderStatusMeta[row.status].type">{{ orderStatusMeta[row.status].label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="230">
                    <template #default="{ row }">
                        <template v-if="row.status === 'pending'">
                            <el-button v-if="auth.can('fb.status')" size="small" type="warning" text @click="changeStatus(row, 'preparing')">Siapkan</el-button>
                        </template>
                        <template v-if="row.status === 'preparing'">
                            <el-button v-if="auth.can('fb.status')" size="small" type="success" text @click="changeStatus(row, 'served')">Sajikan</el-button>
                        </template>
                        <template v-if="row.status === 'served'">
                            <el-button v-if="auth.can('fb.status')" size="small" type="primary" text @click="changeStatus(row, 'paid')">Tandai Lunas</el-button>
                        </template>
                        <el-button v-if="auth.can('fb.update') && ['pending', 'preparing'].includes(row.status)" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button v-if="auth.can('fb.delete') && row.status !== 'paid'" size="small" text type="danger" @click="remove(row)">Hapus</el-button>
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Order' : 'Buat Order F&B'" width="640px">
            <el-form :model="dialog.item" label-position="top">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Tipe Order" required>
                        <el-select v-model="dialog.item.order_type" class="w-full">
                            <el-option v-for="(label, key) in orderTypeLabel" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Meja">
                        <el-input v-model="dialog.item.table_no" placeholder="mis. 12" />
                    </el-form-item>
                </div>
                <el-form-item label="Item Pesanan">
                    <div class="w-full space-y-2">
                        <div v-for="(line, index) in dialog.item.items" :key="index" class="flex items-center gap-2">
                            <el-select v-model="line.food_item_id" filterable placeholder="Pilih menu" class="min-w-0 flex-1" @change="(id) => setLinePrice(line, id)">
                                <el-option v-for="m in menu" :key="m.id" :label="`${m.name} · ${formatCurrency(m.price)}`" :value="m.id" />
                            </el-select>
                            <el-input v-model="line.item_name" placeholder="Nama (manual)" class="min-w-0 flex-1" />
                            <el-input-number v-model="line.quantity" :min="1" :precision="0" class="!w-24" />
                            <el-input-number v-model="line.unit_price" :min="0" :precision="2" class="!w-32" />
                            <el-button text type="danger" :icon="Delete" @click="dialog.item.items.splice(index, 1)" />
                        </div>
                        <el-button type="primary" plain size="small" :icon="Plus" @click="addLine">Tambah Item</el-button>
                    </div>
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
import { formatCurrency, orderStatusMeta, orderTypeLabel } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const orders = ref([]);
const menu = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', status: '', order_type: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(async () => {
    await Promise.all([
        load(),
        http.get('/food-items/all').then((r) => (menu.value = r.data)),
    ]);
});

useAutoRefresh(load);

function newLine() {
    return { food_item_id: null, item_name: '', quantity: 1, unit_price: 0 };
}

function addLine() {
    dialog.item.items.push(newLine());
}

function setLinePrice(line, id) {
    const m = menu.value.find((x) => x.id === id);
    line.unit_price = Number(m?.price ?? 0);
    line.item_name = '';
}

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/food-orders', {
            params: {
                search: filters.search || undefined,
                status: filters.status || undefined,
                order_type: filters.order_type || undefined,
                page: pagination.current_page,
            },
        });
        orders.value = res.data.data;
        pagination.total = res.data.total;
        pagination.per_page = res.data.per_page;
        pagination.current_page = res.data.current_page;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
}

function openForm(order) {
    dialog.item = order
        ? JSON.parse(JSON.stringify(order))
        : { order_type: 'dine_in', table_no: '', items: [newLine()], notes: '' };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        const itemsValid = payload.items.length > 0 && payload.items.every((l) => (l.food_item_id || l.item_name) && l.quantity > 0);
        if (!itemsValid) {
            ElMessage.warning('Tambahkan minimal satu item dengan nama/kuantitas valid.');
            return;
        }
        if (payload.id) {
            await http.put(`/food-orders/${payload.id}`, payload);
            ElMessage.success('Order diperbarui.');
        } else {
            await http.post('/food-orders', payload);
            ElMessage.success('Order F&B dibuat.');
        }
        dialog.visible = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        dialog.saving = false;
    }
}

async function changeStatus(order, status) {
    try {
        await http.post(`/food-orders/${order.id}/status`, { status });
        ElMessage.success(`Status menjadi ${orderStatusMeta[status].label}.`);
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}

async function remove(order) {
    try {
        await ElMessageBox.confirm(`Hapus order "${order.code}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/food-orders/${order.id}`);
        ElMessage.success('Order dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>