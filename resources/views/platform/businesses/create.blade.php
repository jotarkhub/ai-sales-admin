<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tambah Bisnis Baru — Platform Owner — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 560px; color: #111827; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: .25rem; }
        .field input { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; }
        .field .hint { font-size: .8rem; color: #6b7280; margin-top: .25rem; }
        .field .error { font-size: .8rem; color: #b91c1c; margin-top: .25rem; }
        fieldset { border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem; margin: 1.5rem 0; }
        legend { font-weight: 600; padding: 0 .5rem; }
        button { padding: .5rem 1.25rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('platform.businesses.index') }}">&larr; Daftar Bisnis</a></p>
    <h1>Tambah Bisnis Baru</h1>
    <p class="hint">Kredensial WhatsApp klien diisi belakangan dari halaman bisnis setelah App
        Meta mereka siap — form ini cuma bikin bisnis + akun admin pertamanya.</p>

    <form method="POST" action="{{ route('platform.businesses.store') }}">
        @csrf

        <fieldset>
            <legend>Data Bisnis</legend>

            <div class="field">
                <label for="name">Nama Bisnis</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="timezone">Zona Waktu</label>
                <input type="text" id="timezone" name="timezone" value="{{ old('timezone', 'Asia/Jakarta') }}" required>
                <div class="hint">Contoh: Asia/Jakarta, Asia/Makassar, Asia/Jayapura.</div>
                @error('timezone')<div class="error">{{ $message }}</div>@enderror
            </div>
        </fieldset>

        <fieldset>
            <legend>Akun Admin Pertama</legend>

            <div class="field">
                <label for="admin_name">Nama</label>
                <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required>
                @error('admin_name')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="admin_email">Email</label>
                <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required autocomplete="off">
                @error('admin_email')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="admin_password">Password Awal</label>
                <input type="password" id="admin_password" name="admin_password" required minlength="8" autocomplete="new-password">
                <div class="hint">Minimal 8 karakter. Sampaikan ke klien lewat jalur aman —
                    tidak ditampilkan lagi setelah ini.</div>
                @error('admin_password')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="admin_password_confirmation">Konfirmasi Password</label>
                <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
        </fieldset>

        <button type="submit">Buat Bisnis</button>
    </form>
</body>
</html>
