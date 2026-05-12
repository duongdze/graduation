# Frontend API Usage Map

Base URL: `http://localhost:8000/api`

Auth header after login:

```http
Authorization: Bearer <access_token>
```

## 1. Login / Register / Forgot Password

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Login | `POST /auth/login` | ready | Login bằng email/password, trả token + user roles/permissions |
| Register | `POST /auth/register` | ready | Tạo `pending_verify`, gán role player, sinh verification code |
| Send verification code | `POST /auth/send-verification-code` | ready | Local/testing trả debug code; email/SMS provider thật là phase 2 |
| Verify code | `POST /auth/verify-code` | ready | Verify code hash, active account, set `email_verified_at` |
| Forgot password | `POST /auth/forgot-password` | ready | Gửi code qua Laravel Mail/log mailer theo `.env` |
| Reset password | `POST /auth/reset-password` | ready | Reset password + revoke tokens |
| Logout | `POST /auth/logout` | ready | Xóa current token |

## 2. Profile

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Profile detail | `GET /auth/me` | ready | Trả user, roles, permissions |
| Edit profile | `PUT /profile` | ready | full_name/email/phone/avatar_url/bio/sports |
| Change password | `PUT /profile/password` | ready | Revoke tokens sau đổi mật khẩu |
| Avatar upload | `POST /profile/avatar` | ready | Upload image và update `users.avatar_url` |

## 3. Partner Application

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Submit partner application | `POST /partner-applications` | ready | Tạo đơn chủ sân |
| Upload legal files | `POST /partner-applications/{id}/documents` | ready | Upload legal documents gắn trực tiếp vào application |
| My applications | `GET /partner-applications/my` | ready | Pagination |
| Application detail | `GET /partner-applications/{id}` | ready | Owner hoặc admin xem |
| eKYC | None | phase 2 | Out of scope/provider |

## 4. Admin User Management

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| User list | `GET /users?search=&status=&per_page=` | ready | Pagination |
| User detail | `GET /users/{id}` | ready | Roles + permission revokes |
| Create user | `POST /users` | ready | Hash password |
| Update user | `PUT /users/{id}` | ready | Optional password |
| Delete user | `DELETE /users/{id}` | ready | Hard delete model call |
| Lock/unlock | `PATCH /users/{id}/lock`, `PATCH /users/{id}/unlock` | ready | Lock revokes all Sanctum tokens |

## 5. Admin RBAC

| Vue screen | API | Status |
|---|---|---|
| Role list | `GET /roles` | ready |
| Role create/update/delete | `POST /roles`, `PUT /roles/{id}`, `DELETE /roles/{id}` | ready |
| Permission list | `GET /permissions` | ready |
| Permission create/update/delete | `POST /permissions`, `PUT /permissions/{id}`, `DELETE /permissions/{id}` | ready |
| Assign permissions to role | `POST /roles/{role}/permissions/sync` | ready |
| Assign roles to user | `POST /users/{user}/roles/sync` | ready |
| Revoke user permission | `POST /users/{user}/permissions/revoke`, `DELETE /users/{user}/permissions/revoke/{permission}` | ready |

## 6. Admin Partner Approval

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Application list | `GET /partner-applications?status=&search=&per_page=` | ready | Requires `partner_application.view_all` |
| Application detail | `GET /partner-applications/{id}` | ready | Includes media |
| Approve | `PATCH /partner-applications/{id}/approve` | ready | Transaction + role `venue_owner` |
| Reject | `PATCH /partner-applications/{id}/reject` | ready | DB notification và Laravel Mail/log mailer |

## 7. Admin Venue Approval

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Pending venues | `GET /venue-clusters?status=pending` | ready | Pagination |
| Venue detail | `GET /venue-clusters/{id}` | ready | Courts/config/pricing/media |
| Approve venue | `PATCH /venue-clusters/{id}/approve` | ready | Creates default booking config |
| Reject venue | `PATCH /venue-clusters/{id}/reject` | ready | Requires reject_reason |

