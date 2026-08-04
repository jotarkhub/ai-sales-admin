<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Konfigurasi Bisnis — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 640px; color: #111827; }
        label { display: block; font-weight: 600; margin: 1rem 0 .25rem; }
        input, textarea, select { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; font-family: inherit; }
        textarea { font-family: ui-monospace, monospace; min-height: 5rem; }
        .hint { font-size: .8rem; color: #6b7280; margin-top: .15rem; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .15rem; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        button { margin-top: 1.5rem; padding: .6rem 1.25rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
    </style>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>
    <h1>Konfigurasi Bisnis</h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('business.update') }}">
        @csrf
        @method('PUT')

        <label for="name">Nama Bisnis</label>
        <input id="name" name="name" value="{{ old('name', $business->name) }}" required>
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="assistant_name">Nama AI Assistant</label>
        <input id="assistant_name" name="assistant_name" value="{{ old('assistant_name', $business->assistant_name) }}">

        <label for="assistant_identity">Identitas AI</label>
        <textarea id="assistant_identity" name="assistant_identity">{{ old('assistant_identity', $business->assistant_identity) }}</textarea>

        <label for="whatsapp_number">Nomor WhatsApp Business</label>
        <input id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $business->whatsapp_number) }}" placeholder="+62812xxxxxxxx (CREDENTIAL_REQUIRED sampai WhatsApp aktif)">

        <label for="timezone">Zona Waktu</label>
        <input id="timezone" name="timezone" value="{{ old('timezone', $business->timezone) }}" required>
        @error('timezone') <div class="error">{{ $message }}</div> @enderror

        <label for="payment_terms">Syarat Pembayaran</label>
        <textarea id="payment_terms" name="payment_terms">{{ old('payment_terms', $business->payment_terms) }}</textarea>

        <label for="refund_policy">Kebijakan Refund</label>
        <textarea id="refund_policy" name="refund_policy">{{ old('refund_policy', $business->refund_policy) }}</textarea>

        <label for="opt_out_instructions">Instruksi Penghentian Pesan (Opt-out)</label>
        <textarea id="opt_out_instructions" name="opt_out_instructions">{{ old('opt_out_instructions', $business->opt_out_instructions) }}</textarea>

        <label for="operating_hours">Jam Operasional (JSON)</label>
        <textarea id="operating_hours" name="operating_hours">{{ old('operating_hours', $business->operating_hours ? json_encode($business->operating_hours, JSON_PRETTY_PRINT) : '') }}</textarea>
        <div class="hint">Contoh: {"senin_jumat": "09:00-17:00", "sabtu": "09:00-13:00", "minggu": "tutup"}</div>
        @error('operating_hours') <div class="error">{{ $message }}</div> @enderror

        <label for="ai_authority_limit">Batas Kewenangan AI (JSON)</label>
        <textarea id="ai_authority_limit" name="ai_authority_limit">{{ old('ai_authority_limit', $business->ai_authority_limit ? json_encode($business->ai_authority_limit, JSON_PRETTY_PRINT) : '') }}</textarea>
        <div class="hint">Contoh: {"max_discount_percent": 0, "max_transaction_value": 5000000}</div>
        @error('ai_authority_limit') <div class="error">{{ $message }}</div> @enderror

        <label for="escalation_rules">Aturan Eskalasi (JSON)</label>
        <textarea id="escalation_rules" name="escalation_rules">{{ old('escalation_rules', $business->escalation_rules ? json_encode($business->escalation_rules, JSON_PRETTY_PRINT) : '') }}</textarea>
        @error('escalation_rules') <div class="error">{{ $message }}</div> @enderror

        <label for="message_templates">Template Pesan (JSON)</label>
        <textarea id="message_templates" name="message_templates">{{ old('message_templates', $business->message_templates ? json_encode($business->message_templates, JSON_PRETTY_PRINT) : '') }}</textarea>
        @error('message_templates') <div class="error">{{ $message }}</div> @enderror

        <label for="follow_up_schedule">Jadwal Follow-up (JSON)</label>
        <textarea id="follow_up_schedule" name="follow_up_schedule">{{ old('follow_up_schedule', $business->follow_up_schedule ? json_encode($business->follow_up_schedule, JSON_PRETTY_PRINT) : '') }}</textarea>
        @error('follow_up_schedule') <div class="error">{{ $message }}</div> @enderror

        <label for="is_active">Status</label>
        <select id="is_active" name="is_active">
            <option value="1" @selected(old('is_active', $business->is_active))>Aktif</option>
            <option value="0" @selected(! old('is_active', $business->is_active))>Nonaktif</option>
        </select>

        <button type="submit">Simpan</button>
    </form>
</body>
</html>
