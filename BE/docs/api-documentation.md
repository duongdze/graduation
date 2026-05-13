# API Documentation — Sports Venue Booking Platform

## 1. Tổng quan

- Base URL local: `http://localhost:8000/api`
- Auth: Laravel Sanctum Bearer Token.
- Header FE gửi sau khi login: `Authorization: Bearer <access_token>`.
- Content type mặc định: `application/json`, riêng media upload dùng `multipart/form-data`.
- Tổng số API route hiện tại: `188` API routes (`194` total Laravel routes from `php artisan route:list`).
- Public API chính: register, login, forgot/reset password, send/verify code, payment webhook.

Success:

```json
{
  "success": true,
  "message": "Message here",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Error message here",
  "errors": {}
}
```

Pagination:

```json
{
  "success": true,
  "message": "Fetched successfully",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

Ghi chú Axios cho Vue:

```js
axios.defaults.baseURL = 'http://localhost:8000/api'
axios.defaults.headers.common.Authorization = `Bearer ${token}`
```

Validation, auth, not found và authorization errors đã được render JSON trong `bootstrap/app.php`.

## 2. Auth

### POST `/auth/register`

- Auth required: No
- Permission required: None
- Controller: `AuthController@register`

Request body:

```json
{
  "full_name": "Nguyen Van A",
  "email": "player@example.com",
  "phone": "0901234567",
  "password": "password123",
  "password_confirmation": "password123",
  "bio": "Thich da bong",
  "preferred_sports": ["football"],
  "preferred_position": "striker"
}
```

Success response:

```json
{
  "success": true,
  "message": "Registered successfully",
  "data": {
    "user": {
      "id": "uuid",
      "full_name": "Nguyen Van A",
      "roles": [{ "name": "player" }],
      "permissions": ["booking.create"]
    },
    "access_token": "token",
    "token_type": "Bearer"
  }
}
```

Error response:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

Business rules:

- Tạo user thật, hash password bằng `Hash::make`.
- Tự gán role `player` nếu role tồn tại.
- User được tạo với `status=pending_verify`, sinh mã xác thực và gửi qua Laravel Mail/log mailer.
- Login bị chặn cho tới khi `/auth/verify-code` active account.
- Chưa có rate limit/captcha riêng cho đăng ký hàng loạt.

### POST `/auth/login`

- Auth required: No
- Permission required: None
- Controller: `AuthController@login`

Request body:

```json
{
  "identifier": "admin@sportzone.vn",
  "password": "password",
  "device_name": "vue-web"
}
```

Success response:

```json
{
  "success": true,
  "message": "Logged in successfully",
  "data": {
    "user": {
      "id": "uuid",
      "email": "admin@sportzone.vn",
      "roles": [],
      "permissions": []
    },
    "access_token": "token",
    "token_type": "Bearer"
  }
}
```

Business rules:

- Login bằng email hoặc phone qua `identifier`.
- Từ chối user có `status=locked` hoặc `pending_verify`.
- Trả roles và permissions để FE render menu.
- Chưa hỗ trợ refresh token riêng hoặc giới hạn số thiết bị.

### POST `/auth/logout`

- Auth required: Yes
- Permission required: None
- Controller: `AuthController@logout`

Request body: none.

Business rules:

- Xóa current Sanctum token.

### GET `/auth/me`

- Auth required: Yes
- Permission required: None
- Controller: `AuthController@me`

Business rules:

- Trả user profile, roles, permissions.
- Không trả password/remember token.

### PUT `/profile`

- Auth required: Yes
- Permission required: None
- Controller: `ProfileController@update`

Request body:

```json
{
  "full_name": "Nguyen Van A",
  "email": "new@example.com",
  "phone": "0901234567",
  "avatar_url": "/storage/media/avatar.jpg",
  "bio": "Bio",
  "preferred_sports": ["football"],
  "preferred_position": "goalkeeper"
}
```

Business rules:

- Validate unique email/phone.
- Update trực tiếp user đang đăng nhập.

### PUT `/profile/password`

- Auth required: Yes
- Permission required: None
- Controller: `ProfileController@updatePassword`

Request body:

```json
{
  "current_password": "old-password",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Business rules:

- Kiểm tra current password.
- Hash password mới.
- Revoke toàn bộ token hiện có sau khi đổi mật khẩu.

### POST `/auth/forgot-password`

- Auth required: No
- Permission required: None
- Controller: `PasswordResetController@forgotPassword`

Request body:

```json
{
  "email": "player@example.com"
}
```

Business rules:

- Không tiết lộ email có tồn tại hay không.
- Tạo record trong `verification_codes`, code hash, hết hạn sau 5 phút.
- Local/testing trả `debug_code`; production hiện còn TODO gửi email thật.

### POST `/auth/reset-password`

- Auth required: No
- Permission required: None
- Controller: `PasswordResetController@resetPassword`

Request body:

```json
{
  "email": "player@example.com",
  "code": "123456",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Business rules:

- Verify code type `reset_password`.
- Hash password mới.
- Revoke toàn bộ Sanctum tokens.

### POST `/auth/send-verification-code`

- Auth required: No
- Permission required: None
- Controller: `VerificationCodeController@send`

Request body:

```json
{
  "identifier": "player@example.com",
  "type": "register",
  "channel": "email"
}
```

Business rules:

- `type`: `register`, `phone_verify`.
- `channel`: `email`, `sms`.
- Code được hash trong `verification_codes`, max attempts 5, expires 5 minutes.
- Local/testing trả `debug_code`; email channel gửi qua Laravel Mail, SMS channel vẫn cần provider.

### POST `/auth/verify-code`

- Auth required: No
- Permission required: None
- Controller: `VerificationCodeController@verify`

Request body:

```json
{
  "identifier": "player@example.com",
  "type": "register",
  "code": "123456"
}
```

Business rules:

- Verify hash code, check expiry, attempt count.
- Với `type=register`, set `email_verified_at` nếu tìm được user.
- Hiện không tự trả token sau verify.

## 3. RBAC

Dynamic RBAC dùng các bảng: `roles`, `permissions`, `role_permissions`, `user_roles`, `user_permission_revokes`.

Middleware:

- Route dùng dạng `permission:booking.view`.
- `super_admin` bypass toàn bộ permission.
- `User::getAllPermissions()` lấy permission từ role và trừ `user_permission_revokes`.

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/roles` | `role.view` | `RoleController@index` | List roles, filter/search |
| POST | `/roles` | `role.create` | `RoleController@store` | Create role |
| PUT | `/roles/{role}` | `role.update` | `RoleController@update` | Update role |
| DELETE | `/roles/{role}` | `role.delete` | `RoleController@destroy` | Delete role |
| GET | `/permissions` | `permission.view` | `PermissionController@index` | List permissions |
| POST | `/permissions` | `permission.create` | `PermissionController@store` | Create permission |
| GET | `/permissions/{permission}` | `permission.view` | `PermissionController@show` | Detail |
| PUT | `/permissions/{permission}` | `permission.update` | `PermissionController@update` | Update permission |
| DELETE | `/permissions/{permission}` | `permission.delete` | `PermissionController@destroy` | Delete permission |
| POST | `/roles/{role}/permissions/sync` | `permission.assign` | `RoleController@syncPermissions` | Sync role permissions |
| POST | `/users/{user}/roles/sync` | `role.update` | `UserRoleController@sync` | Sync user roles, supports scope |
| POST | `/users/{user}/permissions/revoke` | `permission.assign` | `UserPermissionRevokeController@store` | Revoke permission |
| DELETE | `/users/{user}/permissions/revoke/{permission}` | `permission.assign` | `UserPermissionRevokeController@destroy` | Remove revoke |

## 4. User Management

| Method | Endpoint | Permission | Controller | Query/body chính |
|---|---|---|---|---|
| GET | `/users` | `user.view` | `UserController@index` | `status`, `search`, `per_page` |
| POST | `/users` | `user.create` | `UserController@store` | `full_name`, `email`, `phone`, `password`, `status` |
| GET | `/users/{user}` | `user.view` | `UserController@show` | Include roles/revokes |
| PUT | `/users/{user}` | `user.update` | `UserController@update` | Update user, optional password hash |
| DELETE | `/users/{user}` | `user.delete` | `UserController@destroy` | Delete user |
| PATCH | `/users/{user}/lock` | `user.lock` | `UserController@lock` | Set `status=locked` |
| PATCH | `/users/{user}/unlock` | `user.lock` | `UserController@unlock` | Set `status=active` |

## 5. Partner Application

| Method | Endpoint | Auth | Permission | Controller | Ghi chú |
|---|---|---|---|---|---|
| POST | `/partner-applications` | Yes | None | `PartnerApplicationController@store` | User gửi đơn, chặn nếu đang có đơn pending |
| GET | `/partner-applications/my` | Yes | None | `PartnerApplicationController@my` | User xem đơn của mình |
| GET | `/partner-applications` | Yes | `partner_application.view_all` | `PartnerApplicationController@index` | Admin list, filter `status/search` |
| GET | `/partner-applications/{partnerApplication}` | Yes | Owner hoặc `partner_application.view_all` | `PartnerApplicationController@show` | Detail kèm media |
| PATCH | `/partner-applications/{partnerApplication}/approve` | Yes | `partner_application.approve` | `PartnerApplicationController@approve` | Transaction, set approved, gán role `venue_owner`, tạo notification |
| PATCH | `/partner-applications/{partnerApplication}/reject` | Yes | `partner_application.reject` | `PartnerApplicationController@reject` | Set rejected, lưu reason, tạo notification |

Partner application hiện lưu `business_name`, `tax_code`; giấy tờ dùng media polymorphic upload riêng. Chưa có eKYC provider thật.

## 6. Venue / Court / Media

Venue clusters:

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/venue-clusters` | `venue.view` | `VenueClusterController@index` | Filter `status`, `city`, `district`, `owner_id`, `search`, `court_type_id`, `min_price`, `max_price`, `lat/lng/radius_km` |
| POST | `/venue-clusters` | `venue.create` | `VenueClusterController@store` | Tạo cụm sân |
| GET | `/venue-clusters/{venueCluster}` | `venue.view` | `VenueClusterController@show` | Detail kèm courts/config/pricing/media |
| PUT | `/venue-clusters/{venueCluster}` | `venue.update` | `VenueClusterController@update` | Update thông tin, lat/lng, amenities |
| DELETE | `/venue-clusters/{venueCluster}` | `venue.delete` | `VenueClusterController@destroy` | Delete |
| PATCH | `/venue-clusters/{venueCluster}/approve` | `venue.approve` | `VenueClusterController@approve` | Set active, tạo booking config mặc định, notification |
| PATCH | `/venue-clusters/{venueCluster}/reject` | `venue.reject` | `VenueClusterController@reject` | Set rejected + reason |

Venue courts:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/venue-courts` | `court.view` | `VenueCourtController@index` |
| POST | `/venue-courts` | `court.create` | `VenueCourtController@store` |
| GET | `/venue-courts/{venueCourt}` | `court.view` | `VenueCourtController@show` |
| PUT | `/venue-courts/{venueCourt}` | `court.update` | `VenueCourtController@update` |
| DELETE | `/venue-courts/{venueCourt}` | `court.delete` | `VenueCourtController@destroy` |

Court types:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/court-types` | `court.view` | `CourtTypeController@index` |
| POST | `/court-types` | `court.create` | `CourtTypeController@store` |
| GET | `/court-types/{courtType}` | `court.view` | `CourtTypeController@show` |
| PUT | `/court-types/{courtType}` | `court.update` | `CourtTypeController@update` |
| DELETE | `/court-types/{courtType}` | `court.delete` | `CourtTypeController@destroy` |

Media:

| Method | Endpoint | Auth | Controller | Ghi chú |
|---|---|---|---|---|
| POST | `/media/upload` | Yes | `MediaController@upload` | Upload image max 5MB, polymorphic: `user`, `partner_application`, `refund`, `venue_cluster`, `venue_court`, `player_post`, `review`, `complaint` |
| DELETE | `/media/{media}` | Yes | `MediaController@destroy` | Xóa DB record và file storage |

Media service xóa file nếu DB insert fail. Avatar có thể dùng media `user` hoặc update `avatar_url` qua profile.

## 7. Pricing / Config / Availability / Slot Lock

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/price-slots` | `pricing.view` | `PriceSlotController@index` | Filter `cluster_id`, `is_active` |
| POST | `/price-slots` | `pricing.create` | `PriceSlotController@store` | `start_time`, `end_time`, `price`, `apply_to_days` |
| GET | `/price-slots/{priceSlot}` | `pricing.view` | `PriceSlotController@show` | Detail |
| PUT | `/price-slots/{priceSlot}` | `pricing.update` | `PriceSlotController@update` | Update |
| DELETE | `/price-slots/{priceSlot}` | `pricing.delete` | `PriceSlotController@destroy` | Delete |
| GET | `/holiday-prices` | `pricing.view` | `HolidayPriceController@index` | Filter `cluster_id`, `from`, `to` |
| POST | `/holiday-prices` | `pricing.create` | `HolidayPriceController@store` | `updateOrCreate` theo `cluster_id + holiday_date` |
| GET | `/holiday-prices/{holidayPrice}` | `pricing.view` | `HolidayPriceController@show` | Detail |
| PUT | `/holiday-prices/{holidayPrice}` | `pricing.update` | `HolidayPriceController@update` | Update |
| DELETE | `/holiday-prices/{holidayPrice}` | `pricing.delete` | `HolidayPriceController@destroy` | Delete |
| GET | `/booking-configs` | `pricing.view` | `BookingConfigController@index` | List config |
| PUT | `/booking-configs/{bookingConfig}` | `pricing.update` | `BookingConfigController@update` | min/max duration, cancel hours, refund percent |
| GET | `/venue-courts/{venueCourt}/available-slots` | `court.view` | `AvailabilityController@courtSlots` | Query `date`, optional `duration_minutes` |
| GET | `/venue-courts/{venueCourt}/slot-locks` | `slot_lock.view` | `SlotLockController@index` | Manual locks |
| POST | `/venue-courts/{venueCourt}/slot-locks` | `slot_lock.create` | `SlotLockController@store` | Manual lock, check overlap booking/locks in transaction |
| DELETE | `/venue-courts/{venueCourt}/slot-locks/{slotLock}` | `slot_lock.delete` | `SlotLockController@destroy` | Only manual lock can be deleted |

Business notes:

- Availability trừ bookings `pending_payment/paid/checked_in/completed` và active slot locks.
- Manual slot lock dùng `slot_locks.lock_type=manual` và long-lived expiry.
- Auto slot lock được tạo khi booking pending payment.
- Price slot create/update chặn overlap giữa các price slots cùng cluster/day/time.

## 8. Booking

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/bookings` | `booking.view` | `BookingController@index` | Filter `status`, `court_id`, `cluster_id`, `customer_id`, `date`; scoped nếu không có `booking.manage_all` |
| POST | `/bookings` | `booking.create` | `BookingController@store` | Tạo booking thật qua `BookingService` |
| GET | `/bookings/{booking}` | `booking.view` | `BookingController@show` | Detail kèm payments/refunds/review |
| PATCH | `/bookings/{booking}/cancel` | `booking.cancel` | `BookingController@cancel` | Cancel booking |
| PATCH | `/bookings/{booking}/confirm` | `booking.update` | `BookingController@confirm` | pending_payment -> paid |
| PATCH | `/bookings/{booking}/check-in` | `booking.checkin` | `BookingController@checkIn` | paid -> checked_in |
| PATCH | `/bookings/{booking}/complete` | `booking.update` | `BookingController@complete` | paid/checked_in -> completed + ledger |

Booking create body:

```json
{
  "court_id": "uuid",
  "booking_date": "2026-05-20",
  "start_time": "18:00",
  "end_time": "19:00",
  "source": "online",
  "walk_in_name": "Khach tai quay",
  "walk_in_phone": "0901234567",
  "note": "optional"
}
```

Critical business rules:

- `DB::transaction()` wraps create/cancel/confirm/check-in/complete.
- Uses `lockForUpdate()` on court, booking conflict query, slot lock query.
- Prevents overlap with active bookings and active locks.
- Creates auto `slot_locks` for pending booking.
- Rejects booking in past and `end_time <= start_time`.
- Status values in schema: `pending_payment`, `paid`, `checked_in`, `completed`, `cancelled`, `expired`.
- Command `bookings:expire-pending --minutes=15` expires old pending bookings and cleans expired auto locks.

## 9. Payment / Refund / Finance

Payments:

| Method | Endpoint | Auth | Permission | Controller | Ghi chú |
|---|---|---|---|---|---|
| GET | `/payments` | Yes | `payment.view` | `PaymentController@index` | Scoped by booking owner/creator unless `payment.manage_all` |
| POST | `/payments` | Yes | `payment.create` | `PaymentController@store` | Create pending payment |
| GET | `/payments/{payment}` | Yes | `payment.view` | `PaymentController@show` | Hidden `gateway_response` |
| PATCH | `/payments/{payment}/mark-paid` | Yes | `payment.update` | `PaymentController@markPaid` | Manual mark paid, sets booking paid |
| PATCH | `/payments/{payment}/mark-failed` | Yes | `payment.update` | `PaymentController@markFailed` | Mark failed |
| POST | `/payments/{payment}/retry` | Yes | `payment.create` | `PaymentWebhookController@retry` | Create new pending payment attempt |
| GET | `/payments/{payment}/checkout` | No | Signed token | `PaymentController@checkout` | Read local MVP checkout session |
| POST | `/payments/{payment}/checkout/complete` | No | Signed token | `PaymentController@completeCheckout` | Complete local MVP checkout and update payment/booking |
| POST | `/payments/webhook/{gateway}` | No | Optional HMAC | `PaymentWebhookController@callback` | MVP gateway callback; verifies `X-Webhook-Signature` when `PAYMENT_WEBHOOK_SECRET` is set |

Payment body:

```json
{
  "booking_id": "uuid",
  "amount": 100000,
  "method": "cash",
  "gateway_txn_id": "optional",
  "gateway_response": {}
}
```

Webhook body:

```json
{
  "gateway_txn_id": "GW123",
  "payment_id": "uuid",
  "amount": 100000,
  "status": "success"
}
```

Refunds:

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/refunds` | `payment.refund` | `RefundController@index` | Filter status/booking |
| POST | `/refunds` | `payment.refund` | `RefundController@store` | Create refund, check amount remaining |
| GET | `/refunds/{refund}` | `payment.refund` | `RefundController@show` | Detail |
| PATCH | `/refunds/{refund}/approve` | `payment.refund` | `RefundController@approve` | Complete refund |
| PATCH | `/refunds/{refund}/reject` | `payment.refund` | `RefundController@reject` | Schema lacks rejected status, current implementation deletes pending refund and returns payload |

Finance:

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/platform-fee-configs` | `system_config.view` | `PlatformFeeConfigController@index` | List effective fee configs |
| POST | `/platform-fee-configs` | `system_config.update` | `PlatformFeeConfigController@store` | Create new config |
| GET | `/venue-fee-ledgers` | `payment.manage_all` | `VenueFeeLedgerController@index` | Ledger list, filter `cluster_id/status` |

Notes:

- Online payment supports signed local MVP checkout URL/token; webhook can enforce HMAC when `PAYMENT_WEBHOOK_SECRET` is configured.
- `gateway_response` is hidden from serialized payment responses.
- Venue fee ledger is created on booking complete.
- Settlement/reconciliation action endpoints are implemented for one/batch ledger reconciliation.

## 10. Recruitment / Player Posts

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/player-posts` | `recruitment.view` | `PlayerPostController@index` | Filter status/sport/date |
| POST | `/player-posts` | `recruitment.create` | `PlayerPostController@store` | Create post |
| GET | `/player-posts/{playerPost}` | `recruitment.view` | `PlayerPostController@show` | Detail kèm participants/media |
| PUT | `/player-posts/{playerPost}` | `recruitment.update` | `PlayerPostController@update` | Author or super_admin |
| DELETE | `/player-posts/{playerPost}` | `recruitment.delete` | `PlayerPostController@destroy` | Author or super_admin |
| POST | `/player-posts/{playerPost}/join` | `recruitment.join` | `PlayerPostParticipantController@join` | Transaction, no duplicate |
| DELETE | `/player-posts/{playerPost}/leave` | `recruitment.join` | `PlayerPostParticipantController@leave` | Update current_players if needed |
| PATCH | `/player-posts/{playerPost}/participants/{participant}/approve` | `recruitment.approve_participant` | `PlayerPostParticipantController@approve` | Author or super_admin, enforce capacity |
| PATCH | `/player-posts/{playerPost}/participants/{participant}/reject` | `recruitment.reject_participant` | `PlayerPostParticipantController@reject` | Author or super_admin |

Business rules:

- `RecruitmentService` uses transaction for join/approve/leave.
- Prevents duplicate join.
- Keeps `current_players` synced.
- Capacity uses `min(max_players, needed_players + 1)` to include the author.

## 11. Review / Rating / Report / Complaint

Reviews:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/reviews` | `review.view` | `ReviewController@index` |
| POST | `/reviews` | `review.create` | `ReviewController@store` |
| GET | `/reviews/{review}` | `review.view` | `ReviewController@show` |
| PUT | `/reviews/{review}` | `review.update` | `ReviewController@update` |
| DELETE | `/reviews/{review}` | `review.delete` | `ReviewController@destroy` |

Review rules:

- Only completed bookings can be reviewed.
- Only booking customer or `review.moderate` can create/update.
- Rating aggregate syncs `venue_clusters.rating_avg/rating_count`.

Player ratings:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/player-ratings` | `review.view` | `PlayerRatingController@index` |
| POST | `/player-ratings` | `review.create` | `PlayerRatingController@store` |

Reports:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| POST | `/reports` | `report.create` | `ReportController@store` |
| GET | `/reports` | `report.view` | `ReportController@index` |
| GET | `/reports/{report}` | `report.view` | `ReportController@show` |
| PATCH | `/reports/{report}/review` | `report.resolve` | `ReportController@review` |
| PATCH | `/reports/{report}/resolve` | `report.resolve` | `ReportController@resolve` |
| PATCH | `/reports/{report}/dismiss` | `report.dismiss` | `ReportController@dismiss` |

Report supports aliases: `user`, `review`, `player_post`, `player_rating`.

Complaints:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| POST | `/complaints` | `complaint.create` | `ComplaintController@store` |
| GET | `/complaints` | `complaint.view` | `ComplaintController@index` |
| GET | `/complaints/{complaint}` | `complaint.view` | `ComplaintController@show` |
| PATCH | `/complaints/{complaint}/resolve` | `complaint.resolve` | `ComplaintController@resolve` |

Complaint rules:

- Customer can complain only for own booking, unless user has `complaint.resolve`.
- Evidence images can be attached via media upload with `mediable_type=complaint`.

## 12. Notification / Chat / Audit

Notifications:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/notifications` | `notification.view` | `NotificationController@index` |
| PATCH | `/notifications/{notification}/read` | `notification.view` | `NotificationController@read` |
| PATCH | `/notifications/read-all` | `notification.view` | `NotificationController@readAll` |
| POST | `/notifications` | `notification.create` | `NotificationController@store` |

NotificationService creates DB notifications for partner approve/reject, venue approve, payment success, reminders and admin/system send. There is no FCM/OneSignal/device push provider yet.

Chat:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/conversations` | `chat.view` | `ConversationController@index` |
| POST | `/conversations` | `chat.send` | `ConversationController@store` |
| GET | `/conversations/{conversation}/messages` | `chat.view` | `MessageController@index` |
| POST | `/conversations/{conversation}/messages` | `chat.send` | `MessageController@store` |
| POST | `/conversations/{conversation}/read` | `chat.view` | `MessageController@read` |

Chat uses polling API. Message actions verify user belongs to conversation.

Audit:

| Method | Endpoint | Permission | Controller |
|---|---|---|---|
| GET | `/audit-logs` | `audit_log.view` | `AuditLogController@index` |

AuditLogService exists, but auto-recording sensitive controller actions is not wired broadly yet.

## 13. Dashboard / Statistics / Analytics

| Method | Endpoint | Permission | Controller | Ghi chú |
|---|---|---|---|---|
| GET | `/dashboard/admin/overview` | `dashboard.admin` | `DashboardController@adminOverview` | Users, venues, courts, bookings, revenue, pending moderation |
| GET | `/dashboard/venue-owner/overview` | `dashboard.venue_owner` | `DashboardController@venueOwnerOverview` | Owner scoped KPI/charts/tasks |
| GET | `/dashboard/revenue` | `dashboard.admin` | `DashboardController@revenue` | Revenue grouped by day/month |
| GET | `/dashboard/peak-hours` | `dashboard.admin` | `DashboardController@peakHours` | Heatmap style data |
| GET | `/dashboard/conversion-rate` | `dashboard.admin` | `DashboardController@conversionRate` | Booking conversion MVP |

Venue owner dashboard response includes:

- `venues`: owner venues summary.
- `kpis`: gross/net revenue, platform fee, bookings, occupancy, courts, rating, complaint/refund/task counts.
- `charts`: revenue by day, bookings by day, status breakdown, peak hours.
- `top_courts`, `today_schedule`, `recent_reviews`, `tasks`, `available_actions`.

## 14. Console Commands / Jobs

| Command | Schedule | File | Purpose |
|---|---|---|---|
| `php artisan bookings:expire-pending --minutes=15` | Every minute via `routes/console.php` | `app/Console/Commands/ExpirePendingBookings.php` | Mark old `pending_payment` bookings as `expired`, delete related auto slot locks, cleanup expired auto locks |

Run scheduler locally:

```bash
php artisan schedule:run
```

Run cleanup manually:

```bash
php artisan bookings:expire-pending --minutes=15
```

## 15. Phase 7.6 Added / Completed APIs

| Module | Method | Endpoint | Permission | Purpose |
|---|---|---|---|---|
| Auth | `POST` | `/auth/register` | Public | Creates `pending_verify` user, assigns `player`, creates register verification code |
| Auth | `POST` | `/auth/verify-code` | Public | Activates account and returns Sanctum token for register verification |
| Auth | `POST` | `/auth/login` | Public | Accepts `identifier` as email or phone and blocks `pending_verify` accounts |
| Profile | `POST` | `/profile/avatar` | Auth | Uploads avatar media and updates `users.avatar_url` |
| Venue analytics | `POST` | `/venue-clusters/{venueCluster}/view` | `venue.view` | Records a venue view event for conversion analytics |
| Venue availability | `GET` | `/venue-clusters/{venueCluster}/available-slots` | `court.view` | Returns available slots for all active courts in a cluster |
| Venue staff | `GET` | `/venue-clusters/{venueCluster}/staff` | `venue_staff.manage` | Lists scoped staff of a venue cluster |
| Venue staff | `POST` | `/venue-clusters/{venueCluster}/staff` | `venue_staff.manage` | Creates or attaches a `venue_staff` user scoped to a cluster |
| Venue staff | `DELETE` | `/venue-clusters/{venueCluster}/staff/{user}` | `venue_staff.manage` | Removes scoped `venue_staff` role from a user |
| Partner application | `POST` | `/partner-applications/{partnerApplication}/documents` | Auth / owner or admin | Uploads legal/application document media |
| Booking | `POST` | `/bookings/counter` | `booking.create` | Creates counter booking and cash payment atomically |
| Refund | `POST` | `/refunds/{refund}/proof` | `payment.refund` | Uploads refund proof image linked via polymorphic media |
| Finance | `GET` | `/finance/transactions` | `payment.manage_all` | Unified payment/refund/platform fee transaction feed |
| Finance | `POST` | `/venue-fee-ledgers/reconcile-batch` | `payment.manage_all` | Batch reconciles pending venue fee ledger rows |
| Finance | `PATCH` | `/venue-fee-ledgers/{venueFeeLedger}/reconcile` | `payment.manage_all` | Marks one ledger row as reconciled |
| Notification | `POST` | `/notifications` | `notification.create` | Creates DB notifications for users or a role |
| Dashboard | `GET` | `/dashboard/venue-density` | `dashboard.admin` | Returns district/city venue density aggregate |

Additional backend rules now enforced:

- Register flow is verify-first; login rejects `pending_verify`.
- Verification and reset codes are sent through Laravel Mail when `MAIL_*` is configured; local default can log mail.
- Partner approve/reject sends DB notification and email.
- Payment webhook can enforce HMAC by setting `PAYMENT_WEBHOOK_SECRET` and sending `X-Webhook-Signature`.
- Mutating authenticated API requests are auto-recorded into `audit_logs` by middleware with sensitive fields stripped.
- User profile supports `address`, `ward`, `district`, and `city`.
- Booking slot lock TTL is 15 minutes.
- Booking duration is checked against `booking_configs`.
- Cancel booking enforces cancel window for normal users and creates refund requests from refund policy.
- Price slots reject overlaps for the same cluster/day/time.
- Manual slot locks use a non-null long-lived expiry for MySQL compatibility.
- Court delete is blocked when future active bookings exist.
- Payment success dispatches DB notifications for customer and venue owner.
- `bookings:send-reminders --minutes=120` creates reminder notifications and is scheduled every five minutes.

## Phase 7.9 Advanced Moderation, Content, Community, Favorites

Verified route count after this update: `188` API routes, `194` total Laravel routes.

### Advanced business rules

- Recruitment create/update/join/approve now rejects overlapping player posts for the same user by `play_date`, `start_time`, and `end_time`.
- Conflict checks include `pending` and `approved` participations and ignore `rejected`/`cancelled`.
- Join/approve runs in `DB::transaction()` and uses `lockForUpdate()` on the target post and participant rows.
- Player post `end_time` is now required so schedule conflicts can be checked.
- Venue reviews require a completed booking, only the booking customer can review unless the actor can moderate, and one booking can only have one review.
- Player ratings require a shared `player_post` relationship: author-to-approved participant, approved participant-to-author, or approved participant-to-approved participant. Self-rating is rejected.
- Booking creation rejects locked venues.
- Manual user and venue locks require `reason`; user lock revokes Sanctum tokens and login remains blocked for `status=locked`.

### Reports

`POST /reports`, `GET /reports`, `GET /reports/{report}`, `PATCH /reports/{report}/review`, `PATCH /reports/{report}/resolve`, and `PATCH /reports/{report}/dismiss` remain separate from rating/review APIs.

Supported `reportable_type` aliases now include:

| Alias | Model |
|---|---|
| `user` | `App\Models\User` |
| `venue` / `venue_cluster` | `App\Models\VenueCluster` |
| `booking` | `App\Models\Booking` |
| `review` | `App\Models\Review` |
| `player_post` | `App\Models\PlayerPost` |
| `player_rating` | `App\Models\PlayerRating` |
| `community_post` | `App\Models\CommunityPost` |

Supported report `action_taken` values now include content, account, and venue actions: `warning`, `content_hidden`, `content_deleted`, `user_suspended`, `user_banned`, `account_locked`, `venue_warned`, `venue_locked`.

### Moderation config and automatic evaluation

| Method | Endpoint / Command | Permission | Notes |
|---|---|---|---|
| `GET` | `/moderation-configs` | `moderation_config.view` | Lists report/rating thresholds and auto-lock reasons |
| `PUT` | `/moderation-configs/{key}` | `moderation_config.update` | Updates a threshold or reason |
| command | `php artisan moderation:evaluate` | CLI | Warns or locks users/venues by reports and bad ratings |

Default keys include `warning_report_count_week`, `auto_ban_report_count_month`, `venue_warning_report_count_week`, `venue_auto_lock_report_count_month`, `bad_rating_threshold`, `bad_rating_count_month_warning`, `auto_lock_rating_avg_threshold`, `min_rating_count_for_auto_lock`, and auto-lock reason keys for users/venues.

### System policies, banners, system posts

| Module | Method | Endpoint | Permission |
|---|---|---|---|
| Public policies | `GET` | `/system-policies/public` | None |
| Policy admin | `GET/POST/PUT/DELETE` | `/system-policies`, `/system-policies/{policy}` | `system_policy.*` |
| Public banners | `GET` | `/banners/public` | None |
| Banner admin | `GET/POST/PUT/DELETE/PATCH` | `/banners`, `/banners/{banner}`, `/banners/{banner}/toggle` | `banner.*` |
| Public system posts | `GET` | `/system-posts/public` | None |
| System post admin | `GET/POST/PUT/DELETE/PATCH` | `/system-posts`, `/system-posts/{post}`, `/system-posts/{post}/publish` | `system_post.*` |

### Community posts and favorite venues

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| `GET/POST` | `/community-posts` | `community_post.view/create` | Feed and create posts |
| `GET/PUT/DELETE` | `/community-posts/{post}` | `community_post.view/update/delete` | Owner can manage own post; moderator can manage all |
| `POST/DELETE` | `/community-posts/{post}/like` | `community_post.view` | Unique like per user |
| `GET/POST` | `/community-posts/{post}/comments` | `community_post.view` | Visible comments and create comment |
| `DELETE` | `/community-comments/{comment}` | `community_post.delete` | Owner or moderator |
| `POST` | `/community-posts/{post}/view` | `community_post.view` | View count throttled per user for 30 minutes |
| `PATCH` | `/community-posts/{post}/hide` | `community_post.moderate` | Admin/system moderation |
| `PATCH` | `/community-posts/{post}/publish` | `community_post.moderate` | Republish hidden content |
| `GET` | `/favorite-venues` | `favorite_venue.view` | Current user's saved venues |
| `POST/DELETE` | `/venue-clusters/{venueCluster}/favorite` | `favorite_venue.update` | Unique favorite per user/venue |

`GET /venue-clusters?favorite_only=true` filters saved venues, and `GET /community-posts?prioritize_favorite_venues=true` sorts posts from favorite venues first.

## Phase 7.10 Final API Review Fixes

Final review focused on cross-module business rules and routes that must be callable from Postman/Vue.

### Code fixes after review

| Area | Fix |
|---|---|
| Booking | New bookings now reject any start time in the past, not only fully elapsed slots |
| Booking notifications | Booking created/cancelled/confirmed events create DB notifications for affected users |
| Payment create | `POST /payments` only accepts bookings in `pending_payment` and blocks duplicate pending/success payments |
| Payment state flow | `mark-paid`, `mark-failed`, checkout complete, and gateway webhook now use one service path with transaction/idempotency guards |
| Payment retry | Pending retry returns the existing checkout; failed retry creates a new attempt only when booking is still `pending_payment` |
| Refund | Approve/reject creates DB notifications for the booking customer |
| Report | Resolve/dismiss keeps report logic separate from reviews and notifies the reporter |
| Recruitment | Join/approve/reject creates participant notifications while preserving schedule conflict locks |
| Venue visibility | Non-admin/non-owner venue listing/detail only exposes active venues; owners/admins can still inspect scoped private venues |
| Community comments | Deleting a parent comment decrements `comment_count` by the deleted subtree size |

### Final verification commands

- `php artisan route:list`: passed.
- Route controller reflection check: `route_controller_missing=0`.
- Route permission check against seeded `permissions.code`: `route_permission_missing=0`.
- `php artisan migrate:fresh --seed`: passed.
- `php artisan moderation:evaluate`: passed.
- `php artisan test`: passed.
