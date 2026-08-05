<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Percakapan — {{ $conversation->lead->name }} — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 720px; color: #111827; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; padding: .15rem .5rem; border-radius: .25rem; font-size: .75rem; }
        .badge-takeover { background: #fef3c7; color: #92400e; }
        .badge-closed { background: #f3f4f6; color: #6b7280; }
        .status { background: #ecfdf5; color: #047857; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        .message { max-width: 75%; margin: .5rem 0; padding: .6rem .8rem; border-radius: .75rem; font-size: .9rem; }
        .message.inbound { background: #f3f4f6; margin-right: auto; }
        .message.outbound { background: #e0e7ff; margin-left: auto; text-align: right; }
        .message .meta { font-size: .7rem; color: #6b7280; margin-top: .25rem; }
        button { padding: .5rem 1rem; border-radius: .375rem; border: none; cursor: pointer; color: #fff; }
        .btn-takeover { background: #b45309; }
        .btn-release { background: #4f46e5; }
        section { margin-top: 1.5rem; }
        .escalation { border: 1px solid #fca5a5; background: #fef2f2; padding: .5rem .75rem; border-radius: .375rem; margin-bottom: .5rem; font-size: .85rem; }
        a { color: #4f46e5; }
    </style>
</head>
<body>
    <p><a href="{{ route('leads.show', $conversation->lead) }}">&larr; {{ $conversation->lead->name }}</a></p>
    <h1>Percakapan
        <span class="badge
            @if ($conversation->status->value === 'human_takeover') badge-takeover
            @elseif ($conversation->status->value === 'closed') badge-closed
            @endif">{{ $conversation->status->label() }}</span>
    </h1>
    <p>Kanal: {{ $conversation->channel }} &middot; Ditugaskan ke: {{ $conversation->assignedAdmin?->name ?? '—' }}</p>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if ($conversation->status->value === 'ai_active')
        <form method="POST" action="{{ route('conversations.takeover', $conversation) }}">
            @csrf
            <button type="submit" class="btn-takeover">Ambil Alih (Take Over)</button>
        </form>
    @elseif ($conversation->status->value === 'human_takeover')
        <form method="POST" action="{{ route('conversations.release', $conversation) }}">
            @csrf
            <button type="submit" class="btn-release">Kembalikan ke AI</button>
        </form>
    @endif

    @if ($conversation->escalations->isNotEmpty())
        <section>
            <h2>Eskalasi</h2>
            @foreach ($conversation->escalations as $escalation)
                <div class="escalation">
                    <strong>{{ $escalation->reason }}</strong> — status: {{ $escalation->status }}
                    @if ($escalation->reason_detail)<br>{{ $escalation->reason_detail }}@endif
                    @if ($escalation->claimedBy)<br>Diklaim oleh {{ $escalation->claimedBy->name }}@endif
                </div>
            @endforeach
        </section>
    @endif

    <section>
        <h2>Riwayat Pesan</h2>
        @forelse ($conversation->messages as $message)
            <div class="message {{ $message->direction }}">
                <div>{{ $message->body }}</div>
                <div class="meta">
                    {{ $message->direction === 'inbound' ? 'Pelanggan' : ($message->sender_type ?? 'Terkirim') }}
                    &middot; {{ $message->created_at?->format('d M Y H:i') }}
                </div>
            </div>
        @empty
            <p>Belum ada pesan (menunggu integrasi WhatsApp Fase 3 — CREDENTIAL_REQUIRED).</p>
        @endforelse
    </section>
</body>
</html>
