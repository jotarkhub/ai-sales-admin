<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'lead_source_id',
        'interested_product_id',
        'assigned_admin_id',
        'external_submission_id',
        'name',
        'phone_number',
        'email',
        'city',
        'budget_estimate',
        'purchase_timeline',
        'needs_notes',
        'consent_whatsapp',
        'status',
        'current_score',
        'opted_out_at',
        'won_confirmed_by',
        'won_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_whatsapp' => 'boolean',
            'status' => LeadStatus::class,
            'current_score' => 'integer',
            'opted_out_at' => 'datetime',
            'won_confirmed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function interestedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'interested_product_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function wonConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'won_confirmed_by');
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(LeadFormSubmission::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(LeadScore::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'lead_tags')
            ->withPivot(['tagged_by', 'tagged_at'])
            ->withTimestamps();
    }

    public function isOptedOut(): bool
    {
        return $this->status === LeadStatus::OptOut || $this->opted_out_at !== null;
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(LeadFieldValue::class);
    }
}
