<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed reference data (roles, lead sources) + satu business & admin untuk dev lokal.
     * Semua data di sini adalah DATA PENGUJIAN, bukan data customer nyata — lihat penanda
     * "(Data Pengujian)" pada nama business.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            LeadSourceSeeder::class,
        ]);

        $business = Business::query()->firstOrCreate(
            ['name' => 'Bisnis Contoh (Data Pengujian)'],
            [
                'assistant_name' => 'Nadia',
                'assistant_identity' => 'Asisten virtual sales, ramah dan membantu, bukan manusia.',
                'whatsapp_number' => null, // CREDENTIAL_REQUIRED — belum ada nomor WhatsApp Business nyata
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ]
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Admin Pengujian',
                'password' => bcrypt('password'),
                'business_id' => $business->id,
                'is_active' => true,
            ]
        );

        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();
        if ($adminRole && ! $admin->roles()->where('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole);
        }
    }
}
