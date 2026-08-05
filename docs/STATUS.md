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
| GitHub Actions CI (baseline: composer validate, migrate:fresh, test, pint) | `CI_TEST_PASSED` | Run [#5](https://github.com/jotarkhub/ai-sales-admin/actions/runs/30917950801) commit `02b6dcc` hijau |
| Migration & model tabel inti (24 tabel) | `CI_TEST_PASSED` | Lokal: 3 passed/30 assertions. CI (MySQL 8.0): migrate:fresh + test + pint hijau di run #5. Sempat gagal 2x: (1) nama unique constraint `integration_credentials` >64 char (batas identifier MySQL, tidak ketahuan di SQLite lokal), (2) Pint fully-qualified-class-name di test — keduanya sudah diperbaiki |
| Auth & authorization (role-based) | `CI_TEST_PASSED` | Run [#6](https://github.com/jotarkhub/ai-sales-admin/actions/runs/) commit `1af8e3a` hijau. Login session-based + rate limit 5x/menit, middleware `role:` |
| Audit log service | `CI_TEST_PASSED` | Sama seperti di atas — `App\Services\Audit\AuditLogService`, dipakai di login/logout, teruji |
| Business Configuration module | `LOCAL_TEST_PASSED` | `php artisan test`: 20 passed (72 assertions) di komputer user. Menunggu konfirmasi CI (commit `fd4df06`) untuk naik ke `CI_TEST_PASSED` |
| Lead Intake endpoint | `CI_TEST_PASSED` | Run [#9](https://github.com/jotarkhub/ai-sales-admin/actions) commit `e3f3f09` hijau. Sempat gagal di CI (run #8, `ad8f2b3`): `assertSame` pada kolom JSON gagal di MySQL karena urutan key tidak dijamin sama (beda dari SQLite) — sudah diperbaiki jadi `assertEquals` |
| WhatsApp — Provider Abstraction (fondasi Fase 3) | `CI_TEST_PASSED` | Run [#14](https://github.com/jotarkhub/ai-sales-admin/actions) commit `0f95213` hijau. Lokal: 74 passed (236 assertions), Pint hijau |
| WhatsApp — kirim pesan sungguhan | belum dimulai | `CREDENTIAL_REQUIRED` (token/phone number ID Meta belum ada — provider abstraction di atas sudah siap dipakai begitu kredensial tersedia) |
| AI — Provider Abstraction (fondasi Fase 4) | `IMPLEMENTED_UNVERIFIED` | `AiProviderContract`, `FakeAiProvider` (dipakai testing, balasan bisa diatur lewat `respondWith()`), `OpenAiProvider` (panggilan HTTP nyata ke Chat Completions, diuji dengan `Http::fake()`). Pola sama persis dengan WhatsApp provider abstraction, termasuk `ProviderGuard`. 8 test baru. Lolos `php -l`, **menunggu `php artisan test` sungguhan** |
| AI — Conversation Engine (prompt building, knowledge retrieval, scoring, eskalasi) | belum dimulai | `CREDENTIAL_REQUIRED` (API key OpenAI belum ada — provider abstraction di atas sudah siap dipakai begitu kredensial tersedia) |
| Google Apps Script (Fase 6) | `IMPLEMENTED_UNVERIFIED` | `apps-script/LeadIntake.gs` + `apps-script/README.md` ditulis lengkap (HMAC signature, idempotency lewat sheet log, retry, testConfiguration(), dukungan CUSTOM_FIELD_MAP). **Menunggu Anda buat Google Form + pasang script + jalankan testConfiguration()** sesuai README |
| Custom Lead Fields (form builder) | `CI_TEST_PASSED` | Run [#10](https://github.com/jotarkhub/ai-sales-admin/actions) commit `0e5d910` hijau. Lokal: 35 passed (133 assertions), Pint hijau |
| Dashboard admin — Lead List & Detail (Fase 5a) | `CI_TEST_PASSED` | Run [#11](https://github.com/jotarkhub/ai-sales-admin/actions) commit `988c973` hijau. Lokal: 49 passed (174 assertions), Pint hijau |
| Dashboard admin — Percakapan & Takeover (Fase 5b) | `CI_TEST_PASSED` | Run [#12](https://github.com/jotarkhub/ai-sales-admin/actions) commit `9048523` hijau. Lokal: 58 passed (197 assertions), Pint hijau |
| Dashboard admin — Knowledge Base (Fase 5c) | `CI_TEST_PASSED` | Run [#13](https://github.com/jotarkhub/ai-sales-admin/actions) commit `c76e1cf` hijau. Lokal: 65 passed (218 assertions), Pint hijau |

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
