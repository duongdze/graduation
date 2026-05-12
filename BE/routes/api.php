<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingConfigController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CourtTypeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinanceTransactionController;
use App\Http\Controllers\Api\HolidayPriceController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PartnerApplicationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlatformFeeConfigController;
use App\Http\Controllers\Api\PlayerPostController;
use App\Http\Controllers\Api\PlayerPostParticipantController;
use App\Http\Controllers\Api\PlayerRatingController;
use App\Http\Controllers\Api\PriceSlotController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SlotLockController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPermissionRevokeController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\VenueClusterController;
use App\Http\Controllers\Api\VenueCourtController;
use App\Http\Controllers\Api\VenueFeeLedgerController;
use App\Http\Controllers\Api\VenueStaffController;
use App\Http\Controllers\Api\VerificationCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::post('/send-verification-code', [VerificationCodeController::class, 'send']);
    Route::post('/verify-code', [VerificationCodeController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:role.view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');
    Route::post('/roles/{role}/permissions/sync', [RoleController::class, 'syncPermissions'])->middleware('permission:permission.assign');

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permission.create');
    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permission.view');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permission.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permission.delete');

    Route::post('/users/{user}/roles/sync', [UserRoleController::class, 'sync'])->middleware('permission:role.update');
    Route::post('/users/{user}/permissions/revoke', [UserPermissionRevokeController::class, 'store'])->middleware('permission:permission.assign');
    Route::delete('/users/{user}/permissions/revoke/{permission}', [UserPermissionRevokeController::class, 'destroy'])->middleware('permission:permission.assign');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');
    Route::patch('/users/{user}/lock', [UserController::class, 'lock'])->middleware('permission:user.lock');
    Route::patch('/users/{user}/unlock', [UserController::class, 'unlock'])->middleware('permission:user.lock');

    Route::get('/venue-clusters', [VenueClusterController::class, 'index'])->middleware('permission:venue.view');
    Route::post('/venue-clusters', [VenueClusterController::class, 'store'])->middleware('permission:venue.create');
    Route::post('/venue-clusters/{venueCluster}/view', [VenueClusterController::class, 'recordView'])->middleware('permission:venue.view');
    Route::get('/venue-clusters/{venueCluster}/available-slots', [AvailabilityController::class, 'clusterSlots'])->middleware('permission:court.view');
    Route::get('/venue-clusters/{venueCluster}/staff', [VenueStaffController::class, 'index'])->middleware('permission:venue_staff.manage');
    Route::post('/venue-clusters/{venueCluster}/staff', [VenueStaffController::class, 'store'])->middleware('permission:venue_staff.manage');
    Route::delete('/venue-clusters/{venueCluster}/staff/{user}', [VenueStaffController::class, 'destroy'])->middleware('permission:venue_staff.manage');
    Route::get('/venue-clusters/{venueCluster}', [VenueClusterController::class, 'show'])->middleware('permission:venue.view');
    Route::put('/venue-clusters/{venueCluster}', [VenueClusterController::class, 'update'])->middleware('permission:venue.update');
    Route::delete('/venue-clusters/{venueCluster}', [VenueClusterController::class, 'destroy'])->middleware('permission:venue.delete');
    Route::patch('/venue-clusters/{venueCluster}/approve', [VenueClusterController::class, 'approve'])->middleware('permission:venue.approve');
    Route::patch('/venue-clusters/{venueCluster}/reject', [VenueClusterController::class, 'reject'])->middleware('permission:venue.reject');

    Route::get('/venue-courts', [VenueCourtController::class, 'index'])->middleware('permission:court.view');
    Route::post('/venue-courts', [VenueCourtController::class, 'store'])->middleware('permission:court.create');
    Route::get('/venue-courts/{venueCourt}', [VenueCourtController::class, 'show'])->middleware('permission:court.view');
    Route::put('/venue-courts/{venueCourt}', [VenueCourtController::class, 'update'])->middleware('permission:court.update');
    Route::delete('/venue-courts/{venueCourt}', [VenueCourtController::class, 'destroy'])->middleware('permission:court.delete');
    Route::get('/venue-courts/{venueCourt}/available-slots', [AvailabilityController::class, 'courtSlots'])->middleware('permission:court.view');

    Route::get('/court-types', [CourtTypeController::class, 'index'])->middleware('permission:court.view');
    Route::post('/court-types', [CourtTypeController::class, 'store'])->middleware('permission:court.create');
    Route::put('/court-types/{courtType}', [CourtTypeController::class, 'update'])->middleware('permission:court.update');
    Route::get('/court-types/{courtType}', [CourtTypeController::class, 'show'])->middleware('permission:court.view');
    Route::delete('/court-types/{courtType}', [CourtTypeController::class, 'destroy'])->middleware('permission:court.delete');

    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);

    Route::get('/price-slots', [PriceSlotController::class, 'index'])->middleware('permission:pricing.view');
    Route::post('/price-slots', [PriceSlotController::class, 'store'])->middleware('permission:pricing.create');
    Route::put('/price-slots/{priceSlot}', [PriceSlotController::class, 'update'])->middleware('permission:pricing.update');
    Route::get('/price-slots/{priceSlot}', [PriceSlotController::class, 'show'])->middleware('permission:pricing.view');
    Route::delete('/price-slots/{priceSlot}', [PriceSlotController::class, 'destroy'])->middleware('permission:pricing.delete');

    Route::get('/holiday-prices', [HolidayPriceController::class, 'index'])->middleware('permission:pricing.view');
    Route::post('/holiday-prices', [HolidayPriceController::class, 'store'])->middleware('permission:pricing.create');
    Route::put('/holiday-prices/{holidayPrice}', [HolidayPriceController::class, 'update'])->middleware('permission:pricing.update');
    Route::get('/holiday-prices/{holidayPrice}', [HolidayPriceController::class, 'show'])->middleware('permission:pricing.view');
    Route::delete('/holiday-prices/{holidayPrice}', [HolidayPriceController::class, 'destroy'])->middleware('permission:pricing.delete');

    Route::get('/booking-configs', [BookingConfigController::class, 'index'])->middleware('permission:pricing.view');
    Route::put('/booking-configs/{bookingConfig}', [BookingConfigController::class, 'update'])->middleware('permission:pricing.update');

    Route::get('/bookings', [BookingController::class, 'index'])->middleware('permission:booking.view');
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('permission:booking.create');
    Route::post('/bookings/counter', [BookingController::class, 'storeCounter'])->middleware('permission:booking.create');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->middleware('permission:booking.view');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('permission:booking.cancel');
    Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->middleware('permission:booking.update');
    Route::patch('/bookings/{booking}/check-in', [BookingController::class, 'checkIn'])->middleware('permission:booking.checkin');
    Route::patch('/bookings/{booking}/complete', [BookingController::class, 'complete'])->middleware('permission:booking.update');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:payment.view');
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('permission:payment.create');
    Route::get('/payments/{payment}/checkout', [PaymentController::class, 'checkout'])->withoutMiddleware('auth:sanctum');
    Route::post('/payments/{payment}/checkout/complete', [PaymentController::class, 'completeCheckout'])->withoutMiddleware('auth:sanctum');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:payment.view');
    Route::patch('/payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])->middleware('permission:payment.update');
    Route::patch('/payments/{payment}/mark-failed', [PaymentController::class, 'markFailed'])->middleware('permission:payment.update');
    Route::post('/payments/{payment}/retry', [PaymentWebhookController::class, 'retry'])->middleware('permission:payment.create');
    Route::post('/payments/webhook/{gateway}', [PaymentWebhookController::class, 'callback'])->withoutMiddleware('auth:sanctum');

    Route::get('/refunds', [RefundController::class, 'index'])->middleware('permission:payment.refund');
    Route::post('/refunds', [RefundController::class, 'store'])->middleware('permission:payment.refund');
    Route::patch('/refunds/{refund}/approve', [RefundController::class, 'approve'])->middleware('permission:payment.refund');
    Route::post('/refunds/{refund}/proof', [RefundController::class, 'uploadProof'])->middleware('permission:payment.refund');
    Route::get('/refunds/{refund}', [RefundController::class, 'show'])->middleware('permission:payment.refund');
    Route::patch('/refunds/{refund}/reject', [RefundController::class, 'reject'])->middleware('permission:payment.refund');

    Route::get('/finance/transactions', [FinanceTransactionController::class, 'index'])->middleware('permission:payment.manage_all');
    Route::get('/venue-fee-ledgers', [VenueFeeLedgerController::class, 'index'])->middleware('permission:payment.manage_all');
    Route::post('/venue-fee-ledgers/reconcile-batch', [VenueFeeLedgerController::class, 'reconcileBatch'])->middleware('permission:payment.manage_all');
    Route::patch('/venue-fee-ledgers/{venueFeeLedger}/reconcile', [VenueFeeLedgerController::class, 'reconcile'])->middleware('permission:payment.manage_all');
    Route::get('/platform-fee-configs', [PlatformFeeConfigController::class, 'index'])->middleware('permission:system_config.view');
    Route::post('/platform-fee-configs', [PlatformFeeConfigController::class, 'store'])->middleware('permission:system_config.update');

    Route::get('/player-posts', [PlayerPostController::class, 'index'])->middleware('permission:recruitment.view');
    Route::post('/player-posts', [PlayerPostController::class, 'store'])->middleware('permission:recruitment.create');
    Route::get('/player-posts/{playerPost}', [PlayerPostController::class, 'show'])->middleware('permission:recruitment.view');
    Route::put('/player-posts/{playerPost}', [PlayerPostController::class, 'update'])->middleware('permission:recruitment.update');
    Route::delete('/player-posts/{playerPost}', [PlayerPostController::class, 'destroy'])->middleware('permission:recruitment.delete');
    Route::post('/player-posts/{playerPost}/join', [PlayerPostParticipantController::class, 'join'])->middleware('permission:recruitment.join');
    Route::patch('/player-posts/{playerPost}/participants/{participant}/approve', [PlayerPostParticipantController::class, 'approve'])->middleware('permission:recruitment.approve_participant');
    Route::patch('/player-posts/{playerPost}/participants/{participant}/reject', [PlayerPostParticipantController::class, 'reject'])->middleware('permission:recruitment.reject_participant');
    Route::delete('/player-posts/{playerPost}/leave', [PlayerPostParticipantController::class, 'leave'])->middleware('permission:recruitment.join');

    Route::get('/reviews', [ReviewController::class, 'index'])->middleware('permission:review.view');
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('permission:review.create');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->middleware('permission:review.update');
    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->middleware('permission:review.view');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->middleware('permission:review.delete');

    Route::get('/player-ratings', [PlayerRatingController::class, 'index'])->middleware('permission:review.view');
    Route::post('/player-ratings', [PlayerRatingController::class, 'store'])->middleware('permission:review.create');

    Route::post('/reports', [ReportController::class, 'store'])->middleware('permission:report.create');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:report.view');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('permission:report.view');
    Route::patch('/reports/{report}/review', [ReportController::class, 'review'])->middleware('permission:report.resolve');
    Route::patch('/reports/{report}/resolve', [ReportController::class, 'resolve'])->middleware('permission:report.resolve');
    Route::patch('/reports/{report}/dismiss', [ReportController::class, 'dismiss'])->middleware('permission:report.dismiss');

    Route::post('/complaints', [ComplaintController::class, 'store'])->middleware('permission:complaint.create');
    Route::get('/complaints', [ComplaintController::class, 'index'])->middleware('permission:complaint.view');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->middleware('permission:complaint.view');
    Route::patch('/complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->middleware('permission:complaint.resolve');

    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('permission:notification.view');
    Route::post('/notifications', [NotificationController::class, 'store'])->middleware('permission:notification.create');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('permission:notification.view');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('permission:notification.view');

    Route::get('/conversations', [ConversationController::class, 'index'])->middleware('permission:chat.view');
    Route::post('/conversations', [ConversationController::class, 'store'])->middleware('permission:chat.send');
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->middleware('permission:chat.view');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('permission:chat.send');
    Route::post('/conversations/{conversation}/read', [MessageController::class, 'read'])->middleware('permission:chat.view');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_log.view');

    // ── Partner Applications ──────────────────────────────
    Route::get('/partner-applications/my', [PartnerApplicationController::class, 'my']);
    Route::post('/partner-applications', [PartnerApplicationController::class, 'store']);
    Route::get('/partner-applications', [PartnerApplicationController::class, 'index'])->middleware('permission:partner_application.view_all');
    Route::get('/partner-applications/{partnerApplication}', [PartnerApplicationController::class, 'show']);
    Route::post('/partner-applications/{partnerApplication}/documents', [PartnerApplicationController::class, 'uploadDocument']);
    Route::patch('/partner-applications/{partnerApplication}/approve', [PartnerApplicationController::class, 'approve'])->middleware('permission:partner_application.approve');
    Route::patch('/partner-applications/{partnerApplication}/reject', [PartnerApplicationController::class, 'reject'])->middleware('permission:partner_application.reject');

    // ── Manual Slot Locks ─────────────────────────────────
    Route::get('/venue-courts/{venueCourt}/slot-locks', [SlotLockController::class, 'index'])->middleware('permission:slot_lock.view');
    Route::post('/venue-courts/{venueCourt}/slot-locks', [SlotLockController::class, 'store'])->middleware('permission:slot_lock.create');
    Route::delete('/venue-courts/{venueCourt}/slot-locks/{slotLock}', [SlotLockController::class, 'destroy'])->middleware('permission:slot_lock.delete');

    // ── Dashboard / Analytics ─────────────────────────────
    Route::get('/dashboard/admin/overview', [DashboardController::class, 'adminOverview'])->middleware('permission:dashboard.admin');
    Route::get('/dashboard/venue-owner/overview', [DashboardController::class, 'venueOwnerOverview'])->middleware('permission:dashboard.venue_owner');
    Route::get('/dashboard/revenue', [DashboardController::class, 'revenue'])->middleware('permission:dashboard.admin');
    Route::get('/dashboard/peak-hours', [DashboardController::class, 'peakHours'])->middleware('permission:dashboard.admin');
    Route::get('/dashboard/conversion-rate', [DashboardController::class, 'conversionRate'])->middleware('permission:dashboard.admin');
    Route::get('/dashboard/venue-density', [DashboardController::class, 'venueDensity'])->middleware('permission:dashboard.admin');
});
