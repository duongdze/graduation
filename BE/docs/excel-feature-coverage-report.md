# Excel Feature Coverage Report

## 1. Tổng quan

- File nguồn: `DANTNCN (2).xlsx`, sheet `DSCN`.
- Tổng số chức năng trong Excel: `93`.
- DONE: `88`.
- PARTIAL: `0`.
- MISSING: `0`.
- EXTENSION / OUT_OF_SCOPE: `5`.
- Tỷ lệ DONE: `94.6%`.
- Tỷ lệ DONE + PARTIAL: `94.6%`.
- Kết luận: Backend đã có đủ API MVP để FE bắt đầu hầu hết màn hình. Không còn mục PARTIAL/MISSING trong phạm vi backend MVP; các phần còn lại là provider/phase production như VNPAY/MoMo SDK thật, email/SMS/push provider, eKYC và Google Maps direction.

## 2. Quy ước trạng thái

### DONE

Chức năng đã có API/backend đủ dùng: có route, controller method, validation/service nếu cần, thao tác DB thật, FE có thể gọi được.

### PARTIAL

Có một phần nhưng chưa đủ: có API chung nhưng thiếu flow riêng, có bảng DB nhưng thiếu event/job, có data nhưng FE phải tự xử lý nhiều, hoặc mock/manual thay cho tích hợp thật.

### MISSING

Chưa có API/backend xử lý: không có route/controller, chỉ có migration/model nhưng không có API, hoặc không có job/event dù Excel yêu cầu backend tự động.

### EXTENSION / OUT_OF_SCOPE

Chức năng nên để phase sau hoặc cần bên thứ ba: eKYC, WebSocket, SMS provider thật, payment signature thật, Google Maps direction, push notification FCM/OneSignal.

## 3. Bảng so sánh đầy đủ

