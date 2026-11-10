<template>
  <div class="mobile-page settings-page">
    <NavBar title="SETTINGS" :showBack="true" />
    
    <main class="settings-body">
      <div class="settings-list panel">
        <div class="settings-item" @click="router.push('/settings/login-password')">
          <span>Login password</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="settings-item" @click="router.push('/settings/withdrawal-password')">
          <span>Withdrawal Password</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <div class="settings-item empty"></div>
        <div class="settings-item" @click="openNicknameModal">
          <span>User nickname</span>
          <em>{{ userStore.userInfo.nickname || 'Not set' }}</em>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
      </div>
    </main>

    <!-- Nickname Modal -->
    <div v-if="showNicknameModal" class="modal-mask" @click.self="showNicknameModal = false">
      <div class="nickname-modal">
        <h3>Change Nickname</h3>
        <input type="text" v-model="newNickname" placeholder="Enter new nickname" />
        <div class="modal-actions">
          <button class="cancel-btn" @click="showNicknameModal = false">Cancel</button>
          <button class="confirm-btn" :disabled="submitting" @click="confirmNickname">
            {{ submitting ? 'Submitting...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import { updateUserProfile } from '@/api/user'
import { useUserStore } from '@/store/user'

const router = useRouter()
const userStore = useUserStore()
const showNicknameModal = ref(false)
const newNickname = ref('')
const submitting = ref(false)

const openNicknameModal = () => {
  newNickname.value = userStore.userInfo.nickname || ''
  showNicknameModal.value = true
}

const confirmNickname = async () => {
  if (!newNickname.value.trim()) {
    ElMessage.warning('Nickname cannot be empty')
    return
  }

  submitting.value = true
  try {
    const res = await updateUserProfile({ nickname: newNickname.value.trim() })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    userStore.setUserInfo(res.data.data || { nickname: newNickname.value.trim() })
    ElMessage.success(res.data.msg || 'Nickname updated successfully')
    showNicknameModal.value = false
    newNickname.value = ''
  } catch (error) {
    ElMessage.error(error.message || 'Nickname update failed')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  userStore.fetchUserInfo()
})
</script>

<style scoped>
.settings-page {
  background: #f8fafc;
  min-height: 100vh;
}

.settings-body {
  padding: 16px;
}

.settings-list {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.settings-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  color: #333;
  font-size: 15px;
}

.settings-item em {
  flex: 1;
  margin-left: 12px;
  color: #94a3b8;
  font-size: 13px;
  font-style: normal;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.settings-item:last-child {
  border-bottom: none;
}

.settings-item.empty {
  padding: 16px;
  cursor: default;
}

.chevron {
  width: 20px;
  height: 20px;
  color: #94a3b8;
}

.modal-mask {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.nickname-modal {
  width: 100%;
  max-width: 320px;
  background: #fff;
  border-radius: 12px;
  padding: 24px;
}

.nickname-modal h3 {
  margin: 0 0 16px;
  font-size: 18px;
  color: #1f2933;
}

.nickname-modal input {
  width: 100%;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 15px;
  margin-bottom: 20px;
  outline: none;
}

.nickname-modal input:focus {
  border-color: #ff5500;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.cancel-btn, .confirm-btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  border: none;
}

.cancel-btn {
  background: #f1f5f9;
  color: #64748b;
}

.confirm-btn {
  background: #ff5500;
  color: #fff;
}

.confirm-btn:disabled {
  opacity: 0.6;
}
</style>
