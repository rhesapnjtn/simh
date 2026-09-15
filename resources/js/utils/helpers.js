export const methodsLabel = {
    cash: 'Tunai',
    credit_card: 'Kartu Kredit',
    debit_card: 'Kartu Debit',
    bank_transfer: 'Transfer Bank',
    e_wallet: 'E-Wallet',
    qr: 'QRIS',
};

export const reservationStatus = {
    pending: { label: 'Pending', type: 'info' },
    confirmed: { label: 'Dikonfirmasi', type: 'warning' },
    checked_in: { label: 'Check-in', type: 'success' },
    checked_out: { label: 'Check-out', type: 'primary' },
    cancelled: { label: 'Dibatalkan', type: 'danger' },
    no_show: { label: 'No-Show', type: 'danger' },
};

export const paymentStatusMeta = {
    unpaid: { label: 'Belum Bayar', type: 'danger' },
    partial: { label: 'Sebagian', type: 'warning' },
    paid: { label: 'Lunas', type: 'success' },
};

export const roomStatusMeta = {
    available: { label: 'Tersedia', type: 'success' },
    occupied: { label: 'Terisi', type: 'danger' },
    housekeeping: { label: 'Housekeeping', type: 'warning' },
    maintenance: { label: 'Perawatan', type: 'info' },
};

export const sourceLabel = {
    walk_in: 'Walk-in',
    phone: 'Telepon',
    online: 'Online',
    agent: 'Agen',
};

export const chargeCategoryLabel = {
    food_beverage: 'Makanan & Minuman',
    laundry: 'Laundry',
    spa: 'Spa',
    transport: 'Transportasi',
    minibar: 'Minibar',
    other: 'Lainnya',
};

export const housekeepingStatus = {
    pending: { label: 'Menunggu', type: 'info' },
    in_progress: { label: 'Dikerjakan', type: 'warning' },
    completed: { label: 'Selesai', type: 'success' },
    cancelled: { label: 'Batal', type: 'danger' },
};

export const housekeepingTaskLabel = {
    cleaning: 'Pembersihan',
    deep_clean: 'Pembersihan Menyeluruh',
    amenities: 'Penyediaan Amenities',
    repair: 'Perbaikan',
};

export const roleMeta = {
    super_admin: { label: 'Super Admin', type: 'danger' },
    admin: { label: 'Admin', type: 'danger' },
    general_manager: { label: 'General Manager', type: 'warning' },
    manager: { label: 'Manager', type: 'warning' },
    front_office: { label: 'Front Office', type: 'success' },
    receptionist: { label: 'Resepsionis', type: 'success' },
    reservation_staff: { label: 'Reservation Staff', type: 'primary' },
    housekeeping: { label: 'Housekeeping', type: 'info' },
    finance: { label: 'Finance / Accounting', type: 'warning' },
    fb_staff: { label: 'F&B Staff', type: 'primary' },
    fb_manager: { label: 'F&B Manager', type: 'warning' },
    purchasing: { label: 'Purchasing', type: 'primary' },
    inventory: { label: 'Inventory / Storekeeper', type: 'info' },
    engineering: { label: 'Engineering', type: 'info' },
    hrd: { label: 'HR / HRD', type: 'success' },
    supervisor: { label: 'Manager / Supervisor', type: 'warning' },
    guest: { label: 'Guest / Customer', type: 'info' },
};

export const roleLabel = Object.fromEntries(Object.entries(roleMeta).map(([k, v]) => [k, v.label]));

export const orderStatusMeta = {
    pending: { label: 'Diproses', type: 'info' },
    preparing: { label: 'Disiapkan', type: 'warning' },
    served: { label: 'Disajikan', type: 'success' },
    paid: { label: 'Lunas', type: 'success' },
    cancelled: { label: 'Dibatalkan', type: 'danger' },
};

export const orderTypeLabel = {
    dine_in: 'Dine-in',
    takeaway: 'Takeaway',
    delivery: 'Delivery',
};

export const poStatusMeta = {
    draft: { label: 'Draft', type: 'info' },
    submitted: { label: 'Diajukan', type: 'primary' },
    approved: { label: 'Disetujui', type: 'success' },
    received: { label: 'Diterima', type: 'success' },
    cancelled: { label: 'Dibatalkan', type: 'danger' },
};

export const maintenanceStatusMeta = {
    pending: { label: 'Menunggu', type: 'info' },
    in_progress: { label: 'Dikerjakan', type: 'warning' },
    on_hold: { label: 'Ditunda', type: 'danger' },
    completed: { label: 'Selesai', type: 'success' },
    cancelled: { label: 'Dibatalkan', type: 'danger' },
};

export const maintenanceCategoryLabel = {
    plumbing: 'Plumbing',
    electrical: 'Listrik',
    hvac: 'HVAC / AC',
    mechanical: 'Mekanikal',
    furniture: 'Furniture',
    other: 'Lainnya',
};

export const employeeStatusLabel = {
    active: 'Aktif',
    on_leave: 'Cuti',
    resigned: 'Keluar',
};

export const eventTypeLabel = {
    wedding: 'Pernikahan / Wedding',
    meeting: 'Rapat / Meeting',
    seminar: 'Seminar',
    birthday: 'Ulang Tahun',
    corporate: 'Corporate Event',
    ayce: 'All You Can Eat',
    private_party: 'Pesta Privat',
    other: 'Lainnya',
};

export const eventStatusMeta = {
    pending: { label: 'Pending', type: 'info' },
    confirmed: { label: 'Dikonfirmasi', type: 'warning' },
    in_progress: { label: 'Berlangsung', type: 'success' },
    completed: { label: 'Selesai', type: 'primary' },
    cancelled: { label: 'Dibatalkan', type: 'danger' },
};

export function formatCurrency(value, currency = 'Rp') {
    const num = Number(value ?? 0);
    if (Number.isNaN(num)) return `${currency} 0`;
    return `${currency} ${num.toLocaleString('id-ID')}`;
}

export function formatDate(value, format = 'YYYY-MM-DD') {
    if (!value) return '-';
    return value;
}

export function formatDateTime(value) {
    if (!value) return '-';
    return value.replace('T', ' ').slice(0, 16);
}

export function today() {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}

export function addDays(dateStr, days) {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
}