<?php

namespace App\Models;

use App\Enums\KnowledgeItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeItem extends Model
{
    protected $fillable = [
        'business_id', 'product_id', 'category', 'title', 'content', 'status', 'priority',
        'source', 'owner_id', 'effective_date', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => KnowledgeItemStatus::class,
            'effective_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Satu-satunya sumber knowledge yang boleh dipakai AI: published DAN masih berlaku
     * (effective_date <= sekarang <= expiry_date, atau tanpa batas tanggal).
     */
    public function scopeUsableByAi(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status', KnowledgeItemStatus::Published)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today);
            });
    }
}