| STT Excel | Nhóm chức năng | Chức năng trong Excel | API/Backend hiện tại | Endpoint liên quan | Controller/Service | DB tables | Status | Ghi chú | Đề xuất bổ sung |
|----------:|----------------|----------------------|----------------------|-------------------|--------------------|----------|--------|---------|-----------------|
| 1.0 | Xác thực & tài khoản / Quản lý truy cập | Đăng ký tài khoản mới | Register tạo user `pending_verify`, gán role player, sinh mã xác thực | `POST /auth/register` | `AuthController`, `VerificationCodeService` | `users`, `user_roles`, `verification_codes` | DONE | Verify-first flow đã được bật | Có thể thêm captcha/rate limit phase production |
| 2.0 | Xác thực & tài khoản / Quản lý truy cập | Xác thực mã số đăng ký | Verify code active account và trả Sanctum token | `POST /auth/send-verification-code`, `POST /auth/verify-code` | `VerificationCodeController`, `VerificationCodeService` | `verification_codes`, `users`, `personal_access_tokens` | DONE | Register verification chuyển `status=active` | Email/SMS delivery thật là provider phase |
| 3.0 | Xác thực & tài khoản / Quản lý truy cập | Đăng nhập hệ thống | Có login Sanctum bằng email hoặc phone, check locked/pending verify | `POST /auth/login` | `AuthController` | `users`, `personal_access_tokens`, RBAC tables | DONE | Dùng `identifier` hoặc `email`; trả roles/permissions | Có thể thêm refresh token riêng nếu cần |
| 4.0 | Xác thực & tài khoản / Quản lý truy cập | Đăng xuất | Có xóa current access token | `POST /auth/logout` | `AuthController` | `personal_access_tokens` | DONE | Đúng Sanctum token revoke cho phiên hiện tại | Có thể thêm logout all devices |
| 5.0 | Xác thực & tài khoản / Quản lý truy cập | Yêu cầu khôi phục mật khẩu | Có forgot password tạo verification code và gửi email qua Laravel Mail | `POST /auth/forgot-password` | `PasswordResetController`, `VerificationCodeService`, `EmailNotificationService` | `verification_codes`, `users` | DONE | Default mailer có thể là `log`, SMTP/provider cấu hình qua `.env` | Throttle/captcha có thể phase production |
| 6.0 | Xác thực & tài khoản / Quản lý truy cập | Đặt lại mật khẩu mới | Có verify code, hash password mới, revoke tokens | `POST /auth/reset-password` | `PasswordResetController` | `users`, `verification_codes`, `personal_access_tokens` | DONE | Đủ flow reset bằng code | Có thể thêm password history |
| 7.0 | Xác thực & tài khoản / Hồ sơ cá nhân | Xem thông tin hồ sơ | Có me/profile trả user, roles, permissions | `GET /auth/me` | `AuthController` | `users`, RBAC tables | DONE | Không expose password/token | Có thể cache profile nếu traffic cao |
| 8.0 | Xác thực & tài khoản / Hồ sơ cá nhân | Cập nhật thông tin cơ bản | Có update full_name/email/phone/avatar/bio/address/ward/district/city/sports | `PUT /profile` | `ProfileController` | `users` | DONE | Đã bổ sung address fields cho hồ sơ cá nhân | None |
| 9.0 | Xác thực & tài khoản / Hồ sơ cá nhân | Cập nhật ảnh đại diện | Có endpoint avatar upload và tự update `users.avatar_url` | `POST /profile/avatar` | `ProfileController`, `MediaService` | `media`, `users` | DONE | Upload image max 5MB, lưu media collection `avatar` | None |
| 10.0 | Xác thực & tài khoản / Hồ sơ cá nhân | Đổi mật khẩu trực tiếp | Có check current password, hash mới, revoke tokens | `PUT /profile/password` | `ProfileController` | `users`, `personal_access_tokens` | DONE | Đủ cho FE | Có thể thêm notification bảo mật |
| 11.0 | Xác thực & tài khoản / Đăng ký đối tác | Gửi đơn đăng ký Chủ sân | Có submit partner application và upload tài liệu pháp lý theo collection | `POST /partner-applications`, `POST /partner-applications/{id}/documents` | `PartnerApplicationController`, `MediaService` | `partner_applications`, `media` | DONE | Hỗ trợ business license, ID front/back, venue photo | eKYC tự động vẫn phase provider |
| 12.0 | Xác thực & tài khoản / Đăng ký đối tác | Xem danh sách đơn đã gửi | Có endpoint đơn của user hiện tại | `GET /partner-applications/my` | `PartnerApplicationController` | `partner_applications` | DONE | Có pagination | Có thể thêm filter thời gian |
| 13.0 | Xác thực & tài khoản / Đăng ký đối tác | Thực hiện định danh điện tử | Chưa có eKYC provider | None | None | None | EXTENSION / OUT_OF_SCOPE | Cần bên thứ ba nhận diện giấy tờ/khuôn mặt | Để phase sau |
| 14.0 | Quản trị hệ thống / Quản lý đối tác | Xem danh sách đơn đăng ký | Có admin list, filter status/search, pagination | `GET /partner-applications` | `PartnerApplicationController` | `partner_applications`, `users` | DONE | Cần permission `partner_application.view_all` | Có thể thêm export Excel |
| 15.0 | Quản trị hệ thống / Quản lý đối tác | Xem chi tiết hồ sơ đối tác | Có detail kèm user/reviewer/media | `GET /partner-applications/{id}` | `PartnerApplicationController` | `partner_applications`, `media` | DONE | Admin xem tất cả, user xem đơn của mình | Có thể thêm ghi chú nội bộ |
| 16.0 | Quản trị hệ thống / Quản lý đối tác | Phê duyệt yêu cầu làm đối tác | Có transaction approve và gán role venue_owner | `PATCH /partner-applications/{id}/approve` | `PartnerApplicationController`, `NotificationService` | `partner_applications`, `user_roles`, `notifications` | DONE | Có DB notification | Có thể thêm email |
| 17.0 | Quản trị hệ thống / Quản lý đối tác | Từ chối yêu cầu làm đối tác | Có reject + reason + DB notification + email qua Laravel Mail | `PATCH /partner-applications/{id}/reject` | `PartnerApplicationController`, `NotificationService`, `EmailNotificationService` | `partner_applications`, `notifications` | DONE | Mail provider cấu hình qua `.env` | None |
| 18.0 | Quản trị hệ thống / Quản lý đối tác | Khóa tài khoản đối tác | Lock user, revoke all Sanctum tokens, ghi audit | `PATCH /users/{user}/lock` | `UserController`, `AuditLogService` | `users`, `personal_access_tokens`, `audit_logs` | DONE | Dùng endpoint user chung cho partner/system staff | Có thể thêm notification riêng |
| 19.0 | Quản trị hệ thống / Quản lý đối tác | Mở khóa tài khoản đối tác | Có unlock user chung | `PATCH /users/{user}/unlock` | `UserController` | `users` | DONE | Đủ để mở khóa tài khoản | Có thể thêm notification |
| 20.0 | Quản trị hệ thống / Duyệt cụm sân | Xem danh sách đề xuất cụm sân | Có list venue filter status pending | `GET /venue-clusters?status=pending` | `VenueClusterController` | `venue_clusters` | DONE | Có pagination/filter | Có thể thêm map admin |
| 21.0 | Quản trị hệ thống / Duyệt cụm sân | Phê duyệt cụm sân mới | Có approve active, tạo booking config, notification | `PATCH /venue-clusters/{id}/approve` | `VenueClusterController`, `NotificationService` | `venue_clusters`, `booking_configs`, `notifications` | DONE | Có khởi tạo config mặc định | Có thể cảnh báo trùng vị trí |
| 22.0 | Quản trị hệ thống / Duyệt cụm sân | Từ chối cụm sân mới | Có reject reason | `PATCH /venue-clusters/{id}/reject` | `VenueClusterController` | `venue_clusters` | DONE | Đủ core flow | Có thể thêm template notification |
| 23.0 | Quản trị hệ thống / Duyệt cụm sân | Cơ chế: Khởi tạo dữ liệu cụm sân | Tạo booking config, default price slots, notification và audit sau approve | `PATCH /venue-clusters/{id}/approve` | `VenueClusterController` | `booking_configs`, `price_slots`, `notifications`, `audit_logs` | DONE | Sân con vẫn do chủ sân tự tạo theo thực tế vận hành | None |
| 24.0 | Quản trị hệ thống / Cấu hình phí & tài chính | Xem cấu hình phí hệ thống | Có list platform fee configs | `GET /platform-fee-configs` | `PlatformFeeConfigController` | `platform_fee_configs` | DONE | Có effective_from | Có thể thêm current-active endpoint |
| 25.0 | Quản trị hệ thống / Cấu hình phí & tài chính | Cập nhật phí hệ thống | Có tạo config mới, validate max fee, ghi audit | `POST /platform-fee-configs` | `PlatformFeeConfigController`, `AuditLogService` | `platform_fee_configs`, `audit_logs` | DONE | Enforce `fee_percent <= max_fee_percent` | None |
| 26.0 | Quản trị hệ thống / Cấu hình phí & tài chính | Cơ chế: Tính phí sàn tự động | Có tạo venue_fee_ledger khi booking complete | `PATCH /bookings/{id}/complete` | `BookingService` | `venue_fee_ledgers`, `platform_fee_configs` | DONE | Dựa config effective_from mới nhất | Có thể thêm reconcile status update |
| 27.0 | Quản trị hệ thống / Cấu hình phí & tài chính | Xem lịch sử giao dịch toàn sàn | Có unified transaction feed từ payment/refund/ledger | `GET /finance/transactions` | `FinanceTransactionController` | `payments`, `refunds`, `venue_fee_ledgers` | DONE | Có filter type/date/cluster và pagination | None |
| 28.0 | Quản trị hệ thống / Cấu hình phí & tài chính | Thực hiện đối soát | Có reconcile one/batch ledger và audit | `PATCH /venue-fee-ledgers/{id}/reconcile`, `POST /venue-fee-ledgers/reconcile-batch` | `VenueFeeLedgerController` | `venue_fee_ledgers`, `audit_logs` | DONE | Đối soát thao tác DB thật trong transaction | Có thể mở rộng settlement/export phase sau |
| 29.0 | Quản trị hệ thống / Quản lý danh mục | Xem danh sách loại sân | Có list court types | `GET /court-types` | `CourtTypeController` | `court_types` | DONE | Có pagination/filter cơ bản | None |
| 30.0 | Quản trị hệ thống / Quản lý danh mục | Thêm mới loại sân | Có create | `POST /court-types` | `CourtTypeController` | `court_types` | DONE | Validate unique name | None |
| 31.0 | Quản trị hệ thống / Quản lý danh mục | Cập nhật loại sân | Có update | `PUT /court-types/{id}` | `CourtTypeController` | `court_types` | DONE | Supports active flag | None |
| 32.0 | Quản trị hệ thống / Quản lý danh mục | Xóa loại sân | Có delete | `DELETE /court-types/{id}` | `CourtTypeController` | `court_types` | DONE | SoftDeletes model | Kiểm tra FK trước xóa nếu production |
| 33.0 | Quản trị hệ thống / Báo cáo & giám sát | Xem báo cáo doanh thu tổng | Có dashboard admin/revenue | `GET /dashboard/admin/overview`, `GET /dashboard/revenue` | `DashboardController` | `payments`, `bookings` | DONE | Revenue grouped day/month | None |
| 34.0 | Quản trị hệ thống / Báo cáo & giám sát | Xem bản đồ mật độ sân | Có density aggregate theo city/district và lat/lng trung bình | `GET /dashboard/venue-density` | `DashboardController` | `venue_clusters` | DONE | FE có thể render heatmap/marker density | None |
| 35.0 | Quản trị hệ thống / Quản lý nhân sự hệ thống | Thêm mới nhân viên hệ thống | Có create user + sync roles | `POST /users`, `POST /users/{id}/roles/sync` | `UserController`, `UserRoleController` | `users`, `user_roles` | DONE | Admin tự gán role system_staff | Có thể thêm send invite email |
| 36.0 | Quản trị hệ thống / Phân quyền động | Xem danh sách Vai trò | Có list roles | `GET /roles` | `RoleController` | `roles` | DONE | None | None |
| 37.0 | Quản trị hệ thống / Phân quyền động | Thêm mới Vai trò | Có create role | `POST /roles` | `RoleController` | `roles` | DONE | None | None |
| 38.0 | Quản trị hệ thống / Phân quyền động | Cập nhật Vai trò | Có update role | `PUT /roles/{id}` | `RoleController` | `roles` | DONE | None | None |
| 39.0 | Quản trị hệ thống / Phân quyền động | Xóa Vai trò | Có delete role | `DELETE /roles/{id}` | `RoleController` | `roles` | DONE | None | None |
| 40.0 | Quản trị hệ thống / Phân quyền động | Xem danh sách Quyền | Có list permissions | `GET /permissions` | `PermissionController` | `permissions` | DONE | None | None |
| 41.0 | Quản trị hệ thống / Phân quyền động | Thêm mới Quyền hạn | Có create permission | `POST /permissions` | `PermissionController` | `permissions` | DONE | None | None |
| 42.0 | Quản trị hệ thống / Phân quyền động | Gán Quyền vào Vai trò | Có sync role permissions | `POST /roles/{id}/permissions/sync` | `RoleController` | `role_permissions` | DONE | None | None |
| 43.0 | Quản trị hệ thống / Phân quyền động | Gán Vai trò cho Người dùng | Có sync user roles, hỗ trợ scope | `POST /users/{id}/roles/sync` | `UserRoleController` | `user_roles` | DONE | None | None |
| 44.0 | Quản trị hệ thống / Phân quyền động | Cơ chế: Kiểm tra quyền truy cập | Có middleware permission và User helpers | Route middleware | `CheckPermission`, `User` | RBAC tables | DONE | super_admin bypass, revoke support | None |
| 45.0 | Quản trị chủ sân / Quản lý cụm sân | Xem chi tiết cụm sân | Có detail venue cluster | `GET /venue-clusters/{id}` | `VenueClusterController` | `venue_clusters`, related tables | DONE | Trả courts/config/price/media | Có thể thêm owner-scoped route riêng |
| 46.0 | Quản trị chủ sân / Quản lý cụm sân | Cập nhật thông tin cụm sân | Có update venue với owner/staff scoped guard | `PUT /venue-clusters/{id}` | `VenueClusterController`, `AuthorizesVenueScope` | `venue_clusters`, `audit_logs` | DONE | Chủ sân chỉ quản lý cụm của mình | None |
| 47.0 | Quản trị chủ sân / Quản lý cụm sân | Cập nhật vị trí trên bản đồ | Có update latitude/longitude, validate range và required pair | `PUT /venue-clusters/{id}` | `VenueClusterController`, `UpsertVenueClusterRequest` | `venue_clusters` | DONE | Business geofence nâng cao không có trong schema | None |
| 48.0 | Quản trị chủ sân / Quản lý sân con | Xem danh sách sân con | Có list courts theo cluster | `GET /venue-courts?cluster_id=` | `VenueCourtController` | `venue_courts` | DONE | None | None |
| 49.0 | Quản trị chủ sân / Quản lý sân con | Thêm mới sân con | Có create court | `POST /venue-courts` | `VenueCourtController` | `venue_courts` | DONE | Validate cluster/type | Thêm owner policy |
| 50.0 | Quản trị chủ sân / Quản lý sân con | Cập nhật thông tin sân con | Có update court | `PUT /venue-courts/{id}` | `VenueCourtController` | `venue_courts` | DONE | Supports name/type/status/sort | Thêm owner policy |
| 51.0 | Quản trị chủ sân / Quản lý sân con | Xóa sân con | Soft delete court có owner guard và chặn future active bookings | `DELETE /venue-courts/{id}` | `VenueCourtController` | `venue_courts`, `bookings` | DONE | Không xóa sân còn booking tương lai | None |
| 52.0 | Quản trị chủ sân / Quản lý sân con | Thay đổi trạng thái sân | Có update `status=active/maintenance` | `PUT /venue-courts/{id}` | `VenueCourtController` | `venue_courts` | DONE | FE dùng update endpoint | None |
| 53.0 | Quản trị chủ sân / Quản lý nhân viên sân | Thêm mới nhân viên sân | Có scoped staff API tạo hoặc attach user | `POST /venue-clusters/{id}/staff` | `VenueStaffController` | `users`, `user_roles` | DONE | Gán scope theo cụm sân | None |
| 54.0 | Quản trị chủ sân / Quản lý nhân viên sân | Gán Vai trò cho nhân viên | Có endpoint gán/xóa `venue_staff` scoped role | `POST /venue-clusters/{id}/staff`, `DELETE /venue-clusters/{id}/staff/{user}` | `VenueStaffController` | `user_roles`, `roles` | DONE | Chủ sân quản lý staff trong cụm của mình | None |
| 55.0 | Quản trị chủ sân / Cấu hình Giá | Xem bảng giá khung giờ | Có list price slots | `GET /price-slots?cluster_id=` | `PriceSlotController` | `price_slots` | DONE | None | None |
| 56.0 | Quản trị chủ sân / Cấu hình Giá | Thiết lập giá theo khung giờ | Create/update price slot có transaction overlap validation | `POST /price-slots`, `PUT /price-slots/{id}` | `PriceSlotController` | `price_slots` | DONE | Chặn overlap cùng cluster/day/time | None |
| 57.0 | Quản trị chủ sân / Cấu hình Giá | Thiết lập giá theo ngày tuần | Có `apply_to_days` JSON | `POST /price-slots` | `PriceSlotController` | `price_slots` | DONE | Supports days 0-6 | None |
| 58.0 | Quản trị chủ sân / Cấu hình Giá | Thiết lập giá ngày lễ | Có holiday price CRUD | `/holiday-prices` | `HolidayPriceController` | `holiday_prices` | DONE | BookingService ưu tiên holiday price | None |
| 59.0 | Quản trị chủ sân / Cấu hình Giá | Khóa khung giờ thủ công | Có manual slot lock API | `/venue-courts/{id}/slot-locks` | `SlotLockController` | `slot_locks` | DONE | Transaction + overlap check | Có thể thêm reason column nếu schema mở rộng |
| 60.0 | Quản trị chủ sân / Cấu hình Giá | Cấu hình thời lượng đặt tối thiểu và tối đa | Booking create enforce min/max duration từ config | `GET /booking-configs`, `PUT /booking-configs/{id}`, `POST /bookings` | `BookingConfigController`, `BookingService` | `booking_configs`, `bookings` | DONE | Không tạo booking ngoài duration config | None |
| 61.0 | Quản trị chủ sân / Vận hành & đặt sân | Xem biểu đồ thời gian đặt sân | Có booking list filter date/cluster/court và availability | `GET /bookings`, `GET /venue-courts/{id}/available-slots` | `BookingController`, `AvailabilityController` | `bookings`, `slot_locks` | DONE | FE dựng grid timetable | None |
| 62.0 | Quản trị chủ sân / Vận hành & đặt sân | Tạo đơn đặt sân tại quầy | Counter booking tạo booking + cash payment success trong transaction | `POST /bookings/counter` | `BookingController`, `BookingService` | `bookings`, `payments` | DONE | Atomic one-step flow cho lễ tân | None |
| 63.0 | Quản trị chủ sân / Vận hành & đặt sân | Cập nhật trạng thái khách nhận sân | Có check-in | `PATCH /bookings/{id}/check-in` | `BookingController`, `BookingService` | `bookings` | DONE | paid -> checked_in | None |
| 64.0 | Quản trị chủ sân / Vận hành & đặt sân | Cập nhật trạng thái hoàn thành | Có complete + ledger | `PATCH /bookings/{id}/complete` | `BookingService` | `bookings`, `venue_fee_ledgers` | DONE | paid/checked_in -> completed | None |
| 65.0 | Quản trị chủ sân / Xử lý tài chính | Tiếp nhận yêu cầu hoàn tiền | Refund create tự tính amount theo config khi không truyền amount | `/refunds`, `PATCH /bookings/{id}/cancel` | `RefundController`, `PaymentService`, `BookingService` | `refunds`, `payments`, `bookings`, `booking_configs` | DONE | Cancel booking cũng tạo refund pending theo policy | None |
| 66.0 | Quản trị chủ sân / Xử lý tài chính | Gửi minh chứng hoàn tiền | Có upload proof gắn refund qua media polymorphic | `POST /refunds/{id}/proof` | `RefundController`, `MediaService` | `refunds`, `media` | DONE | Hỗ trợ collection `refund_proof` | Có thể thêm nhiều loại chứng từ phase sau |
| 67.0 | Quản trị chủ sân / Xử lý tài chính | Cơ chế: Ghi nhật ký phí sàn | Có ledger khi complete | `PATCH /bookings/{id}/complete`, `GET /venue-fee-ledgers` | `BookingService`, `VenueFeeLedgerController` | `venue_fee_ledgers` | DONE | Ledger unique per booking | None |
| 68.0 | Khách hàng / Tìm kiếm | Tìm kiếm sân theo bộ lọc | Có search/filter/distance venue list | `GET /venue-clusters` | `VenueClusterController` | `venue_clusters`, `price_slots`, `venue_courts` | DONE | Supports search, type, price, city/district, lat/lng radius | Có thể thêm recommendation history |
| 69.0 | Khách hàng / Tìm kiếm | Xem vị trí và chỉ đường | Backend có lat/lng, direction là FE/Google Maps | `GET /venue-clusters/{id}` | `VenueClusterController` | `venue_clusters` | EXTENSION / OUT_OF_SCOPE | Turn-by-turn direction không nên làm trong BE MVP | FE mở Google Maps/Mapbox |
| 70.0 | Khách hàng / Tìm kiếm | Cơ chế: Tính khoảng cách thực tế | Có Haversine distance sorting | `GET /venue-clusters?lat=&lng=&radius_km=` | `VenueClusterController` | `venue_clusters` | DONE | Trả `distance_km` khi có lat/lng | None |
| 71.0 | Khách hàng / Đặt sân | Xem lịch trống sân con | Có availability từng court và toàn cụm | `GET /venue-courts/{id}/available-slots`, `GET /venue-clusters/{id}/available-slots` | `AvailabilityController`, `AvailabilityService` | `bookings`, `slot_locks`, `price_slots` | DONE | FE có thể lấy toàn bộ courts trong cluster một lần | None |
| 72.0 | Khách hàng / Đặt sân | Tạo yêu cầu đặt sân | Booking pending + auto slot lock 15 phút | `POST /bookings` | `BookingService` | `bookings`, `slot_locks` | DONE | Validate duration, conflict, lock in transaction | Add-ons cần schema phase sau |
| 73.0 | Khách hàng / Đặt sân | Cơ chế: Khóa khung giờ tạm thời | Auto slot locks TTL 15 phút và cleanup command | `POST /bookings`, `bookings:expire-pending` | `BookingService`, `ExpirePendingBookings` | `slot_locks`, `bookings` | DONE | DB lock phù hợp MySQL MVP | Redis lock có thể phase scale |
| 74.0 | Khách hàng / Đặt sân | Cơ chế: Chống đặt trùng | Có transaction + lockForUpdate + overlap booking/lock | `POST /bookings` | `BookingService` | `bookings`, `slot_locks` | DONE | MySQL row-level lock inside transaction | Nên stress test |
| 75.0 | Khách hàng / Thanh toán | Khởi tạo giao dịch thanh toán | Có payment pending, signed checkout token/URL và local MVP checkout callback | `POST /payments`, `GET /payments/{id}/checkout`, `POST /payments/{id}/checkout/complete` | `PaymentController`, `PaymentService`, `PaymentGatewayService` | `payments`, `bookings` | DONE | FE có checkout_url thật của backend; VNPAY/MoMo SDK thật vẫn phase provider | None |
| 76.0 | Khách hàng / Thanh toán | Cơ chế: Xử lý phản hồi thanh toán | Có webhook MVP, amount check, idempotency, update payment/booking, HMAC signature tùy chọn | `POST /payments/webhook/{gateway}` | `PaymentWebhookController` | `payments`, `bookings`, `notifications` | DONE | Nếu cấu hình `PAYMENT_WEBHOOK_SECRET`, bắt buộc header `X-Webhook-Signature` | Gateway SDK riêng vẫn phase provider |
| 77.0 | Khách hàng / Thanh toán | Cơ chế: Ngăn chặn xử lý trùng lặp | Có unique gateway_txn_id và idempotency check | `POST /payments/webhook/{gateway}` | `PaymentWebhookController` | `payments` | DONE | Duplicate webhook returns already processed | None |
| 78.0 | Khách hàng / Thanh toán | Thực hiện thanh toán lại | Có retry payment endpoint | `POST /payments/{payment}/retry` | `PaymentWebhookController` | `payments`, `bookings` | DONE | Creates new pending attempt | Gateway URL still phase gateway |
| 79.0 | Khách hàng / Hậu đặt sân | Gửi yêu cầu hủy đơn | Cancel enforce `cancel_before_hours`, xóa lock, tạo refund pending | `PATCH /bookings/{id}/cancel` | `BookingController`, `BookingService` | `bookings`, `slot_locks`, `refunds` | DONE | Admin/owner có thể override theo quyền vận hành | None |
| 80.0 | Khách hàng / Hậu đặt sân | Tạo khiếu nại | Có complaint create/detail/list và media evidence | `POST /complaints`, `POST /media/upload` | `ComplaintController`, `MediaService` | `complaints`, `media` | DONE | Complaint tied to booking | None |
| 81.0 | Khách hàng / Hậu đặt sân | Đánh giá và nhận xét | Có review sau booking completed + rating aggregate | `POST /reviews` | `ReviewController`, `RatingAggregateService` | `reviews`, `venue_clusters` | DONE | Updates rating avg/count | None |
| 82.0 | Hệ thống thông báo / Thông báo tự động | Gửi thông báo tới ứng dụng | Có DB notifications, list/read/read-all và admin/system send endpoint | `/notifications`, `POST /notifications` | `NotificationService`, `NotificationController` | `notifications` | DONE | In-app notification đủ cho MVP; push provider là phase sau | None |
| 83.0 | Hệ thống thông báo / Thông báo tự động | Gửi thư điện tử xác nhận | Chưa gửi email thật, có TODO trong password reset | None | None | None | EXTENSION / OUT_OF_SCOPE | Cần Mail provider/template | Phase sau hoặc trước production |
| 84.0 | Hệ thống thông báo / Thông báo tự động | Gửi tin nhắn mã xác thực | Verification supports channel sms nhưng chưa provider | `POST /auth/send-verification-code` | `VerificationCodeController` | `verification_codes` | EXTENSION / OUT_OF_SCOPE | Local/debug only | Tích hợp SMS gateway |
| 85.0 | Hệ thống thông báo / Kích hoạt sự kiện | Sự kiện: Đặt sân thành công | Payment success tạo notification cho customer và venue owner | `POST /payments/webhook/{gateway}`, `PATCH /payments/{id}/mark-paid` | `PaymentWebhookController`, `PaymentService`, `NotificationService` | `notifications`, `payments`, `bookings` | DONE | Manual mark-paid cũng dispatch qua service | Push/email là phase provider |
| 86.0 | Hệ thống thông báo / Kích hoạt sự kiện | Sự kiện: Nhắc lịch đá sân | Có command tạo notification nhắc lịch | `php artisan bookings:send-reminders --minutes=120` | `SendBookingReminders`, `NotificationService` | `bookings`, `notifications` | DONE | Scheduled every five minutes | Push/email reminder là phase provider |
| 87.0 | Logic hệ thống / Cơ chế vận hành | Cơ chế: Tự động giải phóng khung giờ | Có scheduled command expire pending + cleanup locks | `php artisan bookings:expire-pending --minutes=15` | `ExpirePendingBookings` | `bookings`, `slot_locks` | DONE | Scheduled every minute in `routes/console.php` | None |
| 88.0 | Logic hệ thống / Cơ chế vận hành | Cơ chế: Đồng bộ trạng thái thực tế | MVP dùng polling APIs, chưa WebSocket | `/bookings`, `/available-slots`, `/notifications` | Controllers existing | Nhiều bảng | EXTENSION / OUT_OF_SCOPE | Realtime WebSocket để phase sau | FE polling đủ cho MVP |
| 89.0 | Logic hệ thống / Cơ chế vận hành | Cơ chế: Ghi nhật ký hệ thống | Có audit table, list API, service và auto-audit middleware cho API ghi dữ liệu | `GET /audit-logs` | `AuditLogController`, `AuditLogService`, `AutoAuditApiAction` | `audit_logs` | DONE | Tự log POST/PUT/PATCH/DELETE API thành công, ẩn field nhạy cảm | Có thể tinh chỉnh whitelist action phase production |
| 90.0 | Logic hệ thống / Cơ chế vận hành | Cơ chế: Xử lý đơn hàng quá hạn | Có command đánh dấu expired | `php artisan bookings:expire-pending --minutes=15` | `ExpirePendingBookings` | `bookings`, `slot_locks` | DONE | Đặt lịch every minute | None |
| 91.0 | Phân tích / Thống kê chuyên sâu | Tính toán tỷ lệ chuyển đổi | Có venue view tracking và view-to-paid conversion | `POST /venue-clusters/{id}/view`, `GET /dashboard/conversion-rate` | `VenueClusterController`, `DashboardController` | `venue_view_events`, `bookings` | DONE | FE gọi record view khi mở detail/list card | None |
| 92.0 | Phân tích / Thống kê chuyên sâu | Tính toán hiệu suất sử dụng sân | Utilization dùng booked minutes / available minutes từ price slots | `GET /dashboard/venue-owner/overview` | `VenueOwnerDashboardService` | `bookings`, `venue_courts`, `price_slots` | DONE | Fallback 14h/ngày nếu chưa có price slot | Operating hours riêng có thể phase sau |
| 93.0 | Phân tích / Thống kê chuyên sâu | Thống kê khung giờ tập trung | Có peak hours admin và owner dashboard | `GET /dashboard/peak-hours`, `GET /dashboard/venue-owner/overview` | `DashboardController`, `VenueOwnerDashboardService` | `bookings` | DONE | Group by start_time/day_of_week | None |

