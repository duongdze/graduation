<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: parent data first, then children.
     */
    public function run(): void
    {
        $this->call([
            // 1. RBAC foundation
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            ModerationConfigSeeder::class,

            // 2. Lookup data
            CourtTypeSeeder::class,

            // 3. Admin user (needs roles)
            AdminUserSeeder::class,

            // 4. Platform config (needs admin user for created_by)
            PlatformFeeConfigSeeder::class,

            // 5. Demo data (needs roles, court types, admin)
            DemoBasicSeeder::class,
        ]);
    }
}
