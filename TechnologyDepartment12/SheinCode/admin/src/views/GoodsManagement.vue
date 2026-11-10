<template>
  <section class="page">
    <el-card shadow="never" class="goods-card">
      <template v-if="!editor.visible">
      <div class="page-header">
        <div class="header-left">
          <el-icon><Menu /></el-icon>
          <h2>Goods</h2>
        </div>
        <div class="header-right">
          <el-button type="primary" plain size="small" :icon="Plus">导出</el-button>
          <el-button type="primary" size="small" :icon="Plus" @click="openEditor()">创建</el-button>
        </div>
      </div>

      <div class="list-summary">
        第{{ total === 0 ? 0 : (page - 1) * pageSize + 1 }}-{{ Math.min(page * pageSize, total) }}条，共{{ total }}条数据.
      </div>

      <el-table :data="goods" v-loading="loading" class="table" :cell-style="{ padding: '12px 0' }">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="标题" min-width="400" show-overflow-tooltip />
        <el-table-column label="单价" width="120">
          <template #default="{ row }">{{ Number(row.price || 0).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="图片" width="100">
          <template #default="{ row }">
            <el-image v-if="row.image" :src="resolveImageUrl(row.image)" fit="cover" class="thumb" />
            <div v-else class="no-image">No image</div>
          </template>
        </el-table-column>
        <el-table-column label="首页显示商品" width="140">
          <template #default>
            <span style="color: #909399">[未设置]</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220">
          <template #default="{ row }">
            <el-button size="small" type="info" plain @click="toggleStatus(row)">
              {{ row.status === 1 ? '禁用' : '启用' }}
            </el-button>
            <el-button size="small" type="primary" @click="openEditor(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="removeGood(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          layout="prev, pager, next, sizes"
          :page-sizes="[10, 20, 50]"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </template>

    <template v-else>
      <div class="page-header">
        <div class="header-left">
          <el-icon><Menu /></el-icon>
          <h2>编辑</h2>
        </div>
      </div>

      <div class="editor-container">
        <el-form label-position="top" class="edit-form">
          <el-form-item label="标题" required>
            <el-input v-model.trim="editor.form.name" />
          </el-form-item>
          <el-form-item label="单价" required>
            <el-input v-model="editor.form.price" />
          </el-form-item>
          <el-form-item label="首页显示商品">
            <el-input v-model="editor.form.sort" />
          </el-form-item>
          <el-form-item label="图片上传">
            <el-upload
              class="image-uploader"
              action="#"
              :show-file-list="false"
              :http-request="uploadImage"
              :before-upload="beforeImageUpload"
            >
              <img v-if="editor.form.image" :src="resolveImageUrl(editor.form.image)" class="upload-preview" />
              <div v-else class="upload-placeholder">
                <el-icon><Plus /></el-icon>
              </div>
            </el-upload>
            <div v-if="editor.form.image" class="image-actions" style="margin-left: 16px;">
              <el-link type="danger" @click.stop="editor.form.image = ''">移除图片</el-link>
            </div>
          </el-form-item>
        </el-form>
        <div class="form-actions">
          <el-button type="primary" :loading="submitLoading" @click="submitGood">保存</el-button>
          <el-button @click="editor.visible = false">返回</el-button>
        </div>
      </div>
    </template>
    </el-card>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Menu } from '@element-plus/icons-vue'
import { deleteGood, getGoods, saveGood, toggleGoodStatus, uploadGoodImage } from '@/api/good'
import { resolveAssetUrl } from '@/utils/url'

const loading = ref(false)
const submitLoading = ref(false)
const goods = ref([])
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const MIN_GOOD_PRICE = 45
const MAX_GOOD_PRICE = 50000
const search = reactive({ keyword: '', status: '' })
const editor = reactive({
  visible: false,
  form: { id: 0, name: '', image: '', price: 100, sort: 0, status: 1 },
})

const resolveImageUrl = (url) => {
  return resolveAssetUrl(url)
}

const beforeImageUpload = (file) => {
  const isImage = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)
  const isLt5M = file.size / 1024 / 1024 < 5

  if (!isImage) ElMessage.warning('只能上传 JPG、PNG、GIF、WebP 图片')
  if (!isLt5M) ElMessage.warning('图片大小不能超过 5MB')

  return isImage && isLt5M
}

