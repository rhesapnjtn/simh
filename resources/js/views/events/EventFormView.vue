<template>
    <div class="mx-auto max-w-4xl space-y-4">
        <div class="flex items-center gap-3">
            <el-button :icon="Back" circle @click="back" />
            <h2 class="page-title">{{ isEdit ? 'Ubah Event' : 'Jadwalkan Event' }}</h2>
        </div>

        <el-card shadow="never" v-loading="bootLoading">
            <template #header>
                <div class="flex items-center gap-2">
                    <el-icon><Calendar /></el-icon>
                    <span class="font-semibold">Detail Event</span>
                </div>
            </template>

            <el-form :model="form" label-position="top">
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="Tipe Event" required>
                        <el-select v-model="form.event_type" class="w-full" placeholder="Pilih tipe event" @change="onTypeChange">
                            <el-option v-for="(label, key) in eventTypeLabel" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Nama Event / Nama Acara" required>
                        <el-input v-model="form.title" placeholder="cth. Pernikahan Andi & Sari" />
                    </el-form-item>

                    <el-form-item v-if="form.event_type === 'ayce'" label="Paket AYCE" required>
                        <el-select v-model="form.ayce_package_id" class="w-full" placeholder="Pilih paket" @change="onPackageChange">
                            <el-option v-for="p in packages" :key="p.id" :value="p.id" :label="`${p.name} · ${formatCurrency(p.price_per_pax)}/orang (${p.min_pax}-${p.max_pax ?? '∞'} pax)`" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-else label="Venue" required>
                        <el-select v-model="form.venue_id" class="w-full" placeholder="Pilih venue" filterable @change="onVenueChange">
                            <el-option v-for="v in venues" :key="v.id" :value="v.id" :label="`${v.name} · kap. ${v.capacity_seated} duduk / ${v.capacity_standing} berdiri`" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Nama Kontak" required>
                        <el-input v-model="form.contact_name" />
                    </el-form-item>
                    <el-form-item label="Telepon">
                        <el-input v-model="form.contact_phone" />
                    </el-form-item>
                    <el-form-item label="Email">
                        <el-input v-model="form.contact_email" type="email" />
                    </el-form-item>

                    <el-form-item label="Tanggal Mulai" required>
                        <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" class="w-full" @change="onDatesChange" />
                    </el-form-item>
                    <el-form-item label="Tanggal Selesai" required>
                        <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" class="w-full" :disabled-date="(d) => form.start_date && d.toISOString().slice(0, 10) < form.start_date" @change="onDatesChange" />
                    </el-form-item>

                    <el-form-item :label="form.event_type === 'ayce' ? 'Jumlah Tamu (Pax)' : 'Jumlah Peserta'" required>
                        <el-input-number v-model="form.pax" :min="1" :max="10000" class="w-full" @change="computePricing" />
                    </el-form-item>
                    <el-form-item label="Total Hari">
                        <el-input-number v-model="form.days" :min="1" class="w-full" disabled />
                    </el-form-item>

                    <el-form-item v-if="selectedVenue && form.event_type !== 'ayce'" label="Harga Sewa/Hari (Rp)" :hint="`Tarif dasar venue: ${formatCurrency(selectedVenue.base_rate)}`">
                        <el-input-number v-model="form.venue_rate" :min="0" :step="50000" :precision="2" class="w-full" @change="computePricing" />
                    </el-form-item>
                    <el-form-item v-if="form.event_type === 'ayce'" label="Harga per Orang (Rp)" :hint="selectedPackage ? `Tarif paket: ${formatCurrency(selectedPackage.price_per_pax)}` : ''">
                        <el-input-number v-model="form.price_per_pax" :min="0" :step="10000" :precision="2" class="w-full" @change="computePricing" />
                    </el-form-item>
                </div>

                <el-divider>{{ form.event_type === 'ayce' ? 'Additional Items' : 'Add-ons (Dekorasi, Sound System, Catering tambahan, dst.)' }}</el-divider>

                <div v-for="(addon, i) in form.addons" :key="i" class="mb-2 flex items-center gap-2">
                    <el-input v-model="addon.name" placeholder="Nama item" style="width: 60%" @input="computePricing" />
                    <el-input-number v-model="addon.amount" :min="0" :step="50000" :precision="2" placeholder="Biaya" controls-position="right" class="flex-1" @change="computePricing" />
                    <el-button type="danger" text :icon="Delete" @click="form.addons.splice(i, 1); computePricing()" />
                </div>
                <el-button size="small" :icon="Plus" @click="addAddon">Tambah Add-on</el-button>

                <el-divider />
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="Diskon (Rp)">
                        <el-input-number v-model="form.discount" :min="0" :step="50000" class="w-full" @change="computePricing" />
                    </el-form-item>
                    <el-form-item label="Pajak (%)">
                        <el-input-number v-model="form.tax_rate" :min="0" :max="100" :step="1" class="w-full" @change="computePricing" />
                    </el-form-item>
                    <el-form-item label="Waktu Setup">
                        <el-date-picker v-model="form.setup_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
                    </el-form-item>
                    <el-form-item v-if="isEdit" label="Status">
                        <el-tag :type="eventStatusMeta[form.status]?.type" size="large">{{ eventStatusMeta[form.status]?.label }}</el-tag>
                    </el-form-item>
                </div>

                <el-form-item label="Catatan">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>

            <div class="mt-2 grid grid-cols-2 gap-3 rounded-xl bg-sky-50 p-4 text-sm sm:grid-cols-4">
                <div>
                    <div class="text-xs text-sky-700/70">Subtotal</div>
                    <div class="font-bold text-sky-900">{{ formatCurrency(preview.subtotal) }}</div>
                </div>
                <div>
                    <div class="text-xs text-sky-700/70">Diskon</div>
                    <div class="font-bold text-sky-900">-{{ formatCurrency(preview.discount) }}</div>
                </div>
                <div>
                    <div class="text-xs text-sky-700/70">Pajak ({{ form.tax_rate }}%)</div>
                    <div class="font-bold text-sky-900">{{ formatCurrency(preview.tax_amount) }}</div>
                </div>
                <div>
                    <div class="text-xs text-sky-700/70">Total</div>
                    <div class="font-bold text-sky-900">{{ formatCurrency(preview.total) }}</div>
                </div>
            </div>
        </el-card>

        <div class="flex justify-end gap-3 pb-8">
            <el-button @click="back">Batal</el-button>
            <el-button type="primary" size="large" :loading="saving" @click="save">
                {{ isEdit ? 'Simpan Perubahan' : 'Buat Event' }}
            </el-button>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Back, Calendar, Delete, Plus } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { addDays, eventStatusMeta, eventTypeLabel, formatCurrency, today } from '../../utils/helpers';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => Boolean(route.params.id) && route.name === 'events-edit');

