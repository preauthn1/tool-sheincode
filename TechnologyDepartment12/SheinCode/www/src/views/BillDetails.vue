<template>
  <div class="mobile-page bill-details-page">
    <NavBar title="BILLDETAILS" :showBack="true" />

    <main class="bill-list">
      <div v-if="loading && bills.length === 0" class="bill-state">Loading...</div>
      <div v-else-if="bills.length === 0" class="bill-state">No Data</div>

      <div v-for="item in bills" :key="item.id" class="bill-item">
        <div class="bill-left">
          <span class="bill-title">{{ item.title }}</span>
        </div>
        <div class="bill-right">
          <span :class="['bill-amount', Number(item.amount) > 0 ? 'positive' : 'negative']">
            {{ formatAmount(item.amount) }}
          </span>
          <span class="bill-date">{{ item.date }}</span>
        </div>
      </div>

      <div v-if="bills.length" ref="loadMoreRef" class="load-more">
        {{ loadingMore ? 'Loading...' : hasMore ? 'Pull up to load more' : 'No more records' }}
      </div>
    </main>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import { getBillRecords } from '@/api/user'

const bills = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const hasMore = ref(false)
const page = ref(1)
const pageSize = 15
const loadMoreRef = ref(null)
let observer = null

const formatAmount = (value) => {
  const amount = Number(value || 0)
  return `${amount > 0 ? '+' : ''}${amount.toFixed(2)}`
}

const loadBills = async (reset = true) => {
  if (reset) {
    page.value = 1
    loading.value = true
  } else {
    if (loadingMore.value || !hasMore.value) return
    loadingMore.value = true
  }

  try {
    const res = await getBillRecords({ page: page.value, pageSize })
    if (res.data.code === 200) {
      const data = res.data.data || {}
      const list = data.list || []
      bills.value = reset ? list : [...bills.value, ...list]
      hasMore.value = Boolean(data.has_more)
      page.value += 1
      await nextTick()
      setupObserver()
    } else {
      ElMessage.warning(res.data.msg || 'Failed to load bill details')
    }
  } catch (error) {
    ElMessage.error('Failed to load bill details')
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
      loadBills(false)
    }
  }, { rootMargin: '120px' })
  observer.observe(loadMoreRef.value)
}

onMounted(() => {
  loadBills(true)
})

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
})
</script>

<style scoped>
.bill-details-page {
  min-height: 100vh;
  background: #f7f8fa;
}

.bill-list {
  min-height: calc(100vh - 70px);
  background: #fff;
}

.bill-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 125px;
  padding: 0 64px 0 36px;
  border-bottom: 1px solid #eee;
}

.bill-left {
  min-width: 0;
  padding-right: 18px;
}

.bill-title {
  color: #1f2933;
  font-size: 29px;
  line-height: 1.2;
}

.bill-right {
  display: flex;
  flex: 0 0 170px;
  flex-direction: column;
  align-items: flex-end;
}

.bill-amount {
  margin-bottom: 10px;
  font-size: 29px;
  font-weight: 500;
  line-height: 1.15;
}

.bill-amount.positive {
  color: #ff4b00;
}

.bill-amount.negative {
  color: #00b894;
}

.bill-date {
  color: #8f969e;
  font-size: 22px;
  line-height: 1.2;
  white-space: nowrap;
}

.bill-state,
.load-more {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 72px;
  color: #8f969e;
  font-size: 14px;
}

@media (max-width: 480px) {
  .bill-item {
    min-height: 92px;
    padding: 0 28px 0 20px;
  }

  .bill-title,
  .bill-amount {
    font-size: 20px;
  }

  .bill-right {
    flex-basis: 132px;
  }

  .bill-date {
    font-size: 15px;
  }
}
</style>
