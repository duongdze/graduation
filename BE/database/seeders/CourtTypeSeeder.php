<?php

namespace Database\Seeders;

use App\Models\CourtType;
use Illuminate\Database\Seeder;

/**
 * Seed default sport/court types.
 * Idempotent: uses firstOrCreate on unique 'name'.
 */
class CourtTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Sân bóng đá 5',   'description' => 'Sân bóng đá 5 người',       'player_count' => 10, 'is_active' => true],
            ['name' => 'Sân bóng đá 7',   'description' => 'Sân bóng đá 7 người',       'player_count' => 14, 'is_active' => true],
            ['name' => 'Sân bóng đá 11',  'description' => 'Sân bóng đá 11 người',      'player_count' => 22, 'is_active' => true],
            ['name' => 'Sân cầu lông',    'description' => 'Sân cầu lông đơn/đôi',      'player_count' => 4,  'is_active' => true],
            ['name' => 'Sân tennis',       'description' => 'Sân tennis đơn/đôi',        'player_count' => 4,  'is_active' => true],
            ['name' => 'Sân pickleball',   'description' => 'Sân pickleball',             'player_count' => 4,  'is_active' => true],
            ['name' => 'Sân bóng rổ',     'description' => 'Sân bóng rổ 5v5',           'player_count' => 10, 'is_active' => true],
            ['name' => 'Sân bóng chuyền', 'description' => 'Sân bóng chuyền 6v6',       'player_count' => 12, 'is_active' => true],
            ['name' => 'Sân đa năng',     'description' => 'Sân đa năng nhiều môn',     'player_count' => 0, 'is_active' => true],
        ];

        foreach ($types as $type) {
            CourtType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
