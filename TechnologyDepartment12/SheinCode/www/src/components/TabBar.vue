<template>
  <div class="tabbar">
    <button :class="{ active: $route.path === '/' }" @click="goTo('/')">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>
      <span>Home</span>
    </button>
    <button :class="{ active: $route.path === '/orders' }" @click="goTo('/orders')">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></span>
      <span>Orders</span>
    </button>
    <button :class="{ active: $route.path === '/tasks' }" @click="goTo('/tasks')">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></span>
      <span>Tasks</span>
    </button>
    <button :class="{ active: $route.path === '/support' }" @click="goTo('/support')">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></span>
      <span>Service</span>
    </button>
    <button :class="{ active: $route.path === '/profile' }" @click="goTo('/profile')">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
      <span>My</span>
    </button>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
const goTo = (path) => {
  if (router.currentRoute.value.path !== path) {
    router.push(path)
  }
}
</script>

<style scoped>
.tabbar {
  position: fixed;
  z-index: 20;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  max-width: var(--app-max-width);
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
  background: #ff5500;
  border-top: none;
}

button {
  flex: 1;
  display: grid;
  gap: 3px;
  place-items: center;
  border: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.9);
  font-size: 11px;
  font-weight: 700;
}

.icon {
  display: grid;
  width: 24px;
  height: 24px;
  place-items: center;
  border-radius: 50%;
}

.icon svg {
  width: 20px;
  height: 20px;
}

button.active {
  color: #fff;
}

button.active .icon {
  background: rgba(255, 255, 255, 0.2);
}
</style>
