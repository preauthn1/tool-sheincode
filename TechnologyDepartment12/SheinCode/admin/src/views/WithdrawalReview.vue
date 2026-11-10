<template>
  <section class="page">
    <div class="panel">
      <div class="panel-header">
        <div class="title">
          <strong>提现审核</strong>
          <span>处理客户前台提交的提款申请</span>
        </div>
        <div class="toolbar">
          <el-input v-model.trim="keyword" placeholder="搜索账号 / 昵称 / 钱包地址" clearable style="width: 260px" @keyup.enter="reload" />
          <el-select v-model="status" clearable placeholder="全部状态" style="width: 140px" @change="reload">
            <el-option label="待审核" value="pending" />
            <el-option label="已同意" value="approved" />
            <el-option label="已驳回" value="rejected" />
          </el-select>
          <el-button type="primary" @click="reload">刷新</el-button>
        </div>
      </div>

      <el-table :data="list" v-loading="loading" stripe row-key="id">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="客户" min-width="180">
          <template #default="{ row }">
            <div class="user-cell">
              <span>ID: {{ row.user_id }}</span>
              <span>账号: {{ row.phone || '-' }}</span>
              <span>昵称: {{ row.nickname || '-' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="提款金额" min-width="120">
          <template #default="{ row }">${{ formatMoney(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="提款地址" min-width="300">
          <template #default="{ row }">
            <div class="address-cell">
              <span>{{ row.wallet_currency || 'BTC' }}</span>
              <strong>{{ row.wallet_address }}</strong>
              <small v-if="row.wallet_note">备注: {{ row.wallet_note }}</small>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="申请时间" min-width="170" />
        <el-table-column prop="reviewed_at" label="审核时间" min-width="170" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'" size="small" type="success" @click="openReview(row, 'approve')">同意</el-button>
            <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="openReview(row, 'reject')">驳回</el-button>
            <span v-else>-</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, sizes, prev, pager, next"
          :page-sizes="[10, 20, 50, 100]"
          @size-change="handleSizeChange"
          @current-change="loadList"
        />
      </div>
    </div>

    <el-dialog v-model="reviewDialog.visible" :title="reviewDialog.action === 'approve' ? '同意提款' : '驳回提款'" width="460px">
      <el-form label-position="top">
        <el-form-item label="客户账号">
          <el-input :model-value="reviewDialog.row?.phone || reviewDialog.row?.user_id" disabled />
        </el-form-item>
        <el-form-item label="提款金额">
          <el-input :model-value="formatMoney(reviewDialog.row?.amount)" disabled />
        </el-form-item>
        <el-form-item label="提款地址">
          <el-input :model-value="reviewDialog.row?.wallet_address" type="textarea" :rows="3" disabled />
        </el-form-item>
        <el-form-item label="后台备注">
          <el-input v-model.trim="reviewDialog.remark" type="textarea" :rows="3" placeholder="仅后台记录，不在前台提示客户" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reviewDialog.visible = false">取消</el-button>
        <el-button :type="reviewDialog.action === 'approve' ? 'success' : 'danger'" :loading="reviewDialog.loading" @click="submitReview">
          确认{{ reviewDialog.action === 'approve' ? '同意' : '驳回' }}
        </el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { approveWithdrawal, getWithdrawals, rejectWithdrawal } from '@/api/user'

const list = ref([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const status = ref('pending')
const keyword = ref('')

const reviewDialog = reactive({
  visible: false,
  loading: false,
  action: 'approve',
  row: null,
  remark: '',
})

const formatMoney = (value) => Number(value || 0).toFixed(2)

const statusText = (value) => ({
  pending: '待审核',
  approved: '已同意',
  rejected: '已驳回',
}[value] || value || '-')

const statusTag = (value) => ({
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
}[value] || 'info')

const loadList = async () => {
  loading.value = true
  try {
    const res = await getWithdrawals({
      page: page.value,
      pageSize: pageSize.value,
      status: status.value,
      keyword: keyword.value,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    list.value = res.data.data.list || []
    total.value = res.data.data.total || 0
  } catch (error) {
    ElMessage.error(error.message || '加载提现申请失败')
  } finally {
    loading.value = false
  }
}

const reload = () => {
  page.value = 1
  loadList()
}

const handleSizeChange = (val) => {
  pageSize.value = val
  reload()
}

const openReview = (row, action) => {
  reviewDialog.row = row
  reviewDialog.action = action
  reviewDialog.remark = ''
  reviewDialog.visible = true
}

const submitReview = async () => {
  if (!reviewDialog.row) return
  reviewDialog.loading = true
  try {
    const payload = { id: reviewDialog.row.id, remark: reviewDialog.remark }
    const res = reviewDialog.action === 'approve'
      ? await approveWithdrawal(payload)
      : await rejectWithdrawal(payload)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success(reviewDialog.action === 'approve' ? '已同意提款' : '已驳回并退回余额')
    reviewDialog.visible = false
    await loadList()
  } catch (error) {
    ElMessage.error(error.message || '审核失败')
  } finally {
    reviewDialog.loading = false
  }
}

onMounted(loadList)
</script>

<style scoped>
.page {
  padding: 18px;
}

.panel {
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
  margin-bottom: 18px;
}

.title {
  display: grid;
  gap: 4px;
}

.title strong {
  color: #263642;
  font-size: 18px;
}

.title span {
  color: #667085;
  font-size: 13px;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-cell,
.address-cell {
  display: grid;
  gap: 4px;
  line-height: 1.35;
}

.address-cell strong {
  overflow-wrap: anywhere;
  color: #263642;
  font-weight: 500;
}

.address-cell small {
  color: #667085;
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
