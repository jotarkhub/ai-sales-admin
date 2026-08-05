<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Business;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendDueFollowUpsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_mengirim_follow_up_yang_jatuh_tempo_dan_melaporkan_ringkasan(): void
    {
        $business = Business::create([
            'name' => 'Bisnis Uji Coba (Data Pengujian)',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'message_templates' => ['auto_reply_awal' => 'Halo dari command!'],
        ]);
        $source = LeadSource::create(['name' => 'Google Form', 'slug' => 'google_form']);
        $lead = Lead::create([
            'business_id' => $business->id,
            'lead_source_id' => $source->id,
            'name' => 'Budi Pengujian',
            'phone_number' => '+628123456789',
            'consent_whatsapp' => true,
            'status' => 'new',
        ]);
        FollowUp::create([
            'business_id' => $business->id,
            'lead_id' => $lead->id,
            'trigger_type' => 'form_submitted_initial_message',
            'scheduled_at' => now()->subMinute(),
            'status' => FollowUp::STATUS_PENDING,
            'channel' => 'whatsapp',
        ]);

        $this->artisan('follow-ups:send')
            ->expectsOutputToContain('terkirim: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('follow_ups', ['lead_id' => $lead->id, 'status' => FollowUp::STATUS_SENT]);
    }
}
