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
| Business Configuration module | `CI_TEST_PASSED` | Run [#7](https://github.com/jotarkhub/ai-sales-admin/actions) commit `fd4df06` hijau. Lokal: 20 passed (72 assertions) |
| Lead Intake endpoint | `CI_TEST_PASSED` | Run [#9](https://github.com/jotarkhub/ai-sales-admin/actions) commit `e3f3f09` hijau. Sempat gagal di CI (run #8, `ad8f2b3`): `assertSame` pada kolom JSON gagal di MySQL karena urutan key tidak dijamin sama (beda dari SQLite) — sudah diperbaiki jadi `assertEquals` |
| WhatsApp — Provider Abstraction (fondasi Fase 3) | `CI_TEST_PASSED` | Run [#14](https://github.com/jotarkhub/ai-sales-admin/actions) commit `0f95213` hijau. Lokal: 74 passed (236 assertions), Pint hijau |
| WhatsApp — FollowUp Dispatch (kirim follow-up jatuh tempo) | `CI_TEST_PASSED` | Run [#16](https://github.com/jotarkhub/ai-sales-admin/actions) commit `89120bd` hijau. Lokal: 89 passed (281 assertions), Pint hijau |
| WhatsApp — kirim pesan sungguhan ke Meta | belum bisa | `CREDENTIAL_REQUIRED` (token/phone number ID Meta belum ada — begitu tersedia, tinggal ganti `WHATSAPP_PROVIDER=meta`, kode dispatch di atas tidak perlu diubah) |
| AI — Provider Abstraction (fondasi Fase 4) | `CI_TEST_PASSED` | Run [#15](https://github.com/jotarkhub/ai-sales-admin/actions) commit `9b87b62` hijau. Lokal: 81 passed (252 assertions), Pint hijau |
| WhatsApp — Webhook Receiver (Fase 4a, terima pesan/status masuk) | `CI_TEST_PASSED` | Run [#18](https://github.com/jotarkhub/ai-sales-admin/actions) commit `0365b02` hijau. Lokal: 100 passed (307 assertions), Pint hijau. Sempat gagal 1x: handshake verifikasi baca `hub.mode` dkk. padahal PHP mengganti titik jadi underscore di query string (`hub_mode`) — sudah diperbaiki |
| AI — Conversation Engine (Fase 4b: prompt building, knowledge retrieval, guardrail, eskalasi, balas otomatis) | `CI_TEST_PASSED` | `ConversationContextBuilder` (system prompt + knowledge base published + riwayat pesan), `AiStructuredReply` (parse & validasi skema output AI — JSON wajib, gagal parse = otomatis eskalasi), `ConversationGuardrailService` (cek escalation_required & ambang confidence dari `Business::ai_authority_limit`), `ConversationEngine` (orkestrator: panggil AI -> catat `ai_runs` -> guardrail -> kirim WhatsApp atau buat `escalations`+`tickets`+set `human_takeover`). AI TIDAK PERNAH membalas selama status bukan `ai_active` — dicek ulang di sini, bukan cuma percaya caller. Terhubung otomatis dari webhook (Fase 4a) setelah pesan inbound tersimpan. Scoring lead (`lead_scores`) SENGAJA belum disentuh di modul ini — akan jadi peningkatan terpisah supaya tidak dibangun setengah-setengah. 7 test baru. Run [#19](https://github.com/jotarkhub/ai-sales-admin/actions) commit `5f57c19` hijau. Lokal: 107 passed (328 assertions), Pint hijau |
| Google Apps Script (Fase 6) | `IMPLEMENTED_UNVERIFIED` | `apps-script/LeadIntake.gs` + `apps-script/README.md` ditulis lengkap (HMAC signature, idempotency lewat sheet log, retry, testConfiguration(), dukungan CUSTOM_FIELD_MAP). **Menunggu Anda buat Google Form + pasang script + jalankan testConfiguration()** sesuai README |
| Custom Lead Fields (form builder) | `CI_TEST_PASSED` | Run [#10](https://github.com/jotarkhub/ai-sales-admin/actions) commit `0e5d910` hijau. Lokal: 35 passed (133 assertions), Pint hijau |
| Dashboard admin — Lead List & Detail (Fase 5a) | `CI_TEST_PASSED` | Run [#11](https://github.com/jotarkhub/ai-sales-admin/actions) commit `988c973` hijau. Lokal: 49 passed (174 assertions), Pint hijau |
| Dashboard admin — Percakapan & Takeover (Fase 5b) | `CI_TEST_PASSED` | Run [#12](https://github.com/jotarkhub/ai-sales-admin/actions) commit `9048523` hijau. Lokal: 58 passed (197 assertions), Pint hijau |
| Dashboard admin — Knowledge Base (Fase 5c) | `CI_TEST_PASSED` | Run [#13](https://github.com/jotarkhub/ai-sales-admin/actions) commit `c76e1cf` hijau. Lokal: 65 passed (218 assertions), Pint hijau |

## Fase 7 — Verifikasi End-to-End

**Status: `CI_TEST_PASSED`** — Run [#20](https://github.com/jotarkhub/ai-sales-admin/actions) commit `dca07f7` hijau. Lokal: 109 passed (363 assertions), Pint hijau (1 style issue auto-fixed di file test ini).

`tests/Feature/EndToEnd/FullPipelineTest.php` (2 test) memverifikasi modul-modul yang sudah
`CI_TEST_PASSED` sendiri-sendiri benar-benar nyambung kalau dipakai berurutan seperti alur
nyata, bukan cuma lulus terisolasi:

1. Form masuk (Lead Intake) -> Lead + FollowUp pending tercipta.
2. FollowUp Dispatch mengirim pesan pembuka -> Conversation + Message pertama.
3. Pelanggan membalas lewat webhook -> masuk ke Lead & Conversation yang SAMA (bukan duplikat).
4. Conversation Engine otomatis membalas (guardrail lolos) -> `ai_runs` tercatat lengkap.
5. Delivery receipt (status webhook) meng-update status pesan.
6. Admin login, lihat lead & percakapan di dashboard.
7. Admin ambil alih percakapan -> AI berhenti membalas (diverifikasi ulang dengan kirim pesan
   pelanggan lagi saat human_takeover — dipastikan TIDAK ada balasan AI/ai_run baru).
8. Admin kembalikan ke AI, lalu konfirmasi lead won -> percakapan otomatis ditutup.
9. (Test kedua) AI mendeteksi komplain -> eskalasi + tiket terbuka -> admin bisa lihat di dashboard.

**Yang SUDAH terverifikasi lewat ini:** wiring antar modul (Lead Intake, FollowUp Dispatch,
Webhook Receiver, Conversation Engine, Dashboard) benar dan konsisten, memakai
`FakeWhatsAppProvider` + `FakeAiProvider`.

**Yang BELUM terverifikasi (`CREDENTIAL_REQUIRED`, sesuai "Aturan Keras" di bawah):**
- Kirim/terima pesan WhatsApp sungguhan ke Meta (token & phone number ID belum ada).
- Balasan AI dari model OpenAI asli (API key belum ada).
- Webhook menerima payload sungguhan dari Meta (perlu server production dengan domain +
  HTTPS publik — komputer lokal tidak bisa diakses Meta).
- Fase 6 (Google Form klien LPK) belum dibangun manual oleh user.

Begitu kredensial & hosting tersedia, ulangi alur yang sama di lingkungan staging dengan
`WHATSAPP_PROVIDER=meta` + `AI_PROVIDER=openai` sebelum status modul terkait naik ke
`PRODUCTION_READY`.

## Fase 8 — Platform Multi-Tenant

Keputusan: tiap klien (bisnis) punya App Meta WhatsApp sendiri-sendiri (bukan satu App
Meta bersama), platform owner mendaftarkan bisnis baru secara manual. Kredensial OpenAI
tetap satu untuk seluruh platform (bukan per klien).

**Fase 8a — Role & akses platform owner: `IMPLEMENTED_UNVERIFIED`.**

- Ditemukan & diperbaiki bug lama: `ResolvesCurrentBusiness` (dipakai Business Configuration,
  Lead Fields, Knowledge Base, daftar Lead) sebelumnya ambil bisnis lewat
  `Business::where('is_active', true)->firstOrFail()` — benar selama cuma satu bisnis aktif,
  tapi begitu ada bisnis kedua, staf bisnis B akan melihat/mengedit data bisnis A. Sekarang
  berdasarkan `business_id` milik user yang login.
- `BusinessPolicy::view`/`update` diperketat supaya cek `business_id` cocok (sebelumnya
  `view` true untuk staf aktif mana pun tanpa cek bisnis — tidak pernah tereksploitasi lewat
  route yang ada, tapi berbahaya begitu ada route/context baru).
- Role baru `Role::PLATFORM_OWNER` — lintas bisnis, `business_id` nullable, tidak melalui
  middleware `role:admin,supervisor,agent` yang mengunci ke satu bisnis.
- Panel baru `platform.businesses.index` (`GET /platform/bisnis`) — daftar semua bisnis +
  jumlah staf/lead per bisnis. Baru daftar sederhana; form "Tambah Bisnis Baru" menyusul di
  Fase 8d.
- Login platform owner diarahkan ke panel ini, bukan ke `dashboard` biasa (yang akan 403
  karena platform owner bukan staf satu bisnis).
- Seeder: user contoh `owner@example.test` / `password` (data pengujian, role platform_owner).
- Test baru: isolasi Business Configuration & daftar Lead antar 2 bisnis, akses panel platform
  owner (positif & negatif), redirect login. **Belum dijalankan user — menunggu bukti nyata
  sebelum naik ke `LOCAL_TEST_PASSED`/`CI_TEST_PASSED`.**

**Fase 8b/8c/8d — kredensial WhatsApp per bisnis, webhook per bisnis, form tambah bisnis:**
`DESIGNED` (lihat rencana di riwayat percakapan), belum dikerjakan.

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
