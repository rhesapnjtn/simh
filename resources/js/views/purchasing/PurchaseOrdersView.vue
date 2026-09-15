<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Purchase Order</h2>
            <el-button v-if="auth.can('purchasing.create')" type="primary" :icon="Plus" @click="openForm()">Buat PO</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari kode PO..." clearable style="width: 220px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.status" placeholder="Semua status" clearable style="width: 160px" @change="load">
                    <el-option v-for="(meta, key) in poStatusMeta" :key="key" :label="meta.label" :value="key" />
                </el-select>
                <el-select v-model="filters.supplier_id" placeholder="Semua supplier" clearable filterable style="width: 200px" @change="load">
                    <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
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
                                        <td class="py-1.5 text-center">{{ it.quantity }} {{ it.unit }}</td>
                                        <td class="py-1.5 text-right">{{ formatCurrency(it.unit_price) }}</td>
                                        <td class="py-1.5 text-right font-medium">{{ formatCurrency(it.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="mt-2 font-semibold text-gray-800">Total: {{ formatCurrency(row.total_amount) }}</div>
                            <div v-if="row.notes" class="mt-1 text-xs text-gray-400">Catatan: {{ row.notes }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Kode" width="180">
                    <template #default="{ row }">
                        <span class="font-semibold text-sky-600">{{ row.code }}</span>
                        <div class="text-xs text-gray-400">{{ row.created_at }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="supplier" label="Supplier" min-width="160">
                    <template #default="{ row }">{{ row.supplier || '-' }}</template>
                </el-table-column>
                <el-table-column label="Tanggal" width="140">
                    <template #default="{ row }">
                        <div class="text-xs text-gray-600">PO: {{ row.order_date }}</div>
                        <div class="text-xs text-gray-400">Datang: {{ row.expected_date || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Total" width="120" align="right">
                    <template #default="{ row }">
                        <span class="font-semibold text-gray-800">{{ formatCurrency(row.total_amount) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Status" width="110">
                    <template #default="{ row }">
                        <el-tag :type="poStatusMeta[row.status].type">{{ poStatusMeta[row.status].label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="250">
                    <template #default="{ row }">
                        <template v-if="row.status === 'draft'">
                            <el-button v-if="auth.can('purchasing.status')" size="small" type="warning" text @click="changeStatus(row, 'submitted')">Ajukan</el-button>
                        </template>
                        <template v-if="row.status === 'submitted'">
                            <el-button v-if="auth.can('purchasing.status')" size="small" type="success" text @click="changeStatus(row, 'approved')">Setujui</el-button>
                        </template>
                        <template v-if="row.status === 'approved'">
                            <el-button v-if="auth.can('purchasing.status')" size="small" type="primary" text @click="changeStatus(row, 'received')">Terima</el-button>
                        </template>
                        <template v-if="!['received', 'cancelled'].includes(row.status)">
                            <el-button v-if="auth.can('purchasing.status')" size="small" text type="danger" @click="changeStatus(row, 'cancelled')">Batal</el-button>
                            <el-button v-if="auth.can('purchasing.update')" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        </template>
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Purchase Order' : 'Buat Purchase Order'" width="720px">
            <el-form :model="dialog.item" label-position="top">
                <div class="grid grid-cols-3 gap-4">
                    <el-form-item label="Supplier" required>
                        <el-select v-model="dialog.item.supplier_id" filterable class="w-full">
                            <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Tanggal Order">
                        <el-date-picker v-model="dialog.item.order_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Tgl. Datang">
                        <el-date-picker v-model="dialog.item.expected_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                </div>
                <el-form-item label="Item">
                    <div class="w-full space-y-2">
                        <div v-for="(line, index) in dialog.item.items" :key="index" class="flex items-center gap-2">
                            <el-input v-model="line.item_name" placeholder="Nama item" class="min-w-0 flex-1" />
                            <el-input-number v-model="line.quantity" :min="0.01" :precision="2" class="!w-28" />
                            <el-input v-model="line.unit" placeholder="unit" class="!w-20" />
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
import { formatCurrency, poStatusMeta } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const auth = useAuthStore();
const orders = ref([]);
const suppliers = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', status: '', supplier_id: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(async () => {
    await Promise.all([
        load(),
        http.get('/suppliers/all').then((r) => (suppliers.value = r.data)),
    ]);
});

useAutoRefresh(load);

function newLine() {
    return { item_name: '', quantity: 1, unit: 'pcs', unit_price: 0 };
}

function addLine() {
    dialog.item.items.push(newLine());
}

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/purchase-orders', {
            params: {
                search: filters.search || undefined,
                status: filters.status || undefined,
                supplier_id: filters.supplier_id || undefined,
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

const todayStr = () => new Date().toISOString().slice(0, 10);

function openForm(order) {
    dialog.item = order
        ? JSON.parse(JSON.stringify(order))
        : { supplier_id: null, order_date: todayStr(), expected_date: '', items: [newLine()], notes: '' };
    dialog.visible = true;
}

async function save() {
    if (!dialog.item.supplier_id) {
        ElMessage.warning('Pilih supplier.');
        return;
    }
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        payload.expected_date = payload.expected_date || null;
        const valid = payload.items.length > 0 && payload.items.every((l) => l.item_name && l.quantity > 0);
        if (!valid) {
            ElMessage.warning('Tambahkan minimal satu item valid.');
            return;
        }
        if (payload.id) {
            await http.put(`/purchase-orders/${payload.id}`, payload);
            ElMessage.success('PO diperbarui.');
        } else {
            await http.post('/purchase-orders', payload);
            ElMessage.success('PO dibuat.');
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
        await http.post(`/purchase-orders/${order.id}/status`, { status });
        ElMessage.success(`Status menjadi ${poStatusMeta[status].label}.`);
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}
</script>