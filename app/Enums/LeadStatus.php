<?php

namespace App\Enums;

/**
 * Status lead sesuai state machine di docs/ARCHITECTURE.md.
 *
 * PENTING: transisi ke Won HANYA boleh dilakukan lewat aksi admin eksplisit
 * (lihat App\Services\Lead\LeadStatusService) — AI tidak pernah diizinkan
 * mengubah status ini sendiri.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Engaged = 'engaged';
    case Qualifying = 'qualifying';
    case Qualified = 'qualified';
    case ProposalRequested = 'proposal_requested';
    case ProposalSent = 'proposal_sent';
    case Negotiating = 'negotiating';
    case Won = 'won';
    case Lost = 'lost';
    case Dormant = 'dormant';
    case OptOut = 'opt_out';
    case Escalated = 'escalated';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Contacted => 'Sudah Dihubungi',
            self::Engaged => 'Merespons',
            self::Qualifying => 'Sedang Kualifikasi',
            self::Qualified => 'Terkualifikasi',
            self::ProposalRequested => 'Minta Penawaran',
            self::ProposalSent => 'Penawaran Terkirim',
            self::Negotiating => 'Negosiasi',
            self::Won => 'Berhasil',
            self::Lost => 'Gagal',
            self::Dormant => 'Tidak Aktif',
            self::OptOut => 'Berhenti Berlangganan',
            self::Escalated => 'Dieskalasi',
        };
    }

    /**
     * Status yang TIDAK BOLEH dicapai lewat rekomendasi AI secara langsung —
     * wajib lewat endpoint/aksi khusus admin.
     */
    public static function requiresAdminConfirmation(): array
    {
        return [self::Won];
    }

    /** Status akhir (final), tidak ada follow-up otomatis lagi setelah ini. */
    public static function terminal(): array
    {
        return [self::Won, self::Lost, self::OptOut];
    }
}
