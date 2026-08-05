<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'assistant_name',
        'assistant_identity',
        'whatsapp_number',
        'timezone',
        'operating_hours',
        'payment_terms',
        'refund_policy',
        'ai_authority_limit',
        'escalation_rules',
        'message_templates',
        'follow_up_schedule',
        'opt_out_instructions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
            'ai_authority_limit' => 'array',
            'escalation_rules' => 'array',
            'message_templates' => 'array',
            'follow_up_schedule' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(KnowledgeItem::class);
    }

    public function promptVersions(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    public function integrationCredentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function leadFieldDefinitions(): HasMany
    {
        return $this->hasMany(LeadFieldDefinition::class);
    }
}
