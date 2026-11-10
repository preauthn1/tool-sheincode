<template>
  <div class="mobile-page about-page">
    <NavBar title="ABOUT" fallback="/" />

    <main class="about-container">
      <section class="about-card">
        <!-- <div class="section-heading">COMPANY PROFILE</div> -->

        <article v-if="aboutText" class="about-content">
          <p v-for="(line, index) in aboutLines" :key="index">{{ line }}</p>
        </article>

        <div v-else class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
              <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
          </div>
          <p>No company profile has been configured.</p>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import NavBar from '@/components/NavBar.vue'
import { getSystemSettings } from '@/api/user'

const aboutText = ref('')

const aboutLines = computed(() => aboutText.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean))

const loadAbout = async () => {
  try {
    const res = await getSystemSettings()
    if (res.data.code === 200) {
      aboutText.value = res.data.data.about_us || ''
    }
  } catch (error) {
    aboutText.value = ''
  }
}

onMounted(loadAbout)
</script>

<style scoped>
.about-page {
  min-height: 100vh;
  background: #f0f4f8;
  display: flex;
  flex-direction: column;
}

.about-container {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.about-card {
  flex: 1;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  padding: 32px 24px;
  animation: fadeUp 0.5s ease-out forwards;
}

.section-heading {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
  color: #0f172a;
  font-size: 20px;
  line-height: 1.2;
  font-weight: 700;
  text-align: center;
  letter-spacing: 0.5px;
}

.about-content {
  color: #334155;
  font-size: 15px;
  line-height: 1.8;
  font-weight: 400;
  word-break: break-word;
}

.about-content p {
  margin-bottom: 16px;
  white-space: pre-line;
}

.about-content p:last-child {
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

@media (max-width: 390px) {
  .about-container {
    padding: 16px 12px;
  }
  .about-card {
    padding: 24px 20px;
  }
}
</style>
