<template>
  <div class="mobile-page password-page">
    <NavBar title="CHANGE PASSWORD" :showBack="true" />
    
    <main class="password-body">
      <div class="form-group">
        <label>Old password*</label>
        <input type="password" v-model="oldPassword" placeholder="Please enterOld password" />
      </div>

      <div class="form-group">
        <label>New password*</label>
        <input type="password" v-model="newPassword" placeholder="Please enterNew password" />
      </div>

      <div class="form-group">
        <input type="password" v-model="confirmPassword" placeholder="Please enterConfirm password" />
      </div>

      <button class="confirm-btn" :disabled="submitting" @click="submit">
        {{ submitting ? 'Submitting...' : 'Confirm change' }}
      </button>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import { changeLoginPassword } from '@/api/user'

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
    const res = await changeLoginPassword({
      old_password: oldPassword.value,
      new_password: newPassword.value,
      confirm_password: confirmPassword.value,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success(res.data.msg || 'Login password changed successfully')
    router.back()
  } catch (error) {
    ElMessage.error(error.message || 'Password update failed')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.password-page {
  background: #fff;
  min-height: 100vh;
}

.password-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  font-size: 15px;
  color: #1f2933;
  margin-bottom: 12px;
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
  margin-top: 20px;
}

.confirm-btn:disabled {
  opacity: 0.6;
}
</style>
