<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => Role::ADMIN, 'description' => 'Kendali penuh: konfigurasi bisnis, kredensial, semua data.'],
            ['name' => 'Supervisor', 'slug' => Role::SUPERVISOR, 'description' => 'Kelola lead, percakapan, knowledge base, follow-up.'],
            ['name' => 'Agent', 'slug' => Role::AGENT, 'description' => 'Menangani percakapan & takeover, tanpa akses konfigurasi.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