const bootLoading = ref(true);
const saving = ref(false);
const venues = ref([]);
const packages = ref([]);

const form = reactive({
    event_type: 'wedding',
    title: '',
    venue_id: null,
    ayce_package_id: null,
    contact_name: '',
    contact_phone: '',
    contact_email: '',
    start_date: today(),
    end_date: addDays(today(), 1),
    pax: 100,
    days: 1,
    venue_rate: 0,
    price_per_pax: 0,
    addons: [],
    discount: 0,
    tax_rate: 10,
    setup_at: null,
    notes: '',
    status: 'pending',
});

const selectedVenue = computed(() => venues.value.find((v) => v.id === form.venue_id) || null);
const selectedPackage = computed(() => packages.value.find((p) => p.id === form.ayce_package_id) || null);

const preview = computed(() => {
    const days = form.days || 1;
    const venueRate = form.event_type === 'ayce' ? 0 : (Number(form.venue_rate) > 0 ? Number(form.venue_rate) : Number(selectedVenue.value?.base_rate ?? 0));
    const pricePerPax = form.event_type === 'ayce' ? Number(form.price_per_pax) : 0;
    const venueCost = venueRate * days;
    const ayceCost = pricePerPax * Number(form.pax || 0);
    const addonsCost = (form.addons || []).reduce((s, a) => s + Number(a.amount || 0), 0);
    const subtotal = venueCost + ayceCost + addonsCost;
    const discount = Number(form.discount || 0);
    const taxAmount = subtotal === 0 ? 0 : (subtotal - discount) * Number(form.tax_rate || 0) / 100;
    return {
        subtotal: subtotal.toFixed(2),
        discount: discount.toFixed(2),
        tax_amount: taxAmount.toFixed(2),
        total: Math.max(0, subtotal - discount + taxAmount).toFixed(2),
    };
});

