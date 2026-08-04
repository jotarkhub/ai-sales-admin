<?php

namespace App\Services\Lead;

use App\Models\Lead;

/**
 * Value object hasil pemrosesan intake, supaya controller tahu persis apa yang terjadi
 * (baru dibuat vs duplikat) tanpa menebak dari state Lead.
 */
class LeadIntakeResult
{
    public function __construct(
        public readonly Lead $lead,
        public readonly bool $wasDuplicate,
        public readonly bool $whatsappScheduled,
    ) {}
}
