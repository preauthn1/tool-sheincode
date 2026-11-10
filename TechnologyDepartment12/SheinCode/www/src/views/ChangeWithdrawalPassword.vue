<template>
  <div class="mobile-page withdraw-pwd-page">
    <NavBar title="CHANGE PASSWORD" :showBack="true" />
    
    <div class="withdraw-pwd-body">
      <div class="form-card">
        <div class="form-group">
          <label>Withdrawal Password*</label>
          <input type="password" v-model="oldPassword" placeholder="Please enter the old withdrawal password" />
        </div>

        <div class="form-group">
          <label>New Withdraw Password*</label>
          <input type="password" v-model="newPassword" placeholder="Please enterWithdrawal Password" />
        </div>

        <div class="form-group">
          <label>Confirm New Withdraw Password*</label>
          <input type="password" v-model="confirmPassword" placeholder="Please enterConfirm withdrawal password" />
        </div>
      </div>

      <button class="confirm-btn" :disabled="submitting" @click="submit">
        {{ submitting ? 'Submitting...' : 'Confirm' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import { changeWithdrawalPassword } from '@/api/user'

const router = useRouter()
const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const submitting = ref(false)

const submit = async () => {
  if (!oldPassword.value || !newPassword.value || !confirmPassword.value) {
    ElMessage.warning('Please fill in all fields')
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    ElMessage.warning('Passwords do not match')
    return
  }

  submitting.value = true
  try {
    const res = await changeWithdrawalPassword({
      old_password: oldPassword.value,
      new_password: newPassword.value,
      confirm_password: confirmPassword.value,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success(res.data.msg || 'Withdrawal password changed successfully')
    router.back()
  } catch (error) {
    ElMessage.error(error.message || 'Withdrawal password update failed')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.withdraw-pwd-page {
  background: #f8fafc;
  min-height: 100vh;
}

.withdraw-pwd-body {
  padding: 16px;
}

.form-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.form-group {
  margin-bottom: 20px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  font-size: 15px;
  color: #1f2933;
  margin-bottom: 10px;
}

.form-group input {
  width: 100%;
  padding: 14px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 12px;
  color: #333;
  outline: none;
}

.form-group input::placeholder {
  color: #94a3b8;
}

.form-group input:focus {
  border-color: #ff5500;
}

.confirm-btn {
  width: 100%;
  background: #ff5500;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 14px 0;
  font-size: 16px;
  font-weight: 500;
  margin-top: 24px;
}

.confirm-btn:disabled {
  opacity: 0.6;
}
</style>