onMounted(async () => {
    try {
        const [venueRes, pkgRes, settingRes] = await Promise.all([
            http.get('/event-venues/all'),
            http.get('/ayce-packages/all'),
            http.get('/settings').catch(() => ({ data: { tax_rate: 10 } })),
        ]);
        venues.value = venueRes.data;
        packages.value = pkgRes.data;
        form.tax_rate = Number(settingRes.data.tax_rate ?? 10);

        if (isEdit.value) {
            const res = await http.get(`/events/${route.params.id}`);
            const e = res.data;
            Object.assign(form, {
                event_type: e.event_type,
                title: e.title,
                venue_id: e.venue?.id ?? null,
                ayce_package_id: e.ayce_package?.id ?? null,
                contact_name: e.contact_name,
                contact_phone: e.contact_phone || '',
                contact_email: e.contact_email || '',
                start_date: e.start_date,
                end_date: e.end_date,
                pax: e.pax,
                days: e.days,
                venue_rate: e.venue_rate,
                price_per_pax: e.price_per_pax,
                addons: e.addons?.map((a) => ({ ...a })) ?? [],
                discount: e.discount,
                tax_rate: e.tax_rate,
                setup_at: e.setup_at,
                notes: e.notes || '',
                status: e.status,
            });
        }
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        bootLoading.value = false;
    }
});

function onTypeChange() {
    if (form.event_type !== 'ayce') form.ayce_package_id = null;
    if (form.event_type === 'ayce') {
        form.price_per_pax = selectedPackage.value?.price_per_pax ?? 0;
    }
    computePricing();
}

function onVenueChange() {
    form.venue_rate = selectedVenue.value?.base_rate ?? 0;
    computePricing();
}

function onPackageChange() {
    form.price_per_pax = selectedPackage.value?.price_per_pax ?? 0;
    computePricing();
}

function onDatesChange() {
    computePricing();
}

function computePricing() {
    if (!form.start_date || !form.end_date) return;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    if (end < start) return;
    const ms = Math.floor((end - start) / 86400000);
    form.days = ms + 1;
}

function addAddon() {
    form.addons.push({ name: '', amount: 0 });
}

async function save() {
    if (!form.event_type || !form.title.trim()) {
        ElMessage.warning('Tipe event dan nama event wajib diisi.');
        return;
    }
    if (form.event_type === 'ayce' && !form.ayce_package_id) {
        ElMessage.warning('Pilih paket AYCE.');
        return;
    }
    if (form.event_type !== 'ayce' && !form.venue_id) {
        ElMessage.warning('Pilih venue.');
        return;
    }
    if (!form.start_date || !form.end_date || form.end_date < form.start_date) {
        ElMessage.warning('Periksa kembali tanggal event.');
        return;
    }
    if (form.event_type === 'ayce' && selectedPackage.value && Number(form.pax) > selectedPackage.value.max_pax) {
        ElMessage.warning(`Jumlah tamu melebihi kuota paket (maks ${selectedPackage.value.max_pax}).`);
        return;
    }
    if (selectedVenue.value?.capacity_seated && Number(form.pax) > selectedVenue.value.capacity_seated) {
        ElMessage.warning(`Melebihi kapasitas duduk venue (${selectedVenue.value.capacity_seated}).`);
        return;
    }

    saving.value = true;
    try {
        const payload = { ...form };
        delete payload.days;
        delete payload.status;
        if (form.event_type === 'ayce') payload.venue_id = null;
        else payload.ayce_package_id = null;

        let res;
        if (isEdit.value) {
            res = await http.put(`/events/${route.params.id}`, payload);
            ElMessage.success('Event diperbarui.');
        } else {
            res = await http.post('/events', payload);
            ElMessage.success('Event berhasil dijadwalkan.');
        }
        router.push({ name: 'events-detail', params: { id: res.data.data.id } });
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        saving.value = false;
    }
}

function back() {
    if (isEdit.value) {
        router.push({ name: 'events-detail', params: { id: route.params.id } });
    } else {
        router.push({ name: 'events' });
    }
}
</script>