# Sistem Pembayaran SKS — MPAI UNISSULA (Laravel)

Sistem pembayaran SKS untuk **Mahasiswa RPL** dan **Mahasiswa Reguler** dengan:
- Virtual Account (VA) **BRI** (VA konstan per mahasiswa, webhook payment & VA assigned)
- Manajemen invoice & verifikasi (otomatis/manual)
- Kuittansi **PDF** per bulan (modal input data)
- Admin dashboard (import CSV, grouping by tanggal impor, filter semester & tahun akademik)
- Queue job untuk pemrosesan transaksi & audit log

> Made with Laravel + PHP 8.4.x. Kode inti dan flow **tidak diubah** oleh dokumen ini. Ini hanya README pengganti yang proper.

---

## Daftar Isi
- [Fitur Utama](#fitur-utama)
- [Arsitektur Singkat](#arsitektur-singkat)
- [Prasyarat](#prasyarat)
- [Instalasi & Setup](#instalasi--setup)
- [Konfigurasi ENV Penting](#konfigurasi-env-penting)
- [Migrasi Database](#migrasi-database)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Integrasi BRI (Webhook)](#integrasi-bri-webhook)
- [Smoke Test (Manual & Otomatis)](#smoke-test-manual--otomatis)
- [VA Konstan & Eligibility API (Roadmap)](#va-konstan--eligibility-api-roadmap)
- [Keamanan & Hardening](#keamanan--hardening)
- [Lisensi](#lisensi)

---

## Fitur Utama
- **Mahasiswa RPL & Reguler**: skema angsuran terpisah, filter semester (Ganjil/Genap) & Tahun Akademik.
- **Invoice & Verifikasi**: unggah bukti pembayaran, status menunggu/verifikasi/lunas, reset & edit jumlah.
- **Kuitansi PDF**: generate kuitansi per baris invoice yang **Lunas**, dengan modal pengisian data (angkatan, no HP).
- **BRI Webhook**:
  - `/api/webhooks/bri/payment` (pembayaran VA)
  - `/api/webhooks/bri/va-assigned` (penetapan VA)
  - Idempotent by `journalSeq`, **closed amount** (harus pas), mismatch → dicatat.
- **Audit & Observabilitas**:
  - `webhook_logs` (log webhook)
  - `unmatched_payments` (mismatch/unmatched)
- **Queue**: `ProcessBriPayment` dijalankan via **database queue**.

---

## Arsitektur Singkat
- **Guards**: `admin`, `mahasiswa`, `mahasiswa_reguler`
- **Controllers**:
  - `Webhooks\BriWebhookController` (payment & va-assigned)
  - `Mahasiswa\InvoiceController`, `MahasiswaReguler\InvoiceRegulerController`
- **Jobs**: `ProcessBriPayment` (validasi, pencocokan invoice, audit)
- **Services**: `BrivaService` / `BriApi` (untuk integrasi API BRIVA ketika dibutuhkan)
- **Routes**:
  - `routes/api.php` → semua endpoint webhook di-*prefix* `/api`
- **Migrations kunci**:
  - `invoices`, `invoices_reguler` (+ `nim`, kolom VA: `va_cust_code`, `va_briva_no`, `va_full`, `va_expired_at`, `va_journal_seq`, `paid_amount`, `paid_at`, `reconcile_source`)
  - `webhook_logs`, `unmatched_payments`
  - `jobs`, `failed_jobs`, `sessions`

---

## Prasyarat
- **PHP** 8.2+ (disarankan 8.4.x sesuai project)
- **Composer**
- **MySQL/MariaDB** (support utf8mb4)
- **Node.js** (opsional, untuk asset pipeline bila dibutuhkan)
- OpenSSL tersedia (untuk HMAC test di mesin dev)

---

## Instalasi & Setup
```bash
git clone <repo-url>
cd pembayaran-sks

cp .env.example .env   # atau gunakan .env yang sudah kamu sediakan
composer install
php artisan key:generate
