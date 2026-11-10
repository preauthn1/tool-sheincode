<template>
  <section class="page">
    <div class="summary-grid">
      <el-card v-for="card in cards" :key="card.key" class="metric-card" shadow="never">
        <div class="metric-head">
          <span>{{ card.label }}</span>
          <el-icon><component :is="card.icon" /></el-icon>
        </div>
        <strong>{{ card.value }}</strong>
        <small>{{ card.desc }}</small>
      </el-card>
    </div>

    <div class="content-grid">
      <el-card shadow="never" class="panel">
        <template #header>
          <div class="panel-head">
            <span>今日任务进度</span>
            <el-button size="small" :icon="Refresh" :loading="loading" @click="loadSummary">刷新</el-button>
          </div>
        </template>
        <div class="progress-line">
          <span>已完成 {{ summary.today_completed_count }} / {{ summary.today_task_count }}</span>
          <strong>{{ progressPercent }}%</strong>
        </div>
        <el-progress :percentage="progressPercent" :stroke-width="14" />
        <div class="task-states">
          <el-tag type="success">Completed {{ summary.today_completed_count }}</el-tag>
          <el-tag type="warning">Frozen {{ summary.today_frozen_count }}</el-tag>
          <el-tag>Pending {{ pendingCount }}</el-tag>
        </div>
      </el-card>

      <el-card shadow="never" class="panel">
        <template #header>
          <div class="panel-head">
            <span>业务规则</span>
          </div>
        </template>
        <ul class="rules">
          <li>账户余额达到 50 美元后才允许开始任务。</li>
          <li>每个账号每日 40 个订单任务，完成后按任务利润结算。</li>
          <li>业务员账号与客户账号互相关联，完成 40 单后自动结算 25% 净利润分成。</li>
          <li>前台不走真实发货，充值和提现均由客服或管理员手动处理。</li>
        </ul>
      </el-card>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Coin, DataLine, Money, Refresh, Tickets, User, Wallet } from '@element-plus/icons-vue'
import { getDashboardSummary } from '@/api/dashboard'

const loading = ref(false)
const summary = reactive({
  user_count: 0,
  customer_count: 0,
  agent_count: 0,
  managed_count: 0,
  active_count: 0,
  today_task_count: 0,
  today_completed_count: 0,
  today_frozen_count: 0,
  today_commission: 0,
  balance_total: 0,
  withdraw_total: 0,
  linked_share_total: 0,
  log_count: 0,
})

const formatMoney = (value) => `$${Number(value || 0).toFixed(2)}`

const cards = computed(() => [
  { key: 'users', label: '用户总数', value: summary.user_count, desc: `正常账号 ${summary.active_count}`, icon: User },
  { key: 'agents', label: '业务员', value: summary.agent_count, desc: `客户 ${summary.customer_count} / 假账号 ${summary.managed_count}`, icon: DataLine },
  { key: 'balance', label: '余额总额', value: formatMoney(summary.balance_total), desc: '所有账号当前余额', icon: Wallet },
  { key: 'commission', label: '今日佣金', value: formatMoney(summary.today_commission), desc: '今日完成订单利润', icon: Coin },
  { key: 'share', label: '关联分润', value: formatMoney(summary.linked_share_total), desc: '25% 自动分成累计', icon: Tickets },
  { key: 'withdraw', label: '提现累计', value: formatMoney(summary.withdraw_total), desc: '管理员手动出款记录', icon: Money },
])

const progressPercent = computed(() => {
  if (!summary.today_task_count) return 0
  return Math.min(Math.round((summary.today_completed_count / summary.today_task_count) * 100), 100)
})

const pendingCount = computed(() => Math.max(summary.today_task_count - summary.today_completed_count - summary.today_frozen_count, 0))

const loadSummary = async () => {
  loading.value = true
  try {
    const res = await getDashboardSummary()
    if (res.data.code === 200) {
      Object.assign(summary, res.data.data)
    } else {
      ElMessage.warning(res.data.msg || '获取数据失败')
    }
  } catch (error) {
    ElMessage.error('加载概览失败')
  } finally {
    loading.value = false
  }
}

onMounted(loadSummary)
</script>

<style scoped>
.page {
  padding: 18px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.metric-card {
  border-radius: 4px;
}

.metric-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #667085;
}

.metric-head .el-icon {
  color: #3c8dbc;
}

.metric-card strong {
  display: block;
  margin-top: 10px;
  font-size: 28px;
  color: #1f2937;
}

.metric-card small {
  display: block;
  margin-top: 6px;
  color: #98a2b3;
}

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
  gap: 14px;
  margin-top: 14px;
}

.panel {
  border-radius: 4px;
}

.panel-head,
.progress-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.progress-line {
  margin-bottom: 12px;
}

.progress-line strong {
  font-size: 24px;
}

.task-states {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 16px;
}

.rules {
  margin: 0;
  padding-left: 18px;
  line-height: 1.9;
  color: #344054;
}

@media (max-width: 1100px) {
  .summary-grid,
  .content-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 760px) {
  .summary-grid,
  .content-grid {
    grid-template-columns: 1fr;
  }
}
</style>
