<template>
  <div class="mobile-page terms-page">
    <NavBar title="TERMS&AGREEMENTS" />

    <main class="terms-container">
      <article class="terms-card">
        <template v-if="termsText">
          <div class="terms-content">
            <p v-for="(line, index) in termsLines" :key="index">{{ line }}</p>
          </div>
        </template>
        <template v-else>
          <div class="empty-state">
            <div class="empty-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
            </div>
            <p>There are no terms or agreements.</p>
          </div>
        </template>
      </article>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import NavBar from '@/components/NavBar.vue'
import { getSystemSettings } from '@/api/user'

const termsText = ref('')

const termsLines = computed(() => termsText.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean))

const loadTerms = async () => {
  try {
    const res = await getSystemSettings()
    if (res.data.code === 200) {
      termsText.value = res.data.data.terms_agreements || ''
    }
  } catch (error) {
    termsText.value = ''
  }
}

onMounted(loadTerms)
</script>

<style scoped>
.terms-page {
  min-height: 100vh;
  background: #f0f4f8;
  display: flex;
  flex-direction: column;
}

.terms-container {
  flex: 1;
  padding: 20px 16px;
  display: flex;
  flex-direction: column;
}

.terms-card {
  flex: 1;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  padding: 32px 24px;
  animation: fadeUp 0.5s ease-out forwards;
}

.terms-content {
  color: #334155;
  font-size: 15px;
  line-height: 1.8;
  font-weight: 400;
}

.terms-content p {
  white-space: pre-line;
  margin-bottom: 16px;
}

.terms-content p:last-child {
  margin-bottom: 0;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 300px;
  color: #94a3b8;
  text-align: center;
}

.empty-icon {
  width: 64px;
  height: 64px;
  margin-bottom: 20px;
  color: #cbd5e1;
  animation: float 3s ease-in-out infinite;
}

.empty-icon svg {
  width: 100%;
  height: 100%;
}

.empty-state p {
  font-size: 16px;
  font-weight: 500;
  color: #64748b;
}

@keyframes fadeUp {
  0% {
    opacity: 0;
    transform: translateY(12px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes float {
  0% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-8px);
  }
  100% {
    transform: translateY(0px);
  }
}
</style>
