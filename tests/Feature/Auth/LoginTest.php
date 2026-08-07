<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_bisa_login_dengan_kredensial_benar(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-benar'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-benar',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_platform_owner_diarahkan_ke_panel_bisnis_bukan_dashboard_biasa(): void
    {
        // Fase 8a — platform owner tidak punya business_id, jadi route('dashboard') biasa
        // (middleware role:admin,supervisor,agent) akan menolaknya. Login harus mengarahkan
        // ke panelnya sendiri, bukan ke dashboard yang pasti 403 untuknya.
        $role = Role::create(['name' => 'Platform Owner', 'slug' => Role::PLATFORM_OWNER]);
        $owner = User::factory()->create([
            'password' => Hash::make('password-benar'),
            'business_id' => null,
            'is_active' => true,
        ]);
        $owner->roles()->attach($role);

        $response = $this->post('/login', [
            'email' => $owner->email,
            'password' => 'password-benar',
        ]);

        $this->assertAuthenticatedAs($owner);
        $response->assertRedirect(route('platform.businesses.index'));
    }

    public function test_login_ditolak_untuk_password_salah(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-benar'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-salah',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_ditolak_untuk_akun_tidak_aktif(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-benar'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-benar',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_dibatasi_rate_limit_setelah_beberapa_kali_gagal(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-benar')]);

        // Simulasikan 5 percobaan gagal sebelumnya langsung lewat RateLimiter (bukan 6x
        // round-trip HTTP berurutan) — throttleKey harus identik dengan
        // App\Http\Requests\Auth\LoginRequest::throttleKey(). Request test Laravel selalu
        // memakai REMOTE_ADDR 127.0.0.1 secara default.
        $throttleKey = Str::transliterate(Str::lower($user->email).'|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password-benar']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_login_dan_logout_tercatat_di_audit_log(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-benar')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password-benar']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user->id,
        ]);

        $this->actingAs($user)->post('/logout');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_id' => $user->id,
        ]);
        $this->assertGuest();
    }
}