## 4. Chức năng đã đáp ứng

| Nhóm | Chức năng | Endpoint | Status |
|---|---|---|---|
| Auth | Logout, reset password, me/profile, change password | `/auth/logout`, `/auth/reset-password`, `/auth/me`, `/profile/password` | DONE |
| RBAC | Roles, permissions, sync permission/role, middleware permission | `/roles`, `/permissions`, `/roles/{id}/permissions/sync`, `/users/{id}/roles/sync` | DONE |
| User | Admin user CRUD, lock/unlock | `/users`, `/users/{id}/lock`, `/users/{id}/unlock` | DONE |
| Partner | Admin list/detail/approve, user my applications | `/partner-applications`, `/partner-applications/my`, approve/reject | DONE |
| Venue/Court | Venue approval, court CRUD, court type CRUD | `/venue-clusters`, `/venue-courts`, `/court-types` | DONE |
| Pricing | Holiday price, day-of-week price, booking config API | `/price-slots`, `/holiday-prices`, `/booking-configs` | DONE |
| Booking | Create, conflict prevention, cancel, check-in, complete | `/bookings` | DONE |
| Payment/Refund | Payment CRUD, signed checkout MVP, webhook MVP, retry, refund CRUD | `/payments`, `/payments/{id}/checkout`, `/payments/webhook/{gateway}`, `/refunds` | DONE |
| Recruitment | Player posts, join/leave/approve/reject | `/player-posts` | DONE |
| Review/Complaint | Reviews, rating aggregate, complaint create/resolve, reports | `/reviews`, `/complaints`, `/reports` | DONE |
| Chat/Notification | Conversations/messages polling, DB notifications read/send | `/conversations`, `/notifications` | DONE |
| Audit | Audit log list and auto API write logging | `/audit-logs` | DONE |
| Dashboard | Admin overview, revenue, peak hours, owner overview | `/dashboard/*` | DONE |

