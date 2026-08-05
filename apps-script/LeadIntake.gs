/**
 * AI Sales Admin — Google Apps Script: Lead Intake Connector
 * =============================================================================================
 * Fungsi skrip ini HANYA satu: begitu Google Form disubmit, ambil jawabannya, susun jadi
 * payload terstruktur, tandatangani dengan HMAC-SHA256, lalu kirim ke endpoint Laravel
 * POST /api/v1/leads/intake (app/Http/Controllers/Api/V1/LeadIntakeController.php).
 *
 * ATURAN KERAS (jangan dilanggar saat mengedit):
 *  - TIDAK ADA API key WhatsApp atau OpenAI di file ini, dan tidak akan pernah ada.
 *  - Secret HMAC (LEAD_INTAKE_SECRET) diambil dari Script Properties, bukan ditulis di kode.
 *  - Skrip ini tidak pernah mengambil keputusan bisnis — cuma meneruskan data mentah.
 *
 * Cara setup lengkap: lihat apps-script/README.md di repo yang sama dengan file ini.
 * =============================================================================================
 */

const CONFIG = {
  // Diisi lewat Project Settings > Script Properties di editor Apps Script — JANGAN hardcode.
  ENDPOINT_URL: PropertiesService.getScriptProperties().getProperty('LEAD_INTAKE_ENDPOINT_URL'),
  SECRET: PropertiesService.getScriptProperties().getProperty('LEAD_INTAKE_SECRET'),

  // WAJIB diedit supaya PERSIS sama (case-sensitive) dengan judul pertanyaan di Google Form
  // Anda. Lihat apps-script/README.md untuk daftar pertanyaan yang disarankan.
  FIELD_MAP: {
    name: 'Nama Lengkap',
    phone_number: 'Nomor WhatsApp',
    email: 'Email',
    city: 'Kota',
    interested_product: 'Produk yang Diminati',
    budget_estimate: 'Perkiraan Anggaran',
    purchase_timeline: 'Waktu Rencana Pembelian',
    needs_notes: 'Kebutuhan / Pertanyaan',
    consent_whatsapp: 'Bersedia dihubungi lewat WhatsApp?',
  },

  // Field TAMBAHAN di luar 9 field standar di atas — dikonfigurasi lewat form builder di
  // dashboard (Pengaturan > Field Custom Lead). "key" di sebelah kiri HARUS SAMA PERSIS dengan
  // kolom "Key" yang ditampilkan di halaman itu (dibuat otomatis dari label saat field dibuat).
  // Kosongkan {} kalau bisnis Anda tidak pakai field custom.
  //
  // Contoh untuk klien konsultan LPK (pembiayaan) — sesuaikan key persis dengan yang muncul
  // di dashboard setelah field-field ini dibuat lewat form builder:
  //   CUSTOM_FIELD_MAP: {
  //     no_ktp_pemohon: 'No KTP Pemohon',
  //     domisili: 'Domisili',
  //     asal_lpk: 'Asal LPK',
  //     nominal_kebutuhan: 'Nominal Kebutuhan',
  //     nama_bapak: 'Nama Bapak',
  //     no_ktp_bapak: 'No KTP Bapak',
  //     nama_ibu: 'Nama Ibu',
  //     no_ktp_ibu: 'No KTP Ibu',
  //     nama_pemilik_jaminan: 'Nama Pemilik Jaminan',
  //     no_ktp_pemilik_jaminan: 'No KTP Pemilik Jaminan',
  //     nama_pasangan_pemilik_jaminan: 'Nama Pasangan Pemilik Jaminan',
  //     no_ktp_pasangan_pemilik_jaminan: 'No KTP Pasangan Pemilik Jaminan',
  //     kab_kota_letak_jaminan: 'Kab/Kota Letak Jaminan',
  //   },
  CUSTOM_FIELD_MAP: {},

  CONSENT_YES_VALUES: ['ya', 'yes', 'setuju', 'bersedia', 'iya'],
  LOG_SHEET_NAME: 'Intake Log',
  MAX_ATTEMPTS: 3,
  RETRY_BASE_DELAY_MS: 1000,

  // Opsional: isi alamat email admin untuk dapat notifikasi kalau pengiriman gagal total.
  // Kosongkan ('') kalau tidak perlu.
  NOTIFY_EMAIL_ON_FAILURE: '',
};

