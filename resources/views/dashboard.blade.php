<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Dashboard — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #111827; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: .15rem .5rem; border-radius: .25rem; font-size: .75rem; }
    </style>
</head>
<body>
    <p><span class="badge">Data Pengujian</span></p>
    <h1>Selamat datang, {{ $user->name }}</h1>
    <p>Peran: {{ $user->roles->pluck('name')->join(', ') ?: 'Belum ada peran' }}</p>
    <p>Dashboard lengkap (lead, percakapan, follow-up, dst.) belum dibangun — ini halaman
       placeholder untuk membuktikan login &amp; role-based authorization berfungsi (Fase 2).
       Lihat <code>docs/STATUS.md</code>.</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</body>
</html>
