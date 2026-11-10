<template>
  <div class="mobile-page language-page">
    <header class="language-header">
      <button class="back-button" @click="router.back()">&lsaquo;</button>
      <h1>{{ t('selectLanguage') }}</h1>
    </header>

    <main class="language-list">
      <button v-for="item in languages" :key="item.code" class="language-row" @click="selectLanguage(item)">
        <span>{{ item.label }}</span>
        <span class="chevron">&rsaquo;</span>
      </button>
    </main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { setLanguage, useI18n } from '@/i18n'

const router = useRouter()
const { languages, t } = useI18n()

const selectLanguage = (item) => {
  setLanguage(item)
  ElMessage.success(item.label)
  router.push('/welcome')
}
</script>

<style scoped>
.language-page {
  min-height: 100vh;
  background: #fff;
  border-top: 4px solid #8b8585;
}

.language-header {
  position: relative;
  height: 68px;
  display: grid;
  place-items: center;
  padding-top: env(safe-area-inset-top);
}

.back-button {
  position: absolute;
  left: 18px;
  top: calc(14px + env(safe-area-inset-top));
  border: 0;
  background: transparent;
  color: #868686;
  font-size: 32px;
  line-height: 1;
}

.language-header h1 {
  margin: 0;
  color: #000;
  font-size: 18px;
  font-weight: 500;
}

.language-list {
  padding: 0 24px calc(20px + env(safe-area-inset-bottom));
}

.language-row {
  width: 100%;
  height: 54px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 0;
  border-bottom: 1px solid #d7d7d7;
  background: #fff;
  color: #000;
  font-size: 18px;
  text-align: left;
}

.language-row span:first-child {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chevron {
  flex: 0 0 auto;
  color: #858585;
  font-size: 30px;
  line-height: 1;
}

</style>
