<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case AiActive = 'ai_active';
    case HumanTakeover = 'human_takeover';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::AiActive => 'AI Aktif',
            self::HumanTakeover => 'Diambil Alih Admin',
            self::Closed => 'Ditutup',
        };
    }
}
