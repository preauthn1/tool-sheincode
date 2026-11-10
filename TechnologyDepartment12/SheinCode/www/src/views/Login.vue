<template>
  <div class="mobile-page login-page">
    <NavBar :title="t('loginTopTitle')" fallback="/welcome" />
    <h1 class="page-title">{{ t('loginPageTitle') }}</h1>

    <section class="login-card">
      <label class="field">
        <span>{{ t('phoneRequired') }}</span>
        <div class="phone-row">
          <select v-model="form.country_code" aria-label="Country code">
            <option value="+1">US+1</option>
          </select>
          <input v-model.trim="form.phone" inputmode="numeric" autocomplete="username"
            :placeholder="t('phonePlaceholder')" />
        </div>
      </label>

      <label class="field">
        <span>{{ t('passwordInputRequired') }}</span>
        <div class="password-wrapper">
          <input v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password"
            :placeholder="t('passwordPlaceholder')" />
          <button type="button" class="eye-icon" @click.prevent="showPassword = !showPassword">
            <svg v-if="!showPassword" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <svg v-else viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
          </button>
        </div>
      </label>

      <button class="forgot-link" @click="handleForgot">{{ t('forgotPassword') }}</button>

      <button class="submit-button" :disabled="loading" @click="handleLogin">
        {{ loading ? t('loggingIn') : t('login') }}
      </button>
    </section>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { login } from '@/api/user'
import { useUserStore } from '@/store/user'
import { useI18n } from '@/i18n'
import NavBar from '@/components/NavBar.vue'

const router = useRouter()
const userStore = useUserStore()
const { t } = useI18n()
const loading = ref(false)
const showPassword = ref(false)
const form = reactive({ country_code: '+1', phone: '', password: '' })

const loginAccount = () => String(form.phone || '').trim()

const handleForgot = () => {
  router.push('/support?from=forgot-password')
}

const handleLogin = async () => {
  if (!loginAccount() || !form.password) {
    ElMessage.warning(t('phonePasswordRequired'))
    return
  }

  loading.value = true
  try {
    const res = await login({ phone: loginAccount(), password: form.password })
    if (res.data.code === 200) {
      userStore.setToken(res.data.data.token)
      userStore.setUserInfo(res.data.data.user || res.data.data || {})
      ElMessage.success(t('loginSuccess'))
      router.replace('/')
    } else {
      ElMessage.error(res.data.msg || t('loginFailed'))
    }
  } catch (error) {
    ElMessage.error(t('loginFailed'))
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  padding-top: 1px; /* Prevent margin collapse */
  background: #fdecec;
}

.page-title {
  margin: 56px 0 40px;
  color: #111827;
  text-align: center;
  font-size: 22px;
  line-height: 1.25;
}

.login-card {
  padding: 28px 24px;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.field {
  display: block;
  margin-bottom: 24px;
}

.field span {
  display: block;
  margin: 0 0 12px 2px;
  color: #374151;
  font-size: 14px;
  font-weight: 500;
  line-height: 1;
}

.field input,
.phone-row select {
  height: 48px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
  color: #111827;
  font-size: 15px;
  outline: none;
  transition: all 0.2s ease;
}

.field input {
  width: 100%;
  min-width: 0;
  padding: 0 14px;
}

.field input:focus,
.phone-row select:focus {
  background: #fff;
  border-color: #ff8a00;
  box-shadow: 0 0 0 3px rgba(255, 138, 0, 0.1);
}

.password-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-wrapper input {
  padding-right: 44px; /* space for icon */
}

.eye-icon {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: #9ca3af;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  cursor: pointer;
  transition: color 0.2s;
}

.eye-icon:hover {
  color: #4b5563;
}

.phone-row {
  display: grid;
  grid-template-columns: 100px minmax(0, 1fr);
  gap: 8px;
}

.phone-row select {
  width: 100%;
  padding: 0 10px;
  appearance: none; /* optional: to style select further */
}

.forgot-link {
  display: inline-block;
  border: 0;
  background: transparent;
  color: #6b7280;
  font-size: 14px;
  line-height: 1;
  text-decoration: none;
  text-align: left;
  margin-top: -8px;
  transition: color 0.2s;
  cursor: pointer;
}

.forgot-link:hover {
  color: #111827;
}

.submit-button {
  width: 100%;
  height: 48px;
  border: 0;
  border-radius: 8px;
  background: #ff8a00;
  color: #fff;
  font-weight: 600;
  font-size: 16px;
  margin-top: 32px;
  cursor: pointer;
  transition: background 0.2s;
}

.submit-button:hover {
  background: #e67a00;
}

.submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
