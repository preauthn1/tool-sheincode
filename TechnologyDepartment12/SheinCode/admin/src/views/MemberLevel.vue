<template>
  <section class="page">
    <div class="level-panel">
      <div class="panel-header">
        <div class="title">
          <el-icon><Grid /></el-icon>
          <strong>会员等级</strong>
        </div>
        <div class="toolbar">
          <el-input
            v-model.trim="search.keyword"
            class="keyword-input"
            placeholder="等级 / 等级名称"
            clearable
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          />
          <el-button :icon="Refresh" @click="resetSearch">刷新</el-button>
          <el-button type="primary" :icon="Plus" @click="openEditor()">创建</el-button>
        </div>
      </div>

      <p class="table-tip">第{{ startIndex }}-{{ endIndex }}条，共{{ total }}条数据。</p>

      <el-table :data="levels" v-loading="loading" class="level-table" row-key="id">
        <el-table-column prop="level" label="等级" min-width="90" />
        <el-table-column prop="level_name" label="等级名称" min-width="180" />
        <el-table-column prop="daily_orders" label="每日订单数" min-width="130" />
        <el-table-column label="操作" width="148" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="openEditor(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="removeLevel(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, sizes, prev, pager, next"
          :page-sizes="[10, 20, 50]"
          @size-change="handleSizeChange"
          @current-change="loadLevels"
        />
      </div>
    </div>

    <el-dialog v-model="editor.visible" :title="editor.form.id ? '编辑会员等级' : '创建会员等级'" width="720px">
      <el-form label-position="top" class="level-form">
        <el-form-item label="等级">
          <el-input-number v-model="editor.form.level" :min="0" :precision="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="等级名称">
          <el-input v-model.trim="editor.form.level_name" placeholder="VIP1" />
        </el-form-item>
        <el-form-item label="每日订单数">
          <el-input-number v-model="editor.form.daily_orders" :min="0" :precision="0" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editor.visible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitLevel">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Grid, Plus, Refresh } from '@element-plus/icons-vue'
import { deleteMemberLevel, getMemberLevels, saveMemberLevel } from '@/api/user'

const levels = ref([])
const loading = ref(false)
const submitLoading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const search = reactive({ keyword: '' })

const emptyForm = () => ({
  id: 0,
  level: 0,
  level_name: '',
  recharge_amount: 0,
  team_count: 0,
  valid_count: 0,
  daily_orders: 0,
  withdraw_min_amount: 0,
  withdraw_max_amount: 0,
  commission_rate: 0,
})

const editor = reactive({
  visible: false,
  form: emptyForm(),
})

const startIndex = computed(() => (total.value ? (page.value - 1) * pageSize.value + 1 : 0))
const endIndex = computed(() => Math.min(page.value * pageSize.value, total.value))

const loadLevels = async () => {
  loading.value = true
  try {
    const res = await getMemberLevels({ page: page.value, pageSize: pageSize.value, keyword: search.keyword })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    levels.value = res.data.data.list || []
    total.value = res.data.data.total || 0
  } catch (error) {
    ElMessage.error(error.message || '加载会员等级失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  loadLevels()
}

const resetSearch = () => {
  search.keyword = ''
  page.value = 1
  loadLevels()
}

const handleSizeChange = (val) => {
  pageSize.value = val
  page.value = 1
  loadLevels()
}

const openEditor = (row = null) => {
  Object.assign(editor.form, row ? { ...emptyForm(), ...row } : emptyForm())
  editor.visible = true
}

const submitLevel = async () => {
  if (editor.form.level === null || editor.form.level === undefined) return ElMessage.warning('请输入等级')
  if (!editor.form.level_name) return ElMessage.warning('请输入等级名称')

  submitLoading.value = true
  try {
    const res = await saveMemberLevel({
      id: editor.form.id,
      level: editor.form.level,
      level_name: editor.form.level_name,
      daily_orders: editor.form.daily_orders,
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
    editor.visible = false
    await loadLevels()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const removeLevel = async (row) => {
  try {
    await ElMessageBox.confirm(`确认删除等级「${row.level_name}」吗？`, '删除确认', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    })
    const res = await deleteMemberLevel(row.id)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('删除成功')
    await loadLevels()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(error.message || '删除失败')
    }
  }
}

onMounted(loadLevels)
</script>

<style scoped>
.page {
  padding: 18px;
}

.level-panel {
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

.title {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #263642;
  font-size: 16px;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
}

.keyword-input {
  width: 220px;
}

.table-tip {
  margin: 0 0 8px;
  color: #667085;
  font-size: 12px;
}

.level-table {
  width: 100%;
}

.level-table :deep(.el-table__header th) {
  color: #52637a;
  font-weight: 700;
  background: #fff;
}

.level-table :deep(.el-table__cell) {
  color: #667085;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.level-form {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 2px 18px;
}

.level-form :deep(.el-input-number .el-input__wrapper),
.level-form :deep(.el-input__wrapper) {
  border-radius: 2px;
}

@media (max-width: 900px) {
  .panel-header,
  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .toolbar,
  .keyword-input {
    width: 100%;
  }

  .level-form {
    grid-template-columns: 1fr;
  }
}
</style>
