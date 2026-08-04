# AI Sales Admin — Arsitektur & Spesifikasi (Fase 1)

Status dokumen: **DESIGNED**. Ini adalah kontrak acuan untuk Fase 2 dan seterusnya. Setiap
kali migration/model/endpoint dibuat, ia harus bisa ditelusuri balik ke bagian di dokumen ini.

Lihat `docs/STATUS.md` untuk definisi status implementasi dan status terkini tiap modul.

---

## 1. System Context Diagram

```mermaid
graph TD
    Customer["Calon Customer\n(WhatsApp)"]
    GForm["Google Form"]
    GSheet["Google Sheets"]
    AppsScript["Google Apps Script\n(on form submit trigger)"]
    App["AI Sales Admin\n(Laravel)"]
    WA["WhatsApp Business\nCloud API (Meta)"]
    OpenAI["OpenAI Responses API"]
    Admin["Admin / Sales Team\n(Dashboard)"]

    Customer -->|isi jawaban| GForm
    GForm -->|simpan| GSheet
    GForm -->|trigger onSubmit| AppsScript
    AppsScript -->|POST + secret| App
    App -->|kirim template & balasan| WA
    WA -->|webhook pesan masuk & status| App
    WA <-->|pesan WhatsApp| Customer
    App -->|kirim context, minta structured output| OpenAI
    OpenAI -->|JSON terstruktur| App
    Admin -->|login, kelola, takeover| App
    App -->|notifikasi eskalasi, ringkasan| Admin
```

Prinsip kunci: Apps Script **tidak pernah** menyimpan API key WhatsApp/OpenAI — ia hanya
meneruskan payload form ke Laravel dengan secret bersama. Semua kecerdasan dan keputusan
transaksional ada di Laravel, bukan di Apps Script maupun langsung di model AI.

## 2. Container Diagram

```mermaid
graph TD
    subgraph "AI Sales Admin (Laravel monolith)"
        WebAPI["HTTP Layer\n(routes/api.php, routes/web.php)\nControllers + FormRequests + Policies"]
        Dashboard["Dashboard Admin\n(Blade/Livewire)"]
        ConvEngine["Conversation Engine\n(app/Services/Conversation)"]
        ScoreEngine["Lead Scoring Engine\n(app/Services/Scoring)"]
        FollowUpEngine["Follow-Up Engine\n(app/Services/FollowUp)"]
        ProviderLayer["Provider Abstraction\nAiProviderInterface / WhatsAppProviderInterface"]
        Queue["Queue Workers\n(php artisan queue:work)"]
        Scheduler["Scheduler\n(php artisan schedule:run)"]
        AuditSvc["Audit Log Service"]
    end

    DB[("MySQL / MariaDB")]
    RedisCache[("Redis\n(fallback: database queue)")]

    WebAPI --> ConvEngine
    WebAPI --> AuditSvc
    ConvEngine --> ProviderLayer
    ConvEngine --> ScoreEngine
    FollowUpEngine --> ProviderLayer
    ProviderLayer -->|OpenAiResponsesProvider| ExtOpenAI["OpenAI API"]
    ProviderLayer -->|WhatsAppCloudProvider| ExtWA["WhatsApp Cloud API"]
    ProviderLayer -->|FakeAiProvider / FakeWhatsAppProvider\nHANYA di testing| Fakes["In-memory fakes"]
    Queue --> ProviderLayer
    Scheduler --> FollowUpEngine
    WebAPI --> DB
    Dashboard --> DB
    ConvEngine --> DB
    Queue --> RedisCache
    Queue --> DB
    AuditSvc --> DB
```

Aplikasi tetap **monolith Laravel** untuk MVP (bukan microservices) — modul dipisah secara
logis lewat namespace `app/Services/*` dan `app/Contracts/*`, bukan lewat proses terpisah.
n8n **tidak** dipakai di MVP, tapi provider abstraction membuatnya bisa disisipkan nanti tanpa
mengubah Conversation Engine.

## 3. Sequence Diagram — Form Submission

