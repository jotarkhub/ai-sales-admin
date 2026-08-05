<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Knowledge Base — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 860px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e5e7eb; font-size: .88rem; vertical-align: top; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: .1rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .badge-draft { background: #f3f4f6; color: #6b7280; }
        .badge-published { background: #ecfdf5; color: #047857; }
        label { display: block; font-weight: 600; margin: 1rem 0 .25rem; }
        input, textarea, select { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; font-family: inherit; }
        textarea { min-height: 6rem; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .15rem; }
        .row { display: flex; gap: 1rem; }
        .row > div { flex: 1; }
        button { margin-top: 1rem; padding: .5rem 1rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        .btn-link { background: none; color: #4f46e5; padding: 0; text-decoration: underline; cursor: pointer; border: none; font-size: .85rem; }
        fieldset { border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem; margin-top: 2rem; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>
    <h1>Knowledge Base — {{ $business->name }}</h1>
    <p>Sumber jawaban yang boleh dipakai AI. Hanya item berstatus <strong>Published</strong> (dan masih berlaku sesuai tanggal) yang benar-benar dipakai — item draft tidak pernah keluar ke pelanggan.</p>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <table>
        <thead>
            <tr><th>Kategori</th><th>Judul</th><th>Status</th><th>Prioritas</th><th>Berlaku</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->category }}</td>
                    <td>{{ $item->title }}</td>
                    <td>
                        <span class="badge {{ $item->status->value === 'published' ? 'badge-published' : 'badge-draft' }}">
                            {{ ucfirst($item->status->value) }}
                        </span>
                    </td>
                    <td>{{ $item->priority }}</td>
                    <td>
                        {{ $item->effective_date?->format('d M Y') ?? '—' }}
                        &ndash;
                        {{ $item->expiry_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td>
                        <a href="{{ route('knowledge.edit', $item) }}">Edit</a>
                        &middot;
                        <form method="POST" action="{{ route('knowledge.toggle-publish', $item) }}" style="display:inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-link">{{ $item->status->value === 'published' ? 'Tarik ke Draft' : 'Publikasikan' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada knowledge item.</td></tr>
            @endforelse
        </tbody>
    </table>

    <fieldset>
        <legend>Tambah Knowledge Item</legend>
        <form method="POST" action="{{ route('knowledge.store') }}">
            @csrf

            <div class="row">
                <div>
                    <label for="category">Kategori</label>
                    <input id="category" name="category" value="{{ old('category') }}" required placeholder="Contoh: harga, promo, syarat, faq">
                    @error('category') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="title">Judul</label>
                    <input id="title" name="title" value="{{ old('title') }}" required>
                    @error('title') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <label for="content">Isi Jawaban</label>
            <textarea id="content" name="content" required>{{ old('content') }}</textarea>
            @error('content') <div class="error">{{ $message }}</div> @enderror

            <div class="row">
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected(old('status', 'draft') === $s->value)>{{ ucfirst($s->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="priority">Prioritas</label>
                    <input id="priority" name="priority" type="number" min="0" value="{{ old('priority', 0) }}">
                </div>
                <div>
                    <label for="source">Sumber</label>
                    <input id="source" name="source" value="{{ old('source') }}" placeholder="opsional">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="effective_date">Berlaku Mulai</label>
                    <input id="effective_date" name="effective_date" type="date" value="{{ old('effective_date') }}">
                </div>
                <div>
                    <label for="expiry_date">Berlaku Sampai</label>
                    <input id="expiry_date" name="expiry_date" type="date" value="{{ old('expiry_date') }}">
                    @error('expiry_date') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit">Tambah</button>
        </form>
    </fieldset>
</body>
</html>
