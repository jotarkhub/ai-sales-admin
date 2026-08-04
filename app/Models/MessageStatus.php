<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageStatus extends Model
{
    protected $fillable = ['message_id', 'status', 'raw_payload', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
