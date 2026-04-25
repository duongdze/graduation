---
name: tailwindcss-development
description: >
  Apply when writing or reviewing any Tailwind CSS classes in Vue templates,
  building responsive layouts (grid, flex), styling UI components (cards, tables,
  navbars, forms, badges), adding dark mode variants, fixing spacing or typography,
  and any Tailwind v4 work in .vue or .html files.
license: MIT
metadata:
  author: graduation-team
---

# Tailwind CSS v4 Development

## Consistency First
Trước khi áp dụng bất kỳ quy tắc nào, hãy kiểm tra những gì project đã làm. Nếu đã có convention, hãy tuân theo.

---

## 1. CSS-First Configuration (v4)

Tailwind v4 dùng `@theme` directive thay cho `tailwind.config.js`:

```css
/* app.css */
@import "tailwindcss";

@theme {
  --color-primary: oklch(0.65 0.15 250);
  --color-secondary: oklch(0.75 0.12 180);
  --font-display: 'Inter', sans-serif;
}
```

**KHÔNG** dùng `@tailwind base/components/utilities` — đó là cú pháp v3 đã lỗi thời.

---

## 2. Replaced Utilities (v3 → v4)

| ❌ Deprecated (v3) | ✅ Replacement (v4) |
|---|---|
| `bg-opacity-50` | `bg-black/50` |
| `text-opacity-75` | `text-white/75` |
| `border-opacity-*` | `border-black/*` |
| `ring-opacity-*` | `ring-black/*` |
| `flex-shrink-*` | `shrink-*` |
| `flex-grow-*` | `grow-*` |
| `overflow-ellipsis` | `text-ellipsis` |
| `decoration-slice` | `box-decoration-slice` |

---

## 3. Spacing

Dùng `gap` thay cho margin khi cần khoảng cách giữa các phần tử con:

```html
<!-- ❌ Sai — dùng margin -->
<div class="flex">
  <div class="mr-4">Item 1</div>
  <div class="mr-4">Item 2</div>
</div>

<!-- ✅ Đúng — dùng gap -->
<div class="flex gap-4">
  <div>Item 1</div>
  <div>Item 2</div>
</div>
```

---

## 4. Dark Mode

Dùng `dark:` variant để hỗ trợ chế độ tối:

```html
<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
  Content adapts to color scheme
</div>
```

Nếu project đã dùng dark mode ở các trang hiện có → bắt buộc trang mới cũng phải hỗ trợ.

---

## 5. Common Layout Patterns

### Flexbox
```html
<div class="flex items-center justify-between gap-4">
  <div>Left</div>
  <div>Right</div>
</div>
```

### Responsive Grid
```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  <div>Card 1</div>
  <div>Card 2</div>
  <div>Card 3</div>
</div>
```

### Centered Content
```html
<div class="flex min-h-screen items-center justify-center">
  <div>Centered box</div>
</div>
```

---

## 6. Common Pitfalls

- ❌ Dùng `@tailwind` directives thay vì `@import "tailwindcss"`
- ❌ Dùng `tailwind.config.js` thay vì CSS `@theme`
- ❌ Dùng margin thay vì `gap` cho layout flex/grid
- ❌ Dùng class v3 đã deprecated (`bg-opacity-*`, `flex-grow-*`...)
- ❌ Quên thêm `dark:` variant khi project có dark mode
- ❌ Hardcode màu sắc thay vì dùng biến theme
