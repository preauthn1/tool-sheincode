<template>
  <div class="mobile-page my-page">
    <NavBar title="MY" :showBack="false">
      <template #right>
        <!-- <button class="icon-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button> -->
        <button class="icon-btn" @click="router.push('/settings')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l8.66 5v10L12 22l-8.66-5V7z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </button>
      </template>
    </NavBar>

    <main class="my-body">
      <div class="user-info-section">
        <div class="user-top-row">
          <img src="/service.png" class="user-avatar" alt="Avatar" />
          <button class="deposit-btn" @click="goTo('/support')">Deposit</button>
        </div>
        <div class="user-details">
          <h2 class="user-id">{{ userInfo.phone || userInfo.id || '716093552' }}</h2>
          <p>Invitation Code: {{ userInfo.my_invite_code || '-' }}</p>
          <p>Credit score: 100</p>
        </div>
      </div>

      <div class="balance-card">
        <div class="balance-item">
          <strong>$ {{ formatMoney(userInfo.balance) }}</strong>
          <span>Account Balance</span>
        </div>
        <div class="balance-item">
          <strong>$ {{ formatMoney(userInfo.frozen_balance) }}</strong>
          <span>Frozen Balance</span>
        </div>
      </div>

      <nav class="menu-list">
        <div class="menu-item" @click="router.push('/my-account')">
          <span>My Account</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="menu-item" @click="router.push('/bill-details')">
          <span>Bill details</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="menu-item" @click="router.push('/bind-wallet')">
          <span>Link wallet address</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <!-- <div class="menu-item">
          <span>Link wallet address</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div> -->
        <div class="menu-item" @click="inviteOpen = true">
          <span>Invite Friends</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="menu-item" @click="languageModalOpen = true">
          <span>Select Language</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="menu-item" @click="logoutConfirmOpen = true">
          <span>Log out</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
      </nav>

      <button class="fab-btn" @click="contactSupport">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          <circle cx="8" cy="10" r="1" fill="currentColor" stroke="none"></circle>
          <circle cx="12" cy="10" r="1" fill="currentColor" stroke="none"></circle>
          <circle cx="16" cy="10" r="1" fill="currentColor" stroke="none"></circle>
        </svg>
      </button>
    </main>

    <div v-if="inviteOpen" class="modal-mask" @click.self="inviteOpen = false">
      <section class="invite-modal">
        <button class="invite-close" @click="inviteOpen = false">x</button>
        <h2>Welcome to Costco</h2>
        <p>Invite friends to get<br />commission</p>
        <img class="invite-image" src="../assets/invite.png" alt="" />
        <strong>Promotion code:&nbsp;&nbsp;{{ userInfo.my_invite_code || '-' }}</strong>
        <button class="invite-copy" @click="copyInviteCode">Copy invitation link</button>
      </section>
    </div>

    <div v-if="logoutConfirmOpen" class="modal-mask" @click.self="logoutConfirmOpen = false">
      <div class="confirm-modal">
        <h3 class="confirm-title">Logout</h3>
        <p class="confirm-desc">Log out the account?</p>
        <div class="confirm-actions">
          <button class="confirm-cancel" @click="logoutConfirmOpen = false">Cancel</button>
          <button class="confirm-ok" @click="logout">OK</button>
        </div>
      </div>
    </div>

    <!-- Language Selection Modal -->
    <div v-if="languageModalOpen" class="modal-mask" @click.self="languageModalOpen = false">
      <div class="language-modal">
        <h3 class="language-title">Select Language</h3>
        <div class="language-list">
          <button v-for="item in languages" :key="item.code" class="language-row" @click="handleSelectLanguage(item)">
            <span>{{ item.label }}</span>
            <svg class="chevron-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"></path></svg>
          </button>
        </div>
      </div>
    </div>

    <TabBar />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import TabBar from '@/components/TabBar.vue'
import { useUserStore } from '@/store/user'
import { useI18n, setLanguage } from '@/i18n'

const router = useRouter()
const userStore = useUserStore()
const { t, languages } = useI18n()
const userInfo = computed(() => userStore.userInfo)
const inviteOpen = ref(false)
const logoutConfirmOpen = ref(false)
const languageModalOpen = ref(false)

const formatMoney = (value) => Number(value || 0).toFixed(2)

const handleSelectLanguage = (item) => {
  setLanguage(item)
  ElMessage.success(item.label)
  languageModalOpen.value = false
}

const copyText = async (text, message) => {
  await navigator.clipboard.writeText(text)
  ElMessage.success(message)
}

const goTo = (path) => {
  if (router.currentRoute.value.path !== path) {
    router.push(path)
  }
}

const copyInviteCode = async () => {
  const code = userInfo.value.my_invite_code
  if (!code) {
    ElMessage.warning('No invitation code')
    return
  }
  await copyText(code, 'Invitation code copied')
}

const copySupport = () => {
  copyText('support@partner-hub.local', 'Support copied')
}

const contactSupport = () => {
  userStore.toggleChat(true)
}

