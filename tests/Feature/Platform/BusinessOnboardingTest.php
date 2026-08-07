<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 8d — form platform owner mendaftarkan bisnis (tenant) baru + akun admin pertamanya.
 * Test paling penting di sini BUKAN validasi form (itu cuma pelengkap), tapi
 * test_dua_bisnis_yang_dibuat_lewat_form_ini_benar_benar_terisolasi — bukti bahwa alur
 * onboarding manual yang sebenarnya dipakai nanti (bukan cuma seeder test) menghasilkan
 * tenant yang benar-benar terpisah, dari ujung ke ujung.
 */
class BusinessOnboardingTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'PT Klien Baru (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'admin_name' => 'Admin Klien Baru',
            'admin_email' => 'admin-klien-baru@example.test',
            'admin_password' => 'password-aman-123',
            'admin_password_confirmation' => 'password-aman-123',
        ], $overrides);
    }

    public function test_platform_owner_bisa_membuat_bisnis_baru_beserta_admin_pertama(): void
    {
        $owner = $this->makePlatformOwner();

        $response = $this->actingAs($owner)->post(route('platform.businesses.store'), $this->validPayload());

        $business = Business::where('name', 'PT Klien Baru (Data Pengujian)')->firstOrFail();
        $response->assertRedirect(route('platform.businesses.show', $business));

        $this->assertNotNull($business->webhook_slug);
        $this->assertTrue($business->is_active);

        $admin = User::where('email', 'admin-klien-baru@example.test')->firstOrFail();
        $this->assertSame($business->id, $admin->business_id);
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue(Hash::check('password-aman-123', $admin->password));

        $this->assertDatabaseHas('audit_logs', ['action' => 'business.created', 'subject_id' => $business->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created_as_business_admin', 'subject_id' => $admin->id]);

        // Password TIDAK PERNAH masuk audit_logs.
        $log = AuditLog::where('action', 'user.created_as_business_admin')->firstOrFail();
        $this->assertStringNotContainsString('password-aman-123', json_encode($log->after));
    }

    public function test_email_admin_yang_sudah_dipakai_ditolak(): void
    {
        $owner = $this->makePlatformOwner();
        $existingBusiness = Business::create(['name' => 'Bisnis Lama (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $this->makeBusinessAdmin($existingBusiness)->update(['email' => 'sudah-ada@example.test']);

        $response = $this->actingAs($owner)->post(
            route('platform.businesses.store'),
            $this->validPayload(['admin_email' => 'sudah-ada@example.test'])
        );

        $response->assertSessionHasErrors('admin_email');
        $this->assertDatabaseMissing('businesses', ['name' => 'PT Klien Baru (Data Pengujian)']);
    }

    public function test_password_terlalu_pendek_ditolak(): void
    {
        $owner = $this->makePlatformOwner();

        $response = $this->actingAs($owner)->post(route('platform.businesses.store'), $this->validPayload([
            'admin_password' => 'pendek',
            'admin_password_confirmation' => 'pendek',
        ]));

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $owner = $this->makePlatformOwner();

        $response = $this->actingAs($owner)->post(route('platform.businesses.store'), $this->validPayload([
            'admin_password_confirmation' => 'beda-sama-sekali',
        ]));

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_admin_bisnis_biasa_tidak_bisa_akses_form_atau_membuat_bisnis(): void
    {
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $admin = $this->makeBusinessAdmin($business);

        $this->actingAs($admin)->get(route('platform.businesses.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('platform.businesses.store'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseMissing('businesses', ['name' => 'PT Klien Baru (Data Pengujian)']);
    }

    public function test_dua_bisnis_yang_dibuat_lewat_form_ini_benar_benar_terisolasi(): void
    {
        $owner = $this->makePlatformOwner();

        $this->actingAs($owner)->post(route('platform.businesses.store'), $this->validPayload([
            'name' => 'PT Klien A (Data Pengujian)',
            'admin_email' => 'admin-a@example.test',
        ]))->assertRedirect();

        $this->actingAs($owner)->post(route('platform.businesses.store'), $this->validPayload([
            'name' => 'PT Klien B (Data Pengujian)',
            'admin_email' => 'admin-b@example.test',
        ]))->assertRedirect();

        $businessA = Business::where('name', 'PT Klien A (Data Pengujian)')->firstOrFail();
        $businessB = Business::where('name', 'PT Klien B (Data Pengujian)')->firstOrFail();
        $adminA = User::where('email', 'admin-a@example.test')->firstOrFail();
        $adminB = User::where('email', 'admin-b@example.test')->firstOrFail();

        $this->assertNotSame($businessA->webhook_slug, $businessB->webhook_slug);

        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);
        Lead::create([
            'business_id' => $businessA->id, 'lead_source_id' => $source->id,
            'name' => 'Lead Milik A', 'phone_number' => '+628111111111', 'consent_whatsapp' => true, 'status' => 'new',
        ]);
        Lead::create([
            'business_id' => $businessB->id, 'lead_source_id' => $source->id,
            'name' => 'Lead Milik B', 'phone_number' => '+628222222222', 'consent_whatsapp' => true, 'status' => 'new',
        ]);

        $responseA = $this->actingAs($adminA)->get(route('leads.index'));
        $responseA->assertOk();
        $responseA->assertSee('Lead Milik A');
        $responseA->assertDontSee('Lead Milik B');

        $responseB = $this->actingAs($adminB)->get(route('leads.index'));
        $responseB->assertOk();
        $responseB->assertSee('Lead Milik B');
        $responseB->assertDontSee('Lead Milik A');

        // Admin A juga tidak bisa lihat/ubah konfigurasi bisnis B lewat rute "bisnis saya sendiri".
        $this->actingAs($adminA)->get(route('business.edit'))->assertOk()->assertDontSee('PT Klien B (Data Pengujian)');
    }
}
