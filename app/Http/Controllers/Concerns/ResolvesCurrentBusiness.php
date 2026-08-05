<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Business;

trait ResolvesCurrentBusiness
{
    /**
     * MVP: satu business aktif. Satu-satunya tempat yang tahu cara menentukan
     * "bisnis aktif saat ini" — kalau nanti multi-tenant, cukup ganti di sini.
     */
    private function currentBusiness(): Business
    {
        return Business::query()->where('is_active', true)->firstOrFail();
    }
}
