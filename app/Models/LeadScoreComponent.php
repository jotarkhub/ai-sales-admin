<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScoreComponent extends Model
{
    protected $fillable = ['lead_score_id', 'component_key', 'label', 'weight', 'raw_value', 'points'];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'raw_value' => 'decimal:2',
            'points' => 'decimal:2',
        ];
    }

    public function leadScore(): BelongsTo
    {
        return $this->belongsTo(LeadScore::class);
    }
}
