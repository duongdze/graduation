---
name: vue-best-practices
description: >
  Apply this rule when writing or reviewing any Vue 3 code: creating .vue components,
  writing composables (useXxx), managing state with ref/reactive/Pinia, defining
  TypeScript props/emits, optimizing renders with computed/watch/watchEffect,
  handling async API calls, setting up Vue Router, or structuring a Vue 3 + Vite project.
  Also triggers when naming components, scoping styles, or reviewing template logic.
license: MIT
metadata:
  author: graduation-team
---

# Vue 3 Best Practices

## 1. Composition API & Script Setup

- Luôn dùng `<script setup lang="ts">` — không dùng Options API cho code mới.
- Khai báo Props bằng `defineProps<{ ... }>()` với kiểu TypeScript inline.
- Khai báo Emits bằng `defineEmits<{ ... }>()` với kiểu rõ ràng.
- Dùng `withDefaults` nếu Props cần giá trị mặc định.

```vue
<script setup lang="ts">
interface Props {
  title: string
  count?: number
}

const props = withDefaults(defineProps<Props>(), {
  count: 0,
})

const emit = defineEmits<{
  update: [value: number]
  close: []
}>()
</script>
```

---

## 2. Component Architecture

- **Single Responsibility**: Mỗi component chỉ làm một nhiệm vụ duy nhất.
- **Đặt tên PascalCase**: `UserCard.vue`, `ProductList.vue`.
- **Prefix `BaseX.vue`** cho component dùng chung cơ bản: `BaseButton.vue`, `BaseInput.vue`, `BaseModal.vue`.
- **Prefix `TheX.vue`** cho component singleton (chỉ dùng một lần): `TheHeader.vue`, `TheSidebar.vue`.
- Tách component khi template vượt quá ~100 dòng hoặc có nhiều logic độc lập.

---

## 3. Composables

- Tách logic tái sử dụng vào `composables/useXxx.ts` — đây là pattern trung tâm của Vue 3.
- Composable luôn **trả về object**, không trả về array (trừ pattern kiểu `useState`).
- Đặt tên file trùng với function export: `useAuth.ts` → `export function useAuth()`.
- Composable nên tự quản lý cleanup (unwatch, removeEventListener) trong `onUnmounted`.

```ts
// composables/useCounter.ts
export function useCounter(initial = 0) {
  const count = ref(initial)
  const increment = () => count.value++
  const reset = () => (count.value = initial)
  return { count, increment, reset }
}
```

---

## 4. Quản lý State

- Dùng `ref` cho kiểu nguyên thủy (`string`, `number`, `boolean`) và array.
- Dùng `reactive` cho object lớn hoặc state của form.
- **Pinia** thay cho Vuex — định nghĩa store bằng `defineStore` với Composition API style.
- Không dùng `reactive` để unwrap store — dùng `storeToRefs` để giữ reactivity.

```ts
// stores/useAuthStore.ts
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isLoggedIn = computed(() => !!user.value)
  return { user, isLoggedIn }
})
```

---

## 5. Computed, Watch & WatchEffect

- Dùng `computed` cho **giá trị phụ thuộc state** — không dùng method thay thế.
- Dùng `watch` khi cần **side effect** khi một source cụ thể thay đổi (gọi API, emit event).
- Dùng `watchEffect` khi cần **track nhiều dependency tự động** mà không cần chỉ định rõ source.
- Tránh side effect bên trong `computed`.

```ts
// ✅ Đúng
const fullName = computed(() => `${firstName.value} ${lastName.value}`)

// ❌ Sai — dùng method thay computed
function getFullName() {
  return `${firstName.value} ${lastName.value}`
}

// watch — track cụ thể
watch(userId, async (newId) => {
  userData.value = await fetchUser(newId)
})

// watchEffect — track tự động
watchEffect(async () => {
  userData.value = await fetchUser(userId.value)
})
```

---

## 6. Async & API Calls

