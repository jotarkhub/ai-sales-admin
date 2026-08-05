<?php

namespace App\Models;

use App\Enums\LeadFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadFieldDefinition extends Model
{
    protected $fillable = [
        'business_id', 'key', 'label', 'field_type', 'is_required', 'is_sensitive',
        'options', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => LeadFieldType::class,
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(LeadFieldValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
