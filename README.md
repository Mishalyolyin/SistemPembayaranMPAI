# Sistem Pembayaran SKS — MPAI UNISSULA (Laravel)

Sistem pembayaran SKS untuk **Mahasiswa RPL** dan **Mahasiswa Reguler** dengan integrasi **BRI Virtual Account (VA)**. Proyek ini menegakkan **idempotensi**, **closed-amount**, **VA konstan** (cust code dari NIM), **webhook terautentikasi**, dan **Eligibility API** untuk kebutuhan pull dari gateway/BRI.

⚠️ **Catatan:** Dokumen ini adalah **dokumentasi teknis**. Tidak mengubah kode, namun mendeskripsikan kontrak dan praktik operasional yang dianjurkan. Angka/identitas VA pada contoh di bawah adalah **placeholder**, **bukan** nomor asli.

---

## Tabel Konten
- [Arsitektur Ringkas](#arsitektur-ringkas)
- [Teknologi & Modul](#teknologi--modul)
- [Struktur Proyek (ringkas)](#struktur-proyek-ringkas)
- [Skema Data Utama](#skema-data-utama)
- [Kontrak VA & Alur Bisnis](#kontrak-va--alur-bisnis)
- [Endpoint & Kontrak API](#endpoint--kontrak-api)
  - [Webhook BRI (Push)](#webhook-bri-push)
  - [Eligibility API (Pull)](#eligibility-api-pull)
- [Konfigurasi](#konfigurasi)
- [Setup Lokal & Deploy](#setup-lokal--deploy)
- [Queue & Jobs](#queue--jobs)
- [Observability & Audit](#observability--audit)
- [Smoke Tests](#smoke-tests)
- [Runbook & Troubleshooting](#runbook--troubleshooting)
- [Roadmap Ringkas](#roadmap-ringkas)
- [Lisensi](#lisensi)

---

## Arsitektur Ringkas

```mermaid
flowchart LR
  subgraph BRI/Gateway
    A1[Webhook: payment]
    A2[Webhook: va-assigned]
    P1[Pull: Eligibility]
  end

  subgraph App (Laravel)
    M1[Middleware VerifyBrivaWebhook<br/>HMAC + TS Skew + Bearer]
    C1[Controller BriWebhookController]
    J1[Job ProcessBriPayment]
    E1[EligibilityController]
    S1[BrivaService / VaHelper]
    L1[(DB: invoices, invoices_reguler,<br/>webhook_logs, unmatched_payments)]
  end

  A1 --> M1 --> C1 --> J1 --> L1
  A2 --> M1 --> C1 --> L1
  P1 -->|Bearer| E1 --> S1 --> L1
```

**Ringkasan:**
- **Cust code** (identitas VA) = **NIM last-N digit** (leading zero dipertahankan).
- **VA penuh** & **BRIVA no** = **ditetapkan oleh BRI** via webhook `va-assigned` (tidak dibuat lokal di production).
- Webhook payment **idempoten** (kunci `journalSeq`) + **closed-amount** (jumlah harus pas).
- **Eligibility API** memberikan tepat 1 angsuran **paling awal** yang belum lunas (no-skip) untuk RPL/Reguler.

---

## Teknologi & Modul
- **Laravel** 10.x, **PHP** ≥ 8.2 (disarankan 8.4)
- Storage S3-kompatibel (mis. DigitalOcean Spaces)
- Mailer (mis. Mailgun)
- Database MySQL (mendukung **TLS** untuk managed DB)
- **Queue**: Database driver (Supervisor di production)

**Komponen Kunci:**
- Middleware: `VerifyBrivaWebhook` (auth & verifikasi) dan `BriSpectate` (Eligibility auth + optional IP whitelist + throttle).
- Controllers: `Webhooks\BriWebhookController`, `Spectate\EligibilityController`, controller Mahasiswa/Reguler.
- Job: `ProcessBriPayment`.
- Service/Helper: `BrivaService`, `VaHelper`.

---

## Struktur Proyek (ringkas)
```
app/
  Http/
    Controllers/
      Webhooks/BriWebhookController.php
      Spectate/EligibilityController.php
    Middleware/
      VerifyBrivaWebhook.php
      BriSpectate.php
  Jobs/
    ProcessBriPayment.php
  Services/
    BrivaService.php
  Support/
    VaHelper.php
config/
  bri.php
database/
  migrations/
    *_create_invoices_table.php
    *_create_invoices_reguler_table.php
    *_create_webhook_logs_table.php
    *_create_unmatched_payments_table.php
    *_create_jobs_table.php
routes/
  api.php
```

---

## Skema Data Utama
**Tabel `invoices` & `invoices_reguler`** (kolom penting):
- Identitas mahasiswa: `nim`
- VA: `va_cust_code`, `va_briva_no`, `va_full`, `va_expired_at`
- Idempoten: `va_journal_seq` (**UNIQUE**)
- Pembayaran: `paid_amount`, `paid_at`, `reconcile_source`
- Indeks: `va_full` (index), gabungan `nim,status,angsuran_ke`

**Tabel `webhook_logs`**: log request/response webhook (tanpa secret).  
**Tabel `unmatched_payments`**: catat mismatch/unknown (journal_seq unique + payload).  
**Tabel `jobs`/`failed_jobs`/`sessions`**: standar Laravel.

---

## Kontrak VA & Alur Bisnis
- **Cust Code**: diturunkan dari **NIM** → ambil **N digit terakhir** (`BRI_CUSTCODE_LAST_N`, default 10), non-digit dibersihkan, **leading zero dipertahankan**.
- **VA Penuh**: hasil **penetapan oleh BRI** (webhook `va-assigned`) berupa `va_briva_no` dan `va_full`.  
  > Production: `BRI_FORBID_LOCAL_VA=true` mencegah generate VA lokal.
- **Eligibility**: pilih **1 angsuran** berstatus `Belum` / `Menunggu Verifikasi` dari **RPL** & **Reguler**, ambil **angsuran_ke terkecil** (no-skip).

---

## Endpoint & Kontrak API

### Webhook BRI (Push)
Prefix: `/api/webhooks/bri`  
Keamanan: `VerifyBrivaWebhook` → **HMAC (raw body)** + **Timestamp Skew** + **Bearer** (kombinasi `require_both` di config).

- `POST /payment`  
  Notifikasi pembayaran VA. **Idempoten** by `journalSeq`. **Closed-amount**: jumlah harus sama dengan invoice target.  
  - Duplikat → balas **200** tanpa efek.  
  - Mismatch/unknown → masukkan ke `unmatched_payments`.
- `POST /va-assigned`  
  Penetapan VA final (`va_briva_no`, `va_full`, `va_expired_at`, opsional `va_cust_code`).  
- `POST /` (legacy) → diarahkan ke `/payment`  
- `POST /ping` → health/auth check

**Header yang harus dikirim oleh Gateway/BRI:**
- `Authorization: Bearer <BRI_WEBHOOK_TOKEN>`
- `<X-Timestamp>` sesuai `BRI_WEBHOOK_TS_HEADER`
- `<X-Signature>` sesuai `BRI_WEBHOOK_SIG_HEADER`  
  Signature = `HMAC(ALGO, RAW_BODY, SECRET)` dengan encoding `base64|hex` sesuai config.

### Eligibility API (Pull)
Prefix: `/api/spectate`  
Keamanan: `BriSpectate` → **Bearer** (`BRI_ELIGIBILITY_TOKEN`) + optional **IP whitelist** + **throttle:spectate** (RPM dari config/env).

- `GET /eligibility?nim=...`  
  Cari **1** angsuran aktif (RPL/Reguler) berdasarkan **NIM**. Non-digit dalam `nim` akan **dibersihkan**.
- `GET /eligibility/by-cust?cust=...`  
  Cari **1** angsuran aktif berdasarkan **cust code** (diturunkan dari NIM last-N, digit-only).

**Response (200 – eligible, contoh placeholder):**
```json
{
  "eligible": true,
  "va": {
    "cust_code": "CUST_CODE_EXAMPLE",
    "briva_no": "BRIVA_NO_EXAMPLE",
    "full": "VA_FULL_EXAMPLE"
  }
}
```
**Response (200 – not eligible):**
```json
{ "eligible": false, "reason": "not_found_or_already_paid" }
```
**Error umum:** `401 Unauthorized`, `403 Forbidden`, `422 Unprocessable Entity`, `429 Too Many Requests`.

> **UI Guard:** jangan tampilkan VA sebelum event `va-assigned` diterima (hindari VA kosong/stale).

---

## Konfigurasi
Semua diatur via `.env` dan dipetakan oleh `config/bri.php`. Ubah nilai → `php artisan config:clear && php artisan config:cache`.

**Inti `.env`:**
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta

QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_DRIVER=database

# Webhook security
BRI_WEBHOOK_SIG_HEADER=X-Signature
BRI_WEBHOOK_TS_HEADER=X-Timestamp
BRI_WEBHOOK_TS_SKEW=300
BRI_HMAC_SECRET=...
BRI_HMAC_ALGO=sha256
BRI_HMAC_ENCODING=base64
BRI_WEBHOOK_TOKEN=...
BRI_WEBHOOK_REQUIRE_BOTH=true

# VA policy
BRI_FORBID_LOCAL_VA=true
BRI_CUSTCODE_LAST_N=10

# Eligibility API
BRI_ELIGIBILITY_TOKEN=...
BRI_ELIGIBILITY_ALLOWED_IPS=        # opsional (csv)
SPECTATE_RPM=120                    # throttle:spectate
```

Storage (S3/Spaces), Mailer (Mailgun), dan koneksi DB (TLS) mengikuti standar Laravel.

---

## Setup Lokal & Deploy
```bash
composer install
cp .env.example .env   # atau gunakan .env yang sudah tersedia
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:clear && php artisan config:cache
```

**Serve (dev cepat):**
```bash
php artisan serve
```

**Queue Worker (production):** gunakan **Supervisor**:
```
[program:sks-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sks-queue.log
```

---

## Queue & Jobs
- `ProcessBriPayment` dipanggil oleh controller webhook (**dispatch**) untuk memproses pembayaran secara asinkron.  
- Pastikan `jobs` table sudah migrate dan worker aktif.

---

## Observability & Audit
- **webhook_logs**: simpan semua event (tanpa rahasia).  
- **unmatched_payments**: simpan anomali (mismatch amount/unknown VA) lengkap dengan `reason` & `payload`.  
- (Disarankan) **X-Request-Id middleware** untuk pelacakan end-to-end.  
- **Masking**: saat output/log, mask sebagian `briva_no` & `va_full`.

---

## Smoke Tests
**Webhook Happy Path (placeholder):**
```bash
URL="https://domainmu.com/api/webhooks/bri/payment"
BODY='{
  "journalSeq":"SMK-1",
  "amount":1500000,
  "custCode":"CUST_CODE_EXAMPLE",
  "bankCode":"BANK_CODE_EXAMPLE",
  "brivaNo":"BRIVA_NO_EXAMPLE",
  "paidAt":"2025-10-22T12:00:00+07:00"
}'
TS=$(date +%s)
SECRET="HMAC_SECRET_MU"
SIG=$(php -r 'echo base64_encode(hash_hmac("sha256", $argv[1], $argv[2], true));' "$BODY" "$SECRET")
BEARER="WEBHOOK_BEARER_MU"

curl -i -X POST "$URL" \
  -H "Content-Type: application/json" \
  -H "X-Timestamp: $TS" \
  -H "X-Signature: $SIG" \
  -H "Authorization: Bearer $BEARER" \
  --data "$BODY"
```

**Duplicate (idempoten) → 200 no-op:** kirim ulang request yang sama.  
**Wrong HMAC → 401**, **Out-of-skew → 401**.

**Eligibility (by NIM):**
```bash
TOKEN="ELIGIBILITY_TOKEN"
NIM="2023123456"
curl -s -G "https://domainmu.com/api/spectate/eligibility" \
  -H "Authorization: Bearer $TOKEN" \
  --data-urlencode "nim=$NIM"
```

---

## Runbook & Troubleshooting
- **401 Webhook:** pastikan Bearer benar, HMAC secret & encoding sesuai, timestamp dalam toleransi (`BRI_WEBHOOK_TS_SKEW`).
- **Pembayaran tidak terproses:** cek worker (`queue:work`), `jobs/failed_jobs`, dan `webhook_logs`.
- **Eligibility 429:** atur `SPECTATE_RPM` atau konfigurasi rate-limiter.
- **VA tidak muncul di UI:** pastikan `va-assigned` sudah diterima; jangan render VA sebelum assigned (UI guard).

---

## Roadmap Ringkas
- Reconcile fallback: `php artisan briva:reconcile --date=YYYY-MM-DD [--fix]`
- Observability lanjutan: metrics, request-id global, masking konsisten
- Hardening: rotasi secret, security headers, cookie/session strict
- DB “Spectate” read-only: VIEW + user RO + IP whitelist + TLS
- Handover & UAT pack: skenario lengkap, checklist penerimaan

---

## Lisensi
© UNISSULA — Penggunaan internal. Hubungi maintainer untuk distribusi/ekstensi.