```mermaid
sequenceDiagram
    actor C as Calon Customer
    participant GF as Google Form
    participant AS as Apps Script
    participant API as Laravel: LeadIntakeController
    participant Val as Validator + Signature Check
    participant DB as Database
    participant Q as Queue

    C->>GF: Isi & submit form
    GF->>AS: Trigger onFormSubmit
    AS->>AS: Susun payload + externalSubmissionId + timestamp
    AS->>API: POST /api/v1/leads/intake (header secret)
    API->>Val: Verifikasi secret/signature
    alt secret tidak valid
        Val-->>API: reject
        API-->>AS: 401 Unauthorized
    else valid
        Val-->>API: ok
        API->>DB: Cek duplikasi (external_submission_id)
        alt sudah ada
            DB-->>API: lead sudah ada
            API-->>AS: 200 (idempotent, no-op)
        else baru
            API->>DB: Simpan raw payload (lead_form_submissions)
            API->>DB: Normalisasi nomor -> buat lead (status=new)
            API->>DB: Catat lead_activity "lead_created"
            API->>DB: Catat audit_log
            alt consent_whatsapp = true
                API->>Q: Queue job kirim template WhatsApp pertama
            else consent_whatsapp = false
                API->>DB: Tandai "menunggu consent", TIDAK menjadwalkan WA
            end
            API-->>AS: 201 Created
        end
    end
```

## 4. Sequence Diagram — Percakapan WhatsApp

```mermaid
sequenceDiagram
    actor C as Customer
    participant WA as WhatsApp Cloud API
    participant WH as Laravel: WhatsAppWebhookController
    participant Idem as Idempotency Check (webhook_events)
    participant CE as Conversation Engine
    participant AI as AiProviderInterface
    participant GR as Guardrail
    participant DB as Database

    C->>WA: Kirim balasan
    WA->>WH: POST webhook (message)
    WH->>Idem: Cek webhook_event_id sudah diproses?
    alt sudah diproses (duplicate)
        Idem-->>WH: duplicate
        WH-->>WA: 200 OK (no-op)
    else baru
        WH->>DB: Simpan message inbound + map ke lead/conversation
        WH->>CE: Proses pesan
        CE->>DB: Ambil profil lead, riwayat pesan, ringkasan, knowledge relevan
        CE->>AI: Kirim context + system prompt versioned
        AI-->>CE: Structured JSON (intent, next_action, reply_message, escalation_required, dst.)
        CE->>DB: Simpan ai_runs (model, token, biaya, prompt_version)
        CE->>GR: Validasi output (schema valid? melanggar larangan? confidence cukup?)
        alt lolos guardrail & tidak perlu eskalasi
            GR->>DB: Update lead_score, lead_activities
            GR->>WA: Kirim reply_message
            WA-->>C: Terima balasan AI
        else perlu eskalasi / gagal guardrail
            GR->>DB: Buat escalation + ticket, set conversation.status=human_takeover
            GR->>DB: AI berhenti membalas otomatis
            Note over GR,DB: Admin dinotifikasi (lihat sequence Human Handoff)
        end
    end
```

## 5. Sequence Diagram — Human Handoff

```mermaid
sequenceDiagram
    actor AI as Conversation Engine
    participant DB as Database
    participant Notif as Notifikasi Admin
    actor Adm as Admin
    participant WA as WhatsApp Cloud API

    AI->>DB: escalation_required=true (alasan, ringkasan, tindakan disarankan)
    AI->>DB: Buat escalations + tickets, conversation.status=human_takeover
    AI->>DB: AI berhenti membalas otomatis pada conversation ini
    DB->>Notif: Trigger notifikasi (dashboard + opsional email/WA internal)
    Notif->>Adm: Tampilkan ringkasan, alasan eskalasi, tindakan disarankan
    Adm->>DB: Klik "Take Over" (audit_log dicatat)
    Adm->>WA: Kirim pesan manual ke customer
    WA-->>Adm: (via dashboard, riwayat tersimpan)
    Adm->>DB: Klik "Kembalikan ke AI" (opsional, kapan pun admin siap)
    DB->>AI: conversation.status=ai_active lagi, AI boleh membalas otomatis
```

## 6. ERD

