<template>
    <div v-if="loading" class="card p-6"><el-skeleton :rows="10" animated /></div>

    <div v-else-if="reservation" class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <el-button :icon="Back" circle @click="router.push({ name: 'reservations' })" />
            <div class="mr-auto">
                <div class="flex items-center gap-2">
                    <h2 class="page-title">{{ reservation.code }}</h2>
                    <el-tag :type="reservationStatus[reservation.status].type">{{ reservationStatus[reservation.status].label }}</el-tag>
                    <el-tag :type="paymentStatusMeta[reservation.payment_status].type">{{ paymentStatusMeta[reservation.payment_status].label }}</el-tag>
                </div>
                <div class="text-sm text-gray-500">
                    Dibuat oleh {{ reservation.user?.name }} · {{ reservation.created_at }}
                </div>
            </div>

            <template v-if="reservation.status === 'pending'">
                <el-button type="warning" :icon="Check" @click="postAction('confirm')">Konfirmasi</el-button>
                <el-button type="danger" plain :icon="Close" @click="cancelReservation">Batalkan</el-button>
            </template>
            <template v-if="['pending', 'confirmed'].includes(reservation.status)">
                <el-button type="success" :icon="Position" @click="openCheckIn">Check-in</el-button>
                <el-button plain :icon="Warning" @click="postAction('no-show')">No-Show</el-button>
            </template>
            <template v-if="reservation.status === 'checked_in'">
                <el-button type="danger" :icon="CircleCheck" @click="checkOutDialog = true">Check-out</el-button>
            </template>
            <el-button v-if="['pending', 'confirmed'].includes(reservation.status)" :icon="Edit" @click="router.push({ name: 'reservations-edit', params: { id: reservation.id } })">
                Ubah
            </el-button>
            <el-button v-if="!['checked_in', 'checked_out'].includes(reservation.status)" type="danger" plain :icon="Delete" @click="deleteReservation">
                Hapus
            </el-button>
        </div>

        <div v-if="reservation.status === 'checked_in' && reservation.balance > 0" class="card border-rose-200 bg-rose-50 p-4">
            <div class="flex items-center gap-3">
                <el-icon :size="20" color="#e11d48"><Warning /></el-icon>
                <div>
                    <div class="font-semibold text-rose-700">Tamu masih memiliki saldo belum lunas</div>
                    <div class="text-sm text-rose-600">{{ formatCurrency(reservation.balance) }} belum dibayar. Catat pembayaran sebelum check-out.</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-1">
                <el-card shadow="never">
                    <template #header><span class="font-semibold">Informasi Tamu</span></template>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Nama</span>
                            <router-link v-if="reservation.guest" :to="{ name: 'guests' }" class="font-semibold text-sky-600">
                                {{ reservation.guest?.name }}
                            </router-link>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Telepon</span>
                            <span>{{ reservation.guest?.phone || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Sumber</span>
                            <el-tag size="small" type="info">{{ sourceLabel[reservation.source] }}</el-tag>
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Detail Menginap</span></template>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Check-in</span>
                            <span class="font-medium">{{ reservation.check_in_date }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Check-out</span>
                            <span class="font-medium">{{ reservation.check_out_date }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Jumlah malam</span>
                            <span>{{ reservation.nights }} malam</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Tamu</span>
                            <span>{{ reservation.adults }} dewasa · {{ reservation.children }} anak</span>
                        </div>
                        <el-divider class="!my-2" />
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Kamar</span>
                            <div class="text-right">
                                <template v-if="reservation.room">
                                    <div class="font-semibold">{{ reservation.room.room_number }}</div>
                                    <div class="text-xs text-gray-400">{{ reservation.room.room_type }}</div>
                                </template>
                                <template v-else>
                                    <el-button size="small" text type="primary" @click="openAssignRoom">Tugaskan kamar</el-button>
                                </template>
                            </div>
                        </div>
                        <div v-if="reservation.special_requests" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-700">
                            {{ reservation.special_requests }}
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Rincian Biaya</span></template>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Tarif/malam</span><span>{{ formatCurrency(reservation.room_rate) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal ({{ reservation.nights }} malam)</span><span>{{ formatCurrency(reservation.subtotal) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Biaya ekstra tamu</span><span>{{ formatCurrency(reservation.extra_person_fee) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Diskon</span><span class="text-emerald-600">-{{ formatCurrency(reservation.discount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Pajak ({{ reservation.tax_rate }}%)</span><span>{{ formatCurrency(reservation.tax_amount) }}</span></div>
                        <el-divider class="!my-2" />
                        <div class="flex justify-between"><span>Biaya kamar</span><span>{{ formatCurrency(reservation.total_amount) }}</span></div>
                        <div v-if="reservation.charges_total > 0" class="flex justify-between"><span>Biaya tambahan / layanan</span><span>{{ formatCurrency(reservation.charges_total) }}</span></div>
                        <div class="flex justify-between font-bold"><span>Total Tagihan</span><span class="text-lg">{{ formatCurrency(reservation.grand_total) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Terbayar</span><span class="text-emerald-600">{{ formatCurrency(reservation.paid_amount) }}</span></div>
                        <div class="flex justify-between text-base"><span>Saldo</span><span :class="reservation.balance > 0 ? 'font-bold text-rose-600' : 'font-bold text-emerald-600'">{{ formatCurrency(reservation.balance) }}</span></div>
                    </div>
                </el-card>
            </div>

            <div class="space-y-5 lg:col-span-2">
                <el-card shadow="never">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Pembayaran</span>
                            <el-button size="small" type="primary" :icon="Plus" @click="paymentDialog = true">Catat Pembayaran</el-button>
                        </div>
                    </template>

                    <el-table :data="reservation.payments" v-loading="paymentsLoading" stripe>
                        <el-table-column label="Tanggal" width="150">
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
                        <el-table-column prop="reference" label="Referensi" />
                        <el-table-column prop="user" label="Petugas" width="120" />
                        <el-table-column label="" width="70">
                            <template #default="{ row }">
                                <el-button size="small" text type="danger" :icon="Delete" @click="deletePayment(row)" />
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!reservation.payments.length" description="Belum ada pembayaran" :image-size="60" class="pt-4" />
                </el-card>

                <el-card shadow="never">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Biaya Tambahan / Layanan</span>
                            <el-button size="small" type="primary" :icon="Plus" @click="chargeDialog = true">Tambah Biaya</el-button>
                        </div>
                    </template>

                    <el-table :data="reservation.charges" empty-text="Belum ada biaya tambahan" stripe>
                        <el-table-column label="Deskripsi" min-width="180">
                            <template #default="{ row }">
                                <div class="font-medium text-gray-800">{{ row.description }}</div>
                                <div class="text-xs text-gray-400">{{ chargeCategoryLabel[row.category] }} · Kamar {{ row.room || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="Qty" prop="quantity" width="60" align="center" />
                        <el-table-column label="Harga" width="130">
                            <template #default="{ row }">{{ formatCurrency(row.unit_price) }}</template>
                        </el-table-column>
                        <el-table-column label="Total" width="140">
                            <template #default="{ row }">
                                <span class="font-medium">{{ formatCurrency(row.amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="charged_at" label="Waktu" width="150" />
                        <el-table-column label="" width="70">
                            <template #default="{ row }">
                                <el-button size="small" text type="danger" :icon="Close" @click="voidCharge(row)" />
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <el-card shadow="never">
                    <template #header><span class="font-semibold">Timeline</span></template>
                    <el-timeline>
                        <el-timeline-item v-if="reservation.created_at" type="primary" :timestamp="reservation.created_at">
                            Reservasi dibuat
                        </el-timeline-item>
                        <el-timeline-item v-if="reservation.status !== 'pending'" :timestamp="reservation.created_at" :type="timelineType(reservation.status)">
                            Status: <b>{{ reservationStatus[reservation.status].label }}</b>
                        </el-timeline-item>
                        <el-timeline-item v-if="reservation.check_in_at" type="success" :timestamp="reservation.check_in_at">
                            Tamu check-in
                        </el-timeline-item>
                        <el-timeline-item v-if="reservation.check_out_at" type="success" :timestamp="reservation.check_out_at">
                            Tamu check-out
                        </el-timeline-item>
                        <el-timeline-item v-if="reservation.cancelled_at" type="danger" :timestamp="reservation.cancelled_at">
                            Dibatalkan: {{ reservation.cancellation_reason || '-' }}
                        </el-timeline-item>
                    </el-timeline>
                </el-card>
            </div>
        </div>

        <el-dialog v-model="paymentDialog" title="Catat Pembayaran" width="440px">
            <el-form :model="paymentForm" label-position="top">
                <el-form-item label="Jumlah" required>
                    <el-input-number v-model="paymentForm.amount" :min="0" class="w-full" :precision="0" :max="Math.max(reservation.balance, reservation.balance)" :step="50000" />
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

        <el-dialog v-model="chargeDialog" title="Tambah Biaya Layanan" width="440px">
            <el-form :model="chargeForm" label-position="top">
                <el-form-item label="Deskripsi" required>
                    <el-input v-model="chargeForm.description" placeholder="cth: Room service - Nasi Goreng" />
                </el-form-item>
                <el-form-item label="Kategori" required>
                    <el-select v-model="chargeForm.category" class="w-full">
                        <el-option v-for="(label, key) in chargeCategoryLabel" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Jumlah" required>
                        <el-input-number v-model="chargeForm.quantity" :min="1" class="w-full" />
                    </el-form-item>
                    <el-form-item label="Harga Satuan" required>
                        <el-input-number v-model="chargeForm.unit_price" :min="0" :precision="0" :step="10000" class="w-full" />
                    </el-form-item>
                </div>
                <div class="mb-3 rounded-lg bg-gray-50 px-4 py-2 text-sm">
                    Total: <b>{{ formatCurrency(chargeForm.quantity * chargeForm.unit_price) }}</b>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="chargeDialog = false">Batal</el-button>
                <el-button type="primary" :loading="savingCharge" @click="saveCharge">Simpan</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="assignRoomDialog" title="Tugaskan Kamar" width="440px">
            <el-form label-position="top">
                <el-form-item label="Pilih Kamar">
                    <el-select v-model="assignRoomId" class="w-full" filterable placeholder="Cari kamar">
                        <el-option v-for="r in assignRoomOptions" :key="r.id" :value="r.id" :label="`${r.room_number} · ${r.room_type?.name} (${formatCurrency(r.room_type?.base_rate)}/malam)`" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="assignRoomDialog = false">Batal</el-button>
                <el-button type="primary" :loading="assigning" @click="doAssignRoom">Tugaskan</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="checkInDialog" title="Check-in Tamu" width="440px">
            <el-form label-position="top">
                <el-form-item v-if="!reservation.room" label="Kamar">
                    <el-select v-model="checkInRoomId" class="w-full" filterable placeholder="Pilih kamar">
                        <el-option v-for="r in checkInRoomOptions" :key="r.id" :value="r.id" :label="`${r.room_number} · ${r.room_type?.name}`" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="reservation.room" label="Kamar">
                    <div class="flex w-full items-center justify-between rounded-lg bg-gray-50 px-4 py-2">
                        <span class="font-medium">{{ reservation.room.room_number }}</span>
                        <el-tag type="success">Siap check-in</el-tag>
                    </div>
                </el-form-item>
                <el-form-item label="Jumlah Malam (untuk early check-in)">
                    <el-input-number v-model="checkInNights" :min="1" class="w-full" />
                    <div class="mt-1 w-full text-xs text-gray-400">Default: {{ reservation.nights }} malam. Hanya ubah bila waktu menginap berbeda.</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="checkInDialog = false">Batal</el-button>
                <el-button type="success" :loading="checkingIn" @click="doCheckIn">Proses Check-in</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="checkOutDialog" title="Check-out Tamu" width="440px">
            <div class="space-y-3">
                <el-alert
                    :title="`Total tagihan ${formatCurrency(reservation.grand_total)} · Terbayar ${formatCurrency(reservation.paid_amount)} · Saldo ${formatCurrency(reservation.balance)}`"
                    :type="reservation.balance > 0 ? 'warning' : 'success'"
                    show-icon
                    :closable="false"
                />
                <div v-if="reservation.balance > 0" class="text-sm text-gray-600">
                    Saldo masih ada. Cek di kolom pemeriksaan untuk menyelesaikan tanpa pajak pada {{ formatCurrency(reservation.balance) }}.
                </div>
            </div>
            <template #footer>
                <el-button @click="checkOutDialog = false">Batal</el-button>
                <el-button type="danger" :loading="checkingOut" @click="doCheckOut(false)">Check-out</el-button>
                <el-button v-if="reservation.balance > 0" type="danger" plain :loading="checkingOut" @click="doCheckOut(true)">Force Check-out</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Back,
    Edit,
    Delete,
    Plus,
    Close,
    Check,
    Position,
    Warning,
    CircleCheck,
} from '@element-plus/icons-vue';
import http, { getErrorMessage } from '../../api/http';
import { useAutoRefresh } from '../../composables/useAutoRefresh';
import {
    formatCurrency,
    methodsLabel,
    paymentStatusMeta,
    reservationStatus,
    sourceLabel,
    chargeCategoryLabel,
    addDays,
    today,
} from '../../utils/helpers';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const reservation = ref(null);
const paymentsLoading = ref(false);

const paymentDialog = ref(false);
const savingPayment = ref(false);
const paymentForm = reactive({ amount: 0, method: 'cash', reference: '', paid_at: '', notes: '' });

const chargeDialog = ref(false);
const savingCharge = ref(false);
const chargeForm = reactive({ description: '', category: 'food_beverage', quantity: 1, unit_price: 50000 });

const assignRoomDialog = ref(false);
const assignRoomOptions = ref([]);
const assignRoomId = ref(null);
const assigning = ref(false);

const checkInDialog = ref(false);
const checkInRoomOptions = ref([]);
const checkInRoomId = ref(null);
const checkInNights = ref(1);
const checkingIn = ref(false);

const checkOutDialog = ref(false);
const checkingOut = ref(false);

onMounted(load);

useAutoRefresh(load);

async function load() {
    loading.value = true;
    try {
        const res = await http.get(`/reservations/${route.params.id}`);
        reservation.value = res.data;
        checkInNights.value = res.data.nights || 1;
        paymentForm.amount = res.data.balance > 0 ? res.data.balance : 0;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
        router.push({ name: 'reservations' });
    } finally {
        loading.value = false;
    }
}

async function postAction(action) {
    try {
        await http.post(`/reservations/${reservation.value.id}/${action}`);
        ElMessage.success('Berhasil.');
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    }
}

async function cancelReservation() {
    try {
        const { value } = await ElMessageBox.prompt('Alasan pembatalan (opsional):', 'Batalkan Reservasi', {
            confirmButtonText: 'Batalkan',
            cancelButtonText: 'Tutup',
        });
        await http.post(`/reservations/${reservation.value.id}/cancel`, { reason: value ?? '' });
        ElMessage.success('Reservasi dibatalkan.');
        await load();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

async function deleteReservation() {
    try {
        await ElMessageBox.confirm(`Hapus reservasi ${reservation.value.code}?`, 'Konfirmasi', { type: 'warning' });
        await http.delete(`/reservations/${reservation.value.id}`);
        ElMessage.success('Reservasi dihapus.');
        router.push({ name: 'reservations' });
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

async function savePayment() {
    if (!paymentForm.amount || paymentForm.amount <= 0) {
        ElMessage.warning('Masukkan jumlah pembayaran.');
        return;
    }
    savingPayment.value = true;
    try {
        const res = await http.post(`/reservations/${reservation.value.id}/payments`, {
            amount: paymentForm.amount,
            method: paymentForm.method,
            reference: paymentForm.reference || null,
            paid_at: paymentForm.paid_at || null,
            notes: paymentForm.notes || null,
        });
        ElMessage.success(`Pembayaran ${formatCurrency(paymentForm.amount)} dicatat.`);
        paymentDialog.value = false;
        Object.assign(reservation.value, res.data.reservation);
        await refreshTransactions();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        savingPayment.value = false;
    }
}

async function saveCharge() {
    if (!chargeForm.description) {
        ElMessage.warning('Deskripsi wajib diisi.');
        return;
    }
    savingCharge.value = true;
    try {
        await http.post(`/reservations/${reservation.value.id}/charges`, {
            description: chargeForm.description,
            category: chargeForm.category,
            quantity: chargeForm.quantity,
            unit_price: chargeForm.unit_price,
            room_id: reservation.value.room?.id ?? null,
        });
        ElMessage.success('Biaya tambahan dicatat.');
        chargeDialog.value = false;
        await refreshTransactions();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        savingCharge.value = false;
    }
}

async function deletePayment(payment) {
    try {
        await ElMessageBox.confirm(`Hapus pembayaran ${formatCurrency(payment.amount)}?`, 'Konfirmasi', { type: 'warning' });
        const res = await http.delete(`/payments/${payment.id}`);
        ElMessage.success('Pembayaran dihapus.');
        Object.assign(reservation.value, res.data.reservation);
        await refreshTransactions();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

async function voidCharge(charge) {
    try {
        await ElMessageBox.confirm(`Batalkan biaya "${charge.description}"?`, 'Konfirmasi', { type: 'warning' });
        await http.post(`/charges/${charge.id}/void`);
        ElMessage.success('Biaya dibatalkan.');
        await refreshTransactions();
    } catch (err) {
        if (err !== 'cancel' && err !== 'close') ElMessage.error(getErrorMessage(err));
    }
}

async function refreshTransactions() {
    paymentsLoading.value = true;
    try {
        const res = await http.get(`/reservations/${reservation.value.id}`);
        reservation.value = res.data;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        paymentsLoading.value = false;
    }
}

function openAssignRoom() {
    assignRoomDialog.value = true;
    loadAvailableRooms().then((rooms) => {
        assignRoomOptions.value = rooms;
    });
}

async function doAssignRoom() {
    if (!assignRoomId.value) {
        ElMessage.warning('Pilih kamar terlebih dahulu.');
        return;
    }
    assigning.value = true;
    try {
        await http.post(`/reservations/${reservation.value.id}/assign-room`, { room_id: assignRoomId.value });
        ElMessage.success('Kamar ditugaskan.');
        assignRoomDialog.value = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        assigning.value = false;
    }
}

function openCheckIn() {
    checkInDialog.value = true;
    checkInNights.value = reservation.value.nights || 1;
    loadAvailableRooms().then((rooms) => {
        checkInRoomOptions.value = rooms;
        checkInRoomId.value = null;
    });
}

async function doCheckIn() {
    checkingIn.value = true;
    try {
        const payload = {};
        if (!reservation.value.room && checkInRoomId.value) {
            payload.room_id = checkInRoomId.value;
        }
        payload.actual_nights = checkInNights.value;
        const res = await http.post(`/reservations/${reservation.value.id}/check-in`, payload);
        ElMessage.success(`Check-in berhasil. Kamar ${res.data.data.room?.room_number}.`);
        checkInDialog.value = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        checkingIn.value = false;
    }
}

async function doCheckOut(force) {
    checkingOut.value = true;
    try {
        await http.post(`/reservations/${reservation.value.id}/check-out`, force ? { force: true } : {});
        ElMessage.success('Check-out berhasil. Kamar menunggu housekeeping.');
        checkOutDialog.value = false;
        await load();
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
    } finally {
        checkingOut.value = false;
    }
}

async function loadAvailableRooms() {
    try {
        const params = {
            check_in: reservation.value.check_in_date,
            check_out: reservation.value.check_out_date,
            exclude: reservation.value.room_id || undefined,
        };
        if (reservation.value.room?.room_type_id) {
            params.room_type_id = reservation.value.room.room_type_id;
        }
        const res = await http.get('/rooms/available', { params });
        return res.data;
    } catch (err) {
        ElMessage.error(getErrorMessage(err));
        return [];
    }
}

function timelineType(status) {
    const map = {
        confirmed: 'warning',
        checked_in: 'success',
        checked_out: 'primary',
        cancelled: 'danger',
        no_show: 'danger',
    };
    return map[status] ?? 'info';
}
</script>