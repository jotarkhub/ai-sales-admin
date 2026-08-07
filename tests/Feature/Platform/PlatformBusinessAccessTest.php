<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 8a — panel lintas-bisnis khusus platform_owner. Fokus test di sini BUKAN fitur
 * (baru daftar sederhana), tapi BATAS AKSESNYA: siapa yang boleh masuk, siapa yang tidak,
 * dan bahwa daftar yang tampil memang mencakup semua tenant (bukan cuma satu).
 */
class PlatformBusinessAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makePlatformOwner(): User
    {
        $role = Role::create(['name' => 'Platform Owner', 'slug' => Role::PLATFORM_OWNER]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => null]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeBusinessAdmin(Business $business): User
    {
        $role = Role::query()->firstOrCreate(['slug' => Role::ADMIN], ['name' => 'Administrator']);
        $user = User::factory()->create(['is_active' => true, 'business_id' => $business->id]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_platform_owner_bisa_melihat_semua_bisnis(): void
    {
        $owner = $this->makePlatformOwner();
        Business::create(['name' => 'Bisnis A (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        Business::create(['name' => 'Bisnis B (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $response = $this->actingAs($owner)->get(route('platform.businesses.index'));

        $response->assertOk();
        $response->assertSee('Bisnis A (Data Pengujian)');
        $response->assertSee('Bisnis B (Data Pengujian)');
    }

    public function test_admin_bisnis_biasa_tidak_bisa_akses_panel_platform_owner(): void
    {
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = $this->makeBusinessAdmin($business);

        $response = $this->actingAs($admin)->get(route('platform.businesses.index'));

        $response->assertForbidden();
    }

    public function test_tamu_tidak_login_diarahkan_ke_halaman_login(): void
    {
        $response = $this->get(route('platform.businesses.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_platform_owner_tidak_bisa_akses_dashboard_staf_biasa(): void
    {
        // Sengaja terbalik dari test di atas: platform_owner bukan admin/supervisor/agent
        // satu bisnis, jadi dashboard operasional biasa juga harus menolaknya.
        $owner = $this->makePlatformOwner();

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertForbidden();
    }
}