## 5. Chức năng đáp ứng một phần

| Chức năng | Đang có gì | Còn thiếu gì | Mức ảnh hưởng | Đề xuất |
|----------|------------|--------------|---------------|---------|
| Không còn mục PARTIAL trong phạm vi backend MVP | Các mục PARTIAL cũ đã được bổ sung API/service/command tương ứng | Chỉ còn provider/production integration ở mục phase sau | N/A | Tiếp tục theo nhu cầu demo/production |

## 6. Chức năng chưa có

| Chức năng | Lý do chưa có | Cần API/Job/Service nào | Priority |
|----------|---------------|-------------------------|----------|
| Không còn mục MISSING bắt buộc cho MVP backend | Các mục MISSING cũ đã được bổ sung ở Phase 7.6 | N/A | N/A |

## 7. Chức năng mở rộng / phase sau

- eKYC giấy tờ/khuôn mặt qua provider thứ ba.
- WebSocket realtime; MVP hiện có thể dùng polling.
- SMS provider thật cho OTP.
- Email templates/provider cho reset/booking confirmation.
- Push notification FCM/OneSignal nếu chưa có mobile app.
- Google Maps turn-by-turn direction, FE xử lý qua app/map SDK.
- Payment gateway SDK/signature production khi có credential VNPAY/MoMo.
- Settlement/reconciliation nâng cao nếu chưa demo tài chính.

