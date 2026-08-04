<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware role-based authorization. Dipakai lewat alias 'role', misal:
 * Route::middleware('role:admin')->group(...)
 * Route::middleware('role:admin,supervisor')->group(...)  // salah satu dari daftar
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Anda harus login terlebih dahulu.');
        }

        if (! $user->is_active) {
            abort(403, 'Akun Anda tidak aktif. Hubungi administrator.');
        }

        $hasAnyRole = collect($roles)->contains(fn (string $role) => $user->hasRole($role));

        if (! $hasAnyRole) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
