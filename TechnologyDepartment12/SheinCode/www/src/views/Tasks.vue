<template>
  <div class="mobile-page tasks-page">
    <NavBar :title="t('tasksTopTitle')" :showBack="false" />

    <main class="tasks-body">
      <section class="member-card">
        <div class="vip-line vip-3">
          <span>VIP3</span>
          <b>3000</b>
        </div>
        <div class="vip-line vip-2">
          <span>VIP2</span>
          <b>2000</b>
        </div>
        <div class="vip-line vip-1">
          <span>VIP1</span>
          <b>1000</b>
        </div>

        <div class="member-card-content">
          <div class="member-title">
            <span class="badge-dot"></span>
            <strong>{{ t('newMemberActivities') }}</strong>
          </div>
          <p>
            {{ t('dailyOrders') }}
            <b>{{ totalCount }}</b>
          </p>
          <p>
            {{ t('commissionRatio') }}
            <b>{{ normalRate }}</b>
          </p>
          <small>{{ t('memberBenefits') }} 500</small>
        </div>
      </section>

      <section class="stats-card">
        <div class="stats-row">
          <div>
            <strong>${{ formatMoney(todayEarn) }}</strong>
            <span>{{ t('earnedCommissions') }}</span>
          </div>
          <div>
            <strong>${{ formatMoney(userInfo.frozen_balance) }}</strong>
            <span>{{ t('frozenAmount') }}</span>
          </div>
        </div>

        <div class="divider"></div>

        <div class="stats-row">
          <div>
            <strong>{{ completedCount }}/{{ totalCount }}</strong>
            <span>{{ t('completedOrder') }}</span>
          </div>
          <div>
            <strong>${{ formatMoney(userInfo.balance) }}</strong>
            <span>{{ t('availableBalance') }}</span>
          </div>
        </div>

        <button class="grab-button" :disabled="grabbing" @click="startGrab">
          <span class="button-dot"></span>
          {{ grabbing ? t('loading') : t('startGrabbingOrders') }}
        </button>
      </section>

      <section class="description-card">
        <h2>{{ t('orderDescription') }}</h2>
        <p>1. {{ t('eachAccountCanMatch') }} {{ totalCount }} {{ t('ordersPerDay') }}</p>
        <p>2. {{ t('commissionRule') }}</p>
        <p>3. {{ t('systemRule') }}</p>
        <p>4. {{ t('supervisionRule') }}</p>
        <p>5. {{ t('pendingRule') }}</p>
      </section>

      <section class="description-card long-text">
        <h2>{{ t('orderDescription') }}</h2>
        <p>{{ t('matchRule') }}</p>
      </section>
    </main>

    <div v-if="grabbing" class="popup-mask">
      <div class="loading-panel">{{ t('loading') }}</div>
    </div>

    <div v-if="activeTask" class="popup-mask" @click.self="closeOrder">
      <section class="order-sheet">
        <header>
          <span>{{ t('startOptimization') }}</span>
          <button @click="closeOrder">X</button>
        </header>

        <div class="order-product">
          <img :src="taskImage(activeTask)" :alt="taskName(activeTask)" />
          <h2>{{ taskName(activeTask) }}</h2>
        </div>

        <div class="order-money">
          <div>
            <span>{{ t('orderAmount') }}</span>
            <strong>${{ formatMoney(activeTask.task_amount) }}</strong>
          </div>
          <div>
            <span>{{ t('orderIncome') }}</span>
            <strong>${{ estimatedIncome(activeTask) }}</strong>
          </div>
        </div>

        <section class="evaluation-box">
          <div class="rating-line">
            <span>{{ t('orderEvaluation') }}</span>
            <div class="stars">
              <svg v-for="star in 5" :key="star" :class="{ active: star <= rating }" @click="rating = star"
                viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
              </svg>
              <b>{{ rating }}</b>
            </div>
          </div>

          <label>{{ t('orderEvaluation') }}</label>
          <select v-model="selectedEvaluation">
            <option v-for="item in evaluationOptions" :key="item" :value="item">{{ item }}</option>
          </select>
        </section>

        <button class="submit-order" :disabled="submitting === activeTask.id" @click="submit(activeTask.id)">
          {{ submitting === activeTask.id ? t('submitting') : t('submit') }}
        </button>
      </section>
    </div>

    <TabBar />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import NavBar from '@/components/NavBar.vue'
import TabBar from '@/components/TabBar.vue'
import { useI18n } from '@/i18n'
import { useUserStore } from '@/store/user'
import { getTodayTasks, startTask, submitOrder } from '@/api/task'
import { resolveAssetUrl } from '@/utils/url'

const { t } = useI18n()
const userStore = useUserStore()
const route = useRoute()
const router = useRouter()
const userInfo = computed(() => userStore.userInfo)
const tasks = ref([])
const completedCount = ref(0)
const totalCount = ref(0)
const todayEarn = ref(0)
const startBalance = ref(0)
const taskRate = ref(0)
const activeTask = ref(null)
const grabbing = ref(false)
const submitting = ref(null)
const rating = ref(5)
const selectedEvaluation = ref('The product is great')

