<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed 5 default system roles.
 * Idempotent: uses firstOrCreate on unique 'name'.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Admin',
                'description' => 'Toàn quyền hệ thống',
                'is_system' => true,
            ],
            [
                'name' => 'system_staff',
                'display_name' => 'Nhân viên hệ thống',
                'description' => 'Nhân viên vận hành nền tảng',
                'is_system' => true,
            ],
            [
                'name' => 'venue_owner',
                'display_name' => 'Chủ sân',
                'description' => 'Chủ sở hữu cụm sân thể thao',
                'is_system' => true,
            ],
            [
                'name' => 'venue_staff',
                'display_name' => 'Nhân viên sân',
                'description' => 'Nhân viên vận hành sân',
                'is_system' => true,
            ],
            [
                'name' => 'player',
                'display_name' => 'Người chơi',
                'description' => 'Người dùng đặt sân và tham gia hoạt động',
                'is_system' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
