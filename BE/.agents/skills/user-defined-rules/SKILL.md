---
name: user-defined-rules
description: >
  LUÔN LUÔN đọc skill này trước khi viết bất kỳ code nào. Đây là các quyết định đã được
  chủ dự án xác nhận và có ưu tiên cao nhất — cao hơn cả các quy tắc chung trong
  laravel-best-practices hay vue-best-practices. Nếu có xung đột, quy tắc ở đây thắng.
license: MIT
metadata:
  author: graduation-team
---

# Quy tắc tùy chỉnh từ người dùng (Backend)

Thư mục này chứa các file `.md` ghi lại các quyết định đã được chốt trong quá trình phát triển. AI phải **ưu tiên tuân thủ** các quy tắc ở đây hơn bất kỳ quy tắc chung nào khác.

---

## Quy tắc hiện tại:
*(Chưa có — sẽ được bổ sung tự động)*

---

## Cơ chế tự động thêm quy tắc mới

### Khi nào AI tạo file rule mới?
AI sẽ tạo một file `.md` mới trong thư mục `rules/` khi phát hiện **BẤT KỲ** tình huống nào sau đây:

1. **Người dùng xác nhận rõ ràng**: Nói các câu như:
   - "Đúng rồi, từ giờ cứ làm thế này"
   - "Hãy nhớ quy tắc này cho mọi lần sau"
   - "Luôn luôn làm theo cách này nhé"
   - "Cái này là chuẩn cho project rồi"

2. **Người dùng sửa lại code của AI** và nói rằng cách sửa đó mới đúng.

3. **Người dùng đưa ra một quyết định kiến trúc** ảnh hưởng đến nhiều file (ví dụ: "Mọi API phải trả về format `{ success, data, message }`").

### Cách tạo file rule mới:
1. **Đặt tên file**: Dùng kebab-case mô tả ngắn gọn nội dung. Ví dụ: `api-response-format.md`, `database-naming.md`, `auth-flow.md`.
2. **Nội dung file** phải tuân theo format sau:

```markdown
# [Tên quy tắc]

## Mô tả
Giải thích ngắn gọn quy tắc này là gì.

## Quy tắc
- Liệt kê các điểm cụ thể cần tuân thủ.

## Ví dụ

### ✅ Đúng
(Code mẫu đúng)

### ❌ Sai
(Code mẫu sai)

## Nguồn gốc
- Ngày tạo: YYYY-MM-DD
- Lý do: Tóm tắt lý do người dùng chọn quy tắc này.
```

3. **Cập nhật file SKILL.md**: Sau khi tạo file rule mới, thêm tên file vào danh sách "Quy tắc hiện tại" ở trên.
