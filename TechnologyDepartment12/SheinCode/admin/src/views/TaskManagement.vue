<template>
  <section class="page">
    <div class="page-toolbar">
      <el-button link :icon="Refresh" :loading="settingsLoading || loading" @click="refreshAll">刷新</el-button>
    </div>

    <el-card shadow="never" class="task-settings-card">
      <template #header>
        <div class="section-title">
          <el-icon><Grid /></el-icon>
          <strong>任务设置</strong>
        </div>
      </template>

      <el-form label-position="top" class="task-form">
        <el-form-item required label="团队奖励">
          <el-input v-model.trim="settings.team_reward_rate" />
        </el-form-item>
        <el-form-item required label="做任务最低累计金额">
          <el-input v-model.trim="settings.task_min_total_amount" />
        </el-form-item>
        <el-form-item required label="做任务最低剩余金额">
          <el-input v-model.trim="settings.task_min_balance" />
        </el-form-item>
        <el-form-item required label="卡单默认差值">
          <el-input v-model.trim="settings.combo_default_diff" />
        </el-form-item>
        <el-form-item required label="任务余额范围最小值">
          <el-input v-model.trim="settings.task_balance_min" />
        </el-form-item>
        <el-form-item required label="任务余额范围最大值">
          <el-input v-model.trim="settings.task_balance_max" />
        </el-form-item>
        <el-form-item required label="完成任务次日是否重置订单">
          <el-radio-group v-model="settings.reset_order_next_day">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item required label="负数是否可获得奖金">
          <el-radio-group v-model="settings.negative_reward_enabled">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item required label="连单是否算1单">
          <el-radio-group v-model="settings.combo_counts_as_one">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item required label="任务开始时间">
          <el-time-picker v-model="settings.task_start_time" format="hh:mm A" value-format="hh:mm A" placeholder="06:00 AM" />
        </el-form-item>
        <el-form-item required label="任务结束时间">
          <el-time-picker v-model="settings.task_end_time" format="hh:mm A" value-format="hh:mm A" placeholder="06:00 AM" />
        </el-form-item>
        <div class="rule-row">
          <el-form-item required label="手机号规则">
            <el-select v-model="settings.phone_rule" filterable>
              <el-option label="美国|US+1" value="US+1" />
              <el-option label="中国|CN+86" value="CN+86" />
              <el-option label="英国|GB+44" value="GB+44" />
              <el-option label="全部" value="ALL" />
            </el-select>
          </el-form-item>
          <el-form-item label="使用规则">
            <el-select v-model="settings.phone_rule_scope">
              <el-option label="全部" value="all" />
              <el-option label="仅注册" value="register" />
              <el-option label="仅登录" value="login" />
            </el-select>
          </el-form-item>
        </div>
      </el-form>

      <div class="settings-actions">
        <el-button type="primary" :loading="settingsSaving" @click="submitSettings">保存</el-button>
        <el-button @click="loadSettings">返回</el-button>
      </div>
    </el-card>

    <div class="summary-grid">
      <el-card v-for="card in cards" :key="card.label" shadow="never" class="metric-card">
        <span>{{ card.label }}</span>
        <strong>{{ card.value }}</strong>
      </el-card>
    </div>

    <el-card shadow="never" class="filter-card">
      <div class="toolbar">
        <el-input-number v-model="search.user_id" :min="0" :precision="0" placeholder="会员ID" class="user-input" />
        <el-date-picker v-model="search.task_date" value-format="YYYY-MM-DD" type="date" placeholder="任务日期" />
        <el-select v-model="search.status" placeholder="任务状态" clearable class="status-select">
          <el-option label="全部状态" value="" />
          <el-option label="未完成" :value="0" />
          <el-option label="已完成" :value="1" />
          <el-option label="冻结中" :value="2" />
        </el-select>
        <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
        <el-button :icon="Refresh" @click="resetSearch">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportTasks">导出</el-button>
      </div>
    </el-card>

    <el-table :data="tasks" border stripe v-loading="loading" class="table">
      <el-table-column prop="display_id" label="任务编号" min-width="190" show-overflow-tooltip />
      <el-table-column label="会员" min-width="150">
        <template #default="{ row }">
          <div class="multi-line">
            <span>ID: {{ row.user_id }}</span>
            <span>{{ row.user_phone || '-' }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="task_date" label="任务日期" width="120" />
      <el-table-column prop="task_index" label="第几单" width="90" />
      <el-table-column label="进度" width="110">
        <template #default="{ row }">{{ formatProgress(row) }}</template>
      </el-table-column>
      <el-table-column prop="good_name" label="产品" min-width="180" show-overflow-tooltip />
      <el-table-column label="金额" width="120">
        <template #default="{ row }">{{ formatMoney(row.task_amount) }}</template>
      </el-table-column>
      <el-table-column label="佣金" width="120">
        <template #default="{ row }">{{ formatMoney(row.profit ?? row.commission) }}</template>
      </el-table-column>
      <el-table-column label="连单" width="90">
        <template #default="{ row }">
          <el-tag :type="Number(row.is_package ?? row.is_combo) === 1 ? 'danger' : 'info'">
            {{ Number(row.is_package ?? row.is_combo) === 1 ? '是' : '否' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <el-tag :type="statusMeta(row.status).type">{{ statusMeta(row.status).label }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="下单时间" width="180" />
      <el-table-column prop="completed_at" label="完成时间" width="180" />
    </el-table>

    <div class="pagination">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        layout="total, sizes, prev, pager, next"
        :page-sizes="[10, 20, 50, 100]"
        @size-change="handleSizeChange"
        @current-change="loadTasks"
      />
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Download, Grid, Refresh, Search } from '@element-plus/icons-vue'
import { getSettings, getTasks, saveSettings } from '@/api/user'

const loading = ref(false)
const settingsLoading = ref(false)
const settingsSaving = ref(false)
const tasks = ref([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const summary = reactive({
  visible_total: 0,
  pending: 0,
  completed: 0,
  frozen: 0,
  combo: 0,
  amount: 0,
})
const search = reactive({
  user_id: undefined,
  task_date: '',
  status: '',
})

const settings = reactive({
  team_reward_rate: '0.25',
  task_min_total_amount: '50',
  task_min_balance: '0',
  combo_default_diff: '10',
  task_balance_min: '40',
  task_balance_max: '70',
  reset_order_next_day: 0,
  negative_reward_enabled: 0,
  combo_counts_as_one: 1,
  task_start_time: '06:00 AM',
  task_end_time: '06:00 AM',
  phone_rule: 'US+1',
  phone_rule_scope: 'all',
})

const formatMoney = (value) => Number(value || 0).toFixed(2)
const formatProgress = (row) => `${Number(row.task_index || 0)}/${Number(row.total_count || 0)}`

const statusMeta = (status) => {
  const map = {
    0: { label: '未完成', type: 'info' },
    1: { label: '已完成', type: 'success' },
    2: { label: '冻结中', type: 'warning' },
  }
  return map[Number(status)] || map[0]
}

const cards = computed(() => {
  return [
    { label: '前台可见订单', value: summary.visible_total },
    { label: '进行中订单', value: summary.pending },
    { label: '已完成订单', value: summary.completed },
    { label: '冻结订单', value: summary.frozen },
    { label: '连单任务', value: summary.combo },
    { label: '订单总额', value: formatMoney(summary.amount) },
  ]
})

const loadSettings = async () => {
  settingsLoading.value = true
  try {
    const res = await getSettings()
    if (res.data.code !== 200) throw new Error(res.data.msg)
    Object.entries(res.data.data || {}).forEach(([key, value]) => {
      if (!(key in settings)) return
      if (['reset_order_next_day', 'negative_reward_enabled', 'combo_counts_as_one'].includes(key)) {
        settings[key] = Number(value || 0)
      } else {
        settings[key] = value
      }
    })
  } catch (error) {
    ElMessage.error(error.message || '加载任务设置失败')
  } finally {
    settingsLoading.value = false
  }
}

const submitSettings = async () => {
  settingsSaving.value = true
  try {
    const res = await saveSettings({ ...settings })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    settingsSaving.value = false
  }
}

const loadTasks = async () => {
  loading.value = true
  try {
    const params = {
      page: page.value,
      pageSize: pageSize.value,
      status: search.status,
      task_date: search.task_date,
    }
    if (search.user_id) params.user_id = search.user_id

    const res = await getTasks(params)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    const data = res.data.data || {}
    tasks.value = data.list || []
    total.value = data.total || 0
    Object.assign(summary, {
      visible_total: Number(data.summary?.visible_total || 0),
      pending: Number(data.summary?.pending || 0),
      completed: Number(data.summary?.completed || 0),
      frozen: Number(data.summary?.frozen || 0),
      combo: Number(data.summary?.combo || 0),
      amount: Number(data.summary?.amount || 0),
    })
  } catch (error) {
    ElMessage.error(error.message || '加载任务失败')
  } finally {
    loading.value = false
  }
}

const refreshAll = () => {
  loadSettings()
  loadTasks()
}

const handleSearch = () => {
  page.value = 1
  loadTasks()
}

const resetSearch = () => {
  search.user_id = undefined
  search.task_date = ''
  search.status = ''
  handleSearch()
}

const handleSizeChange = (val) => {
  pageSize.value = val
  handleSearch()
}

const exportTasks = () => {
  const header = ['任务编号', '会员ID', '会员账号', '任务日期', '第几单', '产品', '金额', '佣金', '连单', '状态', '下单时间', '完成时间']
  const rows = tasks.value.map((row) => [
    row.display_id || row.id,
    row.user_id,
    row.user_phone || '',
    row.task_date || '',
    row.task_index || '',
    row.good_name || '',
    formatMoney(row.task_amount),
    formatMoney(row.profit ?? row.commission),
    Number(row.is_package ?? row.is_combo) === 1 ? '是' : '否',
    statusMeta(row.status).label,
    row.created_at || '',
    row.completed_at || '',
  ])
  const csv = [header, ...rows].map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = 'tasks.csv'
  link.click()
  URL.revokeObjectURL(link.href)
}

onMounted(refreshAll)
</script>

<style scoped>
.page {
  padding: 0 22px 24px;
}

.page-toolbar {
  height: 48px;
  display: flex;
  align-items: center;
}

.task-settings-card,
.filter-card,
.table,
.metric-card {
  border-radius: 4px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #253342;
  font-size: 22px;
}

.task-form {
  padding: 24px 20px 6px;
}

.task-form :deep(.el-form-item) {
  margin-bottom: 20px;
}

.task-form :deep(.el-form-item__label) {
  color: #253342;
  font-size: 17px;
  font-weight: 700;
}

.task-form :deep(.el-form-item.is-required:not(.is-no-asterisk).asterisk-left > .el-form-item__label::before) {
  color: #f00;
}

.task-form :deep(.el-input__wrapper),
.task-form :deep(.el-select__wrapper) {
  min-height: 42px;
  border-radius: 0;
  box-shadow: 0 0 0 1px #dfe5ec inset;
}

.task-form :deep(.el-input__inner) {
  color: #6b7785;
  font-size: 17px;
}

.rule-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 230px;
  gap: 40px;
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 0 20px 6px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin: 14px 0;
}

.metric-card span {
  color: #667085;
}

.metric-card strong {
  display: block;
  margin-top: 8px;
  color: #1f2937;
  font-size: 24px;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.user-input {
  width: 160px;
}

.status-select {
  width: 150px;
}

.table {
  width: 100%;
  margin-top: 14px;
}

.multi-line {
  display: grid;
  gap: 4px;
  color: #52637a;
  font-size: 13px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

@media (max-width: 1100px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 700px) {
  .summary-grid,
  .rule-row {
    grid-template-columns: 1fr;
  }
}
</style>
