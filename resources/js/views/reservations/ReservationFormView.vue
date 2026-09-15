<template>
    <div class="mx-auto max-w-4xl space-y-4">
        <div class="flex items-center gap-3">
            <el-button :icon="Back" circle @click="back" />
            <h2 class="page-title">{{ isEdit ? 'Ubah Reservasi' : 'Reservasi Baru' }}</h2>
        </div>

        <el-card shadow="never" v-loading="bootLoading">
            <template #header>
                <div class="flex items-center gap-2">
                    <el-icon><User /></el-icon>
                    <span class="font-semibold">Data Tamu</span>
                </div>
            </template>

            <el-radio-group v-model="guestMode" class="mb-4">
                <el-radio-button value="existing">Tamu Terdaftar</el-radio-button>
                <el-radio-button value="new">Tamu Baru</el-radio-button>
            </el-radio-group>

            <div v-if="guestMode === 'existing'">
                <el-select
                    v-model="form.guest_id"
                    filterable
                    remote
                    :remote-method="searchGuests"
                    :loading="guestSearching"
                    placeholder="Cari tamu berdasarkan nama/email/telepon..."
                    class="w-full"
                    size="large"
                >
                    <el-option
                        v-for="g in guestOptions"
                        :key="g.id"
                        :value="g.id"
                        :label="`${g.name}${g.phone ? ' · ' + g.phone : ''}`"
                    />
                </el-select>
            </div>

            <el-form v-else :model="newGuest" label-position="top" class="grid grid-cols-2 gap-x-4 gap-y-0">
                <el-form-item label="Nama Depan" required><el-input v-model="newGuest.first_name" /></el-form-item>
                <el-form-item label="Nama Belakang" required><el-input v-model="newGuest.last_name" /></el-form-item>
                <el-form-item label="Email"><el-input v-model="newGuest.email" /></el-form-item>
                <el-form-item label="Telepon"><el-input v-model="newGuest.phone" /></el-form-item>
                <el-form-item label="Jenis Identitas">
                    <el-select v-model="newGuest.id_type" class="w-full">
                        <el-option v-for="t in ['KTP', 'Passport', 'SIM', 'Lainnya']" :key="t" :label="t" :value="t" />
                    </el-select>
                </el-form-item>
                <el-form-item label="No. Identitas"><el-input v-model="newGuest.id_number" /></el-form-item>
                <el-form-item label="Alamat" class="col-span-2"><el-input v-model="newGuest.address" /></el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <template #header>
                <div class="flex items-center gap-2">
                    <el-icon><OfficeBuilding /></el-icon>
                    <span class="font-semibold">Detail Menginap</span>
                </div>
            </template>

            <el-form :model="form" label-position="top">
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="Tipe Kamar">
                        <el-select v-model="form.room_type_id" class="w-full" placeholder="Pilih tipe kamar" @change="onRoomTypeChange" clearable>
                            <el-option v-for="rt in roomTypes" :key="rt.id" :value="rt.id" :label="`${rt.name} · ${formatCurrency(rt.base_rate)} (${rt.capacity} org)`" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Kamar">
                        <el-select
                            v-model="form.room_id"
                            class="w-full"
                            placeholder="Pilih kamar (opsional)"
                            filterable
                            :disabled="!form.room_type_id"
                            :loading="roomsLoading"
                            clearable
                        >
                            <el-option v-for="r in availableRooms" :key="r.id" :value="r.id" :label="`${r.room_number} · Lantai ${r.floor} · ${r.room_type?.name}`" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Check-in" required>
                        <el-date-picker v-model="form.check_in_date" type="date" value-format="YYYY-MM-DD" class="w-full" :disabled-date="(d) => d.getTime() < Date.now() - 86400000" @change="onDatesChange" />
                    </el-form-item>
                    <el-form-item label="Check-out" required>
                        <el-date-picker v-model="form.check_out_date" type="date" value-format="YYYY-MM-DD" class="w-full" :disabled-date="(d) => d <= new Date(form.check_in_date)" @change="onDatesChange" />
                    </el-form-item>
                    <el-form-item label="Dewasa" required>
                        <el-input-number v-model="form.adults" :min="1" :max="20" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Anak-anak">
                        <el-input-number v-model="form.children" :min="0" :max="20" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Sumber Reservasi">
                        <el-select v-model="form.source" class="w-full">
                            <el-option v-for="(label, key) in sourceLabel" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="isEdit" label="Status">
                        <el-tag :type="reservationStatus[form.status]?.type" size="large">{{ reservationStatus[form.status]?.label }}</el-tag>
                    </el-form-item>
                </div>

                <el-form-item label="Permintaan Khusus">
                    <el-input v-model="form.special_requests" type="textarea" :rows="2" placeholder="cth: High floor, king bed, welcome drink..." />
                </el-form-item>
                <el-form-item label="Catatan Internal">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <template #header>
                <div class="flex items-center gap-2">
                    <el-icon><Wallet /></el-icon>
                    <span class="font-semibold">Harga & Pembayaran</span>
                </div>
            </template>

            <div class="grid grid-cols-2 gap-x-4">
                <el-form-item label="Diskon (Rp)">
                    <el-input-number v-model="form.discount" :min="0" :step="10000" class="w-full" :precision="0" @change="loadPricing" />
                </el-form-item>
                <el-form-item label="Pajak (%)">
                    <el-input-number v-model="form.tax_rate" :min="0" :max="100" :step="1" class="w-full" @change="loadPricing" />
                </el-form-item>
            </div>

            <div class="mt-2 grid grid-cols-2 gap-3 rounded-xl bg-sky-50 p-4 text-sm sm:grid-cols-4">
                <div>
                    <div class="text-xs text-sky-700/70">Jumlah Malam</div>
                    <div class="font-bold text-sky-900">{{ pricing.nights || form.nights }} malam</div>
                </div>
                <div>
                    <div class="text-xs text-sky-700/70">Subtotal</div>
                    <div class="font-bold text-sky-900">{{ formatCurrency(pricing.subtotal) }}</div>
                </div>
                <div>
                    <div class="text-xs text-sky-700/70">Biaya Ekstra</div>
                    <div class="font-bold text-sky-900">{{ formatCurrency(pricing.extra_person_fee) }}</div>
                </div>
                <div>
                    <div class="text-xs text-sky-700/70">Pajak ({{ form.tax_rate }}%)</div>
                    <div class="font-bold text-sky-900">{{ formatCurrency(pricing.tax_amount) }}</div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between rounded-xl bg-gray-800 px-5 py-3 text-white">
                <span class="text-sm text-gray-300">Total Tagihan</span>
                <span class="text-xl font-bold">{{ formatCurrency(pricing.total || form.total_amount) }}</span>
            </div>
        </el-card>

        <div class="flex justify-end gap-3 pb-8">
            <el-button @click="back">Batal</el-button>
            <el-button type="primary" size="large" :loading="saving" @click="save">
                {{ isEdit ? 'Simpan Perubahan' : 'Buat Reservasi' }}
            </el-button>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Back, User, OfficeBuilding, Wallet } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { addDays, formatCurrency, reservationStatus, sourceLabel, today } from '../../utils/helpers';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => Boolean(route.params.id) && route.name === 'reservations-edit');