## 8. Admin Dashboard

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Overview cards | `GET /dashboard/admin/overview` | ready | Users, venues, courts, bookings, revenue, pending items |
| Revenue chart | `GET /dashboard/revenue?from=&to=&group_by=day` | ready | Admin only |
| Peak hours | `GET /dashboard/peak-hours?from=&to=&cluster_id=` | ready | Heatmap data |
| Conversion rate | `GET /dashboard/conversion-rate?from=&to=&cluster_id=` | ready | Dùng venue view tracking và paid booking conversion |
| Venue density map | `GET /dashboard/venue-density` | ready | Aggregate theo city/district với lat/lng trung bình |

## 9. Venue Owner Dashboard

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Owner dashboard overview | `GET /dashboard/venue-owner/overview?from=&to=&cluster_id=&limit=` | ready | KPIs, charts, top courts, today schedule, reviews, tasks |
| Today schedule | Same endpoint or `GET /bookings?date=&cluster_id=` | ready | FE can use dashboard or booking list |
| Owner revenue chart | `GET /dashboard/venue-owner/overview` | ready | `charts.revenue_by_day` |
| Owner tasks | `GET /dashboard/venue-owner/overview` | ready | pending_payment, checkins, complaints, refunds, maintenance courts |

## 10. Venue / Court Management

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Venue list/detail | `GET /venue-clusters`, `GET /venue-clusters/{id}` | ready | Public/admin/owner use |
| Create venue | `POST /venue-clusters` | ready | Permission `venue.create` |
| Update venue | `PUT /venue-clusters/{id}` | ready | Owner/staff scoped guard |
| Upload venue media | `POST /media/upload` | ready | `mediable_type=venue_cluster` |
| Court list/detail | `GET /venue-courts?cluster_id=`, `GET /venue-courts/{id}` | ready | Includes court type |
| Create/update court | `POST /venue-courts`, `PUT /venue-courts/{id}` | ready | status active/maintenance |
| Delete court | `DELETE /venue-courts/{id}` | ready | Chặn xóa nếu còn future active bookings |
| Court types | `/court-types` CRUD | ready | Admin catalog |

## 11. Pricing Management

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Price slots | `GET /price-slots?cluster_id=`, `POST /price-slots`, `PUT /price-slots/{id}`, `DELETE /price-slots/{id}` | ready | CRUD và overlap validation |
| Day of week pricing | `POST /price-slots` with `apply_to_days` | ready | Days 0-6 |
| Holiday pricing | `/holiday-prices` CRUD | ready | `updateOrCreate` by cluster/date |
| Booking config | `GET /booking-configs`, `PUT /booking-configs/{cluster_id}` | ready | Booking create enforces min/max duration |

## 12. Manual Slot Lock

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Slot lock list | `GET /venue-courts/{court}/slot-locks?booking_date=` | ready | Manual locks only |
| Create manual lock | `POST /venue-courts/{court}/slot-locks` | ready | Transaction + overlap check |
| Delete manual lock | `DELETE /venue-courts/{court}/slot-locks/{slotLock}` | ready | Cannot delete auto lock |

## 13. Booking Management

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Booking list/calendar | `GET /bookings?date=&cluster_id=&court_id=&status=` | ready | Scoped unless manage_all |
| Booking detail | `GET /bookings/{id}` | ready | Payments/refunds/review |
| Create booking | `POST /bookings` | ready | Transaction + lockForUpdate |
| Counter booking | `POST /bookings/counter` | ready | Atomic counter booking + cash payment |
| Cancel booking | `PATCH /bookings/{id}/cancel` | ready | Enforces cancel window and creates pending refund when applicable |
| Confirm paid | `PATCH /bookings/{id}/confirm` | ready | pending_payment -> paid |
| Check-in | `PATCH /bookings/{id}/check-in` | ready | paid -> checked_in |
| Complete | `PATCH /bookings/{id}/complete` | ready | Creates fee ledger |
| Available slots | `GET /venue-courts/{court}/available-slots?date=&duration_minutes=`, `GET /venue-clusters/{id}/available-slots` | ready | Court and cluster availability |

