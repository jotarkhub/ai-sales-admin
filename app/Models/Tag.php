<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['business_id', 'name', 'slug', 'color'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_tags')
            ->withPivot(['tagged_by', 'tagged_at'])
            ->withTimestamps();
    }
}