- Gọi API trong `onMounted` hoặc tốt hơn là trong **composable riêng** (`useFetch`, `useUser`...).
- Luôn xử lý ba trạng thái: `isLoading`, `data`, `error`.
- Hủy request hoặc set cờ khi component unmount để tránh memory leak.

```ts
// composables/useUser.ts
export function useUser(id: Ref<number>) {
  const data = ref<User | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  watch(id, async (newId) => {
    isLoading.value = true
    error.value = null
    try {
      data.value = await api.getUser(newId)
    } catch (e) {
      error.value = 'Không thể tải dữ liệu'
    } finally {
      isLoading.value = false
    }
  }, { immediate: true })

  return { data, isLoading, error }
}
```

---

## 7. Template Guidelines

- **Không viết logic phức tạp trong template** — chuyển vào `computed` hoặc method.
- Emit events dùng **camelCase** trong `defineEmits`, **kebab-case** trong template.
- Luôn cung cấp `:key` **duy nhất và ổn định** khi dùng `v-for` (dùng `id`, không dùng `index`).
- Dùng `v-show` thay `v-if` nếu phần tử ẩn/hiện **thường xuyên**.
- Không kết hợp `v-if` và `v-for` trên cùng một element — dùng `<template>` wrapper.

```vue
<!-- ❌ Sai -->
<li v-for="item in list" v-if="item.active" :key="item.id">

<!-- ✅ Đúng -->
<template v-for="item in list" :key="item.id">
  <li v-if="item.active">{{ item.name }}</li>
</template>

<!-- ❌ Logic trong template -->
<span>{{ user.firstName + ' ' + user.lastName }}</span>

<!-- ✅ Dùng computed -->
<span>{{ fullName }}</span>
```

---

## 8. TypeScript

- Tránh dùng kiểu `any` — định nghĩa rõ `interface` hoặc `type` cho mọi cấu trúc dữ liệu.
- Đặt types/interfaces dùng chung vào `types/` hoặc `interfaces/`.
- Dùng `?` cho optional field, không dùng `| undefined` thủ công.
- Tận dụng **type inference** của Vue — không cần annotate thủ công những gì TypeScript tự suy ra được.

```ts
// types/user.ts
export interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'user' | 'guest'
  avatar?: string
}
```

---

## 9. Performance & Optimization

- Dùng `defineAsyncComponent` để lazy load các component nặng hoặc ít dùng.
- Wrap component nặng bằng `<Suspense>` khi cần loading state.
- Dùng `shallowRef` / `shallowReactive` cho object lớn không cần deep reactivity.
- Giải phóng listener, timer, subscription trong `onUnmounted`.

```ts
const HeavyChart = defineAsyncComponent(() => import('./HeavyChart.vue'))

onUnmounted(() => {
  clearInterval(timer)
  eventBus.off('update', handler)
})
```

---

## 10. CSS & Scoping

- Mặc định dùng `<style scoped>` để tránh style leak.
- Dùng **CSS Modules** (`<style module>`) khi cần dùng class động trong script.
- Dùng CSS variables (`var(--color-primary)`) cho theme — không hardcode giá trị màu.
- Đặt tên class theo **BEM** hoặc nhất quán với convention của dự án.

```vue
<style scoped>
.user-card {
  background: var(--color-surface);
  border-radius: var(--radius-md);
}

.user-card__title {
  color: var(--color-text-primary);
}
</style>
```

---

## 11. Project Structure (khuyến nghị)

```
src/
├── assets/          # Static files
├── components/
│   ├── base/        # BaseButton.vue, BaseInput.vue...
│   └── features/    # Feature-specific components
├── composables/     # useAuth.ts, useUser.ts...
├── layouts/         # Layout components
├── pages/ (hoặc views/)
├── router/          # index.ts + route guards
├── stores/          # Pinia stores
├── types/           # Global TypeScript types
└── utils/           # Pure helper functions
```