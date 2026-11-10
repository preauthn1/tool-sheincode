<template>
  <header class="nav-bar">
    <button v-if="showBack" class="nav-back" type="button" aria-label="Back" @click="goBack"></button>
    <h1 class="nav-title">{{ title }}</h1>
    <div class="nav-right">
      <slot name="right"></slot>
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  fallback: {
    type: String,
    default: '/'
  },
  showBack: {
    type: Boolean,
    default: true
  }
})

const router = useRouter()

const goBack = () => {
  if (window.history.length > 1) router.back()
  else router.push(props.fallback)
}
</script>

<style scoped>
.nav-bar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 50px;
  padding: 0 10px;
  background: #ff5500;
  color: #fff;
}

.nav-back {
  position: absolute;
  left: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 0;
  background: transparent;
  color: inherit;
  z-index: 10;
}

.nav-back::before {
  content: "";
  width: 10px;
  height: 10px;
  border-left: 3px solid currentColor;
  border-bottom: 3px solid currentColor;
  transform: rotate(45deg);
}

.nav-title {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  height: 70px;
  margin: 0;
  color: inherit;
  font-size: 24px;
  font-weight: 500;
  line-height: 70px;
  text-align: center;
  padding: 0 50px;
}

.nav-right {
  position: absolute;
  right: 15px;
  display: flex;
  align-items: center;
  gap: 14px;
}
</style>
