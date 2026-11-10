<template>
  <section class="page">
    <div class="log-panel">
      <div class="panel-header">
        <div class="title">
          <el-icon><Grid /></el-icon>
          <strong>余额日志</strong>
        </div>
        <div class="toolbar">
          <el-button :icon="Refresh" @click="loadRecords">刷新</el-button>
          <el-button type="primary" :icon="Download" @click="exportRecords">导出</el-button>
          <el-button type="primary" :icon="Plus" @click="openCreate">创建</el-button>
        </div>
      </div>

      <p class="table-tip">第{{ startIndex }}-{{ endIndex }}条，共{{ total }}条数据。</p>

      <el-table :data="records" v-loading="loading" class="log-table" row-key="id">
        <el-table-column label="#" width="90">
          <template #default="{ $index }">{{ startIndex + $index }}</template>
        </el-table-column>
        <el-table-column label="关联用户" min-width="220">
          <template #default="{ row }">
            <div class="user-info">
              <span>用户ID：{{ row.related_user?.id || row.user_id }}</span>
              <span>昵称：{{ row.related_user?.nickname || '' }}</span>
              <span>账号：{{ row.related_user?.account || '' }}</span>
              <span>手机：{{ row.related_user?.phone || '' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作类型" min-width="140">
          <template #default="{ row }">{{ row.display_type || recordType(row) }}</template>
        </el-table-column>
        <el-table-column label="变动数量" min-width="140">
          <template #default="{ row }">{{ formatMoney(row.display_amount ?? Math.abs(Number(row.change_amount))) }}</template>
        </el-table-column>
        <el-table-column label="备注" min-width="170">
          <template #default="{ row }">{{ row.remark || '(未设置)' }}</template>
        </el-table-column>
        <el-table-column label="变动记录" min-width="220">
          <template #default="{ row }">{{ row.change_record || buildChangeRecord(row) }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="添加时间" min-width="180" />
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, sizes, prev, pager, next"
          :page-sizes="[10, 20, 50, 100]"
          @size-change="handleSizeChange"
          @current-change="loadRecords"
        />
      </div>
    </div>

    <el-dialog v-model="createDialog.visible" title="创建余额日志" width="520px">
      <el-form label-position="top">
        <el-form-item label="会员ID">
          <el-input-number v-model="createDialog.form.user_id" :min="1" :precision="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="操作类型">
          <el-select v-model="createDialog.form.operation_type" style="width: 100%">
            <el-option label="交易" value="deposit" />
            <el-option label="调整" value="adjust" />
            <el-option label="提现" value="withdraw" />
          </el-select>
        </el-form-item>
        <el-form-item label="金额">
          <el-input-number v-model="createDialog.form.amount" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model.trim="createDialog.form.remark" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitCreate">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Download, Grid, Plus, Refresh } from '@element-plus/icons-vue'
import { getBalanceRecords, updateUserBalance } from '@/api/user'

const records = ref([])
const loading = ref(false)
const submitLoading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

const createDialog = reactive({
  visible: false,
  form: {
    user_id: 1,
    operation_type: 'deposit',
    amount: 0,
    remark: '',
  },
})

const startIndex = computed(() => (total.value ? (page.value - 1) * pageSize.value + 1 : 0))
const endIndex = computed(() => Math.min(page.value * pageSize.value, total.value))

const formatMoney = (value) => Number(value || 0).toFixed(2)

const buildChangeRecord = (row) => {
  const before = Number(row.before_balance || 0)
  const change = Number(row.change_amount || 0)
  const after = Number(row.after_balance || 0)
  return `${formatMoney(before)}${change >= 0 ? '+' : '-'}${formatMoney(Math.abs(change))}=${formatMoney(after)}`
}

const recordType = (row) => {
  const map = {
    deposit: '交易',
    adjust: '交易',
    withdraw: '提现',
    task_commission: '返佣',
    linked_profit_share: '返佣',
    bonus: '返佣',
  }
  return map[row.type] || (Number(row.change_amount) >= 0 ? '交易' : '提现')
}

const loadRecords = async () => {
  loading.value = true
  try {
    const res = await getBalanceRecords({ page: page.value, pageSize: pageSize.value })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    records.value = res.data.data.list || []
    total.value = res.data.data.total || 0
  } catch (error) {
    ElMessage.error(error.message || '加载余额日志失败')
  } finally {
    loading.value = false
  }
}

const handleSizeChange = (val) => {
  pageSize.value = val
  page.value = 1
  loadRecords()
}

const openCreate = () => {
  Object.assign(createDialog.form, {
    user_id: 1,
    operation_type: 'deposit',
    amount: 0,
    remark: '',
  })
  createDialog.visible = true
}

const submitCreate = async () => {
  if (!createDialog.form.user_id) return ElMessage.warning('请输入会员ID')
  if (!createDialog.form.amount) return ElMessage.warning('请输入金额')
  if (!createDialog.form.remark) return ElMessage.warning('请输入备注')

  submitLoading.value = true
  try {
    const res = await updateUserBalance(createDialog.form)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('创建成功')
    createDialog.visible = false
    await loadRecords()
  } catch (error) {
    ElMessage.error(error.message || '创建失败')
  } finally {
    submitLoading.value = false
  }
}

const exportRecords = () => {
  const header = ['#', '用户ID', '昵称', '账号', '手机', '操作类型', '变动数量', '备注', '变动记录', '添加时间']
  const rows = records.value.map((row, index) => [
    startIndex.value + index,
    row.related_user?.id || row.user_id,
    row.related_user?.nickname || '',
    row.related_user?.account || '',
    row.related_user?.phone || '',
    row.display_type || recordType(row),
    formatMoney(row.display_amount ?? Math.abs(Number(row.change_amount))),
    row.remark || '(未设置)',
    row.change_record || buildChangeRecord(row),
    row.created_at || '',
  ])
  const csv = [header, ...rows].map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = 'balance-logs.csv'
  link.click()
  URL.revokeObjectURL(link.href)
}

onMounted(loadRecords)
</script>

<style scoped>
.page {
  padding: 18px;
}

.log-panel {
  min-height: calc(100vh - 96px);
  padding: 18px 20px 24px;
  border: 1px solid #e6ebf1;
  border-radius: 4px;
  background: #fff;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}

.title,
.toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
}

.title {
  color: #263642;
  font-size: 16px;
}

.table-tip {
  margin: 0 0 8px;
  color: #667085;
  font-size: 12px;
}

.log-table {
  width: 100%;
}

.log-table :deep(.el-table__header th) {
  color: #52637a;
  font-weight: 700;
  background: #fff;
}

.log-table :deep(.el-table__cell) {
  color: #667085;
}

.user-info {
  display: grid;
  gap: 4px;
  color: #52637a;
  font-size: 13px;
  line-height: 1.35;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 900px) {
  .panel-header,
  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
