<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Bisnis — Platform Owner — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 960px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e5e7eb; font-size: .9rem; }
        .badge { display: inline-block; padding: .1rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .badge.active { background: #ecfdf5; color: #047857; }
        .badge.inactive { background: #fef2f2; color: #b91c1c; }
        .top { display: flex; justify-content: space-between; align-items: center; }
        form.logout button { padding: .4rem .9rem; background: #f3f4f6; color: #111827; border: 1px solid #d1d5db; border-radius: .375rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="top">
        <h1>Daftar Bisnis (Tenant)</h1>
        <form class="logout" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </div>

    <p>Login sebagai platform owner — {{ $businesses->count() }} bisnis terdaftar. Menambah bisnis baru + kelola kredensial WhatsApp per klien menyusul di fase berikutnya.</p>

    <table>
        <thead>
            <tr><th>Nama Bisnis</th><th>Status</th><th>Jumlah Staf</th><th>Jumlah Lead</th><th>Zona Waktu</th></tr>
        </thead>
        <tbody>
            @forelse ($businesses as $business)
                <tr>
                    <td>{{ $business->name }}</td>
                    <td>
                        <span class="badge {{ $business->is_active ? 'active' : 'inactive' }}">
                            {{ $business->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td>{{ $business->users_count }}</td>
                    <td>{{ $business->leads_count }}</td>
                    <td>{{ $business->timezone ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada bisnis terdaftar.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
