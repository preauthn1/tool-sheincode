<template>
  <router-view />
  <CustomerServiceChat />
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import CustomerServiceChat from '@/components/CustomerServiceChat.vue'
import { sendHeartbeat } from '@/api/user'

const HEARTBEAT_INTERVAL = 60 * 1000
let heartbeatTimer = null

const beat = () => {
  if (!localStorage.getItem('token')) return
  sendHeartbeat().catch(() => {})
}

const handleVisibility = () => {
  if (document.visibilityState === 'visible') beat()
}

onMounted(() => {
  beat()
  heartbeatTimer = setInterval(beat, HEARTBEAT_INTERVAL)
  document.addEventListener('visibilitychange', handleVisibility)
})

onUnmounted(() => {
  if (heartbeatTimer) clearInterval(heartbeatTimer)
  document.removeEventListener('visibilitychange', handleVisibility)
})
</script>

<style>
:root {
  --app-max-width: 10rem;
  --page-bg: #fff7f2;
  font-family: Inter, "PingFang SC", "Microsoft YaHei", Arial, sans-serif;
  color: #1f2933;
  background: var(--page-bg);
  font-synthesis: none;
  text-rendering: optimizeLegibility;
  -webkit-font-smoothing: antialiased;
  -webkit-text-size-adjust: 100%;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-width: 320px;
  background: #e9eef5;
  overflow-x: hidden;
}

button,
input,
select,
textarea {
  font: inherit;
  min-width: 0;
}

input::placeholder,
textarea::placeholder {
  text-overflow: ellipsis;
}

button {
  cursor: pointer;
}

#app {
  min-height: 100vh;
  background: #e9eef5;
}

.mobile-page {
  width: 100%;
  max-width: var(--app-max-width);
  min-height: 100vh;
  margin: 0 auto;
  background: var(--page-bg);
  overflow-x: hidden;
}

.mobile-page .el-input__wrapper {
  min-height: 40px;
}

.mobile-page .el-input__inner {
  min-width: 0;
}

.shell-page {
  padding: 14px 14px calc(88px + env(safe-area-inset-bottom));
}

.section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 18px 2px 10px;
  font-size: 16px;
  font-weight: 800;
  color: #1f2933;
}

.panel {
  border: 1px solid #ffe4d1;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 10px 28px rgba(122, 72, 24, 0.08);
}

.muted {
  color: #7b8794;
}

.primary-button {
  width: 100%;
  border: 0;
  border-radius: 8px;
  padding: 14px 16px;
  background: #f97316;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  box-shadow: 0 10px 22px rgba(249, 115, 22, 0.22);
}

.secondary-button {
  width: 100%;
  border: 1px solid #fed7aa;
  border-radius: 8px;
  padding: 13px 16px;
  background: #fff;
  color: #c2410c;
  font-size: 15px;
  font-weight: 800;
}

</style>
