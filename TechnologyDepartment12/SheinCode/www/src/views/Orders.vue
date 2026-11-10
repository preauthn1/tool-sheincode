<template>
  <div class="mobile-page orders-page">
    <NavBar title="Orders" :showBack="false">
      <!-- <template #right>
        <button class="bell-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button>
      </template> -->
    </NavBar>

    <nav class="status-tabs">
      <button v-for="tab in tabs" :key="tab.key" :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key">
        {{ tab.label }}
      </button>
    </nav>

    <main class="orders-body">
      <div v-if="loading && orders.length === 0" class="empty panel">Loading...</div>
      <div v-else-if="orders.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round">
            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
            <path
              d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z">
            </path>
          </svg>
        </div>
        <strong>No Data</strong>
        <p>{{ emptyText }}</p>
      </div>

      <article v-for="order in orders" v-else :key="order.record_id || order.id" class="order-card panel">
        <div class="order-top">
          <div class="order-top-left">
            Completed order:<span class="gray-text">{{ completedLabel(order) }}</span>
          </div>
            <button v-if="canSubmitOrder(order)" class="submit-btn" :disabled="isSubmittingOrder(order)" @click="submit(order.id)">
              {{ isSubmittingOrder(order) ? 'Submitting' : 'Submitnow' }}
            </button>
            <div v-else :class="['completed-status', orderStatus(order).className]">
              <svg v-if="Number(order.status) === 1" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
              {{ orderStatus(order).label }}
          </div>
        </div>

        <div class="order-product">
          <img :src="productImage(order)" alt="Order product" />
          <div class="product-info">
            <h3 v-if="Number(order.is_package) === 1">{{ packageTitle(order) }}</h3>
            <h3 v-else>{{ productName(order) }}</h3>
          </div>
        </div>

        <div class="order-details">
          <div class="detail-row">
            <span>Unit price:</span>
            <span>$ {{ formatMoney(unitPrice(order)) }}</span>
          </div>
          <div class="detail-row">
            <span>Order quantity:</span>
            <span>{{ orderQuantity(order) }}</span>
          </div>
          <div class="detail-row">
            <span>Total:</span>
            <span>$ {{ formatMoney(order.task_amount) }}</span>
          </div>
          <div class="detail-row orange-text">
            <span>Commission Ratio:</span>
            <span>{{ formatRate(order.commission_rate) }}</span>
          </div>
          <div class="detail-row orange-text">
            <span>Order commission:</span>
            <span>$ {{ formatMoney(orderCommission(order)) }}</span>
          </div>
        </div>
      </article>

      <div v-if="orders.length" ref="loadMoreRef" class="load-more">
        {{ loadingMore ? 'Loading...' : hasMore ? 'Pull up to load more' : 'No more orders' }}
      </div>
    </main>

    <TabBar />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import NavBar from '@/components/NavBar.vue'
import TabBar from '@/components/TabBar.vue'
import { getOrders, submitOrder } from '@/api/task'
import { useUserStore } from '@/store/user'
import { resolveAssetUrl } from '@/utils/url'

const userStore = useUserStore()
const router = useRouter()
const activeTab = ref('orders')
const loading = ref(false)
const loadingMore = ref(false)
const submitting = ref(null)
const orders = ref([])
const page = ref(1)
const pageSize = 10
const total = ref(0)
const completedCount = ref(0)
const totalCount = ref(0)
const currentTaskId = ref(0)
const hasMore = ref(false)
const loadMoreRef = ref(null)
let observer = null
const today = new Date().toISOString().slice(0, 10)

const tabs = [
  { key: 'orders', label: 'orders' },
  { key: 'pending', label: 'Pending' },
  { key: 'frozen', label: 'Frozen' },
  { key: 'completed', label: 'Completed' },
]

const emptyText = computed(() => {
  const map = {
    orders: 'There are no order records for today.',
    pending: 'No pending orders.',
    frozen: 'No frozen orders.',
    completed: 'No completed orders.',
  }
  return map[activeTab.value]
})

const formatMoney = (value) => Number(value || 0).toFixed(2)
const formatRate = (value) => `${Number(value || 0).toFixed(2)}%`
const orderCommission = (order) => Number(order.task_amount || 0) * Number(order.commission_rate || 0) / 100
const resolveImageUrl = (url) => {
  return resolveAssetUrl(url)
}
const productName = (order) => order.good_name || 'Order product'
const productImage = (order) => resolveImageUrl(order.good_image) || 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=500&q=80'
const orderQuantity = (order) => Number(order.quantity || 1)
const unitPrice = (order) => Number(order.unit_price || order.task_amount || 0)
const packageTitle = (order) => {
  const index = Number(order.package_index || 0)
  const total = Number(order.package_total || 0)
  const prefix = index && total ? `Package ${index}/${total}` : 'Package'
  return `${prefix} - ${productName(order)}`
}
const completedLabel = (order) => {
  const total = totalCount.value || order.total_count || 0
  return `${Number(order.task_index || 0)}/${total || 0}`
}
const canSubmitOrder = (order) => {
  const isCurrentTask = Number(order.id) === Number(currentTaskId.value)
  const isCurrentPackage = Number(order.is_package) !== 1 || Number(order.is_current_package || 0) === 1
  return Number(order.status) === 0 && isCurrentTask && isCurrentPackage
}
const isSubmittingOrder = (order) => submitting.value === order.id
const statusParam = computed(() => {
  if (activeTab.value === 'pending') return 0
  if (activeTab.value === 'completed') return 1
  if (activeTab.value === 'frozen') return 2
  return ''
})

