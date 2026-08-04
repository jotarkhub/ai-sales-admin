# Status Implementasi — AI Sales Admin

Dokumen ini adalah sumber kebenaran tunggal soal "apa yang benar-benar sudah jalan" vs "apa
yang baru ditulis tapi belum terbukti". Update setiap kali sebuah modul berpindah status.
**Jangan menandai modul apa pun selesai tanpa bukti run (lokal atau CI) yang tercantum di sini.**

## Legenda Status

| Status | Arti |
|---|---|
| `DESIGNED` | Arsitektur/spesifikasi sudah dibuat, belum diimplementasikan. |
| `IMPLEMENTED_UNVERIFIED` | Kode sudah ditulis, belum pernah dijalankan. |
| `LOCAL_TEST_PASSED` | Berhasil diuji di komputer lokal (`php artisan test` di mesin user). |
| `CI_TEST_PASSED` | Berhasil diuji lewat GitHub Actions (bukti: link run + commit SHA). |
| `CREDENTIAL_REQUIRED` | Kode integrasi tersedia, tapi belum bisa diuji nyata karena kredensial belum ada. |
| `SIMULATED_TEST_ONLY` | Hanya diuji dengan fake/mock provider, bukan integrasi nyata. |
| `PRODUCTION_READY` | Integrasi nyata + acceptance test + keamanan + staging sudah terverifikasi. |
| `FAILED` | Migration/test/build gagal dan belum diperbaiki. |

Aturan tegas: keberhasilan test dengan fake provider **tidak pernah** disebut sebagai
keberhasilan integrasi nyata. Status integrasi WhatsApp/OpenAI/Google tetap
`CREDENTIAL_REQUIRED` sampai kredensial asli + end-to-end test nyata tersedia — walau kode dan
test dengan fake provider sudah `CI_TEST_PASSED`.

## Status Modul Saat Ini

| Modul | Status | Bukti |
|---|---|---|
| Fase 0 — Audit environment | `DESIGNED` (selesai sebagai temuan, bukan kode) | Lihat riwayat percakapan |
| Fase 1 — Arsitektur & spesifikasi | `DESIGNED` | `docs/ARCHITECTURE.md` |
| Bootstrap Laravel 12 (PHP 8.2, lokal) | `LOCAL_TEST_PASSED` | `composer create-project` sukses, `git push` ke `github.com/jotarkhub/ai-sales-admin` sukses |
| GitHub Actions CI (baseline: default Laravel test suite) | `IMPLEMENTED_UNVERIFIED` | `.github/workflows/ci.yml` dibuat, **belum ada run yang dikonfirmasi hijau** — menunggu push berikutnya |
| Migration & model tabel inti (24 tabel) | `LOCAL_TEST_PASSED` | `php artisan test` di komputer user: 3 passed (30 assertions), termasuk `CoreSchemaSmokeTest` yang menembus semua 24 tabel. Menunggu konfirmasi CI (MySQL) untuk naik ke `CI_TEST_PASSED` |
| Auth & authorization (role-based) | belum dimulai | — |
| Audit log service | belum dimulai | — |
| Business Configuration module | belum dimulai | — |
| Lead Intake endpoint | belum dimulai | — |
| WhatsApp integration | belum dimulai | `CREDENTIAL_REQUIRED` (token/phone number ID belum ada) |
| OpenAI / Conversation Engine | belum dimulai | `CREDENTIAL_REQUIRED` (API key belum ada) |
| Google Apps Script | belum dimulai | `CREDENTIAL_REQUIRED` (belum ada Google Form/Sheet target) |
| Dashboard admin | belum dimulai | — |

## Provider Fake — Aturan Keras

- `FakeAiProvider` dan `FakeWhatsAppProvider` **hanya** boleh dipakai saat `APP_ENV=testing`.
- Aplikasi **wajib menolak boot** (lempar exception saat service provider register) apabila
  `APP_ENV=production` tetapi `AI_PROVIDER=fake` atau `WHATSAPP_PROVIDER=fake`. Ini akan
  diimplementasikan sebagai bagian dari provider abstraction di Fase 4/3, dan diuji lewat test
  otomatis (bukan cuma dokumentasi).

## Cara Memperbarui Status Modul Menjadi CI_TEST_PASSED

1. Push commit ke `main`/`develop` atau buka pull request.
2. Buka tab **Actions** di `github.com/jotarkhub/ai-sales-admin`, tunggu workflow "Laravel CI".
3. Kalau hijau: paste ke Claude link run-nya (atau screenshot) — status modul terkait diupdate
   di sini dengan link tersebut sebagai bukti.
4. Kalau merah: paste log kegagalan ke Claude — diperbaiki dulu sebelum lanjut ke modul
   berikutnya, sesuai aturan "jangan lanjut sebelum kegagalan fundamental diperbaiki".
