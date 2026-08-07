<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\IntegrationCredential;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppCredentialManagementTest extends TestCase
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

    public function test_platform_owner_bisa_mengisi_kredensial_whatsapp_bisnis(): void
    {
        $owner = $this->makePlatformOwner();
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $response = $this->actingAs($owner)->put(route('platform.businesses.credentials.whatsapp.update', $business), [
            'token' => 'token-rahasia-123',
            'phone_number_id' => '9998887776',
        ]);

        $response->assertRedirect(route('platform.businesses.show', $business));

        $this->assertDatabaseHas('integration_credentials', [
            'business_id' => $business->id,
            'provider' => 'whatsapp',
            'credential_key' => 'token',
        ]);
        $this->assertDatabaseHas('integration_credentials', [
            'business_id' => $business->id,
            'provider' => 'whatsapp',
            'credential_key' => 'phone_number_id',
        ]);

        // Nilai asli TIDAK PERNAH masuk audit_logs — hanya nama field yang berubah.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'integration_credential.whatsapp_updated',
            'subject_type' => Business::class,
            'subject_id' => $business->id,
        ]);
        $log = AuditLog::where('action', 'integration_credential.whatsapp_updated')->firstOrFail();
        $this->assertStringNotContainsString('token-rahasia-123', json_encode($log->after));
    }

    public function test_field_kosong_tidak_menimpa_nilai_tersimpan(): void
    {
        $owner = $this->makePlatformOwner();
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $this->actingAs($owner)->put(route('platform.businesses.credentials.whatsapp.update', $business), [
            'token' => 'token-lama',
            'phone_number_id' => '111',
        ]);

        // Update kedua hanya isi phone_number_id -> token lama TIDAK berubah/hilang.
        $this->actingAs($owner)->put(route('platform.businesses.credentials.whatsapp.update', $business), [
            'phone_number_id' => '222',
        ]);

        $token = IntegrationCredential::where('business_id', $business->id)
            ->where('credential_key', 'token')->firstOrFail();
        $phoneNumberId = IntegrationCredential::where('business_id', $business->id)
            ->where('credential_key', 'phone_number_id')->firstOrFail();

        $this->assertSame('token-lama', $token->encrypted_value);
        $this->assertSame('222', $phoneNumberId->encrypted_value);
    }

    public function test_status_menampilkan_terisi_tanpa_pernah_menampilkan_nilai_asli(): void
    {
        $owner = $this->makePlatformOwner();
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $this->actingAs($owner)->put(route('platform.businesses.credentials.whatsapp.update', $business), [
            'token' => 'token-super-rahasia',
            'phone_number_id' => '111',
        ]);

        $response = $this->actingAs($owner)->get(route('platform.businesses.show', $business));

        $response->assertOk();
        $response->assertSee('Terisi');
        $response->assertDontSee('token-super-rahasia');
    }

    public function test_admin_bisnis_biasa_tidak_bisa_mengubah_kredensial_whatsapp(): void
    {
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = $this->makeBusinessAdmin($business);

        $response = $this->actingAs($admin)->put(route('platform.businesses.credentials.whatsapp.update', $business), [
            'token' => 'coba-suntik-token',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('integration_credentials', ['business_id' => $business->id]);
    }
}
