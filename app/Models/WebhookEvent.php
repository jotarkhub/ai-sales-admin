<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_GOOGLE_FORM = 'google_form';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DUPLICATE = 'duplicate';

    protected $fillable = [
        'source', 'event_type', 'external_event_id', 'signature_valid', 'payload', 'status',
        'received_at', 'processed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