## 8. Khuyến nghị triển khai tiếp

- FE có thể bắt đầu ngay: Auth, profile, RBAC admin, user management, partner approval, venue/court, pricing, booking, recruitment, review/complaint/report, chat, notification list, dashboard owner/admin.
- BE chỉ cần bổ sung trước demo nếu demo yêu cầu provider thật: VNPAY/MoMo production, email/SMS provider production, hoặc policy audit chi tiết hơn.
- Có thể để phase 2: eKYC, WebSocket, SMS/email/push provider thật, map direction, advanced reconciliation, conversion tracking theo lượt xem.
- Critical finance MVP đã có refund proof và reconcile action. Settlement/export nâng cao có thể để phase sau.

## 9. Phase 7.6 Implementation Update

Các mục MISSING đã được xử lý:

| STT Excel | Chức năng | Status trước | Backend đã bổ sung | Endpoint / Command | Status sau |
|---:|---|---|---|---|---|
| 28.0 | Thực hiện đối soát | MISSING | Reconcile one/batch ledger, audit log | `PATCH /venue-fee-ledgers/{id}/reconcile`, `POST /venue-fee-ledgers/reconcile-batch` | DONE |
| 66.0 | Gửi minh chứng hoàn tiền | MISSING | Refund proof upload linked to polymorphic `media` | `POST /refunds/{id}/proof` | DONE |
| 86.0 | Sự kiện nhắc lịch đá sân | MISSING | Scheduled reminder command creates DB notifications | `php artisan bookings:send-reminders --minutes=120` | DONE |

