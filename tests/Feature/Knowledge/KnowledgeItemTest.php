<?php

namespace Tests\Feature\Knowledge;

use App\Models\Business;
use App\Models\KnowledgeItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeItemTest extends TestCase
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

    public function test_agent_bisa_melihat_daftar_knowledge_base(): void
    {
        $agent = $this->makeAgent();
        KnowledgeItem::create([
            'business_id' => $this->business->id,
            'category' => 'harga',
            'title' => 'Daftar Harga Paket A',
            'content' => 'Paket A seharga Rp1.000.000.',
            'status' => 'published',
        ]);

        $response = $this->actingAs($agent)->get(route('knowledge.index'));

        $response->assertOk();
        $response->assertSee('Daftar Harga Paket A');
    }

    public function test_admin_bisa_menambah_knowledge_item(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('knowledge.store'), [
            'category' => 'faq',
            'title' => 'Apa itu LPK?',
            'content' => 'LPK adalah Lembaga Pelatihan Kerja.',
            'status' => 'draft',
            'priority' => 5,
        ]);

        $response->assertRedirect(route('knowledge.index'));

        $item = KnowledgeItem::where('business_id', $this->business->id)->firstOrFail();
        $this->assertSame('Apa itu LPK?', $item->title);
        $this->assertSame('draft', $item->status->value);
        $this->assertSame(5, $item->priority);
        $this->assertSame($admin->id, $item->owner_id);
    }

    public function test_agent_tidak_bisa_menambah_knowledge_item(): void
    {
        $agent = $this->makeAgent();

        $response = $this->actingAs($agent)->post(route('knowledge.store'), [
            'category' => 'faq',
            'title' => 'Apa itu LPK?',
            'content' => 'LPK adalah Lembaga Pelatihan Kerja.',
            'status' => 'draft',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_admin_bisa_publikasikan_dan_tarik_kembali_ke_draft(): void
    {
        $admin = $this->makeAdmin();
        $item = KnowledgeItem::create([
            'business_id' => $this->business->id,
            'category' => 'faq',
            'title' => 'Contoh',
            'content' => 'Isi contoh.',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->patch(route('knowledge.toggle-publish', $item));
        $this->assertSame('published', $item->fresh()->status->value);

        $this->actingAs($admin)->patch(route('knowledge.toggle-publish', $item));
        $this->assertSame('draft', $item->fresh()->status->value);
    }

    public function test_admin_bisa_update_isi_knowledge_item(): void
    {
        $admin = $this->makeAdmin();
        $item = KnowledgeItem::create([
            'business_id' => $this->business->id,
            'category' => 'faq',
            'title' => 'Judul Lama',
            'content' => 'Isi lama.',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->put(route('knowledge.update', $item), [
            'category' => 'faq',
            'title' => 'Judul Baru',
            'content' => 'Isi baru.',
            'status' => 'published',
            'priority' => 2,
        ]);

        $response->assertRedirect(route('knowledge.index'));

        $item->refresh();
        $this->assertSame('Judul Baru', $item->title);
        $this->assertSame('published', $item->status->value);
    }

    public function test_expiry_date_sebelum_effective_date_ditolak(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('knowledge.store'), [
            'category' => 'promo',
            'title' => 'Promo Kilat',
            'content' => 'Diskon 10%.',
            'status' => 'draft',
            'effective_date' => '2026-08-10',
            'expiry_date' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('expiry_date');
        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_scope_usable_by_ai_hanya_ambil_item_published_dan_masih_berlaku(): void
    {
        KnowledgeItem::create([
            'business_id' => $this->business->id,
            'category' => 'faq', 'title' => 'Draft', 'content' => '-', 'status' => 'draft',
        ]);
        KnowledgeItem::create([
            'business_id' => $this->business->id,
            'category' => 'faq', 'title' => 'Kadaluarsa', 'content' => '-', 'status' => 'published',
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
        $usable = KnowledgeItem::create([
            'business_id' => $this->business->id,
            'category' => 'faq', 'title' => 'Berlaku', 'content' => '-', 'status' => 'published',
        ]);

        $result = KnowledgeItem::usableByAi()->get();

        $this->assertCount(1, $result);
        $this->assertSame($usable->id, $result->first()->id);
    }
}
