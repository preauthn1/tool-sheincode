<template>
  <div class="mobile-page my-account-page">
    <NavBar title="MYACCOUNT" :showBack="true" />
    
    <main class="account-body">
      <!-- Balance Section -->
      <section class="balance-section">
        <div class="balance-top">
          <div class="balance-info">
            <h2 class="amount">$ {{ formatMoney(userInfo.balance) }}</h2>
            <p class="label">Account Balance</p>
          </div>
          <button class="withdraw-btn" @click="openWithdraw">Withdraw</button>
        </div>
        
        <div class="divider"></div>
        
        <div class="income-row">
          <div class="income-col">
            <h3 class="amount">$ {{ formatMoney(taskIncome) }}</h3>
            <p class="label">Task income</p>
          </div>
          <div class="income-col">
            <h3 class="amount">$ {{ formatMoney(userInfo.team_commission) }}</h3>
            <p class="label">Team income</p>
          </div>
        </div>
      </section>

      <!-- Action Banners -->
      <section class="action-banners">
        <div class="banner-card">
          <div class="banner-left">
            <div class="icon-circle">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="9" y1="9" x2="15" y2="9"></line>
                <line x1="9" y1="13" x2="15" y2="13"></line>
                <line x1="9" y1="17" x2="15" y2="17"></line>
              </svg>
            </div>
            <span>Get income by completing tasks</span>
          </div>
          <button class="outline-btn" @click="router.push('/tasks')">
            Go to<br>complete
          </button>
        </div>

        <div class="banner-card">
          <div class="banner-left">
            <div class="icon-circle">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 12 20 22 4 22 4 12"></polyline>
                <rect x="2" y="7" width="20" height="5"></rect>
                <line x1="12" y1="22" x2="12" y2="7"></line>
                <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
              </svg>
            </div>
            <span>Invite friends to get commission</span>
          </div>
          <button class="outline-btn" @click="router.push('/support?topic=invite')">
            Go to<br>invite
          </button>
        </div>
      </section>

      <!-- FAQ Card -->
      <section class="faq-card">
        <h3>Frequently Asked Questions</h3>
        <!-- FAQ content would go here -->
      </section>
    </main>

    <div v-if="withdrawDialog.visible" class="withdraw-mask" @click.self="withdrawDialog.visible = false">
      <div class="withdraw-dialog">
        <h3>Withdrawal</h3>
        <p class="withdraw-tip">Minimum withdrawal amount is ${{ formatMoney(withdrawDialog.minAmount) }}. You must complete {{ withdrawDialog.requiredTasks }} tasks before withdrawal.</p>
        <div class="withdraw-wallet">
          <span>Wallet</span>
          <strong>{{ userInfo.wallet_currency || 'BTC' }} {{ userInfo.wallet_address || 'Not linked' }}</strong>
        </div>
        <label>Amount</label>
        <input v-model="withdrawDialog.amount" type="number" inputmode="decimal" placeholder="Please enter withdrawal amount" />
        <label>Withdrawal Password</label>
        <input v-model="withdrawDialog.withdrawalPassword" type="password" placeholder="Please enter withdrawal password" />
        <div class="withdraw-actions">
          <button class="cancel-btn" @click="withdrawDialog.visible = false">Cancel</button>
          <button class="submit-btn" :disabled="withdrawDialog.loading" @click="submitWithdraw">
            {{ withdrawDialog.loading ? 'Submitting...' : 'Submit' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import { useUserStore } from '@/store/user'
import { getSystemSettings, submitWithdrawal } from '@/api/user'
import { getTodayTasks } from '@/api/task'

const router = useRouter()
const userStore = useUserStore()
const userInfo = computed(() => userStore.userInfo)
const taskIncome = computed(() => userInfo.value.task_commission ?? userInfo.value.total_commission)
const withdrawDialog = reactive({
  visible: false,
  loading: false,
  amount: '',
  withdrawalPassword: '',
  minAmount: 100,
  maxAmount: 100000,
  requiredTasks: 40,
})
const taskProgress = reactive({
  completedCount: 0,
  totalCount: 0,
})

const formatMoney = (val) => Number(val || 0).toFixed(2)

const loadWithdrawSettings = async () => {
  try {
    const res = await getSystemSettings()
    if (res.data.code === 200) {
      withdrawDialog.minAmount = Number(res.data.data.withdraw_min_amount || 100)
      withdrawDialog.maxAmount = Number(res.data.data.withdraw_max_amount || 100000)
      withdrawDialog.requiredTasks = Number(res.data.data.task_balance_min || 40)
    }
  } catch (error) {
    withdrawDialog.minAmount = 100
  }
}

const loadTaskProgress = async () => {
  try {
    const res = await getTodayTasks()
    if (res.data.code !== 200) return

    const data = res.data.data || {}
    taskProgress.completedCount = Number(data.completed_count || 0)
    taskProgress.totalCount = Number(data.total_count || 0)
    if (taskProgress.totalCount > 0) {
      withdrawDialog.requiredTasks = taskProgress.totalCount
    }
  } catch (error) {
    taskProgress.completedCount = 0
    taskProgress.totalCount = 0
  }
}

const openWithdraw = async () => {
  await Promise.all([userStore.fetchUserInfo(), loadTaskProgress()])
  if (taskProgress.totalCount > 0 && taskProgress.completedCount < taskProgress.totalCount) {
    ElMessage.warning('You need to complete all the order quantities.')
    return
  }
  if (!userInfo.value.wallet_address) {
    ElMessage.warning('Please link your wallet address first')
    router.push('/bind-wallet')
    return
  }
  withdrawDialog.amount = ''
  withdrawDialog.withdrawalPassword = ''
  withdrawDialog.visible = true
}

const submitWithdraw = async () => {
  const amount = Number(withdrawDialog.amount || 0)
  if (amount < withdrawDialog.minAmount) {
    ElMessage.warning(`Minimum withdrawal amount is ${formatMoney(withdrawDialog.minAmount)}`)
    return
  }
  if (withdrawDialog.maxAmount > 0 && amount > withdrawDialog.maxAmount) {
    ElMessage.warning(`Maximum withdrawal amount is ${formatMoney(withdrawDialog.maxAmount)}`)
    return
  }
  if (amount > Number(userInfo.value.balance || 0)) {
    ElMessage.warning('Insufficient balance')
    return
  }
  if (!withdrawDialog.withdrawalPassword) {
    ElMessage.warning('Please enter withdrawal password')
    return
  }

  withdrawDialog.loading = true
  try {
    const res = await submitWithdrawal({
      amount,
      withdrawal_password: withdrawDialog.withdrawalPassword,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success(res.data.msg || 'Withdrawal request submitted')
    withdrawDialog.visible = false
    await userStore.fetchUserInfo()
  } catch (error) {
    ElMessage.error(error.message || 'Withdrawal request failed')
  } finally {
    withdrawDialog.loading = false
  }
}

onMounted(async () => {
  await Promise.all([userStore.fetchUserInfo(), loadWithdrawSettings(), loadTaskProgress()])
})
</script>

<style scoped>
.my-account-page {
  background: #fff;
  min-height: 100vh;
}

.account-body {
  padding: 24px 20px;
}

/* Balance Section */
.balance-section {
  margin-bottom: 32px;
}

.balance-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.balance-info .amount {
  font-size: 28px;
  font-weight: 400;
  color: #1f2933;
  margin: 0 0 4px;
}

.balance-info .label {
  font-size: 15px;
  color: #1f2933;
  margin: 0;
}

.withdraw-btn {
  background: #ffa97a;
  color: #d84a00;
  border: none;
  border-radius: 20px;
  padding: 8px 24px;
  font-size: 15px;
  font-weight: 500;
}

.divider {
  height: 1px;
  background: #e2e8f0;
  margin-bottom: 20px;
}

.income-row {
  display: flex;
  justify-content: space-between;
  padding-right: 20px;
}

.income-col .amount {
  font-size: 16px;
  font-weight: 400;
  color: #1f2933;
  margin: 0 0 4px;
}

.income-col .label {
  font-size: 15px;
  color: #1f2933;
  margin: 0;
}

/* Action Banners */
.action-banners {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 16px;
}

.banner-card {
  background: #ffa97a;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.banner-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.icon-circle {
  width: 28px;
  height: 28px;
  background: #1f2933;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.icon-circle svg {
  width: 16px;
  height: 16px;
}

.banner-left span {
  font-size: 15px;
  color: #1f2933;
  font-weight: 500;
  line-height: 1.3;
}

.outline-btn {
  background: transparent;
  border: 1px solid #1f2933;
  color: #1f2933;
  border-radius: 20px;
  padding: 6px 16px;
  font-size: 13px;
  line-height: 1.2;
  text-align: center;
  flex-shrink: 0;
}

/* FAQ Card */
.faq-card {
  background: #ffa97a;
  border-radius: 12px;
  padding: 20px;
  min-height: 240px;
}

.faq-card h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #1f2933;
}

.withdraw-mask {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgb(15 23 42 / 45%);
}

.withdraw-dialog {
  width: 100%;
  max-width: 520px;
  padding: 22px 20px 24px;
  border-radius: 18px 18px 0 0;
  background: #fff;
  box-shadow: 0 -12px 32px rgb(15 23 42 / 16%);
}

.withdraw-dialog h3 {
  margin: 0 0 10px;
  color: #1f2933;
  font-size: 20px;
}

.withdraw-tip {
  margin: 0 0 14px;
  color: #667085;
  font-size: 13px;
  line-height: 1.5;
}

.withdraw-wallet {
  display: grid;
  gap: 4px;
  margin-bottom: 16px;
  padding: 12px;
  border-radius: 10px;
  background: #f8fafc;
}

.withdraw-wallet span {
  color: #667085;
  font-size: 13px;
}

.withdraw-wallet strong {
  overflow-wrap: anywhere;
  color: #1f2933;
  font-size: 14px;
}

.withdraw-dialog label {
  display: block;
  margin: 14px 0 8px;
  color: #1f2933;
  font-size: 14px;
}

.withdraw-dialog input {
  width: 100%;
  padding: 13px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  outline: none;
  font-size: 15px;
}

.withdraw-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 20px;
}

.cancel-btn,
.submit-btn {
  border: 0;
  border-radius: 24px;
  padding: 13px 0;
  font-size: 15px;
}

.cancel-btn {
  color: #1f2933;
  background: #eef2f6;
}

.submit-btn {
  color: #fff;
  background: #ff5500;
}

.submit-btn:disabled {
  opacity: 0.7;
}
</style>
