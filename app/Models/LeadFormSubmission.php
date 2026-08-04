<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'lead_id',
        'external_submission_id',
        'submitted_at',
        'raw_payload',
        'source',
        'consent_whatsapp',
        'processing_status',
        'rejection_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'consent_whatsapp' => 'boolean',
            'submitted_at' => 'datetime',
            'processed_at' => 'datetime',
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
}
