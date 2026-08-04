<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_diarahkan_ke_login_saat_akses_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_user_tanpa_role_yang_sesuai_mendapat_403(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        // tidak diberi role apa pun

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertForbidden();
    }

    public function test_user_dengan_role_admin_bisa_akses_dashboard(): void
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_user_nonaktif_mendapat_403_walau_punya_role(): void
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => false]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertForbidden();
    }
}
