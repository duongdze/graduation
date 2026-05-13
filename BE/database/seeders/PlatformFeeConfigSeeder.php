<?php

namespace Database\Seeders;

use App\Models\PlatformFeeConfig;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed default platform fee configuration.
 * Append-only table — only insert if no records exist yet.
 */
class PlatformFeeConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (PlatformFeeConfig::count() > 0) {
            return; // Already seeded, don't duplicate
        }

        // Get admin user for created_by FK
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        PlatformFeeConfig::create([
            'fee_percent' => 10.00,
            'max_fee_percent' => 30.00,
            'effective_from' => now(),
            'created_by' => $admin?->id,
        ]);
    }
}
