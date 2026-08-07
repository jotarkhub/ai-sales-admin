<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Business;
use Illuminate\Support\Facades\Auth;

trait ResolvesCurrentBusiness
{
    /**
     * Bisnis milik user yang sedang login. SEBELUM Fase 8 ini salah ambil
     * "Business::where('is_active', true)->firstOrFail()" — benar selama cuma ada satu bisnis
     * aktif, tapi begitu ada bisnis kedua, staf bisnis B akan melihat/mengedit data bisnis A
     * (siapa pun yang kebetulan jadi baris pertama). Sekarang WAJIB berdasarkan business_id
     * milik user, bukan tebakan global.
     *
     * User tanpa business_id (mis. platform_owner, lihat App\Models\Role) tidak pernah sampai
     * ke sini karena middleware 'role:admin,supervisor,agent' di routes/web.php sudah menolak
     * duluan — method ini cuma dipanggil dari controller yang berada di dalam grup itu.
     *
     * Ini menyelesaikan sisi dashboard admin saja. Resolusi bisnis dari webhook WhatsApp masuk
     * (tidak ada user yang login) diselesaikan terpisah lewat routing per bisnis di Fase 8c.
     */
    private function currentBusiness(): Business
    {
        $business = Auth::user()?->business;

        abort_if($business === null, 403, 'Akun ini tidak terhubung ke bisnis mana pun.');

        return $business;
    }
}
