<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'lead_id', 'assigned_admin_id', 'channel', 'status', 'summary',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    public function isUnderHumanControl(): bool
    {
        return $this->status === ConversationStatus::HumanTakeover;
    }
}
