<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    // Slug baku dipakai Lead Intake untuk lookup/first-or-create.
    public const GOOGLE_FORM = 'google_form';

    public const REFERRAL = 'referral';

    public const MANUAL = 'manual';

    public const WHATSAPP_INBOUND = 'whatsapp_inbound';

    protected $fillable = ['name', 'slug'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
