<template>
  <div class="mobile-page service-page">
    <NavBar title="SERVICE" :showBack="false">
      <!-- <template #right>
        <button class="icon-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button>
      </template> -->
    </NavBar>

    <main class="service-body">
      <div class="service-card">
        <div class="card-header">
          <img src="/service.png" alt="Service" class="service-icon" @click="contactSupport" />
          <h2>Hello,</h2>
        </div>
        <p class="service-desc">
          Welcome to use! If you encounter any problems with deposit and withdrawal during use, please contact our customer service team.
        </p>
        <button class="contact-btn" @click="contactSupport">
          Contact customer service
        </button>
        <p class="hours">Operating hours: {{ customerServiceHours }}</p>
      </div>

      <button class="fab-btn" @click="contactSupport">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          <circle cx="8" cy="10" r="1" fill="currentColor" stroke="none"></circle>
          <circle cx="12" cy="10" r="1" fill="currentColor" stroke="none"></circle>
          <circle cx="16" cy="10" r="1" fill="currentColor" stroke="none"></circle>
        </svg>
      </button>
    </main>
    
    <TabBar />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { getSystemSettings } from '@/api/user'
import NavBar from '@/components/NavBar.vue'
import TabBar from '@/components/TabBar.vue'
import { useUserStore } from '@/store/user'

const userStore = useUserStore()
const customerServiceHours = ref('')

const contactSupport = () => {
  userStore.toggleChat(true)
}

const loadSettings = async () => {
  try {
    const res = await getSystemSettings()
    if (res.data.code === 200 && res.data.data.customer_service_hours) {
      customerServiceHours.value = res.data.data.customer_service_hours
    }
  } catch (error) {
    console.error('Failed to load service hours', error)
  }
}

onMounted(loadSettings)
</script>

<style scoped>
.service-page {
  min-height: 100vh;
  padding-bottom: 88px;
  background: #f4f5f7;
}

.icon-btn {
  background: transparent;
  border: none;
  color: #fff;
  padding: 0;
  display: flex;
  align-items: center;
}

.icon-btn svg {
  width: 24px;
  height: 24px;
}

.service-body {
  padding: 24px 16px;
  position: relative;
  min-height: calc(100vh - 70px - 88px);
}

.service-card {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.service-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  object-fit: cover;
}

.card-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 400;
  color: #000;
}

.service-desc {
  margin-top: 20px;
  font-size: 15px;
  color: #333;
  line-height: 1.5;
}

.contact-btn {
  width: 100%;
  margin-top: 30px;
  background: #ff5500;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 14px 0;
  font-size: 16px;
  font-weight: 500;
}

.hours {
  margin-top: 20px;
  text-align: center;
  font-size: 14px;
  color: #666;
}

.fab-btn {
  position: fixed;
  bottom: 110px;
  right: 20px;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #e60000;
  color: #fff;
  border: none;
  display: grid;
  place-items: center;
  box-shadow: 0 4px 12px rgba(230,0,0,0.3);
  z-index: 10;
}

.fab-btn svg {
  width: 30px;
  height: 30px;
}
</style>