/**
 * INI FUNGSI YANG DIDAFTARKAN DI INSTALLABLE TRIGGER (Triggers > Add Trigger >
 * Event source: From spreadsheet > Event type: On form submit).
 * Simple trigger (nama onFormSubmit bawaan) TIDAK dipakai karena tidak boleh melakukan
 * panggilan jaringan keluar (UrlFetchApp) — wajib installable trigger.
 */
function onFormSubmitInstallable(e) {
  try {
    processFormSubmission_(e);
  } catch (err) {
    const message = 'Error tak terduga di onFormSubmitInstallable: ' + (err && err.stack ? err.stack : err);
    Logger.log(message);
    logResult_('(tidak diketahui)', 'error', 0, message);
    notifyFailure_(message);
  }
}

function processFormSubmission_(e) {
  if (!CONFIG.ENDPOINT_URL || !CONFIG.SECRET) {
    throw new Error('LEAD_INTAKE_ENDPOINT_URL atau LEAD_INTAKE_SECRET belum diset di Script Properties.');
  }
  if (!e || !e.namedValues || !e.range) {
    throw new Error('Event trigger tidak berisi namedValues/range — pastikan trigger di-set ke "From spreadsheet", bukan "From form".');
  }

  // Nomor baris di sheet respons stabil & unik per submission — tidak pernah dipakai ulang.
  const submissionId = 'gform-row-' + e.range.getRow();

  if (alreadyProcessed_(submissionId)) {
    Logger.log('Submission ' + submissionId + ' sudah pernah berhasil diproses, dilewati (idempotent).');
    return;
  }

  const answers = flattenNamedValues_(e.namedValues);

  const payload = {
    external_submission_id: submissionId,
    submitted_at: new Date().toISOString(),
    name: pickAnswer_(answers, CONFIG.FIELD_MAP.name),
    phone_number: pickAnswer_(answers, CONFIG.FIELD_MAP.phone_number),
    email: pickAnswer_(answers, CONFIG.FIELD_MAP.email) || null,
    interested_product: pickAnswer_(answers, CONFIG.FIELD_MAP.interested_product) || null,
    city: pickAnswer_(answers, CONFIG.FIELD_MAP.city) || null,
    budget_estimate: pickAnswer_(answers, CONFIG.FIELD_MAP.budget_estimate) || null,
    purchase_timeline: pickAnswer_(answers, CONFIG.FIELD_MAP.purchase_timeline) || null,
    needs_notes: pickAnswer_(answers, CONFIG.FIELD_MAP.needs_notes) || null,
    source: 'google_form',
    consent_whatsapp: isConsentYes_(pickAnswer_(answers, CONFIG.FIELD_MAP.consent_whatsapp)),
    raw_answers: answers,
    custom_answers: buildCustomAnswers_(answers),
  };

  sendWithRetry_(payload, submissionId);
}

/** Bangun {key: jawaban} dari CONFIG.CUSTOM_FIELD_MAP — lihat komentar di CONFIG untuk contoh. */
function buildCustomAnswers_(answers) {
  const result = {};

  Object.keys(CONFIG.CUSTOM_FIELD_MAP).forEach(function (key) {
    const questionTitle = CONFIG.CUSTOM_FIELD_MAP[key];
    const value = pickAnswer_(answers, questionTitle);

    if (value) {
      result[key] = value;
    }
  });

  return result;
}

/** Ubah {"Pertanyaan": ["jawaban"]} jadi {"Pertanyaan": "jawaban"} (checkbox sudah digabung Google Forms). */
function flattenNamedValues_(namedValues) {
  const result = {};
  Object.keys(namedValues).forEach(function (question) {
    const values = namedValues[question] || [];
    result[question] = values.join(', ').trim();
  });

  return result;
}

function pickAnswer_(answers, questionTitle) {
  if (!questionTitle) return '';
  const value = answers[questionTitle];

  return value ? String(value).trim() : '';
}

function isConsentYes_(rawValue) {
  const normalized = String(rawValue || '').trim().toLowerCase();

  return CONFIG.CONSENT_YES_VALUES.indexOf(normalized) !== -1;
}

