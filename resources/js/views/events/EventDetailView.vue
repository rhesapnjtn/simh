<template>
    <div v-if="loading" class="card p-6"><el-skeleton :rows="10" animated /></div>

    <div v-else-if="booking" class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <el-button :icon="Back" circle @click="router.push({ name: 'events' })" />
            <div class="mr-auto">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="page-title">{{ booking.code }}</h2>
                    <el-tag :type="eventStatusMeta[booking.status]?.type">{{ eventStatusMeta[booking.status]?.label }}</el-tag>
                    <el-tag :type="paymentStatusMeta[booking.payment_status]?.type">{{ paymentStatusMeta[booking.payment_status]?.label }}</el-tag>
                </div>
                <div class="text-sm text-gray-500">
                    {{ booking.event_type_label }} · dibuat {{ booking.created_at }}
                </div>
            </div>

            <el-button v-if="auth.can('events.status') && booking.status === 'pending'" type="primary" :icon="Check" @click="changeStatus('confirmed')">Konfirmasi</el-button>
            <el-button v-if="auth.can('events.status') && booking.status === 'confirmed'" type="success" :icon="VideoPlay" @click="changeStatus('in_progress')">Mulai Acara</el-button>
            <el-button v-if="auth.can('events.status') && ['confirmed', 'in_progress'].includes(booking.status)" type="success" :icon="CircleCheck" @click="changeStatus('completed')">Selesai</el-button>
            <el-button v-if="auth.can('events.status') && ['pending', 'confirmed', 'in_progress'].includes(booking.status)" type="danger" plain :icon="Close" @click="cancelEvent">Batalkan</el-button>
            <el-button v-if="auth.can('events.update') && !['completed', 'cancelled'].includes(booking.status)" :icon="Edit" @click="router.push({ name: 'events-edit', params: { id: booking.id } })">Ubah</el-button>
            <el-button v-if="auth.can('events.delete') && ['pending', 'confirmed'].includes(booking.status)" type="danger" plain :icon="Delete" @click="deleteEvent">Hapus</el-button>
        </div>

        <el-alert
            v-if="booking.cancelled_at"
            title="Event dibatalkan"
            :description="booking.cancellation_reason || 'Tanpa alasan'"
            type="error"
            show-icon
            :closable="false"
        />

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-1">
                <el-card shadow="never">
                    <template #header><span class="font-semibold">Informasi Event</span></template>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between"><span class="text-gray-500">Tipe</span><span>{{ booking.event_type_label }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Kontak</span><span class="text-right">{{ booking.contact_name }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Telepon</span><span>{{ booking.contact_phone || '-' }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Email</span><span class="text-right">{{ booking.contact_email || '-' }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Person in charge</span><span>{{ booking.assigned_user?.name || '-' }}</span></div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Venue & Jadwal</span></template>
                    <div class="space-y-2 text-sm">
                        <template v-if="booking.venue">
                            <div class="flex items-center justify-between"><span class="text-gray-500">Venue</span><span class="font-medium">{{ booking.venue.name }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-gray-500">Kapasitas</span><span>{{ booking.venue.capacity_seated }} duduk / {{ booking.venue.capacity_standing }} berdiri</span></div>
                        </template>
                        <template v-else-if="booking.ayce_package">
                            <div class="flex items-center justify-between"><span class="text-gray-500">Paket</span><span class="font-medium">{{ booking.ayce_package.name }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-gray-500">Durasi paket</span><span>{{ booking.ayce_package.duration_minutes }} menit</span></div>
                        </template>
                        <template v-else>
                            <div class="flex items-center justify-between"><span class="text-gray-500">Venue</span><span class="text-rose-500">Belum dipilih</span></div>
                        </template>
                        <el-divider class="!my-2" />
                        <div class="flex items-center justify-between"><span class="text-gray-500">Mulai</span><span class="font-medium">{{ booking.start_date }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Selesai</span><span class="font-medium">{{ booking.end_date }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Lama</span><span>{{ booking.days }} hari</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Jumlah tamu</span><span>{{ booking.pax }} pax</span></div>
                        <div v-if="booking.setup_at" class="flex items-center justify-between"><span class="text-gray-500">Setup</span><span>{{ booking.setup_at }}</span></div>
                        <div v-if="booking.completed_at" class="flex items-center justify-between"><span class="text-gray-500">Selesai pada</span><span>{{ booking.completed_at }}</span></div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Rincian Biaya</span></template>
                    <div class="space-y-2 text-sm">
                        <template v-if="booking.event_type === 'ayce'">
                            <div class="flex justify-between"><span class="text-gray-500">Tarif AYCE ({{ booking.price_per_pax }}/pax × {{ booking.pax }})</span><span>{{ formatCurrency(booking.price_per_pax * booking.pax) }}</span></div>
                        </template>
                        <template v-else>
                            <div class="flex justify-between"><span class="text-gray-500">Sewa venue ({{ formatCurrency(booking.venue_rate) }} × {{ booking.days }} hari)</span><span>{{ formatCurrency(booking.venue_rate * booking.days) }}</span></div>
                        </template>
                        <div v-if="booking.addons?.length">
                            <div class="mb-1 text-gray-500">Add-ons</div>
                            <div v-for="(a, i) in booking.addons" :key="i" class="flex justify-between text-xs">
                                <span>{{ a.name }}</span><span>{{ formatCurrency(a.amount) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>{{ formatCurrency(booking.subtotal) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Diskon</span><span class="text-emerald-600">-{{ formatCurrency(booking.discount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Pajak ({{ booking.tax_rate }}%)</span><span>{{ formatCurrency(booking.tax_amount) }}</span></div>
                        <el-divider class="!my-2" />
                        <div class="flex justify-between font-bold"><span>Total Tagihan</span><span class="text-lg">{{ formatCurrency(booking.total_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Terbayar</span><span class="text-emerald-600">{{ formatCurrency(booking.paid_amount) }}</span></div>
                        <div class="flex justify-between text-base">
                            <span>Saldo</span>
                            <span :class="booking.balance > 0 ? 'font-bold text-rose-600' : 'font-bold text-emerald-600'">{{ formatCurrency(booking.balance) }}</span>
                        </div>
                    </div>
                </el-card>
            </div>

            <div class="space-y-5 lg:col-span-2">
                <el-card shadow="never">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Pembayaran</span>
                            <el-button v-if="auth.can('events.payment') && !['completed', 'cancelled'].includes(booking.status)" size="small" type="primary" :icon="Plus" @click="openPayment">Catat Pembayaran</el-button>
                        </div>
                    </template>

                    <el-table :data="booking.payments" stripe empty-text="Belum ada pembayaran">
                        <el-table-column label="Tanggal" width="160">
                            <template #default="{ row }">{{ row.paid_at }}</template>
                        </el-table-column>
                        <el-table-column label="Jumlah" width="150">
                            <template #default="{ row }">
                                <span class="font-medium text-emerald-600">{{ formatCurrency(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Metode" width="140">
                            <template #default="{ row }">{{ methodsLabel[row.method] }}</template>
                        </el-table-column>
                        <el-table-column prop="reference" label="Referensi" min-width="130" />
                        <el-table-column prop="user" label="Petugas" width="130" />
                        <el-table-column label="" width="70">
                            <template #default="{ row }">
                                <el-button v-if="auth.can('events.payment') && !['completed', 'cancelled'].includes(booking.status)" size="small" text type="danger" :icon="Delete" @click="deletePayment(row)" />
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Timeline</span></template>
                    <el-timeline>
                        <el-timeline-item v-if="booking.created_at" type="primary" :timestamp="booking.created_at">Event dijadwalkan</el-timeline-item>
                        <el-timeline-item v-if="!['pending'].includes(booking.status)" :timestamp="booking.created_at" :type="timelineType(booking.status)">Status: <b>{{ eventStatusMeta[booking.status]?.label }}</b></el-timeline-item>
                        <el-timeline-item v-if="booking.completed_at" type="success" :timestamp="booking.completed_at">Event selesai</el-timeline-item>
                        <el-timeline-item v-if="booking.cancelled_at" type="danger" :timestamp="booking.cancelled_at">Dibatalkan: {{ booking.cancellation_reason || '-' }}</el-timeline-item>
                    </el-timeline>
                </el-card>
            </div>
        </div>

        <el-dialog v-model="paymentDialog" title="Catat Pembayaran Event" width="440px">
            <el-form :model="paymentForm" label-position="top">
                <el-alert
                    class="mb-3"
                    :title="`Total ${formatCurrency(booking.total_amount)} · Terbayar ${formatCurrency(booking.paid_amount)} · Sisa ${formatCurrency(booking.balance)}`"
                    :type="booking.balance > 0 ? 'warning' : 'success'"
                    show-icon
                    :closable="false"
                />
                <el-form-item label="Jumlah" required>
                    <el-input-number v-model="paymentForm.amount" :min="0" :max="Math.max(booking.balance, 0)" class="w-full" :precision="2" :step="50000" />
                </el-form-item>
                <el-form-item label="Metode" required>
                    <el-select v-model="paymentForm.method" class="w-full">
                        <el-option v-for="(label, key) in methodsLabel" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Referensi">
                    <el-input v-model="paymentForm.reference" placeholder="Nomor referensi/struk (opsional)" />
                </el-form-item>
                <el-form-item label="Waktu Pembayaran">
                    <el-date-picker v-model="paymentForm.paid_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="w-full" />
                </el-form-item>
                <el-form-item label="Catatan">
                    <el-input v-model="paymentForm.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="paymentDialog = false">Batal</el-button>
                <el-button type="primary" :loading="savingPayment" @click="savePayment">Simpan</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Back, Check, CircleCheck, Close, Delete, Edit, Plus, VideoPlay } from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { formatCurrency, methodsLabel, paymentStatusMeta, eventStatusMeta } from '../../utils/helpers';
import { useAutoRefresh } from '../../composables/useAutoRefresh';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const loading = ref(true);
const booking = ref(null);
const paymentDialog = ref(false);
const savingPayment = ref(false);
const paymentForm = reactive({ amount: 0, method: 'cash', reference: '', paid_at: '', notes: '' });

onMounted(load);
useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get(`/events/${route.params.id}`);
        booking.value = res.data;
        paymentForm.amount = res.data.balance > 0 ? res.data.balance : 0;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
        router.push({ name: 'events' });
    } finally {
        loading.value = false;
    }
}

async function changeStatus(status) {
    const labels = {
        confirmed: 'Konfirmasi',
        in_progress: 'Mulai acara',
        completed: 'Tandai selesai',
        cancelled: 'Batalkan event',
    };
    try {
        await ElMessageBox.confirm(`${labels[status]} event ${booking.value.code}?`, 'Konfirmasi', { type: 'warning' });
        const payload = {};
        if (status === 'cancelled') {
            const { value } = await ElMessageBox.prompt('Alasan pembatalan (opsional):', 'Batalkan Event', {
                confirmButtonText: 'Batalkan',
                cancelButtonText: 'Tutup',
            });
            payload.reason = value ?? '';
        }
        await http.post(`/events/${booking.value.id}/status`, { status, ...payload });
        ElMessage.success('Status diperbarui.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

function cancelEvent() {
    changeStatus('cancelled');
}

async function deleteEvent() {
    try {
        await ElMessageBox.confirm(`Hapus event ${booking.value.code}?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/events/${booking.value.id}`);
        ElMessage.success('Event dihapus.');
        router.push({ name: 'events' });
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

function openPayment() {
    paymentDialog.value = true;
}

async function savePayment() {
    if (!paymentForm.amount || paymentForm.amount <= 0) {
        ElMessage.warning('Masukkan jumlah pembayaran.');
        return;
    }
    savingPayment.value = true;
    try {
        await http.post(`/events/${booking.value.id}/payments`, {
            amount: paymentForm.amount,
            method: paymentForm.method,
            reference: paymentForm.reference || null,
            paid_at: paymentForm.paid_at || null,
            notes: paymentForm.notes || null,
        });
        ElMessage.success(`Pembayaran ${formatCurrency(paymentForm.amount)} dicatat.`);
        paymentDialog.value = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        savingPayment.value = false;
    }
}

async function deletePayment(payment) {
    try {
        await ElMessageBox.confirm(`Hapus pembayaran ${formatCurrency(payment.amount)}?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/event-payments/${payment.id}`);
        ElMessage.success('Pembayaran dihapus.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

function timelineType(status) {
    const map = {
        confirmed: 'warning',
        in_progress: 'success',
        completed: 'primary',
        cancelled: 'danger',
    };
    return map[status] ?? 'info';
}
</script>