const bootLoading = ref(true);
const saving = ref(false);
const roomTypes = ref([]);
const availableRooms = ref([]);
const roomsLoading = ref(false);
const guestOptions = ref([]);
const guestSearching = ref(false);
const pricing = ref({});

const guestMode = ref('existing');

const form = reactive({
    guest_id: null,
    room_type_id: null,
    room_id: null,
    check_in_date: today(),
    check_out_date: addDays(today(), 1),
    adults: 2,
    children: 0,
    source: 'walk_in',
    discount: 0,
    tax_rate: 10,
    special_requests: '',
    notes: '',
    number_of_guests: '',
    nights: 1,
    room_rate: 0,
    subtotal: 0,
    tax_amount: 0,
    extra_person_fee: 0,
    total_amount: 0,
    status: 'pending',
});

const newGuest = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    id_type: 'KTP',
    id_number: '',
    address: '',
});

onMounted(async () => {
    try {
        const typesRes = await http.get('/room-types/all');
        roomTypes.value = typesRes.data;

        if (isEdit.value) {
            const res = await http.get(`/reservations/${route.params.id}`);
            const r = res.data;
            Object.assign(form, {
                guest_id: r.guest?.id ?? null,
                room_type_id: r.room?.room_type_id ?? null,
                room_id: r.room?.id ?? null,
                check_in_date: r.check_in_date,
                check_out_date: r.check_out_date,
                adults: r.adults,
                children: r.children,
                source: r.source,
                discount: r.discount,
                tax_rate: r.tax_rate,
                special_requests: r.special_requests || '',
                notes: r.notes || '',
                room_rate: r.room_rate,
                nights: r.nights,
                subtotal: r.subtotal,
                extra_person_fee: r.extra_person_fee,
                tax_amount: r.tax_amount,
                total_amount: r.total_amount,
                status: r.status,
            });
            guestMode.value = 'existing';
            guestOptions.value = [r.guest].filter(Boolean);
            if (r.room_type_id) {
                onRoomTypeChange(true);
            }
            loadPricing();
        } else {
            form.tax_rate = Number((await http.get('/settings').catch(() => ({ data: { tax_rate: 10 } }))).data.tax_rate ?? 10);
        }
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        bootLoading.value = false;
    }
});

