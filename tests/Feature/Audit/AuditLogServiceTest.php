<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_menyimpan_before_after_dan_aktor(): void
    {
        $user = User::factory()->create();
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)']);

        $log = app(AuditLogService::class)->record(
            action: 'business.updated',
            subject: $business,
            before: ['name' => 'Lama'],
            after: ['name' => 'Baru'],
            actor: $user,
            actorType: AuditLog::ACTOR_USER,
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'business.updated',
            'actor_type' => AuditLog::ACTOR_USER,
            'actor_id' => $user->id,
            'subject_type' => Business::class,
            'subject_id' => $business->id,
        ]);
        $this->assertSame(['name' => 'Lama'], $log->fresh()->before);
        $this->assertSame(['name' => 'Baru'], $log->fresh()->after);
    }

    public function test_record_system_tidak_punya_actor_id(): void
    {
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)']);

        $log = app(AuditLogService::class)->recordSystem('lead.follow_up_scheduled', $business);

        $this->assertNull($log->actor_id);
        $this->assertSame(AuditLog::ACTOR_SYSTEM, $log->actor_type);
    }

    public function test_record_ai_menandai_actor_type_ai(): void
    {
        $business = Business::create(['name' => 'Bisnis Uji Coba (Data Pengujian)']);

        $log = app(AuditLogService::class)->recordAi('lead.status_recommended', $business);

        $this->assertSame(AuditLog::ACTOR_AI, $log->actor_type);
        $this->assertNull($log->actor_id);
    }
}
