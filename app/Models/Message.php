<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'conversation_id', 'lead_id', 'sent_by', 'ai_run_id', 'direction', 'sender_type',
        'whatsapp_message_id', 'template_name', 'body', 'media_type', 'media_url',
        'latest_status',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(MessageStatus::class);
    }
}
