<?php

namespace Tests\Feature\Lead;

use App\Models\Business;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadListTest extends TestCase
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

    public function test_staf_aktif_bisa_melihat_daftar_lead(): void
    {
        $agent = $this->makeAgent();
        $this->makeLead(['name' => 'Budi Satu']);
        $this->makeLead(['name' => 'Citra Dua', 'phone_number' => '+628123456790']);

        $response = $this->actingAs($agent)->get(route('leads.index'));

        $response->assertOk();
        $response->assertSee('Budi Satu');
        $response->assertSee('Citra Dua');
    }

    public function test_filter_status_hanya_menampilkan_lead_yang_cocok(): void
    {
        $agent = $this->makeAgent();
        $this->makeLead(['name' => 'Lead Baru', 'status' => 'new']);
        $this->makeLead(['name' => 'Lead Kualifikasi', 'phone_number' => '+628123456791', 'status' => 'qualified']);

        $response = $this->actingAs($agent)->get(route('leads.index', ['status' => 'qualified']));

        $response->assertOk();
        $response->assertSee('Lead Kualifikasi');
        $response->assertDontSee('Lead Baru');
    }

    public function test_pencarian_nama_bekerja(): void
    {
        $agent = $this->makeAgent();
        $this->makeLead(['name' => 'Ahmad Ridwan']);
        $this->makeLead(['name' => 'Siti Aminah', 'phone_number' => '+628123456792']);

        $response = $this->actingAs($agent)->get(route('leads.index', ['q' => 'Ridwan']));

        $response->assertOk();
        $response->assertSee('Ahmad Ridwan');
        $response->assertDontSee('Siti Aminah');
    }

    public function test_user_tidak_login_ditolak(): void
    {
        $response = $this->get(route('leads.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_agent_bisnis_lain_tidak_melihat_lead_bisnis_ini(): void
    {
        // Isolasi multi-tenant (Fase 8a) — LeadController::index() lewat currentBusiness(),
        // yang sebelum Fase 8a selalu ambil "bisnis aktif pertama" di database tanpa peduli
        // agent yang login itu staf bisnis mana. Dengan dua bisnis aktif, bug ini baru bisa
        // benar-benar diuji.
        $otherBusiness = Business::create(['name' => 'Bisnis Lain (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $agentRole = Role::create(['name' => 'Agent', 'slug' => Role::AGENT]);
        $otherAgent = User::factory()->create(['is_active' => true, 'business_id' => $otherBusiness->id]);
        $otherAgent->roles()->attach($agentRole);

        $this->makeLead(['name' => 'Lead Bisnis Ini']);
        Lead::create([
            'business_id' => $otherBusiness->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Lead Bisnis Lain',
            'phone_number' => '+628199999999',
            'consent_whatsapp' => true,
            'status' => 'new',
        ]);

        $response = $this->actingAs($otherAgent)->get(route('leads.index'));

        $response->assertOk();
        $response->assertSee('Lead Bisnis Lain');
        $response->assertDontSee('Lead Bisnis Ini');
    }
}