const uploadImage = async ({ file, onSuccess, onError }) => {
  const formData = new FormData()
  formData.append('file', file)

  try {
    const res = await uploadGoodImage(formData)
    if (res.data.code !== 200) throw new Error(res.data.msg || '上传失败')

    editor.form.image = res.data.data?.url || ''
    ElMessage.success('上传成功')
    onSuccess?.(res.data)
  } catch (error) {
    ElMessage.error(error.message || '上传失败')
    onError?.(error)
  }
}

const loadGoods = async () => {
  loading.value = true
  try {
    const res = await getGoods({ page: page.value, pageSize: pageSize.value, ...search })
    if (res.data.code === 200) {
      goods.value = res.data.data.list || []
      total.value = res.data.data.total || 0
    } else {
      ElMessage.warning(res.data.msg || '加载商品失败')
    }
  } catch (error) {
    ElMessage.error('加载商品失败')
  } finally {
    loading.value = false
  }
}

const handleSizeChange = (val) => {
  pageSize.value = val
  loadGoods()
}

const handleCurrentChange = (val) => {
  page.value = val
  loadGoods()
}

const openEditor = (row) => {
  editor.form = row
    ? { id: row.id, name: row.name, image: row.image || '', price: Number(row.price || 0), sort: Number(row.sort || 0), status: Number(row.status ?? 1) }
    : { id: 0, name: '', image: '', price: '', sort: '', status: 1 }
  editor.visible = true
}

const submitGood = async () => {
  if (!editor.form.name || Number(editor.form.price) <= 0) return ElMessage.warning('请填写商品名称和价格')
  if (Number(editor.form.price) < MIN_GOOD_PRICE || Number(editor.form.price) > MAX_GOOD_PRICE) {
    return ElMessage.warning(`商品价格必须在${MIN_GOOD_PRICE}-${MAX_GOOD_PRICE}美元之间`)
  }
  submitLoading.value = true
  try {
    const res = await saveGood({
      ...editor.form,
      price: Number(editor.form.price),
      sort: Number(editor.form.sort || 0)
    })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
    editor.visible = false
    loadGoods()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const toggleStatus = async (row) => {
  try {
    const newStatus = row.status === 1 ? 0 : 1;
    const res = await toggleGoodStatus(row.id, newStatus)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    row.status = newStatus;
    ElMessage.success(row.status === 1 ? '商品已启用' : '商品已禁用')
  } catch (error) {
    ElMessage.error(error.message || '更新失败')
  }
}

const removeGood = async (row) => {
  try {
    await ElMessageBox.confirm(`确认删除商品「${row.name}」？`, '删除确认', { type: 'warning' })
    const res = await deleteGood(row.id)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('删除成功')
    loadGoods()
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '删除失败')
  }
}

onMounted(loadGoods)
</script>

<style scoped>
.page {
  padding: 18px;
}

.goods-card {
  border-radius: 4px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 20px;
  border-bottom: 1px solid #ebeef5;
  margin-bottom: 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #606266;
}

.header-left h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 500;
}

.header-right {
  display: flex;
  gap: 8px;
}

.list-summary {
  font-size: 13px;
  color: #606266;
  margin-bottom: 16px;
}

.table {
  width: 100%;
}

.thumb,
.no-image {
  width: 48px;
  height: 48px;
  border-radius: 4px;
  border: 1px solid #ebeef5;
}

.no-image {
  display: grid;
  place-items: center;
  background: #f5f7fa;
  color: #909399;
  font-size: 11px;
}

.pagination {
  display: flex;
  justify-content: flex-start;
  margin-top: 20px;
}

.editor-container {
  max-width: 100%;
}

.edit-form :deep(.el-form-item__label) {
  padding-bottom: 4px;
  font-weight: 500;
  color: #606266;
}

.image-uploader :deep(.el-upload) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100px;
  height: 100px;
  background: #fbfdff;
  border: 1px dashed #c0ccda;
  border-radius: 6px;
  cursor: pointer;
}

.image-uploader :deep(.el-upload:hover) {
  border-color: #409eff;
}

.upload-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
}

.upload-placeholder {
  display: grid;
  place-items: center;
  color: #8c939d;
  font-size: 28px;
}

.form-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-top: 40px;
}

.form-actions .el-button {
  min-width: 80px;
}

:deep(.el-form-item__content) {
  display: flex;
  align-items: flex-end;
}
</style>
