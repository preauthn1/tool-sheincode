<template>
  <div class="mobile-page bind-wallet-page">
    <NavBar title="WALLET ADDRESS" :showBack="true" />
    
    <main class="bind-wallet-body">
      <div class="form-group">
        <label>Currency</label>
        <div class="custom-select" :class="{ open: isOpen }" @click="isOpen = !isOpen" v-click-outside="() => isOpen = false">
          <div class="selected-value">
            <span>{{ form.currency }}</span>
            <svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <div class="options-list" v-if="isOpen">
            <div class="option" v-for="opt in ['USDT', 'BTC', 'ETH']" :key="opt" @click.stop="selectOption(opt)">
              {{ opt }}
            </div>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Address</label>
        <input type="text" v-model="form.address" />
      </div>

      <div class="form-group">
        <label>Note</label>
        <input type="text" v-model="form.note" placeholder="Please enterNote" />
      </div>

      <button class="confirm-btn" :disabled="loading" @click="submit">
        {{ loading ? 'Saving...' : 'Confirm add' }}
      </button>
    </main>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import { getUserWallet, saveUserWallet } from '@/api/user'
import { useUserStore } from '@/store/user'

const router = useRouter()
const userStore = useUserStore()
const form = reactive({
  currency: 'BTC',
  address: '',
  note: ''
})

const isOpen = ref(false)
const loading = ref(false)

const selectOption = (opt) => {
  form.currency = opt
  isOpen.value = false
}

const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event)
      }
    }
    document.addEventListener('click', el.clickOutsideEvent)
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent)
  }
}

const loadWallet = async () => {
  try {
    const res = await getUserWallet()
    if (res.data.code === 200) {
      form.currency = res.data.data.currency || 'BTC'
      form.address = res.data.data.address || ''
      form.note = res.data.data.note || ''
    }
  } catch (error) {
    ElMessage.error(error.message || 'Failed to load wallet address')
  }
}

const submit = async () => {
  if (!form.address) {
    ElMessage.warning('Please enter wallet address')
    return
  }
  loading.value = true
  try {
    const res = await saveUserWallet(form)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success(res.data.msg || 'Wallet address linked successfully')
    userStore.setUserInfo({
      wallet_currency: form.currency,
      wallet_address: form.address,
      wallet_note: form.note,
    })
    router.back()
  } catch (error) {
    ElMessage.error(error.message || 'Failed to save wallet address')
  } finally {
    loading.value = false
  }
}

onMounted(loadWallet)
</script>

<style scoped>
.bind-wallet-page {
  background: #f8fafc;
  min-height: 100vh;
}

.bind-wallet-body {
  padding: 24px 20px;
  padding-top: 10px;
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

.custom-select {
  position: relative;
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  font-size: 16px;
  color: #1f2933;
  cursor: pointer;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.custom-select.open {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  border-color: #ff5500;
}

.selected-value {
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.dropdown-icon {
  width: 18px;
  height: 18px;
  color: #1f2933;
  transition: transform 0.2s;
}

.custom-select.open .dropdown-icon {
  transform: rotate(180deg);
}

.options-list {
  position: absolute;
  top: 100%;
  left: -1px;
  right: -1px;
  background: #fff;
  border: 1px solid #ff5500;
  border-top: 1px solid #ff5500;
  border-bottom-left-radius: 10px;
  border-bottom-right-radius: 10px;
  z-index: 10;
  overflow: hidden;
}

.option {
  padding: 8px 16px;
  font-size: 14px;
  color: #1f2933;
  text-align: left;
}

.form-group input {
  width: 100%;
  padding: 14px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  font-size: 16px;
  color: #1f2933;
  outline: none;
}

.form-group input::placeholder {
  color: #94a3b8;
}

/* .form-group input:focus {
  border-color: #ff5500;
} */

.confirm-btn {
  width: 100%;
  background: #ff5500;
  color: #fff;
  border: none;
  border-radius: 25px;
  padding: 15px 0;
  font-size: 18px;
  font-weight: 500;
  margin-top: 32px;
}

.confirm-btn:disabled {
  opacity: 0.7;
}
</style>
