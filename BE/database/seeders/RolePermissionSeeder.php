<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Assign default permissions to each role.
 * Idempotent: uses syncWithoutDetaching (no duplicates, no removals).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::pluck('id', 'code');

        // ── Super Admin: ALL permissions ───────────────────
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching($allPermissions->values());
        }

        // ── System Staff: moderate + user management ───────
        $systemStaff = Role::where('name', 'system_staff')->first();
        if ($systemStaff) {
            $staffPerms = $allPermissions->filter(fn ($id, $code) => str_starts_with($code, 'user.')
                || str_starts_with($code, 'venue.view')
                || $code === 'venue.approve'
                || $code === 'venue.reject'
                || str_starts_with($code, 'report.')
                || str_starts_with($code, 'complaint.')
                || str_starts_with($code, 'review.moderate')
                || str_starts_with($code, 'review.view')
                || str_starts_with($code, 'notification.')
                || $code === 'audit_log.view'
                || str_starts_with($code, 'system_config.')
                || $code === 'booking.view'
                || $code === 'payment.view'
            );
            $systemStaff->permissions()->syncWithoutDetaching($staffPerms->values());
        }

        // ── Venue Owner: manage own venue/court/pricing/booking ──
        $venueOwner = Role::where('name', 'venue_owner')->first();
        if ($venueOwner) {
            $ownerPerms = $allPermissions->filter(fn ($id, $code) => str_starts_with($code, 'venue.view')
                || str_starts_with($code, 'venue.create')
                || str_starts_with($code, 'venue.update')
                || str_starts_with($code, 'court.')
                || str_starts_with($code, 'pricing.')
                || str_starts_with($code, 'slot_lock.')
                || str_starts_with($code, 'booking.')
                || $code === 'venue_staff.manage'
                || str_starts_with($code, 'payment.view')
                || $code === 'payment.create'
                || $code === 'payment.refund'
                || str_starts_with($code, 'review.view')
                || str_starts_with($code, 'review.update') // reply to review
                || str_starts_with($code, 'recruitment.')
                || str_starts_with($code, 'notification.view')
                || str_starts_with($code, 'chat.')
                || $code === 'dashboard.venue_owner'
            );
            $venueOwner->permissions()->syncWithoutDetaching($ownerPerms->values());
        }

        // ── Venue Staff: basic booking/court operations ────
        $venueStaff = Role::where('name', 'venue_staff')->first();
        if ($venueStaff) {
            $staffVenuePerms = $allPermissions->filter(fn ($id, $code) => $code === 'court.view'
                || $code === 'booking.view'
                || $code === 'booking.create'
                || $code === 'booking.update'
                || $code === 'booking.checkin'
                || $code === 'payment.view'
                || $code === 'payment.create'
                || $code === 'pricing.view'
                || $code === 'slot_lock.view'
                || $code === 'slot_lock.create'
                || $code === 'slot_lock.delete'
                || $code === 'notification.view'
                || $code === 'chat.view'
                || $code === 'chat.send'
            );
            $venueStaff->permissions()->syncWithoutDetaching($staffVenuePerms->values());
        }

        // ── Player: book, review, recruit, chat ────────────
        $player = Role::where('name', 'player')->first();
        if ($player) {
            $playerPerms = $allPermissions->filter(fn ($id, $code) => $code === 'venue.view'
                || $code === 'court.view'
                || $code === 'pricing.view'
                || $code === 'booking.view'
                || $code === 'booking.create'
                || $code === 'booking.cancel'
                || $code === 'payment.view'
                || $code === 'payment.create'
                || $code === 'review.view'
                || $code === 'review.create'
                || $code === 'review.update'
                || $code === 'recruitment.view'
                || $code === 'recruitment.create'
                || $code === 'recruitment.update'
                || $code === 'recruitment.delete'
                || $code === 'recruitment.join'
                || $code === 'recruitment.approve_participant'
                || $code === 'recruitment.reject_participant'
                || $code === 'report.create'
                || $code === 'complaint.create'
                || $code === 'notification.view'
                || $code === 'chat.view'
                || $code === 'chat.send'
            );
            $player->permissions()->syncWithoutDetaching($playerPerms->values());
        }
    }
}