const orderStatus = (order) => {
  if (Number(order.status) === 1) return { label: 'Completed', className: 'completed' }
  if (Number(order.status) === 2) return { label: 'Frozen', className: 'frozen' }
  return { label: 'Pending', className: 'pending' }
}

const load = async (reset = true) => {
  if (reset) {
    page.value = 1
    loading.value = true
  } else {
    if (loadingMore.value || !hasMore.value) return
    loadingMore.value = true
  }

  try {
    const res = await getOrders({ page: page.value, pageSize, status: statusParam.value })
    if (res.data.code === 200) {
      const data = res.data.data || {}
      const list = data.list || []
      orders.value = reset ? list : [...orders.value, ...list]
      total.value = Number(data.total || 0)
      completedCount.value = Number(data.completed_count || 0)
      totalCount.value = Number(data.total_count || 0)
      currentTaskId.value = Number(data.current_task_id || 0)
      hasMore.value = Boolean(data.has_more)
      page.value += 1
      await nextTick()
      setupObserver()
    } else {
      ElMessage.warning(res.data.msg || 'Failed to load orders')
    }
  } catch (error) {
    ElMessage.error('Failed to load orders')
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

const setupObserver = () => {
  if (observer) observer.disconnect()
  if (!loadMoreRef.value || !hasMore.value) return

  observer = new IntersectionObserver((entries) => {
    if (entries[0]?.isIntersecting) {
      load(false)
    }
  }, { rootMargin: '120px' })
  observer.observe(loadMoreRef.value)
}

const submit = async (id) => {
  submitting.value = id
  try {
    const res = await submitOrder({ task_id: id })
    if (res.data.code === 200) {
      const data = res.data.data || {}
      const label = data.is_package ? `Package order ${formatRate(data.rate)}` : 'Normal order'
      ElMessage.success(`${label}: +$${formatMoney(data.commission)}`)
      await userStore.fetchUserInfo()
      if (Number(data.is_package || 0) === 1 && Number(data.has_pending_package || 0) === 1) {
        await router.push({ path: '/tasks', query: { openCurrent: '1' } })
        return
      }
      await load(true)
    } else {
      ElMessage.warning(res.data.msg || 'Submit failed')
      await userStore.fetchUserInfo()
    }
  } catch (error) {
    ElMessage.error('Submit failed')
  } finally {
    submitting.value = null
  }
}

watch(activeTab, () => load(true))

onMounted(() => load(true))

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
})
</script>

<style scoped>
.orders-page {
  min-height: 100vh;
  padding-bottom: 88px;
  background: #f4f5f7;
}

.bell-btn {
  background: transparent;
  border: none;
  color: #fff;
  padding: 0;
  display: flex;
  align-items: center;
}

.bell-btn svg {
  width: 24px;
  height: 24px;
}

.status-tabs {
  display: flex;
  align-items: center;
  justify-content: space-around;
  height: 50px;
  background: #f4f5f7;
  border-bottom: 1px solid #e5e7eb;
}

.status-tabs button {
  border: 0;
  background: transparent;
  color: #6b7280;
  font-size: 15px;
  font-weight: 500;
}

.status-tabs button.active {
  color: #000;
  font-weight: bold;
}

.orders-body {
  padding: 14px;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 0;
  color: #94a3b8;
  text-align: center;
}

.empty-icon {
  width: 72px;
  height: 72px;
  margin-bottom: 20px;
  color: #cbd5e1;
  animation: float 3s ease-in-out infinite;
}

.empty-icon svg {
  width: 100%;
  height: 100%;
}

.empty-state strong {
  color: #475569;
  font-size: 18px;
  margin-bottom: 8px;
}

.empty-state p {
  font-size: 15px;
  color: #64748b;
  margin: 0;
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

.order-card {
  padding: 14px;
  margin-bottom: 12px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.order-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #eee;
}

.order-top-left {
  font-size: 14px;
  color: #000;
}

.order-top-left .gray-text {
  color: #999;
}

.submit-btn {
  background: #00b848;
  color: #fff;
  border: none;
  border-radius: 20px;
  padding: 4px 12px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
}

.submit-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.completed-status {
  display: flex;
  align-items: center;
  color: #000;
  font-size: 14px;
  font-weight: bold;
}

.completed-status svg {
  width: 18px;
  height: 18px;
  color: #ff5500;
  margin-right: 4px;
}

.completed-status.frozen svg {
  color: #0369a1;
}

.completed-status.pending {
  color: #64748b;
}

.order-product {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 14px;
  padding-bottom: 14px;
}

.order-product img {
  width: 60px;
  height: 60px;
  object-fit: contain;
  border-radius: 4px;
}

.product-info h3 {
  font-size: 14px;
  font-weight: 400;
  color: #000;
  margin: 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  overflow: hidden;
}

.order-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: #000;
}

.detail-row.orange-text {
  color: #ff9900;
}

.load-more {
  padding: 14px 0 4px;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
}
</style>
