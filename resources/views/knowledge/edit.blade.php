<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Edit — {{ $item->title }} — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 640px; color: #111827; }
        label { display: block; font-weight: 600; margin: 1rem 0 .25rem; }
        input, textarea, select { width: 100%; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; font-family: inherit; }
        textarea { min-height: 8rem; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .15rem; }
        .row { display: flex; gap: 1rem; }
        .row > div { flex: 1; }
        button { margin-top: 1.5rem; padding: .6rem 1.25rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('knowledge.index') }}">&larr; Knowledge Base</a></p>
    <h1>Edit Knowledge Item</h1>

    <form method="POST" action="{{ route('knowledge.update', $item) }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div>
                <label for="category">Kategori</label>
                <input id="category" name="category" value="{{ old('category', $item->category) }}" required>
                @error('category') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="title">Judul</label>
                <input id="title" name="title" value="{{ old('title', $item->title) }}" required>
                @error('title') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <label for="content">Isi Jawaban</label>
        <textarea id="content" name="content" required>{{ old('content', $item->content) }}</textarea>
        @error('content') <div class="error">{{ $message }}</div> @enderror

        <div class="row">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    @foreach (\App\Enums\KnowledgeItemStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(old('status', $item->status->value) === $s->value)>{{ ucfirst($s->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="priority">Prioritas</label>
                <input id="priority" name="priority" type="number" min="0" value="{{ old('priority', $item->priority) }}">
            </div>
            <div>
                <label for="source">Sumber</label>
                <input id="source" name="source" value="{{ old('source', $item->source) }}">
            </div>
        </div>

        <div class="row">
            <div>
                <label for="effective_date">Berlaku Mulai</label>
                <input id="effective_date" name="effective_date" type="date" value="{{ old('effective_date', $item->effective_date?->toDateString()) }}">
            </div>
            <div>
                <label for="expiry_date">Berlaku Sampai</label>
                <input id="expiry_date" name="expiry_date" type="date" value="{{ old('expiry_date', $item->expiry_date?->toDateString()) }}">
                @error('expiry_date') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <button type="submit">Simpan</button>
    </form>
</body>
</html>
