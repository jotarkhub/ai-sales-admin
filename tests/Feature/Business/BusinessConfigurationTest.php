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

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeAgent(): User
    {
        $role = Role::create(['name' => 'Agent', 'slug' => Role::AGENT]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_bisa_melihat_halaman_konfigurasi(): void
    {
        Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'is_active' => true]);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('business.edit'));

        $response->assertOk();
        $response->assertSee('Konfigurasi Bisnis');
    }

    public function test_admin_bisa_memperbarui_konfigurasi_bisnis(): void
    {
        $business = Business::create(['name' => 'Nama Lama', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('business.update'), [
            'name' => 'Nama Baru',
            'timezone' => 'Asia/Jakarta',
            'is_active' => 1,
            'ai_authority_limit' => json_encode(['max_discount_percent' => 5]),
        ]);

        $response->assertRedirect();
        $business->refresh();
        $this->assertSame('Nama Baru', $business->name);
        $this->assertSame(5, $business->ai_authority_limit['max_discount_percent']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'business.updated',
            'actor_id' => $admin->id,
            'subject_type' => Business::class,
            'subject_id' => $business->id,
        ]);
    }

    public function test_agent_tidak_bisa_memperbarui_konfigurasi_bisnis(): void
    {
        Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $agent = $this->makeAgent();

        $response = $this->actingAs($agent)->put(route('business.update'), [
            'name' => 'Coba Ubah',
            'timezone' => 'Asia/Jakarta',
            'is_active' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('businesses', ['name' => 'Coba Ubah']);
    }

    public function test_json_tidak_valid_ditolak_validasi(): void
    {
        Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
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
        Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('business.update'), [
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Bukan/Timezone',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('timezone');
    }
}
