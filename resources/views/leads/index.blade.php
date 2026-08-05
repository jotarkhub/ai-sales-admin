<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Lead — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 960px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e5e7eb; font-size: .9rem; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: .1rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .filters { display: flex; gap: .75rem; align-items: end; margin: 1rem 0; }
        .filters label { display: block; font-weight: 600; font-size: .8rem; margin-bottom: .25rem; }
        .filters input, .filters select { padding: .4rem; border: 1px solid #d1d5db; border-radius: .375rem; }
        .filters button { padding: .45rem 1rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        .pagination { margin-top: 1rem; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>
    <h1>Daftar Lead — {{ $business->name }}</h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('leads.index') }}" class="filters">
        <div>
            <label for="q">Cari (nama/WA/email)</label>
            <input id="q" name="q" value="{{ $search }}">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected($currentStatus === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Filter</button>
    </form>

    <table>
        <thead>
            <tr><th>Nama</th><th>WA</th><th>Status</th><th>Sumber</th><th>Produk</th><th>Skor</th><th>Ditugaskan</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td>{{ $lead->name }}</td>
                    <td>{{ $lead->phone_number }}</td>
                    <td><span class="badge">{{ $lead->status->label() }}</span></td>
                    <td>{{ $lead->leadSource?->name ?? '—' }}</td>
                    <td>{{ $lead->interestedProduct?->name ?? '—' }}</td>
                    <td>{{ $lead->current_score ?? '—' }}</td>
                    <td>{{ $lead->assignedAdmin?->name ?? '—' }}</td>
                    <td><a href="{{ route('leads.show', $lead) }}">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="8">Belum ada lead.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">{{ $leads->links() }}</div>
</body>
</html>