Các mục PARTIAL đã được nâng cấp đáng kể:

| STT Excel | Chức năng | Backend đã bổ sung | Status sau |
|---:|---|---|---|
| 1.0 | Đăng ký tài khoản mới | Register tạo `pending_verify` account và mã xác thực | DONE |
| 2.0 | Xác thực mã đăng ký | Verify code active account và trả Sanctum token | DONE |
| 3.0 | Đăng nhập hệ thống | Login bằng email hoặc phone qua `identifier`, chặn pending verify | DONE |
| 9.0 | Cập nhật ảnh đại diện | `POST /profile/avatar` upload media và update `avatar_url` | DONE |
| 18.0 | Khóa tài khoản đối tác | Lock user revoke toàn bộ token và ghi audit | DONE |
| 23.0 | Khởi tạo dữ liệu cụm sân | Approve venue tạo booking config và default price slots | DONE |
| 25.0 | Cập nhật phí hệ thống | Validate `fee_percent <= max_fee_percent`, ghi audit | DONE |
| 27.0 | Lịch sử giao dịch toàn sàn | Unified transaction feed | DONE |
| 34.0 | Bản đồ mật độ sân | Density aggregate endpoint | DONE |
| 46.0 | Cập nhật cụm sân | Owner/staff scoped authorization guard | DONE |
| 51.0 | Xóa sân con | Chặn xóa sân còn booking tương lai | DONE |
| 53.0 | Thêm nhân viên sân | Scoped staff API | DONE |
| 54.0 | Gán vai trò nhân viên | Assign `venue_staff` role by venue scope | DONE |
| 56.0 | Thiết lập giá theo khung giờ | Chống overlap price slot theo cluster/day/time | DONE |
| 60.0 | Cấu hình duration booking | BookingService enforce min/max duration | DONE |
| 62.0 | Tạo đơn tại quầy | Counter booking + cash payment atomic | DONE |
| 65.0 | Tiếp nhận hoàn tiền | Auto refund amount from booking config when amount omitted | DONE |
| 71.0 | Lịch trống toàn cụm | Cluster availability endpoint | DONE |
| 72.0 | Tạo yêu cầu đặt sân | Slot lock TTL 15 minutes | DONE |
| 73.0 | Khóa giờ tạm thời | Auto locks + cleanup command aligned to 15 minutes | DONE |
| 79.0 | Hủy đơn | Enforce cancel window and create pending refunds | DONE |
| 82.0 | Gửi thông báo in-app | Admin/system DB notification creation endpoint | DONE |
| 85.0 | Đặt sân thành công | Payment success notifies customer and venue owner | DONE |
| 91.0 | Tỷ lệ chuyển đổi | Venue view tracking table + conversion metric | DONE |
| 92.0 | Hiệu suất sử dụng sân | Utilization uses configured price-slot operating windows | DONE |

