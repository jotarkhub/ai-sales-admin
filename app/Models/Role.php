<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    // Slug baku yang dipakai policy/middleware. Tidak hardcode string di tempat lain.
    public const ADMIN = 'admin';

    public const SUPERVISOR = 'supervisor';

    public const AGENT = 'agent';

    // Tidak terikat satu business_id — lihat App\Http\Controllers\PlatformBusinessController.
    // Dipisah dari admin/supervisor/agent karena wewenangnya lintas-bisnis (kelola tenant),
    // bukan mengelola operasional satu bisnis.
    public const PLATFORM_OWNER = 'platform_owner';

    protected $fillable = ['name', 'slug', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
