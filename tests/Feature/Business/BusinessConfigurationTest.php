<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function makeAdmin(?Business $business = null): User
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => ($business ?? $this->business)->id]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeAgent(): User
    {
        $role = Role::create(['name' => 'Agent', 'slug' => Role::AGENT]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => $this->business->id]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_bisa_melihat_halaman_konfigurasi(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('business.edit'));

        $response->assertOk();
        $response->assertSee('Konfigurasi Bisnis');
    }

    public function test_admin_bisa_memperbarui_konfigurasi_bisnis(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('business.update'), [
            'name' => 'Nama Baru',
            'timezone' => 'Asia/Jakarta',
            'is_active' => 1,
            'ai_authority_limit' => json_encode(['max_discount_percent' => 5]),
        ]);

        $response->assertRedirect();
        $this->business->refresh();
        $this->assertSame('Nama Baru', $this->business->name);
        $this->assertSame(5, $this->business->ai_authority_limit['max_discount_percent']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business.updated',
            'actor_id' => $admin->id,
            'subject_type' => Business::class,
            'subject_id' => $this->business->id,
        ]);
    }

    public function test_agent_tidak_bisa_memperbarui_konfigurasi_bisnis(): void
    {
        $agent = $this->makeAgent();

        $response = $this->actingAs($agent)->put(route('business.update'), [
            'name' => 'Coba Ubah',
            'timezone' => 'Asia/Jakarta',
            'is_active' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('businesses', ['name' => 'Coba Ubah']);
    }

    public function test_admin_bisnis_lain_tidak_bisa_melihat_atau_mengubah_bisnis_ini(): void
    {
        // Isolasi multi-tenant (Fase 8a) — sebelum ResolvesCurrentBusiness diperbaiki, admin
        // bisnis mana pun selalu diarahkan ke "bisnis aktif pertama" di database, jadi bug ini
        // tidak pernah ketahuan lewat test selama cuma ada satu bisnis. Sekarang ada dua.
        $otherBusiness = Business::create(['name' => 'Bisnis Lain (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $otherAdmin = $this->makeAdmin($otherBusiness);

        $response = $this->actingAs($otherAdmin)->get(route('business.edit'));
        $response->assertOk();
        $response->assertSee($otherBusiness->name);
        $response->assertDontSee($this->business->name);

        $updateResponse = $this->actingAs($otherAdmin)->put(route('business.update'), [
            'name' => 'Dicoba Diubah Admin Bisnis Lain',
            'timezone' => 'Asia/Jakarta',
            'is_active' => 1,
        ]);
        $updateResponse->assertRedirect();

        $this->business->refresh();
        $this->assertNotSame('Dicoba Diubah Admin Bisnis Lain', $this->business->name);
        $this->assertSame('Dicoba Diubah Admin Bisnis Lain', $otherBusiness->fresh()->name);
    }

    public function test_json_tidak_valid_ditolak_validasi(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('business.update'), [
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => 1,
            'escalation_rules' => '{ini bukan json valid',
        ]);

        $response->assertSessionHasErrors('escalation_rules');
    }

    public function test_timezone_tidak_valid_ditolak_validasi(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('business.update'), [
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Bukan/Timezone',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('timezone');
    }
}
