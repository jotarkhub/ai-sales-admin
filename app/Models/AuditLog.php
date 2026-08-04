<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const ACTOR_USER = 'user';

    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_AI = 'ai';

    // Tabel append-only: tidak ada kolom updated_at.
    public $timestamps = false;

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
