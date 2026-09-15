<template>
    <div class="mx-auto max-w-3xl space-y-4">
        <h2 class="page-title">Pengaturan Hotel</h2>

        <el-card shadow="never" v-loading="loading">
            <template #header><span class="font-semibold">Informasi Umum</span></template>
            <el-form :model="form" label-position="top">
                <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                    <el-form-item label="Nama Hotel" required>
                        <el-input v-model="form.hotel_name" />
                    </el-form-item>
                    <el-form-item label="Telepon">
                        <el-input v-model="form.hotel_phone" />
                    </el-form-item>
                    <el-form-item label="Email">
                        <el-input v-model="form.hotel_email" />
                    </el-form-item>
                    <el-form-item label="Mata Uang">
                        <el-input v-model="form.currency" placeholder="Rp" />
                    </el-form-item>
                </div>
                <el-form-item label="Alamat">
                    <el-input v-model="form.hotel_address" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <template #header><span class="font-semibold">Operasional</span></template>
            <el-form :model="form" label-position="top">
                <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-3">
                    <el-form-item label="Pajak (%)">
                        <el-input-number v-model="form.tax_rate" :min="0" :max="100" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Jam Check-in">
                        <el-time-select v-model="form.default_check_in" start="06:00" end="23:59" step="00:30" placeholder="14:00" style="width: 100%" />
                    </el-form-item>
                    <el-form-item label="Jam Check-out">
                        <el-time-select v-model="form.default_check_out" start="06:00" end="23:59" step="00:30" placeholder="12:00" style="width: 100%" />
                    </el-form-item>
                </div>
                <el-form-item label="Teks Footer">
                    <el-input v-model="form.footer_text" />
                </el-form-item>
            </el-form>
        </el-card>

        <div class="flex justify-end pb-8">
            <el-button type="primary" size="large" :loading="saving" @click="save">Simpan Pengaturan</el-button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import http, { getErrorMessage } from '../../api/http';

const loading = ref(true);
const saving = ref(false);

const form = reactive({
    hotel_name: '',
    hotel_address: '',
    hotel_phone: '',
    hotel_email: '',
    currency: 'Rp',
    tax_rate: 10,
    default_check_in: '14:00',
    default_check_out: '12:00',
    footer_text: '',
});

onMounted(async () => {
    try {
        const res = await http.get('/settings');
        Object.assign(form, res.data);
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        loading.value = false;
    }
});

async function save() {
    if (!form.hotel_name) {
        ElMessage.warning('Nama hotel wajib diisi.');
        return;
    }
    saving.value = true;
    try {
        await http.put('/settings', form);
        ElMessage.success('Pengaturan berhasil disimpan.');
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        saving.value = false;
    }
}
</script>