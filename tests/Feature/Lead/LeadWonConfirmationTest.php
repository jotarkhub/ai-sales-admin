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

class LeadWonConfirmationTest extends TestCase
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

    private function makeUserWithRole(string $roleSlug): User
    {
        $role = Role::create(['name' => ucfirst($roleSlug), 'slug' => $roleSlug]);
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
            'status' => 'negotiating',
        ], $overrides));
    }

    public function test_admin_bisa_konfirmasi_won_dan_tercatat_lengkap(): void
    {
        $admin = $this->makeUserWithRole(Role::ADMIN);
        $lead = $this->makeLead();

        $response = $this->actingAs($admin)->post(route('leads.confirm-won', $lead));

        $response->assertRedirect(route('leads.show', $lead));

        $lead->refresh();
        $this->assertSame('won', $lead->status->value);
        $this->assertSame($admin->id, $lead->won_confirmed_by);
        $this->assertNotNull($lead->won_confirmed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.won_confirmed',
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $admin->id,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'won_confirmed',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_supervisor_bisa_konfirmasi_won(): void
    {
        $supervisor = $this->makeUserWithRole(Role::SUPERVISOR);
        $lead = $this->makeLead();

        $response = $this->actingAs($supervisor)->post(route('leads.confirm-won', $lead));

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame('won', $lead->fresh()->status->value);
    }

    public function test_agent_tidak_bisa_konfirmasi_won(): void
    {
        $agent = $this->makeUserWithRole(Role::AGENT);
        $lead = $this->makeLead();

        $response = $this->actingAs($agent)->post(route('leads.confirm-won', $lead));

        $response->assertForbidden();
        $this->assertSame('negotiating', $lead->fresh()->status->value);
    }

    public function test_konfirmasi_won_dua_kali_tidak_menimpa_data_konfirmasi_pertama(): void
    {
        $admin = $this->makeUserWithRole(Role::ADMIN);
        $lead = $this->makeLead();

        $this->actingAs($admin)->post(route('leads.confirm-won', $lead));
        $firstConfirmedAt = $lead->fresh()->won_confirmed_at;

        $response = $this->actingAs($admin)->post(route('leads.confirm-won', $lead));

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame($firstConfirmedAt->toDateTimeString(), $lead->fresh()->won_confirmed_at->toDateTimeString());
    }
}
