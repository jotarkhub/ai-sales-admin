<?php

namespace Tests\Feature\Lead;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menegakkan state machine di docs/ARCHITECTURE.md #8: human_takeover/ai_active -> closed
 * begitu lead mencapai status akhir (won/lost/opt_out).
 */
class LeadConversationAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);

        $this->lead = Lead::create([
            'business_id' => $this->business->id,
            'lead_source_id' => $source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'negotiating',
        ]);
    }

    private function makeAdmin(): User
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => Role::ADMIN]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => $this->business->id]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_status_lost_menutup_percakapan_yang_masih_aktif(): void
    {
        $admin = $this->makeAdmin();
        $conversation = Conversation::create([
            'business_id' => $this->business->id,
            'lead_id' => $this->lead->id,
            'status' => 'human_takeover',
        ]);

        $this->actingAs($admin)->patch(route('leads.status.update', $this->lead), ['status' => 'lost']);

        $this->assertSame('closed', $conversation->fresh()->status->value);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'conversation_auto_closed',
        ]);
    }

    public function test_konfirmasi_won_menutup_percakapan_yang_masih_aktif(): void
    {
        $admin = $this->makeAdmin();
        $conversation = Conversation::create([
            'business_id' => $this->business->id,
            'lead_id' => $this->lead->id,
            'status' => 'ai_active',
        ]);

        $this->actingAs($admin)->post(route('leads.confirm-won', $this->lead));

        $this->assertSame('closed', $conversation->fresh()->status->value);
    }

    public function test_status_non_terminal_tidak_menutup_percakapan(): void
    {
        $admin = $this->makeAdmin();
        $conversation = Conversation::create([
            'business_id' => $this->business->id,
            'lead_id' => $this->lead->id,
            'status' => 'ai_active',
        ]);

        $this->actingAs($admin)->patch(route('leads.status.update', $this->lead), ['status' => 'contacted']);

        $this->assertSame('ai_active', $conversation->fresh()->status->value);
    }
}
