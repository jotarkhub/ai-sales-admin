# AI Sales Admin (nama sementara)

Agen administrasi penjualan berbasis Laravel: menerima calon customer dari Google Form,
menghubungi via WhatsApp Business Cloud API (resmi), menjawab & menggali kebutuhan pakai
OpenAI, menilai kualitas prospek, follow-up otomatis, dan bisa diambil alih admin manusia
kapan pun diperlukan.

Status implementasi terkini ada di [`docs/STATUS.md`](docs/STATUS.md) — **jangan anggap suatu
modul selesai hanya dari nama filenya**, cek status di sana.

Arsitektur, ERD, sequence diagram, state machine, daftar environment variable, risiko, dan
acceptance criteria ada di [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Stack

- Laravel 12, PHP ^8.2
- MySQL/MariaDB (wajib — lihat `.env.example`; SQLite hanya dipakai untuk test lokal cepat via
  `.env.testing`)
- Laravel Queue (fallback `database`, `redis` bila tersedia) + Scheduler
- Blade/Livewire untuk dashboard internal
- WhatsApp Business Platform Cloud API (bukan otomasi WhatsApp Web)
- OpenAI Responses API sebagai mesin percakapan, dengan provider abstraction agar bisa diganti
- Google Apps Script sebagai penghubung Google Form -> backend (tanpa API key WhatsApp/AI di
  dalamnya)
- Pest/PHPUnit untuk automated test
- GitHub Actions sebagai bukti eksekusi utama (`.github/workflows/ci.yml`)

## Setup Lokal (Windows + XAMPP)

```powershell
git clone https://github.com/jotarkhub/ai-sales-admin.git
cd ai-sales-admin
composer install
copy .env.example .env
php artisan key:generate
```

Buat database MySQL kosong bernama `ai_sales_admin` lewat phpMyAdmin/HeidiSQL (default XAMPP:
user `root`, password kosong — sudah sesuai default di `.env.example`), lalu:

```powershell
php artisan migrate
php artisan test
```

`php artisan test` otomatis memakai SQLite in-memory (lihat `.env.testing`), jadi tidak perlu
database test terpisah untuk menjalankan test secara lokal.

## CI

Setiap push/PR ke `main`/`develop` menjalankan `composer validate`, `composer install`,
`migrate:fresh` di MySQL sungguhan, `php artisan test`, dan `vendor/bin/pint --test`. Ini adalah
bukti utama bahwa kode benar-benar bisa dieksekusi — cek tab **Actions** di GitHub sebelum
menganggap sebuah perubahan "selesai".

## Prinsip Pengembangan

- Tidak ada kode dummy yang berpura-pura bekerja. Fungsi yang butuh kredensial (WhatsApp,
  OpenAI, Google) ditandai jelas `CREDENTIAL_REQUIRED` sampai kredensial asli tersedia.
- Semua secret ada di environment variable, tidak pernah di source code.
- Setiap perubahan status prospek dan konfigurasi tercatat di `audit_logs`.
- Admin manusia selalu punya kendali akhir — AI tidak bisa mengubah lead menjadi `won`.
- UI berbahasa Indonesia; kode, nama class/tabel/API berbahasa Inggris.
- Zona waktu `Asia/Jakarta`, nomor telepon dinormalisasi ke format internasional (E.164).