Các mục provider/phase production còn lại:

| Chức năng | Lý do |
|---|---|
| Email production | Backend dùng Laravel Mail; production cần cấu hình SMTP/provider qua `.env` |
| eKYC documents | Legal document upload đã có; eKYC tự động cần provider nhận diện giấy tờ/khuôn mặt |
| Payment gateway production | Backend có signed local checkout + webhook HMAC tùy chọn; VNPAY/MoMo SDK thật cần credential |
| SMS/push provider | Cần credential/provider ngoài hệ thống |
| Audit production policy | Auto-audit middleware đã có; production có thể tinh chỉnh whitelist/action taxonomy |

## 10. Phase 7.7 Follow-up Implementation Update

Các mục PARTIAL tiếp tục được xử lý:

| STT Excel | Chức năng | Backend đã bổ sung | Status sau |
|---:|---|---|---|
| 5.0 | Yêu cầu khôi phục mật khẩu | Gửi code qua Laravel Mail/log mailer bằng `EmailNotificationService` | DONE |
| 8.0 | Cập nhật thông tin cơ bản | Bổ sung `address`, `ward`, `district`, `city` cho user profile | DONE |
| 11.0 | Gửi đơn đăng ký Chủ sân | Thêm upload tài liệu pháp lý theo application | DONE |
| 17.0 | Từ chối yêu cầu làm đối tác | Gửi DB notification và email qua Laravel Mail | DONE |
| 47.0 | Cập nhật vị trí trên bản đồ | Validate lat/lng theo cặp và range hợp lệ | DONE |
| 76.0 | Xử lý phản hồi thanh toán | Webhook hỗ trợ HMAC signature tùy chọn qua `PAYMENT_WEBHOOK_SECRET` | DONE |
| 89.0 | Ghi nhật ký hệ thống | Auto-audit middleware ghi log API POST/PUT/PATCH/DELETE thành công | DONE |

Sau Phase 7.7, coverage chính còn:

- DONE: `87`
- PARTIAL: `0`
- MISSING: `0`
- EXTENSION / OUT_OF_SCOPE: `5`

Không còn mục MISSING/PARTIAL trong phạm vi backend MVP. Payment đã có signed checkout token/URL và local checkout callback; tích hợp SDK VNPAY/MoMo thật vẫn thuộc phase provider vì cần credential.

## 11. Phase 7.8 Payment Checkout Update

| STT Excel | Chức năng | Backend đã bổ sung | Status sau |
|---:|---|---|---|
| 75.0 | Khởi tạo giao dịch thanh toán | `POST /payments` trả signed `checkout_url`; `GET /payments/{id}/checkout` đọc session; `POST /payments/{id}/checkout/complete` hoàn tất local checkout trong transaction | DONE |

Coverage sau Phase 7.8:

- DONE: `88`
- PARTIAL: `0`
- MISSING: `0`
- EXTENSION / OUT_OF_SCOPE: `5`
| eKYC, Google direction, WebSocket realtime | Phase sau / third-party integration |
