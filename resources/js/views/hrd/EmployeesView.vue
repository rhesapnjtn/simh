<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="page-title mr-auto">Karyawan (HRD)</h2>
            <el-button v-if="auth.can('hr.create')" type="primary" :icon="Plus" @click="openForm()">Tambah Karyawan</el-button>
        </div>

        <div class="card p-4">
            <div class="toolbar">
                <el-input v-model="filters.search" placeholder="Cari nama/email/jabatan..." clearable style="width: 260px" :prefix-icon="Search" @input="load" />
                <el-select v-model="filters.department" placeholder="Semua departemen" clearable style="width: 190px" @change="load">
                    <el-option v-for="(label, key) in departmentLabel" :key="key" :label="label" :value="key" />
                </el-select>
                <el-select v-model="filters.status" placeholder="Status" clearable style="width: 130px" @change="load">
                    <el-option v-for="(label, key) in employeeStatusLabel" :key="key" :label="label" :value="key" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="employees" stripe class="mt-4">
                <el-table-column prop="name" label="Nama" min-width="170">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.name }}</div>
                        <div class="text-xs text-gray-400">{{ row.position || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="email" label="Email" min-width="170" />
                <el-table-column prop="phone" label="Telepon" width="130" />
                <el-table-column label="Departemen" width="170">
                    <template #default="{ row }">
                        <el-tag size="small" type="info">{{ departmentLabel[row.department] || row.department || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="join_date" label="Bergabung" width="110" />
                <el-table-column label="Gaji" width="120" align="right">
                    <template #default="{ row }">{{ formatCurrency(row.salary) }}</template>
                </el-table-column>
                <el-table-column label="Status" width="90">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.status === 'active' ? 'success' : row.status === 'on_leave' ? 'warning' : 'info'">
                            {{ employeeStatusLabel[row.status] }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Aksi" width="160">
                    <template #default="{ row }">
                        <el-button v-if="auth.can('hr.update')" size="small" text type="primary" :icon="Edit" @click="openForm(row)">Edit</el-button>
                        <el-button v-if="auth.can('hr.delete')" size="small" text type="danger" :icon="Delete" @click="remove(row)" />
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

        <el-dialog v-model="dialog.visible" :title="dialog.item?.id ? 'Edit Karyawan' : 'Tambah Karyawan'" width="560px">
            <el-form :model="dialog.item" label-position="top">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Nama" required>
                        <el-input v-model="dialog.item.name" />
                    </el-form-item>
                    <el-form-item label="Jabatan">
                        <el-input v-model="dialog.item.position" />
                    </el-form-item>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Email">
                        <el-input v-model="dialog.item.email" type="email" />
                    </el-form-item>
                    <el-form-item label="Telepon">
                        <el-input v-model="dialog.item.phone" />
                    </el-form-item>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <el-form-item label="Departemen">
                        <el-select v-model="dialog.item.department" class="w-full">
                            <el-option v-for="(label, key) in departmentLabel" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Tanggal Bergabung">
                        <el-date-picker v-model="dialog.item.join_date" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Gaji">
                        <el-input-number v-model="dialog.item.salary" :min="0" :precision="2" class="w-full" />
                    </el-form-item>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Status">
                        <el-select v-model="dialog.item.status" class="w-full">
                            <el-option v-for="(label, key) in employeeStatusLabel" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Catatan">
                        <el-input v-model="dialog.item.notes" />
                    </el-form-item>
                </div>
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
import { employeeStatusLabel, formatCurrency } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const departmentLabel = {
    front_office: 'Front Office',
    reservation: 'Reservation',
    housekeeping: 'Housekeeping',
    finance: 'Finance / Accounting',
    fb: 'Food & Beverage',
    purchasing: 'Purchasing',
    inventory: 'Inventory',
    engineering: 'Engineering',
    hrd: 'HRD',
    management: 'Management',
};

const auth = useAuthStore();
const employees = ref([]);
const loading = ref(true);
const filters = reactive({ search: '', department: '', status: '' });
const pagination = reactive({ total: 0, current_page: 1, per_page: 12 });
const dialog = reactive({ visible: false, saving: false, item: null });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get('/employees', {
            params: {
                search: filters.search || undefined,
                department: filters.department || undefined,
                status: filters.status || undefined,
                page: pagination.current_page,
            },
        });
        employees.value = res.data.data;
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
        : { name: '', email: '', phone: '', position: '', department: '', join_date: '', salary: 0, status: 'active', notes: '' };
    dialog.visible = true;
}

async function save() {
    dialog.saving = true;
    try {
        const payload = JSON.parse(JSON.stringify(dialog.item));
        payload.join_date = payload.join_date || null;
        if (payload.id) {
            await http.put(`/employees/${payload.id}`, payload);
            ElMessage.success('Data karyawan diperbarui.');
        } else {
            await http.post('/employees', payload);
            ElMessage.success('Karyawan ditambahkan.');
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
        await ElMessageBox.confirm(`Hapus karyawan "${item.name}"?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/employees/${item.id}`);
        ElMessage.success('Karyawan dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}
</script>