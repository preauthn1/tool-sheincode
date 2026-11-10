<template>
  <section class="page">
    <el-card shadow="never" class="filter-card">
      <div class="toolbar">
        <el-input v-model.trim="keyword" placeholder="站点名称 / 链接" clearable class="keyword" @clear="handleSearch" />
        <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
        <el-button :icon="Refresh" @click="resetSearch">重置</el-button>
        <el-button type="success" :icon="Plus" @click="openEditor()">新增站点</el-button>
      </div>
    </el-card>

    <el-table :data="sites" border stripe v-loading="loading" class="table">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="站点名称" min-width="180" />
      <el-table-column prop="url" label="站点链接" min-width="260" show-overflow-tooltip />
      <el-table-column label="Logo" width="110">
        <template #default="{ row }">
          <el-image v-if="row.logo" :src="resolveImageUrl(row.logo)" fit="contain" class="logo-thumb" />
          <div v-else class="no-logo">No logo</div>
        </template>
      </el-table-column>
      <el-table-column prop="sort" label="排序" width="90" />
      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'">
            {{ Number(row.status) === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="updated_at" label="更新时间" width="180" />
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="openEditor(row)">编辑</el-button>
          <el-button size="small" type="danger" @click="removeSite(row)">删除</el-button>
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
        @current-change="loadSites"
      />
    </div>

    <el-dialog v-model="editor.visible" :title="editor.form.id ? '编辑站点' : '新增站点'" width="560px">
      <el-form label-width="92px">
        <el-form-item label="站点名称">
          <el-input v-model.trim="editor.form.name" />
        </el-form-item>
        <el-form-item label="站点链接">
          <el-input v-model.trim="editor.form.url" placeholder="https://" />
        </el-form-item>
        <el-form-item label="Logo">
          <el-upload
            class="logo-uploader"
            action="#"
            :show-file-list="false"
            :http-request="uploadLogo"
            :before-upload="beforeLogoUpload"
          >
            <img v-if="editor.form.logo" :src="resolveImageUrl(editor.form.logo)" class="upload-preview" />
            <div v-else class="upload-placeholder">
              <el-icon><Plus /></el-icon>
              <span>Upload Logo</span>
            </div>
          </el-upload>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editor.form.sort" :precision="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="editor.form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model.trim="editor.form.remark" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editor.visible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitSite">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { deletePartner, getPartners, savePartner, uploadPartnerLogo } from '@/api/user'
import { resolveAssetUrl } from '@/utils/url'

const loading = ref(false)
const submitLoading = ref(false)
const sites = ref([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const keyword = ref('')

const editor = reactive({
  visible: false,
  form: { id: 0, name: '', url: '', logo: '', sort: 0, status: 1, remark: '' },
})

const resolveImageUrl = (url) => {
  return resolveAssetUrl(url)
}

const beforeLogoUpload = (file) => {
  const isImage = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)
  const isLt5M = file.size / 1024 / 1024 < 5

  if (!isImage) ElMessage.warning('Only JPG, PNG, GIF, and WebP images are supported')
  if (!isLt5M) ElMessage.warning('Image size cannot exceed 5MB')

  return isImage && isLt5M
}

const uploadLogo = async ({ file, onSuccess, onError }) => {
  const formData = new FormData()
  formData.append('file', file)

  try {
    const res = await uploadPartnerLogo(formData)
    if (res.data.code !== 200) throw new Error(res.data.msg || 'Upload failed')

    editor.form.logo = resolveImageUrl(res.data.data?.url)
    ElMessage.success('Upload successful')
    onSuccess?.(res.data)
  } catch (error) {
    ElMessage.error(error.message || 'Upload failed')
    onError?.(error)
  }
}

const loadSites = async () => {
  loading.value = true
  try {
    const res = await getPartners({ page: page.value, pageSize: pageSize.value, keyword: keyword.value })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    sites.value = res.data.data.list || []
    total.value = res.data.data.total || 0
  } catch (error) {
    ElMessage.error(error.message || '加载站点失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  loadSites()
}

const resetSearch = () => {
  keyword.value = ''
  handleSearch()
}

const handleSizeChange = (val) => {
  pageSize.value = val
  handleSearch()
}

const openEditor = (row) => {
  editor.form = row
    ? { id: row.id, name: row.name, url: row.url, logo: row.logo || '', sort: Number(row.sort || 0), status: Number(row.status ?? 1), remark: row.remark || '' }
    : { id: 0, name: '', url: '', logo: '', sort: 0, status: 1, remark: '' }
  editor.visible = true
}

const submitSite = async () => {
  if (!editor.form.name || !editor.form.url) return ElMessage.warning('请填写站点名称和链接')
  submitLoading.value = true
  try {
    const res = await savePartner(editor.form)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
    editor.visible = false
    loadSites()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

const removeSite = async (row) => {
  try {
    await ElMessageBox.confirm(`确认删除站点“${row.name}”？`, '删除确认', { type: 'warning' })
    const res = await deletePartner(row.id)
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('删除成功')
    loadSites()
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '删除失败')
  }
}

onMounted(loadSites)
</script>

<style scoped>
.page {
  padding: 18px;
}

.filter-card,
.table {
  border-radius: 4px;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.keyword {
  width: 280px;
}

.table {
  width: 100%;
  margin-top: 14px;
}

.logo-thumb,
.no-logo {
  width: 64px;
  height: 40px;
  border-radius: 4px;
}

.no-logo {
  display: grid;
  place-items: center;
  background: #f2f4f7;
  color: #98a2b3;
  font-size: 11px;
}

.logo-uploader {
  width: 140px;
}

.logo-uploader :deep(.el-upload) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 140px;
  height: 96px;
  overflow: hidden;
  cursor: pointer;
  background: #fafafa;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
}

.logo-uploader :deep(.el-upload:hover) {
  border-color: #409eff;
}

.upload-preview {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.upload-placeholder {
  display: grid;
  gap: 8px;
  place-items: center;
  color: #8c939d;
  font-size: 13px;
}

.upload-placeholder .el-icon {
  font-size: 28px;
}

.logo-actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
