# 🏨 SIMH — Sistem Informasi Manajemen Hotel

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="280" alt="Laravel Logo">
</p>

<p align="center">
  <strong>Full-Stack Hotel Management System</strong>
</p>

<p align="center">
  Platform manajemen hotel terpadu untuk mengelola reservasi, kamar, tamu,
  F&B, inventory, purchasing, event, housekeeping, maintenance, dan reporting.
</p>

<p align="center">

<a href="https://laravel.com/">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
</a>
<a href="https://vuejs.org/">
  <img src="https://img.shields.io/badge/Vue.js-3.x-42B883?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue 3">
</a>
<a href="https://tailwindcss.com/">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4.x-38BDF8?style=for-the-badge&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
</a>
<a href="https://element-plus.org/">
  <img src="https://img.shields.io/badge/Element_Plus-2.x-409EFF?style=for-the-badge&logo=element&logoColor=white" alt="Element Plus">
</a>
<a href="https://www.php.net/">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2">
</a>

</p>

<p align="center">

<a href="https://vitejs.dev/">
  <img src="https://img.shields.io/badge/Vite-7.x-646CFF?style=for-the-badge&logo=vite&logoColor=white" alt="Vite 7">
</a>
<a href="https://opensource.org/licenses/MIT">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="MIT License">
</a>

</p>

---

## 📖 About The Project

**SIMH (Sistem Informasi Manajemen Hotel)** adalah aplikasi **full-stack hotel management** yang dibangun dengan arsitektur **Laravel + Vue 3 SPA**.

Aplikasi ini mengintegrasikan berbagai operasional hotel ke dalam satu platform, mulai dari **reservasi dan pengelolaan kamar hingga F&B, inventory, purchasing, event, HR, maintenance, dan laporan**.

### 🎯 Main Goals

* 🏨 Memusatkan seluruh operasional hotel dalam satu sistem
* 📋 Mengurangi proses administrasi manual
* ⚡ Mempercepat proses reservasi dan check-in/out
* 📊 Menyediakan informasi operasional secara terpusat
* 🔐 Mengontrol akses berdasarkan permission
* 🧾 Menyediakan audit trail aktivitas pengguna
* 📈 Mendukung reporting dan monitoring bisnis hotel

---

## ✨ Features

### 🏨 Front Office

| Module              | Description                                                              |
| ------------------- | ------------------------------------------------------------------------ |
| 📊 **Dashboard**    | Ringkasan okupansi, revenue, reservasi, dan aktivitas terkini            |
| 📅 **Reservations** | Booking, check-in, check-out, cancellation, no-show, dan room assignment |
| 🛏️ **Rooms**       | Manajemen tipe kamar, status kamar, dan okupansi                         |
| 👤 **Guests**       | Data tamu, pencarian, dan riwayat reservasi                              |
| 💳 **Payments**     | Pengelolaan transaksi pembayaran dan service charge                      |

### 🧹 Hotel Operations

| Module              | Description                                            |
| ------------------- | ------------------------------------------------------ |
| 🧹 **Housekeeping** | Cleaning task dan monitoring status kamar              |
| 🔧 **Maintenance**  | Request perbaikan dan tracking status                  |
| 📦 **Inventory**    | Stok barang, transaksi inventory, dan stock adjustment |
| 🛒 **Purchasing**   | Supplier, purchase order, dan approval workflow        |
| 👨‍💼 **HR**        | Pengelolaan data karyawan                              |

### 🍽️ Food & Beverage

| Module                       | Description                                 |
| ---------------------------- | ------------------------------------------- |
| 🍴 **F&B**                   | Manajemen menu makanan dan minuman          |
| 🧾 **Orders**                | Pengelolaan pesanan F&B                     |
| 📦 **Inventory Integration** | Integrasi kebutuhan stok dengan operasional |

### 🎉 Event & Venue

| Module               | Description                     |
| -------------------- | ------------------------------- |
| 🏛️ **Venue**        | Pengelolaan venue dan booking   |
| 🍽️ **AYCE Package** | Paket All You Can Eat           |
| 💳 **Event Payment** | Pengelolaan pembayaran event    |
| 📅 **Event Booking** | Reservasi dan pengelolaan event |

### 📈 Reporting & Administration

| Module                   | Description                          |
| ------------------------ | ------------------------------------ |
| 📊 **Revenue Report**    | Monitoring pendapatan                |
| 🛏️ **Occupancy Report** | Monitoring tingkat okupansi          |
| 📥 **CSV Export**        | Export data laporan                  |
| 📝 **Activity Log**      | Audit trail aktivitas pengguna       |
| 🔐 **Permissions**       | Kontrol akses berdasarkan permission |

---

## 🧩 System Architecture

SIMH menggunakan **Laravel sebagai application/backend layer** dan **Vue 3 sebagai frontend SPA**.

