<?php

namespace Database\Seeders;

use App\Services\ModerationService;
use Illuminate\Database\Seeder;

class ModerationConfigSeeder extends Seeder
{
    public function run(): void
    {
        app(ModerationService::class)->seedDefaultConfigs();
    }
}
