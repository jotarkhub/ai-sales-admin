<?php

namespace Tests\Feature\Conversation;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Escalation;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $otherBusiness;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->otherBusiness = Business::create([
            'name' => 'Bisnis Lain (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => false,
        ]);

        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);

        $this->lead = Lead::create([
            'business_id' => $this->business->id,
            'lead_source_id' => $source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'engaged',
        ]);
    }

    private function makeAgent(?Business $business = null): User
    {
        $role = Role::create(['name' => 'Agent', 'slug' => Role::AGENT]);
        $user = User::factory()->create(['is_active' => true, 'business_id' => ($business ?? $this->business)->id]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeConversation(array $overrides = []): Conversation
    {
        return Conversation::create(array_merge([
            'business_id' => $this->business->id,
            'lead_id' => $this->lead->id,
            'status' => 'ai_active',
        ], $overrides));
    }

    public function test_staf_bisa_melihat_percakapan(): void
    {
        $agent = $this->makeAgent();
        $conversation = $this->makeConversation();

        $response = $this->actingAs($agent)->get(route('conversations.show', $conversation));

        $response->assertOk();
    }

    public function test_staf_dari_bisnis_lain_tidak_bisa_melihat_percakapan(): void
    {
        $agentLain = $this->makeAgent($this->otherBusiness);
        $conversation = $this->makeConversation();

        $response = $this->actingAs($agentLain)->get(route('conversations.show', $conversation));

        $response->assertForbidden();
    }

    public function test_take_over_mengubah_status_menugaskan_admin_dan_klaim_eskalasi_terbuka(): void
    {
        $agent = $this->makeAgent();
        $conversation = $this->makeConversation();
        $escalation = Escalation::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $this->lead->id,
            'reason' => Escalation::REASON_CUSTOMER_REQUESTED_HUMAN,
            'status' => Escalation::STATUS_OPEN,
        ]);

        $response = $this->actingAs($agent)->post(route('conversations.takeover', $conversation));

        $response->assertRedirect(route('conversations.show', $conversation));

        $conversation->refresh();
        $this->assertSame('human_takeover', $conversation->status->value);
        $this->assertSame($agent->id, $conversation->assigned_admin_id);

        $escalation->refresh();
        $this->assertSame(Escalation::STATUS_CLAIMED, $escalation->status);
        $this->assertSame($agent->id, $escalation->claimed_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conversation.takeover',
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $agent->id,
            'subject_type' => Conversation::class,
            'subject_id' => $conversation->id,
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'conversation_takeover',
            'actor_id' => $agent->id,
        ]);
    }

    public function test_take_over_saat_sudah_human_takeover_tidak_mengubah_apa_pun(): void
    {
        $agent = $this->makeAgent();
        $conversation = $this->makeConversation(['status' => 'human_takeover', 'assigned_admin_id' => $agent->id]);

        $response = $this->actingAs($agent)->post(route('conversations.takeover', $conversation));

        $response->assertRedirect();
        $this->assertSame('human_takeover', $conversation->fresh()->status->value);
    }

    public function test_release_mengembalikan_ke_ai_dan_melepas_penugasan(): void
    {
        $agent = $this->makeAgent();
        $conversation = $this->makeConversation(['status' => 'human_takeover', 'assigned_admin_id' => $agent->id]);

        $response = $this->actingAs($agent)->post(route('conversations.release', $conversation));

        $response->assertRedirect(route('conversations.show', $conversation));

        $conversation->refresh();
        $this->assertSame('ai_active', $conversation->status->value);
        $this->assertNull($conversation->assigned_admin_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conversation.released_to_ai',
            'subject_type' => Conversation::class,
            'subject_id' => $conversation->id,
        ]);
    }

    public function test_release_saat_masih_ai_active_tidak_mengubah_apa_pun(): void
    {
        $agent = $this->makeAgent();
        $conversation = $this->makeConversation();

        $response = $this->actingAs($agent)->post(route('conversations.release', $conversation));

        $response->assertRedirect();
        $this->assertSame('ai_active', $conversation->fresh()->status->value);
    }
}