const logout = () => {
  userStore.clearUserInfo()
  router.replace('/login')
}

onMounted(() => {
  userStore.fetchUserInfo()
})
</script>

<style scoped>
.my-page {
  min-height: 100vh;
  padding-bottom: 88px;
  background: #fcfcfd;
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

.my-body {
  padding: 24px 16px;
  position: relative;
  min-height: calc(100vh - 70px - 88px);
}

.user-info-section {
  display: flex;
  flex-direction: column;
}

.user-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.user-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
}

.deposit-btn {
  background: #ffc1a3;
  color: #ff5500;
  border: none;
  border-radius: 999px;
  padding: 8px 30px;
  font-size: 15px;
  font-weight: 500;
}

.user-details {
  margin-top: 24px;
}

.user-id {
  font-size: 22px;
  font-weight: 800;
  color: #000;
  margin: 0 0 10px;
}

.user-details p {
  font-size: 15px;
  color: #333;
  margin: 0 0 8px;
}

.balance-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: #000;
  border-radius: 8px;
  margin-top: 24px;
  padding: 24px 0;
}

.balance-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.balance-item strong {
  color: #fff;
  font-size: 16px;
  font-weight: bold;
}

.balance-item span {
  color: #fff;
  font-size: 14px;
  margin-top: 6px;
}

.menu-list {
  margin-top: 30px;
  display: flex;
  flex-direction: column;
}

.menu-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid #ffccb3;
  cursor: pointer;
}

.menu-item span {
  font-size: 16px;
  color: #333;
}

.chevron {
  width: 20px;
  height: 20px;
  color: #999;
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

/* Modal styles kept for functionality */
.modal-mask {
  position: fixed;
  z-index: 50;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(0, 0, 0, 0.45);
}

.info-modal {
  width: 100%;
  max-width: min(100%, 8.4rem);
  padding: 18px;
  border-radius: 8px;
  background: #fff;
}

.invite-modal {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 335px;
  padding: 50px 18px 28px;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.22);
  text-align: left;
}

.invite-close {
  position: absolute;
  top: 14px;
  right: 18px;
  width: 22px;
  height: 22px;
  border: 0;
  background: transparent;
  color: #c6c8ce;
  font-weight: 200;
  line-height: 0.75;
}

.invite-modal h2,
.invite-modal p {
  margin: 0;
}

.invite-modal h2 {
  color: #ff4b00;
  font-size: 22px;
  line-height: 1.2;
  font-weight: 900;
}

.invite-modal p {
  margin-top: 12px;
  color: #181b20;
  font-size: 23px;
}

.invite-image {
  display: block;
  width: 250px;
  height: 250px;
  margin: 18px auto 20px;
  object-fit: contain;
}

.invite-modal strong {
  display: block;
  color: #101318;
  font-size: 20px;
  font-weight: 500;
  line-height: 1.25;
  text-align: center;
  overflow-wrap: anywhere;
}

.invite-copy {
  display: block;
  width: 300px;
  height: 58px;
  margin: 30px auto 0;
  border: 0;
  border-radius: 999px;
  background: #ff4b00;
  color: #fff;
  font-size: 20px;
  font-weight: 900;
}

.info-modal header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.info-modal h2 {
  font-size: 18px;
  margin: 0;
}

.info-modal header button {
  border: 0;
  background: transparent;
  color: #7b8794;
  font-size: 20px;
  font-weight: 900;
}

.info-modal p {
  margin: 14px 0;
  color: #52606d;
  font-size: 14px;
  line-height: 1.7;
}

.confirm-modal {
  width: 300px;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  text-align: center;
}

.confirm-title {
  font-size: 18px;
  color: #000;
  margin: 20px 0 10px;
  font-weight: 500;
}

.confirm-desc {
  font-size: 15px;
  color: #999;
  margin: 0 0 20px;
  padding: 0 20px;
}

.confirm-actions {
  display: flex;
  border-top: 1px solid #f0f0f0;
}

.confirm-actions button {
  flex: 1;
  height: 50px;
  border: none;
  background: transparent;
  font-size: 16px;
  cursor: pointer;
}

.confirm-cancel {
  color: #000;
  border-right: 1px solid #f0f0f0 !important;
}

.confirm-ok {
  color: #007aff;
}

/* Language Modal */
.language-modal {
  width: 100%;
  max-width: 400px;
  max-height: 85vh;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.language-title {
  margin: 0;
  padding: 24px 20px 12px;
  text-align: center;
  font-size: 18px;
  font-weight: 500;
  color: #1f2933;
}

.language-list {
  flex: 1;
  overflow-y: auto;
  padding: 0 20px 20px;
}

.language-row {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border: none;
  background: transparent;
  border-bottom: 1px solid #f1f5f9;
  font-size: 16px;
  color: #1f2933;
  text-align: left;
}

.language-row:last-child {
  border-bottom: none;
}

.chevron-sm {
  width: 16px;
  height: 16px;
  color: #94a3b8;
}
</style>
