<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Edit Field — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 560px; color: #111827; }
        label { display: block; font-weight: 600; margin: 1rem 0 .25rem; }
        input, textarea, select { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; }
        button { margin-top: 1.5rem; padding: .6rem 1.25rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        .hint { font-size: .8rem; color: #6b7280; margin-top: .15rem; }
    </style>
</head>
<body>
    <p><a href="{{ route('lead-fields.index') }}">&larr; Field Custom Lead</a></p>
    <h1>Edit Field: {{ $leadField->label }}</h1>
    <p class="hint">Key: <code>{{ $leadField->key }}</code> (tidak bisa diubah — dipakai di Apps Script &amp; data lead yang sudah tersimpan)</p>

    <form method="POST" action="{{ route('lead-fields.update', $leadField) }}">
        @csrf
        @method('PUT')

        <label for="label">Label Pertanyaan</label>
        <input id="label" name="label" value="{{ old('label', $leadField->label) }}" required>
        @error('label') <div style="color:#dc2626;font-size:.85rem">{{ $message }}</div> @enderror

        <label for="field_type">Tipe</label>
        <select id="field_type" name="field_type">
            @foreach (\App\Enums\LeadFieldType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('field_type', $leadField->field_type->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>

        <label><input type="checkbox" name="is_required" value="1" style="width:auto" @checked(old('is_required', $leadField->is_required))> Wajib diisi</label>
        <label><input type="checkbox" name="is_sensitive" value="1" style="width:auto" @checked(old('is_sensitive', $leadField->is_sensitive))> Data sensitif (disimpan terenkripsi)</label>

        <label for="options_text">Pilihan (khusus tipe "Pilihan", satu per baris)</label>
        <textarea id="options_text" name="options_text" rows="3">{{ old('options_text', $leadField->options ? implode("\n", $leadField->options) : '') }}</textarea>

        <label for="sort_order">Urutan Tampil</label>
        <input id="sort_order" name="sort_order" type="number" value="{{ old('sort_order', $leadField->sort_order) }}">

        <button type="submit">Simpan</button>
    </form>
</body>
</html>
