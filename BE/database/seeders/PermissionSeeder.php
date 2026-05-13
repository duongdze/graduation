<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seed all system permissions grouped by module.
 * Idempotent: uses firstOrCreate on unique 'code'.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ── User Management ────────────────────────────
            'Quản lý người dùng' => [
                ['code' => 'user.view',   'name' => 'Xem người dùng'],
                ['code' => 'user.create', 'name' => 'Tạo người dùng'],
                ['code' => 'user.update', 'name' => 'Cập nhật người dùng'],
                ['code' => 'user.delete', 'name' => 'Xóa người dùng'],
                ['code' => 'user.lock',   'name' => 'Khóa/mở khóa người dùng'],
            ],

            // ── Role & Permission ──────────────────────────
            'Phân quyền' => [
                ['code' => 'role.view',        'name' => 'Xem vai trò'],
                ['code' => 'role.create',      'name' => 'Tạo vai trò'],
                ['code' => 'role.update',      'name' => 'Cập nhật vai trò'],
                ['code' => 'role.delete',      'name' => 'Xóa vai trò'],
                ['code' => 'permission.view',  'name' => 'Xem quyền'],
                ['code' => 'permission.create', 'name' => 'Tạo quyền'],
                ['code' => 'permission.update', 'name' => 'Cập nhật quyền'],
                ['code' => 'permission.delete', 'name' => 'Xóa quyền'],
                ['code' => 'permission.assign', 'name' => 'Gán/thu hồi quyền'],
            ],

            // ── Venue Management ───────────────────────────
            'Quản lý sân' => [
                ['code' => 'venue.view',    'name' => 'Xem sân'],
                ['code' => 'venue.create',  'name' => 'Tạo sân'],
                ['code' => 'venue.update',  'name' => 'Cập nhật sân'],
                ['code' => 'venue.delete',  'name' => 'Xóa sân'],
                ['code' => 'venue.approve', 'name' => 'Duyệt sân'],
                ['code' => 'venue.reject',  'name' => 'Từ chối sân'],
                ['code' => 'venue_staff.manage', 'name' => 'Quản lý nhân viên sân'],
            ],

            // ── Court Management ───────────────────────────
            'Quản lý sân con' => [
                ['code' => 'court.view',   'name' => 'Xem sân con'],
                ['code' => 'court.create', 'name' => 'Tạo sân con'],
                ['code' => 'court.update', 'name' => 'Cập nhật sân con'],
                ['code' => 'court.delete', 'name' => 'Xóa sân con'],
            ],

            // ── Pricing ────────────────────────────────────
            'Quản lý giá' => [
                ['code' => 'pricing.view',   'name' => 'Xem bảng giá'],
                ['code' => 'pricing.create', 'name' => 'Tạo bảng giá'],
                ['code' => 'pricing.update', 'name' => 'Cập nhật bảng giá'],
                ['code' => 'pricing.delete', 'name' => 'Xóa bảng giá'],
            ],

            // ── Booking ────────────────────────────────────
            'Quản lý đặt sân' => [
                ['code' => 'booking.view',       'name' => 'Xem đặt sân'],
                ['code' => 'booking.create',     'name' => 'Tạo đặt sân'],
                ['code' => 'booking.update',     'name' => 'Cập nhật đặt sân'],
                ['code' => 'booking.cancel',     'name' => 'Hủy đặt sân'],
                ['code' => 'booking.checkin',    'name' => 'Check-in đặt sân'],
                ['code' => 'booking.manage_all', 'name' => 'Quản lý tất cả booking'],
            ],

            // ── Payment ────────────────────────────────────
            'Quản lý thanh toán' => [
                ['code' => 'payment.view',       'name' => 'Xem thanh toán'],
                ['code' => 'payment.create',     'name' => 'Tạo thanh toán'],
                ['code' => 'payment.update',     'name' => 'Cập nhật thanh toán'],
                ['code' => 'payment.refund',     'name' => 'Hoàn tiền'],
                ['code' => 'payment.manage_all', 'name' => 'Quản lý tất cả thanh toán'],
            ],

            // ── Recruitment ────────────────────────────────
            'Tuyển người chơi' => [
                ['code' => 'recruitment.view',               'name' => 'Xem bài tuyển'],
                ['code' => 'recruitment.create',             'name' => 'Tạo bài tuyển'],
                ['code' => 'recruitment.update',             'name' => 'Cập nhật bài tuyển'],
                ['code' => 'recruitment.delete',             'name' => 'Xóa bài tuyển'],
                ['code' => 'recruitment.join',               'name' => 'Tham gia tuyển'],
                ['code' => 'recruitment.approve_participant', 'name' => 'Duyệt người tham gia'],
                ['code' => 'recruitment.reject_participant', 'name' => 'Từ chối người tham gia'],
            ],

            // ── Review & Feedback ──────────────────────────
            'Đánh giá' => [
                ['code' => 'review.view',     'name' => 'Xem đánh giá'],
                ['code' => 'review.create',   'name' => 'Tạo đánh giá'],
                ['code' => 'review.update',   'name' => 'Cập nhật đánh giá'],
                ['code' => 'review.delete',   'name' => 'Xóa đánh giá'],
                ['code' => 'review.moderate', 'name' => 'Kiểm duyệt đánh giá'],
            ],

            // ── Report & Moderation ────────────────────────
            'Báo cáo vi phạm' => [
                ['code' => 'report.create',  'name' => 'Tạo báo cáo'],
                ['code' => 'report.view',    'name' => 'Xem báo cáo'],
                ['code' => 'report.resolve', 'name' => 'Xử lý báo cáo'],
                ['code' => 'report.dismiss', 'name' => 'Bỏ qua báo cáo'],
            ],

            // ── Complaint ───────────────────────────────────
            'Khiếu nại' => [
                ['code' => 'complaint.create',  'name' => 'Tạo khiếu nại'],
                ['code' => 'complaint.view',    'name' => 'Xem khiếu nại'],
                ['code' => 'complaint.update',  'name' => 'Cập nhật khiếu nại'],
                ['code' => 'complaint.resolve', 'name' => 'Xử lý khiếu nại'],
                ['code' => 'complaint.close',   'name' => 'Đóng khiếu nại'],
            ],

            // ── Notification ───────────────────────────────
            'Thông báo' => [
                ['code' => 'notification.view',   'name' => 'Xem thông báo'],
                ['code' => 'notification.create', 'name' => 'Tạo thông báo'],
                ['code' => 'notification.manage', 'name' => 'Quản lý thông báo'],
            ],

            // ── Chat ───────────────────────────────────────
            'Trò chuyện' => [
                ['code' => 'chat.view', 'name' => 'Xem tin nhắn'],
                ['code' => 'chat.send', 'name' => 'Gửi tin nhắn'],
            ],

            // ── Audit Log ──────────────────────────────────
            'Nhật ký hệ thống' => [
                ['code' => 'audit_log.view', 'name' => 'Xem nhật ký'],
            ],

            // ── System Config ──────────────────────────────
            'Cấu hình hệ thống' => [
                ['code' => 'system_config.view',   'name' => 'Xem cấu hình'],
                ['code' => 'system_config.update', 'name' => 'Cập nhật cấu hình'],
            ],

            // ── Partner Applications ──────────────────────
            'Đăng ký đối tác' => [
                ['code' => 'partner_application.view_all', 'name' => 'Xem tất cả đơn đăng ký đối tác'],
                ['code' => 'partner_application.approve',  'name' => 'Duyệt đơn đăng ký đối tác'],
                ['code' => 'partner_application.reject',   'name' => 'Từ chối đơn đăng ký đối tác'],
            ],

            // ── Slot Lock ─────────────────────────────────
            'Khóa khung giờ' => [
                ['code' => 'slot_lock.view',   'name' => 'Xem khóa khung giờ'],
                ['code' => 'slot_lock.create', 'name' => 'Tạo khóa khung giờ'],
                ['code' => 'slot_lock.delete', 'name' => 'Xóa khóa khung giờ'],
            ],

            // ── Dashboard ─────────────────────────────────
            'Bảng điều khiển' => [
                ['code' => 'dashboard.admin',       'name' => 'Xem dashboard admin'],
                ['code' => 'dashboard.venue_owner', 'name' => 'Xem dashboard chủ sân'],
            ],

            'Moderation Extension' => [
                ['code' => 'venue.lock', 'name' => 'Lock/unlock venue'],
                ['code' => 'moderation_config.view', 'name' => 'View moderation configs'],
                ['code' => 'moderation_config.update', 'name' => 'Update moderation configs'],
                ['code' => 'system_policy.view', 'name' => 'View system policies'],
                ['code' => 'system_policy.create', 'name' => 'Create system policies'],
                ['code' => 'system_policy.update', 'name' => 'Update system policies'],
                ['code' => 'system_policy.delete', 'name' => 'Delete system policies'],
                ['code' => 'banner.view', 'name' => 'View banners'],
                ['code' => 'banner.create', 'name' => 'Create banners'],
                ['code' => 'banner.update', 'name' => 'Update banners'],
                ['code' => 'banner.delete', 'name' => 'Delete banners'],
                ['code' => 'system_post.view', 'name' => 'View system posts'],
                ['code' => 'system_post.create', 'name' => 'Create system posts'],
                ['code' => 'system_post.update', 'name' => 'Update system posts'],
                ['code' => 'system_post.delete', 'name' => 'Delete system posts'],
                ['code' => 'community_post.view', 'name' => 'View community posts'],
                ['code' => 'community_post.create', 'name' => 'Create community posts'],
                ['code' => 'community_post.update', 'name' => 'Update community posts'],
                ['code' => 'community_post.delete', 'name' => 'Delete community posts'],
                ['code' => 'community_post.moderate', 'name' => 'Moderate community posts'],
                ['code' => 'favorite_venue.view', 'name' => 'View favorite venues'],
                ['code' => 'favorite_venue.update', 'name' => 'Update favorite venues'],
            ],
        ];

        foreach ($permissions as $groupName => $items) {
            foreach ($items as $perm) {
                Permission::firstOrCreate(
                    ['code' => $perm['code']],
                    [
                        'name' => $perm['name'],
                        'group_name' => $groupName,
                    ]
                );
            }
        }
    }
}
