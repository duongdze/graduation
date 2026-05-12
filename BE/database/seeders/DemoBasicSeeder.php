<?php

namespace Database\Seeders;

use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\PriceSlot;
use App\Models\Role;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Minimal demo data for API testing:
 *   - 1 venue owner + 1 player
 *   - 1 venue cluster (approved)
 *   - 3 courts
 *   - Price slots (morning/afternoon/evening)
 *   - Booking config
 *
 * Idempotent: uses firstOrCreate on unique fields.
 */
class DemoBasicSeeder extends Seeder
{
    public function run(): void
    {
        // ── Demo Venue Owner ───────────────────────────────
        $owner = User::firstOrCreate(
            ['email' => 'owner@sportzone.vn'],
            [
                'full_name' => 'Nguyễn Văn Sân',
                'phone' => '0900000002',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        $ownerRole = Role::where('name', 'venue_owner')->first();
        if ($ownerRole && ! $owner->userRoles()->where('role_id', $ownerRole->id)->exists()) {
            $owner->userRoles()->create([
                'role_id' => $ownerRole->id,
                'scope_type' => 'system',
                'scope_id' => null,
            ]);
        }

        // ── Demo Player ────────────────────────────────────
        $player = User::firstOrCreate(
            ['email' => 'player@sportzone.vn'],
            [
                'full_name' => 'Trần Minh Khang',
                'phone' => '0900000003',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'preferred_sports' => ['football', 'badminton'],
            ]
        );

        $playerRole = Role::where('name', 'player')->first();
        if ($playerRole && ! $player->userRoles()->where('role_id', $playerRole->id)->exists()) {
            $player->userRoles()->create([
                'role_id' => $playerRole->id,
                'scope_type' => 'system',
                'scope_id' => null,
            ]);
        }

        // ── Demo Venue Cluster ─────────────────────────────
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        $cluster = VenueCluster::firstOrCreate(
            ['slug' => 'san-the-thao-phu-nhuan'],
            [
                'owner_id' => $owner->id,
                'name' => 'Sân Thể Thao Phú Nhuận',
                'description' => 'Cụm sân thể thao đa năng tại quận Phú Nhuận, TP.HCM. Bao gồm sân bóng đá, cầu lông và pickleball.',
                'phone_contact' => '0283000001',
                'address' => '123 Phan Xích Long',
                'ward' => 'Phường 7',
                'district' => 'Phú Nhuận',
                'city' => 'Hồ Chí Minh',
                'latitude' => 10.8012,
                'longitude' => 106.6807,
                'amenities' => ['parking', 'water', 'changing_room', 'lighting'],
                'status' => 'active',
                'approved_by' => $admin?->id,
            ]
        );

        // ── Booking Config ─────────────────────────────────
        BookingConfig::firstOrCreate(
            ['cluster_id' => $cluster->id],
            [
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
                'cancel_before_hours' => 24,
                'refund_percent' => 100,
            ]
        );

        // ── Demo Courts ────────────────────────────────────
        $football5 = CourtType::where('name', 'Sân bóng đá 5')->first();
        $badminton = CourtType::where('name', 'Sân cầu lông')->first();
        $pickleball = CourtType::where('name', 'Sân pickleball')->first();

        $courts = [
            ['name' => 'Sân Bóng Đá A1', 'court_type_id' => $football5?->id, 'sort_order' => 1],
            ['name' => 'Sân Bóng Đá A2', 'court_type_id' => $football5?->id, 'sort_order' => 2],
            ['name' => 'Sân Cầu Lông B1', 'court_type_id' => $badminton?->id, 'sort_order' => 3],
            ['name' => 'Sân Cầu Lông B2', 'court_type_id' => $badminton?->id, 'sort_order' => 4],
            ['name' => 'Sân Pickleball C1', 'court_type_id' => $pickleball?->id, 'sort_order' => 5],
        ];

        foreach ($courts as $court) {
            VenueCourt::firstOrCreate(
                ['cluster_id' => $cluster->id, 'name' => $court['name']],
                array_merge($court, [
                    'cluster_id' => $cluster->id,
                    'status' => 'active',
                ])
            );
        }

        // ── Demo Price Slots ───────────────────────────────
        $priceSlots = [
            // Morning (6:00 - 12:00)
            [
                'start_time' => '06:00:00',
                'end_time' => '12:00:00',
                'price' => 200000,
                'apply_to_days' => [1, 2, 3, 4, 5], // Mon-Fri
            ],
            // Afternoon (12:00 - 17:00)
            [
                'start_time' => '12:00:00',
                'end_time' => '17:00:00',
                'price' => 250000,
                'apply_to_days' => [1, 2, 3, 4, 5],
            ],
            // Evening (17:00 - 22:00) — peak hours
            [
                'start_time' => '17:00:00',
                'end_time' => '22:00:00',
                'price' => 350000,
                'apply_to_days' => [1, 2, 3, 4, 5],
            ],
            // Weekend all day
            [
                'start_time' => '06:00:00',
                'end_time' => '22:00:00',
                'price' => 400000,
                'apply_to_days' => [0, 6], // Sun, Sat
            ],
        ];

        foreach ($priceSlots as $slot) {
            PriceSlot::firstOrCreate(
                [
                    'cluster_id' => $cluster->id,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ],
                array_merge($slot, [
                    'cluster_id' => $cluster->id,
                    'is_active' => true,
                ])
            );
        }
    }
}