## 14. Payment / Refund

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Payment list/detail | `GET /payments`, `GET /payments/{id}` | ready | Hidden gateway_response |
| Create payment | `POST /payments` | ready | Creates pending payment and returns signed local MVP checkout URL/token |
| Manual mark paid/failed | `PATCH /payments/{id}/mark-paid`, `PATCH /payments/{id}/mark-failed` | ready | Admin/staff use |
| Gateway callback | `POST /payments/webhook/{gateway}` | ready | Optional HMAC verification with `PAYMENT_WEBHOOK_SECRET` |
| Retry payment | `POST /payments/{id}/retry` | ready | New pending attempt |
| Refund list/detail | `GET /refunds`, `GET /refunds/{id}` | ready | Permission `payment.refund` |
| Create refund | `POST /refunds` | ready | Amount can be omitted to auto-calculate from booking config |
| Approve/reject refund | `PATCH /refunds/{id}/approve`, `PATCH /refunds/{id}/reject` | ready | Reject removes pending refund because schema has no rejected status |
| Refund proof | `POST /refunds/{id}/proof` | ready | Upload image proof linked to refund |
| Platform fee config | `GET /platform-fee-configs`, `POST /platform-fee-configs` | ready | Admin/system config |
| Venue fee ledgers | `GET /venue-fee-ledgers`, `PATCH /venue-fee-ledgers/{id}/reconcile`, `POST /venue-fee-ledgers/reconcile-batch` | ready | List and reconcile one/batch |

## 15. Recruitment

| Vue screen | API | Status |
|---|---|---|
| Player post list/detail | `GET /player-posts`, `GET /player-posts/{id}` | ready |
| Create/update/delete post | `POST /player-posts`, `PUT /player-posts/{id}`, `DELETE /player-posts/{id}` | ready |
| Join/leave | `POST /player-posts/{id}/join`, `DELETE /player-posts/{id}/leave` | ready |
| Approve/reject participant | `PATCH /player-posts/{id}/participants/{participant}/approve`, `PATCH /player-posts/{id}/participants/{participant}/reject` | ready |

## 16. Review / Complaint / Report

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Reviews | `/reviews` CRUD | ready | Review only after completed booking |
| Player ratings | `GET /player-ratings`, `POST /player-ratings` | ready | Rating aggregate |
| Reports | `/reports` create/list/detail/review/resolve/dismiss | ready | Polymorphic aliases |
| Complaints | `/complaints` create/list/detail/resolve | ready | Evidence via media upload |

## 17. Notification

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Notification list | `GET /notifications?is_read=` | ready | Current user only |
| Mark read | `PATCH /notifications/{id}/read` | ready | Ownership check |
| Mark all read | `PATCH /notifications/read-all` | ready | Current user only |
| Push notification | None | phase 2 | Requires provider/device tokens |
| Booking reminder | `php artisan bookings:send-reminders --minutes=120` | ready | Scheduled every five minutes; creates DB notifications |

## 18. Chat

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Conversation list | `GET /conversations` | ready | User's conversations |
| Create conversation | `POST /conversations` | ready | `participant_ids` |
| Message list | `GET /conversations/{id}/messages` | ready | Participant check |
| Send message | `POST /conversations/{id}/messages` | ready | `content` |
| Mark read | `POST /conversations/{id}/read` | ready | Updates `last_read_at` |
| Realtime chat | None | phase 2 | Polling API is MVP |

## 19. Audit Log

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Audit log list | `GET /audit-logs?actor_id=&entity_type=&action=` | ready | List ready |
| Auto audit sensitive actions | All mutating authenticated `/api/*` routes | ready | Middleware records successful POST/PUT/PATCH/DELETE |

## 20. Phase 7.6 Newly Available APIs

