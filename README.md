# Graduation Project

Dự án tốt nghiệp được xây dựng với kiến trúc tách biệt hoàn toàn giữa Backend (Laravel) và Frontend (Vue.js).

## ⚙️ Thiết lập ban đầu (Chỉ làm 1 lần khi mới clone)

Để project có thể chạy được, bạn cần cấu hình môi trường cho cả 2 phần:

### 1. Cấu hình Backend (BE)
Di chuyển vào thư mục `BE` và thực hiện:
- **Tạo file .env**: Copy từ `.env.example`.
- **Cài đặt thư viện**: `composer install`, `npm install`.
- **Tạo App Key**: `php artisan key:generate`.
- **Cấu hình Database**: Mở file `.env` và chỉnh sửa `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` cho phù hợp.
- **Cập nhật FRONTEND_URL**: Đảm bảo trong `.env` có dòng `FRONTEND_URL=http://localhost:5173`.
- **Chạy Migration**: `php artisan migrate`.

### 2. Cấu hình Frontend (FE)
Di chuyển vào thư mục `FE` và thực hiện:
- **Tạo file .env**: Copy từ `.env.example`.
- **Cài đặt thư viện**: `npm install`.
- **Kiểm tra VITE_API_BASE_URL**: Đảm bảo trỏ đúng về `http://localhost:8000/api`.

---

## 🚀 Quy trình sau khi Git Pull

Mỗi khi có cập nhật mới từ Git, hãy chạy các lệnh sau để đồng bộ:

### Backend (Laravel)
```bash
cd BE
composer setup        # Lệnh tự động (đã bao gồm install, migrate)
# Hoặc chạy thủ công:
composer install
php artisan migrate
php artisan optimize:clear
```

### Frontend (Vue/Vite)
```bash
cd FE
npm install
```

---

## 🛠 Cách chạy Project

### Chạy Backend & API
```bash
cd BE
composer dev          # Chạy đồng thời Server, Queue và Vite (cho backend admin)
```

### Chạy Frontend (Client)
```bash
cd FE
npm run dev           # Mở tại http://localhost:5173
```

---

## 💡 Thông tin kỹ thuật (Dành cho thành viên)

### 1. Hệ thống API & Sanctum
Project đã được cài đặt hệ thống API tự động thông qua lệnh `php artisan install:api`. Các thành phần đã được thiết lập bao gồm:
- **Kích hoạt Sanctum**: Hỗ trợ xác thực các yêu cầu từ Vue qua Token.
- **File Routes**: Code API sẽ viết trong `BE/routes/api.php` (tất cả các route trong này sẽ có tiền tố là `/api/...`).
- **User Model**: Đã được thêm Trait `HasApiTokens` để hỗ trợ đăng nhập.

### 2. Cách gọi API từ Frontend
Dùng đường dẫn base đã cấu hình trong `.env` của FE. Ví dụ gọi API lấy thông tin:
`URL: http://localhost:8000/api/example-route`

### 3. Lưu ý về Branching & Git
- Luôn tạo nhánh mới: `git checkout -b feature/ten-tinh-nang`.
- Không bao giờ đẩy file `.env` lên Git.
- Trước khi tạo Pull Request, hãy đảm bảo code đã chạy được ở máy cá nhân.
