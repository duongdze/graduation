<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Create default super admin user for system access.
 * Idempotent: uses firstOrCreate on email.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@sportzone.vn'],
            [
                'full_name' => 'Super Admin',
                'phone' => '0900000001',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Assign super_admin role (system scope)
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole && ! $admin->userRoles()->where('role_id', $superAdminRole->id)->exists()) {
            $admin->userRoles()->create([
                'role_id' => $superAdminRole->id,
                'scope_type' => 'system',
                'scope_id' => null,
            ]);
        }
    }
}