async function searchGuests(query) {
    if (!query || query.length < 2) {
        guestOptions.value = [];
        return;
    }
    guestSearching.value = true;
    try {
        const res = await http.get('/guests/search', { params: { q: query } });
        guestOptions.value = res.data;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        guestSearching.value = false;
    }
}

function onRoomTypeChange(skipReload = false) {
    const rt = roomTypes.value.find((t) => t.id === form.room_type_id);
    form.room_rate = rt?.base_rate ?? 0;
    if (form.room_id && availableRooms.value.find((r) => r.id === form.room_id)?.room_type_id !== form.room_type_id) {
        form.room_id = null;
    }
    loadAvailableRooms();
    loadPricing();
}

function onDatesChange() {
    loadAvailableRooms();
    loadPricing();
}

async function loadAvailableRooms() {
    if (!form.room_type_id || !form.check_in_date || !form.check_out_date) {
        availableRooms.value = [];
        return;
    }
    roomsLoading.value = true;
    try {
        const params = {
            check_in: form.check_in_date,
            check_out: form.check_out_date,
            room_type_id: form.room_type_id,
        };
        if (isEdit.value) {
            params.exclude = form.room_id;
        }
        const res = await http.get('/rooms/available', { params });
        availableRooms.value = res.data;
    } catch (err) {
        availableRooms.value = [];
    } finally {
        roomsLoading.value = false;
    }
}

async function loadPricing() {
    if (!form.room_type_id || !form.check_in_date || !form.check_out_date) {
        pricing.value = {};
        return;
    }
    try {
        const res = await http.get('/reservations/pricing', {
            params: {
                room_type_id: form.room_type_id,
                check_in_date: form.check_in_date,
                check_out_date: form.check_out_date,
                adults: form.adults,
                discount: form.discount ?? 0,
                tax_rate: form.tax_rate ?? 0,
            },
        });
        pricing.value = res.data;
    } catch (err) {
        pricing.value = {};
    }
}

async function save() {
    if (guestMode.value === 'existing' && !form.guest_id) {
        ElMessage.warning('Pilih tamu terlebih dahulu, atau pilih "Tamu Baru".');
        return;
    }

    if (!form.check_in_date || !form.check_out_date) {
        ElMessage.warning('Tanggal check-in dan check-out wajib diisi.');
        return;
    }
    if (form.check_out_date <= form.check_in_date) {
        ElMessage.warning('Tanggal check-out harus setelah check-in.');
        return;
    }

    saving.value = true;
    try {
        const payload = { ...form };
        delete payload.room_type_id;
        delete payload.nights;
        delete payload.subtotal;
        delete payload.tax_amount;
        delete payload.extra_person_fee;
        delete payload.total_amount;
        delete payload.status;

        if (guestMode.value === 'new') {
            Object.assign(payload, {
                first_name: newGuest.first_name,
                last_name: newGuest.last_name,
                email: newGuest.email || null,
                phone: newGuest.phone || null,
                id_type: newGuest.id_type,
                id_number: newGuest.id_number || null,
                address: newGuest.address || null,
            });
            delete payload.guest_id;
        }

        let res;
        if (isEdit.value) {
            res = await http.put(`/reservations/${route.params.id}`, payload);
            ElMessage.success('Reservasi diperbarui.');
        } else {
            res = await http.post('/reservations', payload);
            ElMessage.success('Reservasi berhasil dibuat.');
        }
        router.push({ name: 'reservations-detail', params: { id: res.data.data.id } });
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        saving.value = false;
    }
}

function back() {
    if (isEdit.value) {
        router.push({ name: 'reservations-detail', params: { id: route.params.id } });
    } else {
        router.push({ name: 'reservations' });
    }
}
</script>