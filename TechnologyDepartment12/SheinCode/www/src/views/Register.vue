<template>
  <div class="mobile-page register-page">
    <header class="register-top">
      <button class="back" @click="$router.push('/welcome')">&lsaquo;</button>
      <span>{{ t('registerTopTitle') }}</span>
    </header>

    <h1 class="page-title">{{ t('registerTitle') }}</h1>

    <section class="register-card">
      <label class="field">
        <span>{{ t('phoneRequired') }}</span>
        <div class="phone-row">
          <select v-model="form.country_code" aria-label="Country code">
            <option value="+1">US+1</option>
          </select>
          <input v-model.trim="form.phone" inputmode="numeric" autocomplete="tel-national"
            :placeholder="t('phonePlaceholder')" />
        </div>
      </label>

      <label class="field">
        <span>{{ t('passwordRequired') }}</span>
        <input v-model="form.password" type="password" autocomplete="new-password"
          :placeholder="t('passwordCreatePlaceholder')" />
      </label>

      <label class="field">
        <span>{{ t('confirmPassword') }}</span>
        <input v-model="form.confirm_password" type="password" autocomplete="new-password"
          :placeholder="t('confirmPasswordPlaceholder')" />
      </label>

      <label class="field">
        <span>{{ t('withdrawalPassword') }}</span>
        <input v-model="form.withdrawal_password" type="password" autocomplete="new-password"
          :placeholder="t('withdrawalPasswordPlaceholder')" />
      </label>

      <label class="field">
        <span>{{ t('inviteCode') }}</span>
        <input v-model.trim="form.invite_code" :placeholder="t('inviteCodePlaceholder')" />
      </label>

      <div class="agreement">
        <input v-model="agreed" type="checkbox" />
        <span class="agreement-link" @click="showAgreement = true">{{ t('agreement') }}</span>
      </div>
    </section>

    <button class="submit-button enabled" :disabled="loading" @click="handleRegister">
      {{ loading ? t('registering') : t('registerSubmit') }}
    </button>

    <!-- Agreement Modal -->
    <div v-if="showAgreement" class="modal-overlay" @click.self="showAgreement = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Registration Agreement</h2>
          <button class="close-btn" @click="showAgreement = false">X</button>
        </div>
        <div class="modal-body">
          <template v-if="agreementLines.length">
            <p v-for="(line, index) in agreementLines" :key="index">{{ line }}</p>
          </template>
          <p v-else>{{ t('agreementEmpty') }}</p>
        </div>
        <div class="modal-footer">
          <button class="confirm-btn" @click="agreed = true; showAgreement = false">Confirm Registration</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getSystemSettings, register } from '@/api/user'
import { useUserStore } from '@/store/user'
import { useI18n } from '@/i18n'

const router = useRouter()
const userStore = useUserStore()
const { t } = useI18n()
const loading = ref(false)
const agreed = ref(false)
const showAgreement = ref(false)
const agreementText = ref('')
const form = reactive({
  country_code: '+1',
  phone: '',
  password: '',
  confirm_password: '',
  withdrawal_password: '',
  invite_code: '',
})

const normalizePhone = () => `${form.country_code}${String(form.phone || '').replace(/\D/g, '')}`
const isUsPhone = computed(() => form.country_code === '+1' && /^[2-9]\d{6,9}$/.test(String(form.phone || '').replace(/\D/g, '')))
const agreementLines = computed(() => agreementText.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean))

const loadAgreement = async () => {
  try {
    const res = await getSystemSettings()
    if (res.data.code === 200) {
      agreementText.value = res.data.data.register_agreement || ''
    }
  } catch (error) {
    agreementText.value = ''
  }
}

const handleRegister = async () => {
  if (!isUsPhone.value) return ElMessage.warning(t('usOnly'))
  if (!form.password || !form.confirm_password || !form.withdrawal_password) return ElMessage.warning(t('registerRequired'))
  if (!form.invite_code) return ElMessage.warning(t('inviteCodeRequired'))
  if (form.password !== form.confirm_password) return ElMessage.warning(t('passwordMismatch'))
  if (!agreed.value) return ElMessage.warning(t('agreementRequired'))

  loading.value = true
  try {
    const res = await register({
      phone: normalizePhone(),
      password: form.password,
      confirm_password: form.confirm_password,
      withdrawal_password: form.withdrawal_password,
      invite_code: form.invite_code,
    })
    if (res.data.code === 200) {
      userStore.setToken(res.data.data.token)
      userStore.setUserInfo(res.data.data)
      ElMessage.success(t('registerSuccess'))
      router.replace('/')
    } else {
      ElMessage.error(res.data.msg || t('registerFailed'))
    }
  } catch (error) {
    ElMessage.error(t('registerFailed'))
  } finally {
    loading.value = false
  }
}

onMounted(loadAgreement)
</script>

<style scoped>
.register-page {
  min-height: 100vh;
  padding: 14px 18px calc(28px + env(safe-area-inset-bottom));
  background: #fdecec;
}

.register-top {
  position: relative;
  height: 44px;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 20px;
  font-weight: 400;
  letter-spacing: 0;
}

.back {
  position: absolute;
  left: -4px;
  top: 6px;
  border: 0;
  background: transparent;
  /* color: #fff; */
  font-size: 38px;
  line-height: 1;
}

.page-title {
  margin: 14px 0 18px;
  color: #111827;
  text-align: center;
  font-size: 25px;
  line-height: 1;
}

.register-card {
  padding: 16px 14px 4px;
  border-radius: 8px;
  background: #fff;
}

.field {
  display: block;
  margin-bottom: 15px;
}

.field span {
  display: block;
  margin: 0 0 9px 6px;
  color: #1f2933;
  font-size: 16px;
  line-height: 1;
}

.field input,
.phone-row select {
  height: 42px;
  border: 1px solid #d5d5d5;
  border-radius: 5px;
  background: #fff;
  color: #111;
  font-size: 16px;
  outline: none;
}

.field input {
  width: 100%;
  min-width: 0;
  padding: 0 10px;
  font-size: 15px;
}

.field input:focus,
.phone-row select:focus {
  background: #eaf2ff;
  border-color: #c7d7ef;
}

.phone-row {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr);
  gap: 8px;
}

.phone-row select {
  padding: 0 8px;
}

.agreement {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 0 0 4px;
  color: #000;
  font-size: 15px;
}

.agreement input {
  width: 22px;
  height: 22px;
  margin: 0;
  accent-color: #ff4b05;
}

.submit-button {
  width: 100%;
  height: 46px;
  margin-top: 18px;
  border: 0;
  border-radius: 5px;
  background: #a69c9c;
  color: #fff;
  font-size: 16px;
}

.submit-button.enabled {
  background: #ff4b05;
}

.submit-button:disabled {
  cursor: not-allowed;
}

.agreement-link {
  text-decoration: underline;
  cursor: pointer;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: #fff;
  border-radius: 10px;
  width: 100%;
  max-width: 400px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: bold;
  color: #000;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  cursor: pointer;
  color: #333;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  font-size: 14px;
  line-height: 1.6;
  color: #333;
}

.modal-body p {
  margin: 0 0 12px 0;
}

.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid #eee;
}

.confirm-btn {
  width: 100%;
  padding: 14px;
  background: #000;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
}
</style>
