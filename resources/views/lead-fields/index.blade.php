<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Field Custom Lead — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 780px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e5e7eb; font-size: .9rem; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: .1rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .badge-sensitive { background: #fef2f2; color: #b91c1c; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        label { display: block; font-weight: 600; margin: 1rem 0 .25rem; }
        input, textarea, select { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        .row { display: flex; gap: 1rem; }
        .row > div { flex: 1; }
        button { margin-top: 1rem; padding: .5rem 1rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        .btn-link { background: none; color: #4f46e5; padding: 0; text-decoration: underline; cursor: pointer; border: none; font-size: .85rem; }
        fieldset { border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem; margin-top: 2rem; }
    </style>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a> · <a href="{{ route('business.edit') }}">Konfigurasi Bisnis</a></p>
    <h1>Field Custom Lead — {{ $business->name }}</h1>
    <p>Field tambahan di luar field standar (nama, WA, produk, dst.) yang muncul di form intake bisnis ini. Contoh: bisnis pembiayaan bisa menambah "No KTP Pemohon", "Nama Bapak", dst.</p>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <table>
        <thead>
            <tr><th>Label</th><th>Key</th><th>Tipe</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($fields as $field)
                <tr>
                    <td>{{ $field->label }}</td>
                    <td><code>{{ $field->key }}</code></td>
                    <td>
                        {{ $field->field_type->label() }}
                        @if ($field->is_required) <span class="badge">wajib</span> @endif
                        @if ($field->is_sensitive) <span class="badge badge-sensitive">sensitif</span> @endif
                    </td>
                    <td>
                        @if ($field->is_active) Aktif @else <span class="badge badge-inactive">Nonaktif</span> @endif
                    </td>
                    <td>
                        <a href="{{ route('lead-fields.edit', $field) }}">Edit</a>
                        &middot;
                        <form method="POST" action="{{ route('lead-fields.toggle', $field) }}" style="display:inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-link">{{ $field->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada field custom.</td></tr>
            @endforelse
        </tbody>
    </table>

    <fieldset>
        <legend>Tambah Field Baru</legend>
        <form method="POST" action="{{ route('lead-fields.store') }}">
            @csrf

            <label for="label">Label Pertanyaan</label>
            <input id="label" name="label" value="{{ old('label') }}" required placeholder="Contoh: No KTP Pemohon">
            @error('label') <div style="color:#dc2626;font-size:.85rem">{{ $message }}</div> @enderror

            <div class="row">
                <div>
                    <label for="field_type">Tipe</label>
                    <select id="field_type" name="field_type">
                        @foreach (\App\Enums\LeadFieldType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('field_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <label><input type="checkbox" name="is_required" value="1" style="width:auto" @checked(old('is_required'))> Wajib diisi</label>
                    <label><input type="checkbox" name="is_sensitive" value="1" style="width:auto" @checked(old('is_sensitive'))> Data sensitif (mis. NIK/KTP — disimpan terenkripsi)</label>
                </div>
            </div>

            <label for="options_text">Pilihan (khusus tipe "Pilihan", satu per baris)</label>
            <textarea id="options_text" name="options_text" rows="3">{{ old('options_text') }}</textarea>

            <button type="submit">Tambah Field</button>
        </form>
    </fieldset>
</body>
</html>
