<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'salah']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('email')
        );
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
