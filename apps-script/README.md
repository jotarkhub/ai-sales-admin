# Fase 6 — Google Apps Script (Lead Intake Connector)

Skrip ini menghubungkan Google Form ke endpoint `POST /api/v1/leads/intake` di aplikasi
Laravel. Tidak butuh kredensial WhatsApp atau OpenAI sama sekali — hanya `LEAD_INTAKE_SECRET`
yang sudah dikonfigurasi di `.env` sejak Fase 2.

Status: `IMPLEMENTED_UNVERIFIED` sampai Anda ikuti langkah di bawah dan konfirmasi lead
sungguhan berhasil masuk (lihat `docs/STATUS.md`).

## 1. Buat Google Form

Buat form baru di [forms.google.com](https://forms.google.com) dengan pertanyaan berikut
(judul harus **persis sama**, termasuk kapitalisasi — atau edit `CONFIG.FIELD_MAP` di
`LeadIntake.gs` supaya cocok dengan judul pertanyaan Anda sendiri):

| Judul Pertanyaan | Tipe | Wajib? |
|---|---|---|
| Nama Lengkap | Jawaban singkat | Ya |
| Nomor WhatsApp | Jawaban singkat | Ya |
| Email | Jawaban singkat | Tidak |
| Kota | Jawaban singkat | Tidak |
| Produk yang Diminati | Jawaban singkat / Dropdown | Tidak |
| Perkiraan Anggaran | Jawaban singkat | Tidak |
| Waktu Rencana Pembelian | Jawaban singkat | Tidak |
| Kebutuhan / Pertanyaan | Paragraf | Tidak |
| Bersedia dihubungi lewat WhatsApp? | Pilihan ganda: "Ya" / "Tidak" | Ya |

## 2. Hubungkan Form ke Spreadsheet

Di editor Form: tab **Responses** → klik ikon Sheets hijau → **Create a new spreadsheet**.
Ini penting — skrip harus terpasang di **spreadsheet respons**, bukan di form itu sendiri
(supaya event trigger memberi `namedValues` yang dipakai skrip).

## 3. Tempel Script

Buka spreadsheet respons tadi → menu **Extensions > Apps Script**. Hapus isi default
`Code.gs`, lalu salin seluruh isi [`LeadIntake.gs`](./LeadIntake.gs) dari repo ini ke sana.
Simpan project (beri nama, mis. "AI Sales Admin - Lead Intake").

## 4. Set Script Properties (secret, JANGAN ditulis di kode)

Di editor Apps Script: ikon gerigi **Project Settings** → scroll ke **Script Properties** →
**Add script property**, tambahkan dua baris:

| Property | Value |
|---|---|
| `LEAD_INTAKE_ENDPOINT_URL` | `https://domain-server-anda.com/api/v1/leads/intake` |
| `LEAD_INTAKE_SECRET` | isi persis sama dengan `LEAD_INTAKE_SECRET` di `.env` server Laravel |

Selama server masih di localhost (belum di-deploy ke domain publik), Apps Script **tidak
bisa** menjangkaunya — Anda perlu server yang bisa diakses dari internet (staging/production),
atau tunnel sementara seperti `ngrok`/`Cloudflare Tunnel` untuk uji coba dari komputer lokal.

## 5. Pasang Installable Trigger

Di editor Apps Script: ikon jam **Triggers** (sisi kiri) → **Add Trigger** → isi:

- Choose which function to run: `onFormSubmitInstallable`
- Choose which deployment should run: `Head`
- Select event source: `From spreadsheet`
- Select event type: `On form submit`

Klik **Save**. Google akan minta otorisasi akses (karena skrip memanggil URL eksternal) —
setujui dengan akun Google yang sama dengan pemilik form.

**Kenapa bukan trigger sederhana (`onFormSubmit` biasa)?** Trigger sederhana tidak diizinkan
melakukan panggilan jaringan keluar (`UrlFetchApp`). Wajib installable trigger seperti di atas.

## 6. Uji Tanpa Submit Form Sungguhan

Di editor Apps Script, pilih fungsi `testConfiguration` dari dropdown di toolbar, lalu klik
**Run**. Ini mengirim satu payload uji ke endpoint Laravel. Cek hasilnya di:

- Tab **Execution log** di Apps Script (harus tanpa error)
- Sheet baru bernama **"Intake Log"** yang otomatis dibuat di spreadsheet respons Anda
- Tabel `leads` di database Laravel (cari `name = 'Test Konfigurasi Apps Script'`)

Kalau gagal dengan status 401: signature tidak cocok — cek ulang `LEAD_INTAKE_SECRET` sama
persis di kedua sisi. Status 503: `LEAD_INTAKE_SECRET` kosong di server. Status 422: field
wajib (nama/nomor WhatsApp/consent) kosong atau nomor telepon tidak valid.

## 6b. Field Custom (Opsional) — untuk Bisnis dengan Kebutuhan Khusus

Kalau bisnis Anda butuh field di luar 9 field standar (misalnya klien konsultan LPK yang perlu
No KTP pemohon, data orang tua, data penjamin/jaminan), jangan tambah kolom baru di Laravel —
pakai **form builder**:

1. Login dashboard admin → **Pengaturan > Field Custom Lead**.
2. Tambah field satu per satu (label, tipe, wajib/tidak, dan tandai **"Data sensitif"** untuk
   field seperti NIK/KTP — nilainya otomatis dienkripsi di database, tidak pernah tersimpan
   sebagai teks biasa).
3. Setiap field dapat **Key** otomatis (mis. "No KTP Pemohon" → `no_ktp_pemohon`).
4. Di form Google Anda, tambahkan pertanyaan yang sesuai (judulnya bebas, tidak harus sama
   dengan label di dashboard).
5. Di `LeadIntake.gs`, isi `CONFIG.CUSTOM_FIELD_MAP` — key di kiri harus **sama persis** dengan
   Key di dashboard, value di kanan adalah judul pertanyaan di form Anda. Contoh lengkap untuk
   kasus LPK/pembiayaan sudah ada sebagai komentar di dalam `CONFIG.CUSTOM_FIELD_MAP` pada file
   tersebut — tinggal un-comment dan sesuaikan.

Field custom yang ditandai wajib di dashboard akan divalidasi otomatis oleh Laravel (submission
ditolak dengan pesan jelas kalau field wajib kosong) — Apps Script tidak perlu tahu soal ini.

## 7. Uji dengan Form Sungguhan

Isi form seperti calon customer asli, submit, lalu cek sheet **Intake Log** dan tabel `leads`
lagi. Setelah ini berhasil, update `docs/STATUS.md` di repo Laravel untuk modul Google Apps
Script.

## Yang TIDAK Boleh Ditambahkan ke Skrip Ini

- API key/token WhatsApp Business Cloud API
- API key OpenAI
- Logika keputusan bisnis apa pun (lead scoring, kualifikasi, dst.) — itu semua tugas
  Laravel, skrip ini murni penghubung form → endpoint.