function sendWithRetry_(payload, submissionId) {
  const body = JSON.stringify(payload);
  const signature = computeSignatureHex_(body, CONFIG.SECRET);

  const options = {
    method: 'post',
    contentType: 'application/json',
    payload: body, // string mentah — HARUS identik dengan yang ditandatangani di atas.
    headers: { 'X-Signature': 'sha256=' + signature },
    muteHttpExceptions: true,
  };

  let lastError = '';

  for (let attempt = 1; attempt <= CONFIG.MAX_ATTEMPTS; attempt++) {
    try {
      const response = UrlFetchApp.fetch(CONFIG.ENDPOINT_URL, options);
      const status = response.getResponseCode();
      const responseBody = response.getContentText();

      if (status === 201 || status === 200) {
        logResult_(submissionId, 'success', status, responseBody);

        return;
      }

      // 4xx (selain 429) tidak akan pernah berhasil walau diulang — jangan buang percobaan.
      if (status >= 400 && status < 500 && status !== 429) {
        logResult_(submissionId, 'rejected', status, responseBody);
        notifyFailure_('Lead intake ditolak server (HTTP ' + status + ') untuk ' + submissionId + ': ' + responseBody);

        return;
      }

      lastError = 'HTTP ' + status + ': ' + responseBody;
    } catch (err) {
      lastError = String(err && err.stack ? err.stack : err);
    }

    if (attempt < CONFIG.MAX_ATTEMPTS) {
      Utilities.sleep(CONFIG.RETRY_BASE_DELAY_MS * attempt);
    }
  }

  logResult_(submissionId, 'failed', 0, lastError);
  notifyFailure_('Gagal mengirim lead intake untuk ' + submissionId + ' setelah ' + CONFIG.MAX_ATTEMPTS + 'x percobaan: ' + lastError);
}

/** HMAC-SHA256 hex lowercase — harus sama persis dengan hash_hmac('sha256', $body, $secret) di PHP. */
function computeSignatureHex_(body, secret) {
  const signatureBytes = Utilities.computeHmacSha256Signature(body, secret);

  return signatureBytes
    .map(function (byte) {
      const unsigned = byte < 0 ? byte + 256 : byte;
      const hex = unsigned.toString(16);

      return hex.length === 1 ? '0' + hex : hex;
    })
    .join('');
}

/** Sheet "Intake Log" dipakai sekaligus sebagai audit trail DAN penanda anti-duplikat. */
function getOrCreateLogSheet_() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = spreadsheet.getSheetByName(CONFIG.LOG_SHEET_NAME);

  if (!sheet) {
    sheet = spreadsheet.insertSheet(CONFIG.LOG_SHEET_NAME);
    sheet.appendRow(['Waktu', 'Submission ID', 'Status', 'HTTP Status', 'Detail']);
  }

  return sheet;
}

function alreadyProcessed_(submissionId) {
  const sheet = getOrCreateLogSheet_();
  const data = sheet.getDataRange().getValues();

  for (let i = 1; i < data.length; i++) {
    if (data[i][1] === submissionId && data[i][2] === 'success') {
      return true;
    }
  }

  return false;
}

function logResult_(submissionId, status, httpStatus, detail) {
  const sheet = getOrCreateLogSheet_();
  sheet.appendRow([new Date(), submissionId, status, httpStatus, String(detail).substring(0, 2000)]);
}

function notifyFailure_(message) {
  Logger.log(message);

  if (CONFIG.NOTIFY_EMAIL_ON_FAILURE) {
    MailApp.sendEmail(
      CONFIG.NOTIFY_EMAIL_ON_FAILURE,
      '[AI Sales Admin] Lead intake gagal',
      message
    );
  }
}

/**
 * Jalankan fungsi ini SECARA MANUAL dari editor Apps Script (tombol Run, pilih fungsi ini)
 * untuk menguji koneksi ke endpoint Laravel tanpa perlu submit form sungguhan.
 */
function testConfiguration() {
  if (!CONFIG.ENDPOINT_URL || !CONFIG.SECRET) {
    Logger.log('GAGAL: LEAD_INTAKE_ENDPOINT_URL atau LEAD_INTAKE_SECRET belum diset di Script Properties.');

    return;
  }

  const testPayload = {
    external_submission_id: 'test-manual-' + new Date().getTime(),
    submitted_at: new Date().toISOString(),
    name: 'Test Konfigurasi Apps Script',
    phone_number: '081234567890',
    email: null,
    interested_product: null,
    city: 'Jakarta',
    budget_estimate: null,
    purchase_timeline: null,
    needs_notes: 'Dikirim dari testConfiguration() — aman dihapus dari database.',
    source: 'google_form',
    consent_whatsapp: false,
    raw_answers: { catatan: 'Ini payload uji dari testConfiguration()' },
    custom_answers: {},
  };

  sendWithRetry_(testPayload, testPayload.external_submission_id);
  Logger.log('Selesai. Cek sheet "' + CONFIG.LOG_SHEET_NAME + '" untuk hasilnya.');
}