| Vue screen | API | Status | Ghi chú |
|---|---|---|---|
| Register verification | `POST /auth/register`, `POST /auth/verify-code` | ready | Register creates pending account; verify returns token |
| Login | `POST /auth/login` | ready | Use `identifier` for email or phone |
| Forgot password | `POST /auth/forgot-password` | ready | Sends code through Laravel Mail/log mailer |
| Profile avatar | `POST /profile/avatar` | ready | Multipart image upload updates `avatar_url` |
| Profile address | `PUT /profile` | ready | Supports `address`, `ward`, `district`, `city` |
| Partner documents | `POST /partner-applications/{id}/documents` | ready | Collections: business license, ID front/back, venue photo |
| Venue detail analytics | `POST /venue-clusters/{id}/view` | ready | Call on venue detail/card view |
| Cluster availability | `GET /venue-clusters/{id}/available-slots` | ready | All active courts in one response |
| Venue staff management | `GET/POST /venue-clusters/{id}/staff`, `DELETE /venue-clusters/{id}/staff/{user}` | ready | Owner-scoped staff role |
| Counter booking | `POST /bookings/counter` | ready | One-step walk-in/cash booking |
| Finance transaction history | `GET /finance/transactions` | ready | Unified payment/refund/platform fee feed |
| Ledger reconciliation | `PATCH /venue-fee-ledgers/{id}/reconcile`, `POST /venue-fee-ledgers/reconcile-batch` | ready | Admin finance action |
| Refund proof | `POST /refunds/{id}/proof` | ready | Upload image proof linked to refund |
| Admin notification sender | `POST /notifications` | ready | Create DB notification by users or role |
| Venue density dashboard | `GET /dashboard/venue-density` | ready | Map/heatmap aggregate |

## 21. Blocked / Phase 2 Summary

| Feature | Status | Reason |
|---|---|---|
| eKYC | phase 2 | Needs third-party provider |
| Refund proof | ready | Implemented via `POST /refunds/{id}/proof` |
| Reminder notification | ready | Implemented via scheduled command |
| Payment gateway production | phase 2 | Optional HMAC exists; real gateway SDK/redirect still needs credentials |
| SMS/push provider | phase 2 | Email uses Laravel Mail; SMS/push still need provider |
| WebSocket realtime | phase 2 | MVP uses polling |
| Reconciliation action | ready | Implemented one/batch reconcile APIs |

## 22. Phase 7.7 Updates

| Vue screen | API | Status | Notes |
|---|---|---|---|
| Register | `POST /auth/register` | ready | Creates `pending_verify` account and sends verification code through Laravel Mail/log mailer |
| Verify registration | `POST /auth/verify-code` | ready | Activates account and returns Sanctum token |
| Forgot password | `POST /auth/forgot-password` | ready | Sends reset code through Laravel Mail/log mailer |
| Profile address | `PUT /profile` | ready | Supports `address`, `ward`, `district`, `city` |
| Partner legal files | `POST /partner-applications/{id}/documents` | ready | Uploads business license, ID card front/back, venue photo |
| Partner reject email | `PATCH /partner-applications/{id}/reject` | ready | Sends DB notification and Laravel Mail |
| Payment webhook | `POST /payments/webhook/{gateway}` | ready | Optional HMAC verification using `PAYMENT_WEBHOOK_SECRET` and `X-Webhook-Signature` |
| Audit log | All successful authenticated mutating `/api/*` routes | ready | Auto-audit middleware records POST/PUT/PATCH/DELETE with sensitive fields stripped |

## 23. Phase 7.8 Payment Checkout Update

| Vue screen | API | Status | Notes |
|---|---|---|---|
| Create payment | `POST /payments` | ready | Returns signed local MVP `checkout_url` and `checkout_token` |
| Payment checkout | `GET /payments/{payment}/checkout?token=` | ready | Reads payment checkout session without auth, token-protected |
| Complete local checkout | `POST /payments/{payment}/checkout/complete` | ready | Body: `token`, `status=success|failed`; updates payment and booking in transaction |
| Gateway webhook | `POST /payments/webhook/{gateway}` | ready | Optional HMAC verification with `PAYMENT_WEBHOOK_SECRET` |
