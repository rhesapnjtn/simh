<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-sky-800 via-sky-900 to-slate-900 px-4">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-3xl font-bold text-white ring-1 ring-white/20">
                    S
                </div>
                <h1 class="text-2xl font-bold text-white">SIMH Hotel Management</h1>
                <p class="mt-1 text-sm text-sky-200">Sistem Informasi Manajemen Hotel</p>
            </div>

            <el-card class="!rounded-2xl shadow-2xl">
                <template #header>
                    <div class="font-semibold text-gray-800">Masuk ke Akun</div>
                </template>

                <el-form :model="form" :rules="rules" ref="formRef" label-position="top" @submit.prevent="onSubmit">
                    <el-form-item label="Email" prop="email">
                        <el-input v-model="form.email" placeholder="admin@simh.test" size="large" :prefix-icon="Message" />
                    </el-form-item>
                    <el-form-item label="Password" prop="password">
                        <el-input v-model="form.password" type="password" show-password placeholder="••••••••" size="large" :prefix-icon="Lock" @keyup.enter="onSubmit" />
                    </el-form-item>
                    <el-form-item>
                        <el-checkbox v-model="form.remember">Ingat saya</el-checkbox>
                    </el-form-item>

                    <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="mb-4 text-left" />

                    <el-button type="primary" size="large" class="w-full" :loading="auth.loading" @click="onSubmit">
                        Masuk
                    </el-button>
                </el-form>

                <div class="mt-6 rounded-lg bg-sky-50 p-3 text-xs leading-relaxed text-sky-800">
                    <div class="mb-1 font-semibold">Akun demo (password: <b>password</b>):</div>
                    <div>Super Admin: <b>superadmin@simh.test</b></div>
                    <div>General Manager: <b>gm@simh.test</b> · Front Office: <b>frontoffice@simh.test</b></div>
                    <div>Finance: <b>finance@simh.test</b> · Housekeeping: <b>housekeeping@simh.test</b></div>
                    <div>F&B: <b>fbstaff@simh.test</b> · Purchasing: <b>purchasing@simh.test</b></div>
                    <div>Inventory: <b>storekeeper@simh.test</b> · Engineering: <b>engineering@simh.test</b></div>
                    <div>HRD: <b>hrd@simh.test</b> · Guest: <b>guest@simh.test</b></div>
                </div>
            </el-card>

            <p class="mt-6 text-center text-xs text-sky-300">© {{ new Date().getFullYear() }} SIMH Hotel Management System</p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Message, Lock } from '@element-plus/icons-vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const formRef = ref();
const error = ref('');
const form = reactive({
    email: 'admin@simh.test',
    password: 'password',
    remember: true,
});

const rules = {
    email: [{ required: true, message: 'Email wajib diisi', trigger: 'blur' }],
    password: [{ required: true, message: 'Password wajib diisi', trigger: 'blur' }],
};

async function onSubmit() {
    error.value = '';
    try {
        await formRef.value.validate();
    } catch (e) {
        return;
    }

    try {
        await auth.login(form);
        ElMessage.success('Selamat datang kembali!');
        const redirect = route.query.redirect;
        router.push(redirect && redirect !== '/login' ? redirect : '/');
    } catch (err) {
        error.value = err.message;
    }
}
</script>