<?php

namespace Tests\Feature\Lead;

use App\Models\Business;
use App\Models\LeadFieldDefinition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadFieldDefinitionTest extends TestCase
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

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => $this->business->id]);
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

    public function test_admin_bisa_menambah_field_custom_dan_key_dibuat_otomatis(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('lead-fields.store'), [
            'label' => 'No KTP Pemohon',
            'field_type' => 'nik',
            'is_required' => '1',
            'is_sensitive' => '1',
        ]);

        $response->assertRedirect(route('lead-fields.index'));

        $field = LeadFieldDefinition::where('business_id', $this->business->id)->firstOrFail();
        $this->assertSame('no_ktp_pemohon', $field->key);
        $this->assertSame('No KTP Pemohon', $field->label);
        $this->assertTrue($field->is_required);
        $this->assertTrue($field->is_sensitive);
        $this->assertTrue($field->is_active);
    }

    public function test_key_yang_bentrok_otomatis_diberi_akhiran(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('lead-fields.store'), ['label' => 'Kota', 'field_type' => 'text']);
        $this->actingAs($admin)->post(route('lead-fields.store'), ['label' => 'Kota', 'field_type' => 'text']);

        $keys = LeadFieldDefinition::where('business_id', $this->business->id)->pluck('key')->sort()->values();
        $this->assertSame(['kota', 'kota_2'], $keys->all());
    }

    public function test_agent_tidak_bisa_menambah_field_custom(): void
    {
        $agent = $this->makeAgent();

        $response = $this->actingAs($agent)->post(route('lead-fields.store'), [
            'label' => 'No KTP Pemohon',
            'field_type' => 'nik',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('lead_field_definitions', 0);
    }

    public function test_admin_bisa_menonaktifkan_dan_mengaktifkan_kembali_field(): void
    {
        $admin = $this->makeAdmin();
        $field = LeadFieldDefinition::create([
            'business_id' => $this->business->id,
            'key' => 'kota', 'label' => 'Kota', 'field_type' => 'text', 'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('lead-fields.toggle', $field));
        $this->assertFalse($field->fresh()->is_active);

        $this->actingAs($admin)->patch(route('lead-fields.toggle', $field));
        $this->assertTrue($field->fresh()->is_active);
    }

    public function test_admin_bisa_update_label_tapi_key_tidak_berubah(): void
    {
        $admin = $this->makeAdmin();
        $field = LeadFieldDefinition::create([
            'business_id' => $this->business->id,
            'key' => 'kota', 'label' => 'Kota', 'field_type' => 'text', 'sort_order' => 10, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('lead-fields.update', $field), [
            'label' => 'Kota Domisili',
            'field_type' => 'text',
            'sort_order' => 20,
        ]);

        $response->assertRedirect(route('lead-fields.index'));
        $field->refresh();
        $this->assertSame('kota', $field->key);
        $this->assertSame('Kota Domisili', $field->label);
        $this->assertSame(20, $field->sort_order);
    }
}