const products = [
  {
    name: 'Premium Leather Shoulder Bag',
    image: 'https://images.unsplash.com/photo-1591561954557-26941169b49e?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Diamond Dial Stainless Watch',
    image: 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Gold Chain Necklace',
    image: 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Luxury Mini Satchel Bag',
    image: 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=500&q=80',
  },
]

const evaluationOptions = [
  'The product is great',
  'Finally received it. The product is very good',
  'I am very satisfied with the product and recommend purchasing it',
  'I am very happy and satisfied',
  'the price is affordable',
  'The workmanship is very good',
  'and I like it very much',
  'It looks very good',
  'and my friend has bought it for the second time',
]

const normalRate = computed(() => `${(taskRate.value * 100).toFixed(2)}%`)
const nextTask = computed(() => tasks.value.find((item) => Number(item.status) === 0))

const formatMoney = (value) => Number(value || 0).toFixed(2)
const productByIndex = (index) => products[Math.max(Number(index ?? 0) - 1, 0) % products.length]
const taskName = (task) => task?.good_name || productByIndex(task?.task_index).name
const taskImage = (task) => {
  const image = task?.good_image || productByIndex(task?.task_index).image
  return resolveAssetUrl(image)
}
const estimatedIncome = (task) => {
  const rate = Number(task?.commission_rate || 0) > 0 ? Number(task.commission_rate) / 100 : taskRate.value
  return formatMoney(Number(task?.task_amount || 0) * rate)
}

const applyTaskPayload = (data = {}) => {
  tasks.value = data.tasks || []
  completedCount.value = data.completed_count || 0
  totalCount.value = Number(data.total_count || 0)
  todayEarn.value = data.today_earn || 0
  startBalance.value = Number(data.start_balance || 0)
  taskRate.value = Number(data.task_rate || 0)
}

const openTask = (task) => {
  activeTask.value = task
  rating.value = 5
  selectedEvaluation.value = evaluationOptions[0]
}

const load = async () => {
  try {
    const res = await getTodayTasks()
    if (res.data.code === 200) {
      applyTaskPayload(res.data.data || {})
    }
  } catch (error) {
    ElMessage.error(t('loadTasksFailed'))
  }
}

const startGrab = async () => {
  grabbing.value = true
  try {
    const res = await startTask()
    if (res.data.code !== 200) {
      ElMessage.warning(res.data.msg || t('balanceStartTip'))
      return
    }

    const data = res.data.data || {}
    applyTaskPayload(data)

    const matchedTask = data.task || nextTask.value
    if (!matchedTask) {
      ElMessage.success(t('allTasksCompleted'))
      return
    }

    window.setTimeout(() => {
      openTask(matchedTask)
      grabbing.value = false
    }, 600)
  } catch (error) {
    ElMessage.error(t('loadTasksFailed'))
  } finally {
    window.setTimeout(() => {
      grabbing.value = false
    }, 650)
  }
}

const closeOrder = () => {
  activeTask.value = null
}

const openCurrentFromRoute = async () => {
  if (route.query.openCurrent !== '1') return

  const current = nextTask.value
  if (current) {
    openTask(current)
  }

  const query = { ...route.query }
  delete query.openCurrent
  await router.replace({ path: '/tasks', query })
}

const submit = async (id) => {
  submitting.value = id
  try {
    const res = await submitOrder({ task_id: id })
    if (res.data.code === 200) {
      const data = res.data.data || {}
      const label = data.is_package ? `${t('packageTask')} ${Number(data.rate || 0).toFixed(2)}%` : t('normalTask')
      ElMessage.success(`${label}: ${t('income')} +$${formatMoney(data.commission)}`)
      await userStore.fetchUserInfo()
      if (Number(data.is_package || 0) === 1 && Number(data.has_pending_package || 0) === 1) {
        const nextRes = await startTask()
        if (nextRes.data.code === 200) {
          const nextData = nextRes.data.data || {}
          applyTaskPayload(nextData)
          const matchedTask = nextData.task || nextTask.value
          if (matchedTask) {
            openTask(matchedTask)
            return
          }
        }
      }
      activeTask.value = null
      await load()
    } else {
      ElMessage.warning(res.data.msg || t('submitFailed'))
      await userStore.fetchUserInfo()
    }
  } catch (error) {
    ElMessage.error(t('submitFailed'))
  } finally {
    submitting.value = null
  }
}

onMounted(async () => {
  await userStore.fetchUserInfo()
  await load()
  await openCurrentFromRoute()
})
</script>

<style scoped>
.tasks-page {
  min-height: 100vh;
  padding-bottom: 88px;
  background: #f8f9fb;
}

.tasks-body {
  padding: 12px 12px 22px;
}