```mermaid
erDiagram
    BUSINESSES ||--o{ USERS : "mempekerjakan"
    BUSINESSES ||--o{ LEADS : memiliki
    BUSINESSES ||--o{ PRODUCTS : menjual
    BUSINESSES ||--o{ KNOWLEDGE_ITEMS : memiliki
    BUSINESSES ||--o{ PROMPT_VERSIONS : memiliki
    BUSINESSES ||--o{ INTEGRATION_CREDENTIALS : memiliki

    ROLES ||--o{ ROLE_USER : "memberi peran"
    USERS ||--o{ ROLE_USER : "punya peran"

    LEAD_SOURCES ||--o{ LEADS : "sumber dari"
    LEAD_FORM_SUBMISSIONS }o--o| LEADS : "membentuk"
    PRODUCTS ||--o{ LEADS : "diminati (nullable)"

    LEADS ||--o{ CONVERSATIONS : punya
    CONVERSATIONS ||--o{ MESSAGES : berisi
    MESSAGES ||--o{ MESSAGE_STATUSES : "riwayat status"
    LEADS ||--o{ LEAD_ACTIVITIES : "log aktivitas"
    LEADS ||--o{ LEAD_SCORES : "riwayat skor"
    LEAD_SCORES ||--o{ LEAD_SCORE_COMPONENTS : "rincian komponen"
    LEADS }o--o{ TAGS : "diberi tag"
    LEADS ||--o{ LEAD_TAGS : ""
    TAGS ||--o{ LEAD_TAGS : ""

    LEADS ||--o{ FOLLOW_UPS : dijadwalkan
    CONVERSATIONS ||--o{ ESCALATIONS : memicu
    ESCALATIONS ||--o| TICKETS : menghasilkan
    CONVERSATIONS ||--o{ AI_RUNS : mencatat
    PROMPT_VERSIONS ||--o{ AI_RUNS : dipakai

    LEADS {
        bigint id PK
        bigint business_id FK
        bigint lead_source_id FK
        string external_submission_id UK "nullable"
        string name
        string phone_number "E.164"
        string email "nullable"
        string city "nullable"
        bigint interested_product_id FK "nullable"
        string budget_estimate "nullable"
        string purchase_timeline "nullable"
        text needs_notes "nullable"
        boolean consent_whatsapp
        string status "enum, lihat state machine"
        integer current_score
        bigint assigned_admin_id FK "nullable"
        timestamp opted_out_at "nullable"
        bigint won_confirmed_by FK "nullable, wajib admin"
        timestamp won_confirmed_at "nullable"
        timestamps created_at_updated_at
    }
    LEAD_FORM_SUBMISSIONS {
        bigint id PK
        bigint lead_id FK "nullable"
        string external_submission_id UK
        timestamp submitted_at
        json raw_payload
        string source
        boolean consent_whatsapp
        string processing_status "pending/processed/duplicate/rejected"
        string rejection_reason "nullable"
    }
    CONVERSATIONS {
        bigint id PK
        bigint lead_id FK
        bigint business_id FK
        string channel "whatsapp"
        string status "ai_active/human_takeover/closed"
        bigint assigned_admin_id FK "nullable"
        text summary "nullable"
        timestamp last_message_at
    }
    MESSAGES {
        bigint id PK
        bigint conversation_id FK
        bigint lead_id FK
        string direction "inbound/outbound"
        string sender_type "customer/ai/admin/system"
        string whatsapp_message_id UK "nullable"
        string template_name "nullable"
        text body
        string media_type "nullable"
        string media_url "nullable"
        string latest_status "queued/sent/delivered/read/failed"
        bigint ai_run_id FK "nullable"
    }
    MESSAGE_STATUSES {
        bigint id PK
        bigint message_id FK
        string status
        json raw_payload
        timestamp occurred_at
    }
    WEBHOOK_EVENTS {
        bigint id PK
        string source "whatsapp/google_form"
        string event_type
        string external_event_id UK
        boolean signature_valid
        json payload
        string status "pending/processed/failed/duplicate"
        timestamp received_at
        timestamp processed_at "nullable"
    }
    LEAD_SCORES {
        bigint id PK
        bigint lead_id FK
        integer total_score
        integer previous_score
        string status_before
        string status_after
        string computed_by "system/admin"
        timestamp computed_at
    }
    LEAD_SCORE_COMPONENTS {
        bigint id PK
        bigint lead_score_id FK
        string component_key
        string label
        decimal weight
        decimal raw_value
        decimal points
    }
    ESCALATIONS {
        bigint id PK
        bigint conversation_id FK
        bigint lead_id FK
        string reason
        text reason_detail
        string status "open/claimed/resolved"
        decimal ai_confidence_at_escalation "nullable"
        text suggested_action
        bigint claimed_by FK "nullable"
        timestamp claimed_at "nullable"
        timestamp resolved_at "nullable"
    }
    TICKETS {
        bigint id PK
        bigint escalation_id FK "nullable"
        bigint lead_id FK
        string subject
        string status
        string priority
        bigint assigned_to FK "nullable"
        timestamp resolved_at "nullable"
    }
    AI_RUNS {
        bigint id PK
        bigint conversation_id FK
        bigint message_id FK "nullable"
        bigint prompt_version_id FK
        string provider "openai/fake"
        string model_used
        integer input_tokens
        integer output_tokens
        decimal estimated_cost_usd
        integer latency_ms
        json raw_output
        boolean structured_output_valid
        string status "success/failed/guardrail_blocked"
    }
    PROMPT_VERSIONS {
        bigint id PK
        bigint business_id FK "nullable"
        string name
        string version_label
        longtext content
        boolean is_active
        bigint created_by FK
    }
    INTEGRATION_CREDENTIALS {
        bigint id PK
        bigint business_id FK
        string provider "whatsapp/openai/google"
        string credential_key
        text encrypted_value
        boolean is_active
        timestamp expires_at "nullable"
    }
    AUDIT_LOGS {
        bigint id PK
        string actor_type "user/system/ai"
        bigint actor_id "nullable"
        string action
        string subject_type
        bigint subject_id
        json before
        json after
        string ip_address "nullable"
        timestamp created_at
    }
```

