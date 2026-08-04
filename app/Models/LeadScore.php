<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadScore extends Model
{
    protected $fillable = [
        'lead_id', 'total_score', 'previous_score', 'status_before', 'status_after',
        'computed_by', 'computed_by_user_id', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function computedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by_user_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(LeadScoreComponent::class);
    }
}
