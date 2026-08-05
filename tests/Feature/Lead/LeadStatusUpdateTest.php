<?php

namespace Tests\Feature\Lead;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);
    }

    private function makeAgent(): User
    {
        $role = Role::create(['name' => 'Agent', 'slug' => Role::AGENT]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => $this->business->id]);
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

    public function test_staf_bisa_mengubah_status_lead_dan_tercatat_di_audit_dan_aktivitas(): void
    {
        $agent = $this->makeAgent();
        $lead = $this->makeLead();

        $response = $this->actingAs($agent)
            ->patch(route('leads.status.update', $lead), ['status' => 'contacted']);

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame('contacted', $lead->fresh()->status->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.status_changed',
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $agent->id,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'status_changed',
            'actor_id' => $agent->id,
        ]);
    }

    public function test_status_won_ditolak_lewat_endpoint_update_status_umum(): void
    {
        $agent = $this->makeAgent();
        $lead = $this->makeLead();

        $response = $this->actingAs($agent)
            ->patch(route('leads.status.update', $lead), ['status' => 'won']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('new', $lead->fresh()->status->value);
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $agent = $this->makeAgent();
        $lead = $this->makeLead();

        $response = $this->actingAs($agent)
            ->patch(route('leads.status.update', $lead), ['status' => 'bukan-status-valid']);

        $response->assertSessionHasErrors('status');
    }
}
