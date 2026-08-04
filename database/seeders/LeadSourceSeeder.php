<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use Illuminate\Database\Seeder;

class LeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'Google Form', 'slug' => LeadSource::GOOGLE_FORM],
            ['name' => 'Referral', 'slug' => LeadSource::REFERRAL],
            ['name' => 'Input Manual Admin', 'slug' => LeadSource::MANUAL],
        ];

        foreach ($sources as $source) {
            LeadSource::query()->updateOrCreate(['slug' => $source['slug']], $source);
        }
    }
}
