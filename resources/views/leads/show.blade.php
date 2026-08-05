<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $lead->name }} — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 860px; color: #111827; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem 1.5rem; margin: 1rem 0; }
        .grid dt { font-weight: 600; font-size: .8rem; color: #6b7280; }
        .grid dd { margin: 0 0 .5rem; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: .1rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .badge-won { background: #ecfdf5; color: #047857; }
        .badge-sensitive { background: #fef2f2; color: #b91c1c; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .15rem; }
        section { margin-top: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .4rem .5rem; border-bottom: 1px solid #e5e7eb; font-size: .88rem; }
        select, button { padding: .45rem .75rem; border-radius: .375rem; border: 1px solid #d1d5db; }
        button { background: #4f46e5; color: #fff; border: none; cursor: pointer; }
        .btn-won { background: #047857; }
        form.inline { display: inline-flex; gap: .5rem; align-items: center; }
        .activity { border-left: 2px solid #e5e7eb; padding-left: .75rem; margin-bottom: .75rem; }
        .activity .meta { font-size: .75rem; color: #6b7280; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('leads.index') }}">&larr; Daftar Lead</a></p>
    <h1>{{ $lead->name }}
        <span class="badge {{ $lead->status->value === 'won' ? 'badge-won' : '' }}">{{ $lead->status->label() }}</span>
    </h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <dl class="grid">
        <div><dt>WhatsApp</dt><dd>{{ $lead->phone_number }}</dd></div>
        <div><dt>Email</dt><dd>{{ $lead->email ?? '—' }}</dd></div>
        <div><dt>Kota</dt><dd>{{ $lead->city ?? '—' }}</dd></div>
        <div><dt>Sumber</dt><dd>{{ $lead->leadSource?->name ?? '—' }}</dd></div>
        <div><dt>Produk Diminati</dt><dd>{{ $lead->interestedProduct?->name ?? '—' }}</dd></div>
        <div><dt>Estimasi Budget</dt><dd>{{ $lead->budget_estimate ?? '—' }}</dd></div>
        <div><dt>Timeline Pembelian</dt><dd>{{ $lead->purchase_timeline ?? '—' }}</dd></div>
        <div><dt>Skor Saat Ini</dt><dd>{{ $lead->current_score ?? '—' }}</dd></div>
        <div><dt>Ditugaskan ke</dt><dd>{{ $lead->assignedAdmin?->name ?? '—' }}</dd></div>
        <div><dt>Consent WhatsApp</dt><dd>{{ $lead->consent_whatsapp ? 'Ya' : 'Tidak' }}</dd></div>
    </dl>

    @if ($lead->needs_notes)
        <p><strong>Catatan Kebutuhan:</strong> {{ $lead->needs_notes }}</p>
    @endif

    @if ($lead->status->value === 'won')
        <p class="badge badge-won">Won dikonfirmasi oleh {{ $lead->wonConfirmedBy?->name ?? '—' }} pada {{ $lead->won_confirmed_at?->format('d M Y H:i') }}</p>
    @endif

    <section>
        <h2>Ubah Status</h2>
        <form method="POST" action="{{ route('leads.status.update', $lead) }}" class="inline">
            @csrf
            @method('PATCH')
            <select name="status">
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <button type="submit">Simpan</button>
        </form>
        @error('status') <div class="error">{{ $message }}</div> @enderror

        @if ($canConfirmWon && $lead->status->value !== 'won')
            <form method="POST" action="{{ route('leads.confirm-won', $lead) }}" style="margin-top: .75rem;"
                  onsubmit="return confirm('Konfirmasi lead ini sebagai WON? Aksi ini final dan tercatat di audit log.');">
                @csrf
                <button type="submit" class="btn-won">Konfirmasi Won</button>
            </form>
        @endif
    </section>

    @if ($lead->fieldValues->isNotEmpty())
        <section>
            <h2>Field Custom</h2>
            <table>
                <thead><tr><th>Label</th><th>Nilai</th></tr></thead>
                <tbody>
                    @foreach ($lead->fieldValues as $value)
                        <tr>
                            <td>
                                {{ $value->definition->label }}
                                @if ($value->definition->is_sensitive) <span class="badge badge-sensitive">sensitif</span> @endif
                            </td>
                            <td>{{ $value->displayValue() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($lead->tags->isNotEmpty())
        <section>
            <h2>Tag</h2>
            @foreach ($lead->tags as $tag)
                <span class="badge">{{ $tag->name }}</span>
            @endforeach
        </section>
    @endif

    <section>
        <h2>Percakapan</h2>
        @forelse ($lead->conversations as $conversation)
            <p>
                <span class="badge">{{ $conversation->status->label() }}</span>
                {{ $conversation->channel }} — pesan terakhir: {{ $conversation->last_message_at?->format('d M Y H:i') ?? 'belum ada' }}
            </p>
        @empty
            <p>Belum ada percakapan (menunggu integrasi WhatsApp Fase 3).</p>
        @endforelse
    </section>

    <section>
        <h2>Riwayat Aktivitas</h2>
        @forelse ($lead->activities as $activity)
            <div class="activity">
                <div>{{ $activity->description }}</div>
                <div class="meta">{{ $activity->created_at?->format('d M Y H:i') }} &middot; {{ $activity->type }}</div>
            </div>
        @empty
            <p>Belum ada aktivitas.</p>
        @endforelse
    </section>
</body>
</html>
