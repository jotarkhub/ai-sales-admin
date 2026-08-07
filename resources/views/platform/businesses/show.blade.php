<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $business->name }} — Platform Owner — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 720px; color: #111827; }
        .badge { display: inline-block; padding: .1rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .badge.configured { background: #ecfdf5; color: #047857; }
        .badge.missing { background: #fef2f2; color: #b91c1c; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: .25rem; }
        .field input { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; }
        .field .hint { font-size: .8rem; color: #6b7280; margin-top: .25rem; }
        .field .error { font-size: .8rem; color: #b91c1c; margin-top: .25rem; }
        button { padding: .5rem 1.25rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        th, td { text-align: left; padding: .4rem; border-bottom: 1px solid #e5e7eb; font-size: .85rem; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('platform.businesses.index') }}">&larr; Daftar Bisnis</a></p>
    <h1>{{ $business->name }}</h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <h2>Status Kredensial WhatsApp</h2>
    <table>
        <thead><tr><th>Field</th><th>Status</th><th>Terakhir diubah</th></tr></thead>
        <tbody>
            @foreach ($credentialKeys as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>
                        <span class="badge {{ $credentialStatus[$key]['configured'] ? 'configured' : 'missing' }}">
                            {{ $credentialStatus[$key]['configured'] ? 'Terisi' : 'Belum diisi' }}
                        </span>
                    </td>
                    <td>{{ $credentialStatus[$key]['updated_at']?->diffForHumans() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Perbarui Kredensial WhatsApp</h2>
    <p class="hint">Nilai yang sudah tersimpan tidak pernah ditampilkan lagi (keamanan). Kosongkan
        field yang tidak ingin diubah — hanya field yang diisi di sini yang akan diperbarui.
        Sumbernya: App Meta milik bisnis ini sendiri, lihat panduan pendaftaran di riwayat percakapan.</p>

    <form method="POST" action="{{ route('platform.businesses.credentials.whatsapp.update', $business) }}">
        @csrf
        @method('PUT')

        @foreach ($credentialKeys as $key => $label)
            <div class="field">
                <label for="{{ $key }}">{{ $label }}</label>
                <input type="text" id="{{ $key }}" name="{{ $key }}" autocomplete="off" placeholder="{{ $credentialStatus[$key]['configured'] ? '•••••••• (sudah terisi, kosongkan jika tidak diubah)' : 'Belum diisi' }}">
                @error($key)
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
        @endforeach

        <button type="submit">Simpan</button>
    </form>
</body>
</html>
