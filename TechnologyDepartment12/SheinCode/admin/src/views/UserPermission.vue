<template>
  <section class="page">
    <div class="layout-grid">
      <el-card shadow="never" class="panel">
        <template #header>
          <div class="panel-head">
            <strong>管理员账号</strong>
            <el-button type="success" :icon="Plus" @click="openAdmin()">新增管理员</el-button>
          </div>
        </template>

        <el-table :data="admins" border stripe>
          <el-table-column prop="username" label="账号" min-width="130" />
          <el-table-column prop="role" label="角色" width="130" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-switch v-model="row.enabled" @change="persist" />
            </template>
          </el-table-column>
          <el-table-column prop="last_login" label="最后登录" width="180" />
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button size="small" @click="openAdmin(row)">编辑</el-button>
              <el-button size="small" type="danger" :disabled="row.username === 'admin'" @click="removeAdmin(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <el-card shadow="never" class="panel">
        <template #header>
          <div class="panel-head">
            <strong>角色权限</strong>
            <el-button type="primary" :icon="Plus" @click="openRole()">新增角色</el-button>
          </div>
        </template>

        <div class="role-tabs">
          <button
            v-for="role in roles"
            :key="role.name"
            :class="{ active: activeRole === role.name }"
            type="button"
            @click="activeRole = role.name"
          >
            {{ role.label }}
          </button>
        </div>

        <div v-if="currentRole" class="permission-box">
          <div class="role-actions">
            <span>{{ currentRole.description }}</span>
            <div class="role-action-buttons">
              <el-button size="small" @click="openRole(currentRole)">编辑角色</el-button>
              <el-button
                size="small"
                type="danger"
                :disabled="currentRole.name === 'super_admin'"
                @click="removeRole(currentRole)"
              >
                删除角色
              </el-button>
            </div>
          </div>

          <el-table :data="permissionGroups" border class="permission-table">
            <el-table-column prop="label" label="模块" width="160" />
            <el-table-column label="权限">
              <template #default="{ row }">
                <el-checkbox-group v-model="currentRole.permissions" @change="persist">
                  <el-checkbox v-for="item in row.items" :key="item.key" :label="item.key">
                    {{ item.label }}
                  </el-checkbox>
                </el-checkbox-group>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-card>
    </div>

    <el-dialog v-model="adminDialog.visible" :title="adminDialog.form.id ? '编辑管理员' : '新增管理员'" width="500px">
      <el-form label-width="92px">
        <el-form-item label="账号">
          <el-input v-model.trim="adminDialog.form.username" :disabled="adminDialog.form.username === 'admin'" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="adminDialog.form.role" style="width: 100%">
            <el-option v-for="role in roles" :key="role.name" :label="role.label" :value="role.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model.trim="adminDialog.form.password" type="password" show-password placeholder="留空则不修改" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="adminDialog.form.enabled" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adminDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitAdmin">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="roleDialog.visible" :title="roleDialog.form.name ? '编辑角色' : '新增角色'" width="520px">
      <el-form label-width="92px">
        <el-form-item label="角色标识">
          <el-input v-model.trim="roleDialog.form.name" :disabled="Boolean(roleDialog.originalName)" placeholder="manager" />
        </el-form-item>
        <el-form-item label="角色名称">
          <el-input v-model.trim="roleDialog.form.label" placeholder="运营管理员" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model.trim="roleDialog.form.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="roleDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitRole">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getAdminPermissions, saveAdminAccounts, saveAdminRoles } from '@/api/user'

const permissionGroups = [
  {
    label: '侧边栏菜单',
    items: [
      { key: 'menu.site', label: '站点管理' },
      { key: 'menu.task_system', label: '任务系统功能' },
      { key: 'menu.permission', label: '用户权限' },
      { key: 'menu.users', label: '会员信息' },
      { key: 'menu.member_levels', label: '会员等级' },
      { key: 'menu.balance_logs', label: '余额日志' },
      { key: 'menu.withdrawals', label: '提现审核' },
      { key: 'menu.goods', label: '产品管理' },
      { key: 'menu.tasks', label: '任务管理' },
      { key: 'menu.partners', label: '合作网站' },
      { key: 'menu.settings', label: '参数设置' },
    ],
  },
]

const allPermissions = permissionGroups.flatMap((group) => group.items.map((item) => item.key))

const defaultRoles = [
  { name: 'super_admin', label: '超级管理员', description: '拥有后台全部菜单权限', permissions: [...allPermissions] },
  { name: 'operator', label: '运营管理员', description: '处理会员、任务、商品和日志', permissions: ['menu.users', 'menu.member_levels', 'menu.balance_logs', 'menu.withdrawals', 'menu.goods', 'menu.tasks', 'menu.partners', 'menu.settings'] },
  { name: 'support', label: '客服', description: '查看会员与任务，协助处理基础问题', permissions: ['menu.users', 'menu.tasks', 'menu.balance_logs', 'menu.withdrawals'] },
]

const defaultAdmins = [
  { id: 1, username: 'admin', role: 'super_admin', enabled: true, last_login: '2026-06-13 09:00:00' },
  { id: 2, username: 'service', role: 'support', enabled: true, last_login: '-' },
]

const roles = ref(defaultRoles)
const admins = ref(defaultAdmins)
const activeRole = ref(roles.value[0]?.name || '')

const adminDialog = reactive({
  visible: false,
  form: { id: 0, username: '', role: 'support', password: '', enabled: true },
})

