<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'description', 'price', 'price_unit', 'category', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'interested_product_id');
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }
}