```text
┌─────────────────────────────────────────────┐
│                    USER                     │
│              Browser / Web App              │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│                  Vue 3 SPA                  │
│                                             │
│  Vue Router • Pinia • Element Plus          │
│  Tailwind CSS                               │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│                Laravel 12                   │
│                                             │
│ Controllers • Middleware • Services         │
│ Eloquent Models • Authentication            │
└──────────────────────┬──────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────┐
│                   SQLite                    │
│                                             │
│ Reservations • Guests • Rooms • Inventory   │
│ F&B • Purchasing • Events • HR • Logs       │
└─────────────────────────────────────────────┘
```

---

## 🔄 Core Business Flow

### 🛎️ Reservation Flow

```text
Guest
  │
  ▼
Reservation
  │
  ▼
Room Assignment
  │
  ▼
Check-In
  │
  ▼
Stay
  │
  ▼
Payment
  │
  ▼
Check-Out
  │
  ▼
Completed
```

### 🧹 Housekeeping Flow

```text
Room
  │
  ▼
Check-Out
  │
  ▼
Cleaning Required
  │
  ▼
Housekeeping Task
  │
  ▼
Cleaning Completed
  │
  ▼
Room Available
```

### 🛒 Purchasing Flow

```text
Purchase Request
       │
       ▼
     Review
       │
       ▼
    Approval
       │
       ▼
Purchase Order
       │
       ▼
    Supplier
       │
       ▼
Goods Received
       │
       ▼
Inventory Updated
```

---

## 🛠️ Tech Stack

### Backend

* **Laravel 12**
* **PHP 8.2+**
* **SQLite**
* Eloquent ORM
* Laravel Session Authentication
* Middleware & Permission System

### Frontend

* **Vue 3**
* **Pinia**
* **Vue Router**
* **Element Plus**
* **Tailwind CSS 4**

### Development

* **Vite 7**
* Composer
* NPM
* Laravel Artisan

---

## 📁 Project Structure

```text
simh/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   └── Services/
│
├── database/
│   ├── migrations/
│   └── seeders/
│
├── resources/
│   ├── js/
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── router/
│   │   └── stores/
│   └── views/
│
├── routes/
│   └── web.php
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
├── .env.example
├── composer.json
├── package.json
├── vite.config.js
└── README.md
```

---

## 🚀 Installation

### Prerequisites

| Requirement | Version               |
| ----------- | --------------------- |
| PHP         | `>= 8.2`              |
| Composer    | Latest                |
| Node.js     | `>= 18`               |
| NPM         | Included with Node.js |
| Git         | Latest                |

### 1. Clone Repository

```bash
git clone https://github.com/rhesapnjtn/simh.git
cd simh
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure Environment

Linux / macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 4. Setup Database

```bash
php artisan migrate --seed
```

### 5. Build Frontend

```bash
npm run build
```

### 6. Run Application

```bash
php artisan serve
```

Application:

```text
http://localhost:8000
```

---

## ⚡ Development Mode

Untuk menjalankan seluruh environment development:

```bash
composer dev
```

Atau secara manual:

**Terminal 1**

```bash
php artisan serve
```

**Terminal 2**

```bash
npm run dev
```

---

## 🔐 Authentication & Authorization

SIMH menggunakan **session-based authentication** dengan middleware permission untuk mengontrol akses pengguna.

```text
User
 │
 ▼
Authentication
 │
 ▼
Session
 │
 ▼
Permission Middleware
 │
 ├── Dashboard
 ├── Reservations
 ├── Rooms
 ├── Inventory
 ├── Purchasing
 ├── Events
 ├── HR
 └── Reports
```

---

## 👤 Default Login

Untuk development:

| Role     | Email              | Password   |
| -------- | ------------------ | ---------- |
| 👑 Admin | `admin@simh.local` | `password` |

> ⚠️ **Jangan gunakan credential default pada production.**

---

## 🧪 Testing

Menjalankan seluruh test suite:

```bash
composer test
```

Atau:

```bash
php artisan test
```

Menjalankan test tertentu:

```bash
php artisan test --filter=ReservationTest
```

---

## 🔒 Security

Sebelum deployment ke production:

* [ ] Set `APP_ENV=production`
* [ ] Set `APP_DEBUG=false`
* [ ] Gunakan HTTPS
* [ ] Gunakan password database yang kuat
* [ ] Jangan commit `.env`
* [ ] Ganti default admin credentials
* [ ] Backup database secara berkala
* [ ] Batasi akses berdasarkan permission
* [ ] Review activity logs secara berkala
* [ ] Gunakan secure session configuration

---

## 🗺️ Roadmap

* [ ] Advanced role & permission management
* [ ] Real-time occupancy dashboard
* [ ] QR-based check-in
* [ ] Digital invoice
* [ ] Payment gateway integration
* [ ] WhatsApp notification
* [ ] Email notification system
* [ ] Advanced analytics
* [ ] Automated hotel reports
* [ ] Docker deployment
* [ ] CI/CD pipeline
* [ ] Production monitoring

---

## 📄 License

This project is licensed under the **MIT License**.

Free to use, modify, and distribute for personal or commercial purposes.

---

<div align="center">

### 🏨 SIMH

**Sistem Informasi Manajemen Hotel**

Built with ❤️ using

**Laravel 12 · Vue 3 · Element Plus · Tailwind CSS**

⭐ If you find this project useful, consider giving it a star!

</div>