const roleDialog = reactive({
  visible: false,
  originalName: '',
  form: { name: '', label: '', description: '' },
})

const currentRole = computed(() => roles.value.find((role) => role.name === activeRole.value))

const applyPermissionData = (data) => {
  if (Array.isArray(data?.permissionGroups) && data.permissionGroups.length) {
    permissionGroups.splice(0, permissionGroups.length, ...data.permissionGroups)
  }
  if (Array.isArray(data?.roles)) roles.value = data.roles
  if (Array.isArray(data?.admins)) admins.value = data.admins
  if (!roles.value.some((role) => role.name === activeRole.value)) {
    activeRole.value = roles.value[0]?.name || ''
  }
}

const loadPermissions = async () => {
  try {
    const res = await getAdminPermissions()
    if (res.data.code === 200) applyPermissionData(res.data.data)
  } catch (error) {
    ElMessage.error('权限配置加载失败')
  }
}

const persist = async () => {
  try {
    const roleRes = await saveAdminRoles(roles.value)
    const adminRes = await saveAdminAccounts(admins.value)
    if (roleRes.data.code !== 200 || adminRes.data.code !== 200) {
      // 保存失败时展示后端 error，方便定位 runtime 权限等部署问题。
      console.log(roleRes.data.error || adminRes.data.error || roleRes.data.msg || adminRes.data.msg || '保存失败')
      return false
    }
    applyPermissionData(adminRes.data.data || roleRes.data.data)
    return true
  } catch (error) {
    // axios 异常也尽量透出后端返回，避免只看到笼统失败。
    console.log(error.response?.data?.error || error.response?.data?.msg || '保存失败')
    return false
  }
}

const openAdmin = (row) => {
  adminDialog.form = row
    ? { id: row.id, username: row.username, role: row.role, password: '', enabled: row.enabled }
    : { id: 0, username: '', role: roles.value[0]?.name || 'support', password: '', enabled: true }
  adminDialog.visible = true
}

const submitAdmin = async () => {
  if (!adminDialog.form.username || !adminDialog.form.role) return ElMessage.warning('请填写账号和角色')
  if (adminDialog.form.id) {
    const target = admins.value.find((item) => item.id === adminDialog.form.id)
    if (target) Object.assign(target, { ...adminDialog.form, password: undefined })
  } else {
    admins.value.push({ ...adminDialog.form, id: Date.now(), last_login: '-' })
  }
  if (!(await persist())) return
  adminDialog.visible = false
  ElMessage.success('保存成功')
}

const removeAdmin = async (row) => {
  try {
    await ElMessageBox.confirm(`确认删除管理员“${row.username}”？`, '删除确认', { type: 'warning' })
    admins.value = admins.value.filter((item) => item.id !== row.id)
    if (!(await persist())) return
    ElMessage.success('删除成功')
  } catch (error) {
    if (error !== 'cancel') ElMessage.error('删除失败')
  }
}

const openRole = (row) => {
  roleDialog.originalName = row?.name || ''
  roleDialog.form = row
    ? { name: row.name, label: row.label, description: row.description }
    : { name: '', label: '', description: '' }
  roleDialog.visible = true
}

const submitRole = async () => {
  if (!roleDialog.form.name || !roleDialog.form.label) return ElMessage.warning('请填写角色标识和名称')
  if (roleDialog.originalName) {
    const target = roles.value.find((item) => item.name === roleDialog.originalName)
    if (target) Object.assign(target, roleDialog.form)
  } else {
    roles.value.push({ ...roleDialog.form, permissions: [] })
    activeRole.value = roleDialog.form.name
  }
  if (!(await persist())) return
  roleDialog.visible = false
  ElMessage.success('保存成功')
}

const removeRole = async (role) => {
  if (!role || role.name === 'super_admin') return
  const usedByAdmins = admins.value.filter((admin) => admin.role === role.name).map((admin) => admin.username)
  if (usedByAdmins.length) {
    ElMessage.warning(`该角色正在被管理员使用：${usedByAdmins.join('、')}`)
    return
  }

  try {
    // 删除角色只改角色列表，管理员账号不跟着改，避免误删账号。
    await ElMessageBox.confirm(`确认删除角色“${role.label}”？`, '删除确认', { type: 'warning' })
    roles.value = roles.value.filter((item) => item.name !== role.name)
    activeRole.value = roles.value[0]?.name || ''
    if (!(await persist())) return
    ElMessage.success('删除成功')
  } catch (error) {
    if (error !== 'cancel') ElMessage.error('删除失败')
  }
}

onMounted(loadPermissions)
</script>

<style scoped>
.page {
  padding: 18px;
}

.layout-grid {
  display: grid;
  grid-template-columns: minmax(520px, 0.95fr) minmax(520px, 1.05fr);
  gap: 14px;
}

.panel {
  border-radius: 4px;
}

.panel-head,
.role-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.role-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.role-tabs button {
  min-height: 34px;
  padding: 0 14px;
  color: #52637a;
  background: #f5f7fa;
  border: 1px solid #d9e0e8;
  border-radius: 4px;
  cursor: pointer;
}

.role-tabs button.active {
  color: #fff;
  background: #3c8dbc;
  border-color: #3c8dbc;
}

.permission-box {
  display: grid;
  gap: 14px;
}

.role-actions span {
  color: #667085;
}

.role-action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.permission-table :deep(.el-checkbox) {
  margin-right: 24px;
}

@media (max-width: 1180px) {
  .layout-grid {
    grid-template-columns: 1fr;
  }
}
</style>
