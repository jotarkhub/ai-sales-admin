<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppProviderContract;
use App\Models\Business;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\WhatsApp\FollowUpDispatchService;
use App\Services\WhatsApp\WhatsAppSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpDispatchServiceTest extends TestCase
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
            'status' => 'new',
        ]);
    }

    private function makeDueFollowUp(array $overrides = []): FollowUp
    {
        return FollowUp::create(array_merge([
            'business_id' => $this->business->id,
            'lead_id' => $this->lead->id,
            'trigger_type' => 'form_submitted_initial_message',
            'scheduled_at' => now()->subMinute(),
            'status' => FollowUp::STATUS_PENDING,
            'channel' => 'whatsapp',
        ], $overrides));
    }

    private function bindFailingProvider(string $errorMessage = 'Simulasi gagal kirim.'): void
    {
        $fake = new class($errorMessage) implements WhatsAppProviderContract
        {
            public function __construct(private readonly string $errorMessage) {}

            public function sendTextMessage(string $to, string $body): WhatsAppSendResult
            {
                return WhatsAppSendResult::failure($this->errorMessage);
            }
        };

        $this->app->instance(WhatsAppProviderContract::class, $fake);
    }

    public function test_follow_up_terkirim_lewat_alias_template_dan_membuat_message_serta_conversation(): void
    {
        $this->business->update(['message_templates' => ['auto_reply_awal' => 'Halo! Terima kasih sudah mendaftar.']]);
        $followUp = $this->makeDueFollowUp();

        $result = app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertSame(['sent' => 1, 'skipped' => 0, 'retry_scheduled' => 0, 'failed' => 0], $result);

        $followUp->refresh();
        $this->assertSame(FollowUp::STATUS_SENT, $followUp->status);
        $this->assertNotNull($followUp->sent_message_id);

        $this->assertDatabaseHas('messages', [
            'lead_id' => $this->lead->id,
            'direction' => 'outbound',
            'body' => 'Halo! Terima kasih sudah mendaftar.',
        ]);

        $this->assertDatabaseHas('conversations', [
            'lead_id' => $this->lead->id,
            'business_id' => $this->business->id,
            'status' => 'ai_active',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'whatsapp_message_sent',
        ]);
    }

    public function test_key_persis_trigger_type_lebih_diutamakan_daripada_alias(): void
    {
        $this->business->update(['message_templates' => [
            'form_submitted_initial_message' => 'Pesan spesifik trigger.',
            'auto_reply_awal' => 'Pesan alias — tidak dipakai.',
        ]]);
        $this->makeDueFollowUp();

        app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertDatabaseHas('messages', ['body' => 'Pesan spesifik trigger.']);
        $this->assertDatabaseMissing('messages', ['body' => 'Pesan alias — tidak dipakai.']);
    }

    public function test_lead_opt_out_dilewati_tanpa_mengirim(): void
    {
        $this->business->update(['message_templates' => ['auto_reply_awal' => 'Halo!']]);
        $this->lead->update(['status' => 'opt_out', 'opted_out_at' => now()]);
        $followUp = $this->makeDueFollowUp();

        $result = app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(FollowUp::STATUS_SKIPPED, $followUp->fresh()->status);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'follow_up_skipped_opt_out',
        ]);
    }

    public function test_template_belum_dikonfigurasi_dilewati_tanpa_mengirim(): void
    {
        $followUp = $this->makeDueFollowUp();

        $result = app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(FollowUp::STATUS_SKIPPED, $followUp->fresh()->status);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'follow_up_skipped_no_template',
        ]);
    }

    public function test_follow_up_yang_belum_jatuh_tempo_tidak_diproses(): void
    {
        $this->business->update(['message_templates' => ['auto_reply_awal' => 'Halo!']]);
        $followUp = $this->makeDueFollowUp(['scheduled_at' => now()->addHour()]);

        $result = app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertSame(['sent' => 0, 'skipped' => 0, 'retry_scheduled' => 0, 'failed' => 0], $result);
        $this->assertSame(FollowUp::STATUS_PENDING, $followUp->fresh()->status);
    }

    public function test_kegagalan_kirim_dijadwalkan_ulang_kalau_masih_ada_sisa_percobaan(): void
    {
        $this->business->update(['message_templates' => ['auto_reply_awal' => 'Halo!']]);
        $this->bindFailingProvider('Timeout ke Meta.');
        $followUp = $this->makeDueFollowUp(['attempt_number' => 1, 'max_attempts' => 3]);

        $result = app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertSame(1, $result['retry_scheduled']);

        $followUp->refresh();
        $this->assertSame(FollowUp::STATUS_PENDING, $followUp->status);
        $this->assertSame(2, $followUp->attempt_number);
        $this->assertTrue($followUp->scheduled_at->isFuture());

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'whatsapp_message_failed_retry_scheduled',
        ]);
    }

    public function test_kegagalan_kirim_setelah_percobaan_maksimal_ditandai_gagal_permanen(): void
    {
        $this->business->update(['message_templates' => ['auto_reply_awal' => 'Halo!']]);
        $this->bindFailingProvider('Nomor tidak valid.');
        $followUp = $this->makeDueFollowUp(['attempt_number' => 3, 'max_attempts' => 3]);

        $result = app(FollowUpDispatchService::class)->dispatchDue();

        $this->assertSame(1, $result['failed']);

        $followUp->refresh();
        $this->assertSame(FollowUp::STATUS_FAILED, $followUp->status);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $this->lead->id,
            'type' => 'whatsapp_message_failed_permanently',
        ]);
    }
}
