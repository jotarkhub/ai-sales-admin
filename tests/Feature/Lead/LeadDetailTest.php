<?php

namespace Tests\Feature\Lead;

use App\Models\Business;
use App\Models\Lead;
use App\Models\LeadFieldDefinition;
use App\Models\LeadFieldValue;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDetailTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $otherBusiness;

    private LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->otherBusiness = Business::create([
            'name' => 'Bisnis Lain (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => false,
        ]);

        $this->source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);
    }

    private function makeAgent(?Business $business = null): User
    {
        $role = Role::create(['name' => 'Agent', 'slug' => Role::AGENT]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => ($business ?? $this->business)->id]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'business_id' => $this->business->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'new',
        ], $overrides));
    }

    public function test_staf_bisa_melihat_detail_lead_di_bisnisnya(): void
    {
        $agent = $this->makeAgent();
        $lead = $this->makeLead();

        $response = $this->actingAs($agent)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Budi Pengujian');
    }

    public function test_field_custom_sensitif_ditampilkan_terdekripsi_di_detail(): void
    {
        $agent = $this->makeAgent();
        $lead = $this->makeLead();
        $definition = LeadFieldDefinition::create([
            'business_id' => $this->business->id,
            'key' => 'no_ktp_pemohon',
            'label' => 'No KTP Pemohon',
            'field_type' => 'nik',
            'is_required' => true,
            'is_sensitive' => true,
            'is_active' => true,
        ]);
        LeadFieldValue::create(array_merge(
            ['lead_id' => $lead->id, 'lead_field_definition_id' => $definition->id],
            LeadFieldValue::makeFor($definition, '3201019001010001'),
        ));

        $response = $this->actingAs($agent)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('3201019001010001');
    }

    public function test_staf_dari_bisnis_lain_tidak_bisa_melihat_lead_ini(): void
    {
        $agentLainBisnis = $this->makeAgent($this->otherBusiness);
        $lead = $this->makeLead();

        $response = $this->actingAs($agentLainBisnis)->get(route('leads.show', $lead));

        $response->assertForbidden();
    }
}
