<template>
  <div class="mobile-page welcome-page">
    <header class="welcome-top">
      <button class="language-link" @click="router.push('/language')">
        <!-- <span class="swap-icon">&harr;</span> -->
        <span>{{ language.label || t('selectLanguage') }}</span>
      </button>
      <button class="support-button" @click="openSupport" aria-label="Customer service">
        <span>24/7</span>
        <strong>HELP</strong>
      </button>
    </header>

    <main class="welcome-main">
      <div class="brand-logo">costco,</div>

      <section class="auth-card">
        <h1>{{ t('welcomeTitle') }}</h1>
        <button class="login-button" @click="router.push('/login')">{{ t('login') }}</button>
        <button class="create-button" @click="router.push('/register')">{{ t('createAccount') }}</button>
      </section>
    </main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useI18n } from '@/i18n'

const router = useRouter()
const { language, t } = useI18n()

const openSupport = () => {
  router.push('/support')
}
</script>

<style scoped>
.welcome-page {
  min-height: 100vh;
  padding: max(24px, env(safe-area-inset-top)) 22px calc(24px + env(safe-area-inset-bottom));
  background: #fdecec;
}

.welcome-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.language-link {
  min-width: 0;
  display: inline-flex;
  align-items: center;
  gap: 9px;
  border: 0;
  background: transparent;
  color: #060606;
  font-size: 17px;
}

.language-link span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.swap-icon {
  flex: 0 0 auto;
  color: #8b8b8b;
  font-size: 22px;
  line-height: 1;
}

.support-button {
  flex: 0 0 auto;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border: 3px solid #7aa2ff;
  border-radius: 50%;
  background: radial-gradient(circle at 45% 35%, #0b4f54 0, #062b34 58%, #111827 100%);
  color: #8df7d6;
  box-shadow: 0 2px 0 #d9d9d9, 0 0 0 2px #fff inset;
  font-size: 7px;
  line-height: 1;
}

.support-button strong {
  color: #32e4ff;
  font-size: 7px;
}

.welcome-main {
  min-height: calc(100vh - 58px);
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 62px;
  padding-bottom: 64px;
}

.brand-logo {
  text-align: center;
  color: #fff0f0;
  font-size: 42px;
  font-weight: 1000;
  letter-spacing: 0;
  line-height: 1;
  text-shadow:
    -3px -3px 0 #153b78,
    3px -3px 0 #153b78,
    -3px 3px 0 #153b78,
    3px 3px 0 #153b78,
    0 5px 0 #2c9be8;
}

.auth-card {
  padding: 26px 20px 42px;
  border-radius: 8px;
  background: #fff;
}

.auth-card h1 {
  margin: 0 0 24px;
  color: #000;
  font-size: 18px;
  line-height: 1.25;
  font-weight: 500;
  text-align: center;
}

.login-button,
.create-button {
  width: 100%;
  height: 50px;
  border-radius: 7px;
  font-size: 18px;
}

.login-button {
  border: 0;
  background: #ff4b05;
  color: #fff;
}

.create-button {
  margin-top: 22px;
  border: 1px solid #d3d3d3;
  background: #fff;
  color: #60656d;
}

</style>