.member-card {
  position: relative;
  min-height: 188px;
  overflow: hidden;
  border-radius: 8px;
  background:
    linear-gradient(135deg, rgba(13, 27, 42, 0.88), rgba(255, 85, 0, 0.42)),
    url('https://www.defizapm.com/h5/static/img/taskqqqqqq_11.06a8b423.svg') center / cover;
  color: #fff;
}

.member-card-content {
  position: relative;
  z-index: 1;
  padding: 22px 18px;
}

.member-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 800;
}

.badge-dot,
.button-dot {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.22);
}

.member-card p,
.member-card small {
  display: block;
  margin: 15px 0 0;
  font-size: 14px;
}

.member-card b {
  margin-left: 8px;
  color: #ff3b1f;
}

.vip-line {
  position: absolute;
  z-index: 2;
  color: rgba(255, 255, 255, 0.9);
  font-size: 13px;
  font-weight: 800;
  text-align: center;
}

.vip-line b {
  display: block;
  margin: 2px 0 0;
  color: inherit;
}

.vip-3 {
  right: 14px;
  top: 18px;
}

.vip-2 {
  right: 70px;
  top: 74px;
}

.vip-1 {
  right: 16px;
  bottom: 28px;
}

.stats-card,
.description-card {
  margin-top: 12px;
  border-radius: 8px;
  background: #fff;
}

.stats-card {
  padding: 14px 10px 16px;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.stats-row div {
  text-align: center;
}

.stats-row strong,
.stats-row span {
  display: block;
}

.stats-row strong {
  color: #101828;
  font-size: 17px;
}

.stats-row span {
  margin-top: 7px;
  color: #667085;
  font-size: 14px;
}

.divider {
  height: 1px;
  margin: 14px 0;
  background: rgba(0, 0, 0, 0.1);
}

.grab-button {
  display: flex;
  width: 100%;
  height: 46px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 18px;
  border: 0;
  border-radius: 999px;
  background: #ff5500;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
}

.grab-button:disabled {
  opacity: 0.72;
}

.description-card {
  padding: 16px;
  color: #344054;
  font-size: 13px;
  line-height: 1.55;
}

.description-card h2,
.description-card p {
  margin: 0;
}

.description-card h2 {
  color: #101828;
  font-size: 16px;
}

.description-card p {
  margin-top: 12px;
}

.long-text {
  margin-top: 18px;
}

.popup-mask {
  position: fixed;
  z-index: 50;
  inset: 0;
  display: grid;
  align-items: end;
  justify-items: center;
  background: rgba(0, 0, 0, 0.32);
}

.loading-panel {
  align-self: center;
  border-radius: 8px;
  padding: 10px 20px;
  background: #fff;
  color: #ff5500;
  font-weight: 800;
  font-size: 20px;
}

.order-sheet {
  width: 100%;
  max-width: var(--app-max-width);
  max-height: 76vh;
  overflow-y: auto;
  border-radius: 12px 12px 0 0;
  background: #fff;
  padding: 14px 16px 20px;
}

.order-sheet header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #101828;
  font-weight: 800;
}

.order-sheet span {
  font-size: 16px;
}

.order-sheet header button {
  border: 0;
  background: transparent;
  color: #667085;
  font-weight: 900;
  font-size: 20px;
}

.order-product {
  margin-top: 16px;
  text-align: center;
}

.order-product img {
  width: 92px;
  height: 92px;
  border-radius: 8px;
  object-fit: cover;
}

.order-product h2 {
  margin: 10px auto 0;
  overflow: hidden;
  color: #101828;
  font-size: 15px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-product p {
  margin: 6px 0 0;
  color: #667085;
  font-size: 12px;
}

.order-money {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 18px;
  text-align: center;
}

.order-money span,
.order-money strong {
  display: block;
}

.order-money span {
  color: #838383;
  font-size: 13px;
}

.order-money strong {
  margin-top: 8px;
  color: #101828;
  font-size: 18px;
}

.evaluation-box {
  margin-top: 18px;
  border-radius: 8px;
  padding: 12px;
  background: #f3f4f6;
}

.rating-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: #667085;
  font-size: 13px;
}

.stars {
  display: flex;
  align-items: center;
  gap: 4px;
}

.stars svg {
  width: 22px;
  height: 22px;
  color: #d0d5dd;
  cursor: pointer;
}

.stars svg.active,
.stars b {
  color: #ffb400;
}

.evaluation-box label {
  display: block;
  margin-top: 12px;
  color: #667085;
  font-size: 13px;
}

.evaluation-box select {
  width: 100%;
  margin-top: 8px;
  border: 1px solid #d0d5dd;
  border-radius: 6px;
  padding: 10px;
  background: #fff;
  color: #344054;
  font-size: 13px;
}

.submit-order {
  width: 100%;
  height: 46px;
  margin-top: 18px;
  border: 0;
  border-radius: 999px;
  background: #08b43f;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
}
</style>