Tabel yang tidak ditampilkan atributnya di atas (`businesses`, `users`, `roles`, `role_user`,
`lead_sources`, `products`, `knowledge_items`, `follow_ups`, `tags`, `lead_tags`,
`lead_activities`) mengikuti pola kolom standar Laravel (id, timestamps) ditambah field sesuai
namanya sebagaimana dirinci di modul "Business Configuration", "Knowledge Base", "Follow-Up
Engine" pada spesifikasi asli — akan dituliskan lengkap saat migration masing-masing dibuat
(Fase 2, Task #4) supaya tidak dua kali menulis skema yang sama.

## 7. State Machine — Lead

```mermaid
stateDiagram-v2
    [*] --> new
    new --> contacted: pesan pertama terkirim
    contacted --> engaged: customer membalas
    engaged --> qualifying: AI mulai tanya kualifikasi
    qualifying --> qualified: skor & jawaban cukup
    qualified --> proposal_requested: customer minta penawaran
    proposal_requested --> proposal_sent: admin/AI kirim penawaran
    proposal_sent --> negotiating: ada tawar-menawar
    negotiating --> won: **hanya via konfirmasi admin/transaksi tervalidasi**
    negotiating --> lost
    qualified --> lost
    engaged --> dormant: tidak ada respons lama
    contacted --> dormant: tidak ada respons lama
    dormant --> engaged: customer aktif lagi
    new --> opt_out
    contacted --> opt_out
    engaged --> opt_out
    qualifying --> opt_out
    qualified --> opt_out
    any --> escalated: trigger eskalasi apa pun\n(lihat state machine conversation)
    escalated --> engaged: admin selesai tangani, lanjut alur normal
    won --> [*]
    lost --> [*]
    opt_out --> [*]

    note right of won
        Transisi ke "won" TIDAK BOLEH
        dilakukan otomatis oleh AI.
        Wajib approval admin atau
        record transaksi tervalidasi.
    end note
```

## 8. State Machine — Conversation

```mermaid
stateDiagram-v2
    [*] --> ai_active
    ai_active --> human_takeover: escalation_required=true\n(dari Conversation Engine)
    ai_active --> human_takeover: admin klik "Take Over" manual
    human_takeover --> ai_active: admin klik "Kembalikan ke AI"
    ai_active --> closed: lead won/lost/opt_out
    human_takeover --> closed: lead won/lost/opt_out
    closed --> [*]

    note right of human_takeover
        Selama status ini, AI TIDAK BOLEH
        mengirim balasan otomatis apa pun,
        sampai admin eksplisit menyerahkan
        kembali ke AI.
    end note
```

## 9. Daftar Environment Variables

| Variable | Wajib di | Keterangan |
|---|---|---|
| `APP_TIMEZONE` | semua | `Asia/Jakarta` (fixed) |
| `APP_LOCALE` | semua | `id` (UI Bahasa Indonesia) |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | semua | MySQL/MariaDB. Local dev boleh sqlite hanya untuk `.env.testing` |
| `QUEUE_CONNECTION` | semua | `redis` bila tersedia, fallback `database` |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | opsional | Kosongkan jika pakai fallback database queue |
| `LEAD_INTAKE_SECRET` | Fase 2 | Shared secret Apps Script <-> endpoint intake. **Belum dibuat, akan ditambah saat modul Lead Intake dikerjakan.** |
| `WHATSAPP_CLOUD_API_TOKEN` | Fase 3 | Access token Meta. **CREDENTIAL_REQUIRED — belum ada.** |
| `WHATSAPP_PHONE_NUMBER_ID` | Fase 3 | **CREDENTIAL_REQUIRED** |
| `WHATSAPP_BUSINESS_ACCOUNT_ID` | Fase 3 | **CREDENTIAL_REQUIRED** |
| `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | Fase 3 | **CREDENTIAL_REQUIRED** |
| `WHATSAPP_APP_SECRET` | Fase 3 | Untuk verifikasi signature webhook. **CREDENTIAL_REQUIRED** |
| `WHATSAPP_PROVIDER` | Fase 3+ | `fake` (testing) atau `whatsapp_cloud` (production). App **wajib gagal boot** jika `APP_ENV=production` dan value = `fake` |
| `OPENAI_API_KEY` | Fase 4 | **CREDENTIAL_REQUIRED — belum ada** |
| `OPENAI_MODEL_CHEAP`, `OPENAI_MODEL_ADVANCED` | Fase 4 | Untuk model router |
| `AI_PROVIDER` | Fase 4+ | `fake` (testing) atau `openai` (production). App **wajib gagal boot** jika `APP_ENV=production` dan value = `fake` |
| `AI_CONFIDENCE_ESCALATION_THRESHOLD` | Fase 4 | Ambang confidence -> eskalasi otomatis |
| `ESCALATION_TRANSACTION_VALUE_LIMIT` | Fase 4 | Nilai transaksi di atas ini wajib eskalasi |

Env var Fase 3/4 di atas **belum ditambahkan ke `.env.example`** — akan ditambah tepat saat
modul terkait mulai dikerjakan, supaya `.env.example` selalu mencerminkan apa yang benar-benar
sudah aktif, bukan daftar aspirasional.

## 10. Daftar Risiko

1. **Sandbox eksekusi Claude tidak punya akses Packagist/GitHub/root.** Mitigasi: alur kerja
   local-bootstrap + GitHub Actions CI (lihat `docs/STATUS.md`) — sudah berjalan.
2. **PHP lokal Claude hanya bisa 8.1 (tidak dipakai lagi setelah keputusan pivot ke CI).**
   Tidak berdampak karena eksekusi nyata sekarang di komputer user (PHP 8.2.4) dan CI (PHP 8.2).
3. **Kredensial WhatsApp/OpenAI/Google belum ada** — modul terkait berstatus
   `CREDENTIAL_REQUIRED` sampai tersedia; tidak memblokir Fase 0-2.
4. **Idempotency webhook** — WhatsApp dan provider lain umum mengirim webhook duplikat/retry.
   Wajib unique constraint di `webhook_events.external_event_id` sebelum diproses.
5. **Normalisasi nomor Indonesia** — banyak format input (`08xx`, `+628xx`, `628xx`, spasi/strip).
   Wajib normalizer terpusat + test kasus-kasus umum sebelum dipakai di WhatsApp API.
6. **Biaya OpenAI tak terkendali** — mitigasi lewat model router (model hemat utk rutin) +
   pencatatan token/biaya per `ai_runs` + limit di guardrail.
7. **AI mengubah status "won" secara otomatis** — dicegah di level aplikasi (bukan cuma prompt):
   endpoint ubah status ke `won` wajib melalui aksi admin eksplisit, model hanya boleh
   merekomendasikan.
8. **Multi-bisnis di masa depan** — MVP pakai satu `business_id` aktif, tapi semua tabel inti
   sudah memiliki `business_id` FK sejak awal supaya migrasi ke multi-tenant tidak perlu
   migration ulang skema besar-besaran.
9. **Fake provider bocor ke production** — wajib guard di `AppServiceProvider`/config yang
   melempar exception saat boot jika `APP_ENV=production` tapi provider yang dikonfigurasi
   `fake`.

## 11. Acceptance Criteria

**Fase 1 (dokumen ini) dianggap selesai bila:**
- Semua diagram di atas merepresentasikan 15 poin alur MVP di spesifikasi asli tanpa langkah
  yang hilang.
- ERD mencakup seluruh 24 tabel yang diwajibkan, tidak lebih tidak kurang, dengan relasi jelas.
- State machine lead melarang transisi otomatis ke `won` oleh AI (tervalidasi di desain, akan
  ditegakkan lagi di level policy/service saat Fase 2/6).

**Fase 2 (fondasi inti) dianggap selesai bila, dibuktikan lewat CI hijau (bukan klaim):**
- Migration seluruh tabel inti berjalan bersih dari kosong (`migrate:fresh`) di MySQL (CI) dan
  SQLite (lokal).
- Login admin + role-based authorization berfungsi, dibuktikan test authorization (positif &
  negatif).
- Endpoint Lead Intake: submission valid membuat lead; submission duplikat tidak membuat lead
  kedua; nomor invalid ditolak dengan pesan jelas; consent=false tidak menjadwalkan pengiriman
  WhatsApp apa pun.
- Setiap perubahan status lead & submission form menghasilkan baris `audit_logs`.
- Business Configuration bisa dibaca/ditulis admin dan divalidasi.
- `php artisan test` dan `vendor/bin/pint --test` hijau di GitHub Actions.
