<template>
  <div class="admin-shell">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar"></div>
        <div class="account">
          <strong>{{ adminUser.username || 'admin' }}</strong>
          <span><i></i> {{ adminUser.role_label || adminUser.role || '管理员' }}</span>
        </div>
      </div>

      <div class="menu-title">系统菜单</div>
      <nav class="menu">
        <template v-for="item in menu" :key="item.path">
          <router-link v-if="!item.children" :to="item.path" class="menu-item">
            <el-icon><component :is="item.icon" /></el-icon>
            <span>{{ item.label }}</span>
          </router-link>

          <div v-else class="menu-group" :class="{ active: isGroupActive(item) }">
            <div class="menu-item menu-parent">
              <el-icon><component :is="item.icon" /></el-icon>
              <span>{{ item.label }}</span>
              <el-icon class="menu-arrow"><ArrowDown /></el-icon>
            </div>
            <div class="submenu">
              <router-link v-for="child in item.children" :key="child.path" :to="child.path" class="submenu-item">
                {{ child.label }}
              </router-link>
            </div>
          </div>
        </template>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="tabs">
          <router-link v-for="tab in quickTabs" :key="tab.path" :to="tab.path" class="tab">
            {{ tab.label }}
          </router-link>
        </div>
        <div class="top-actions">
          <el-tag effect="plain" type="success">{{ adminUser.username || 'admin' }}</el-tag>
          <el-button :icon="SwitchButton" @click="logout">退出</el-button>
        </div>
      </header>

      <div class="content-wrapper">
        <router-view />
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowDown, DataLine, Document, Operation, Setting, SwitchButton, UserFilled } from '@element-plus/icons-vue'
import { getAdminUser, hasPermission } from '@/utils/permissions'

const route = useRoute()
const router = useRouter()
const adminUser = computed(() => getAdminUser())

const rawMenu = [
  // 平台首页始终显示，不参与用户权限配置。
  { path: '/', label: '平台首页', icon: DataLine },
  {
    path: '/system',
    label: '系统管理',
    icon: Setting,
    children: [
      { path: '/site-settings', label: '站点管理', permission: 'menu.site' },
      { path: '/task-system', label: '任务系统功能', permission: 'menu.task_system' },
      { path: '/permissions', label: '用户权限', permission: 'menu.permission' },
    ],
  },
  {
    path: '/members',
    label: '会员管理',
    icon: UserFilled,
    children: [
      { path: '/users', label: '会员信息', permission: 'menu.users' },
      { path: '/member-levels', label: '会员等级', permission: 'menu.member_levels' },
    ],
  },
  {
    path: '/member-logs',
    label: '会员日志',
    icon: Document,
    children: [
      { path: '/balance-logs', label: '余额日志', permission: 'menu.balance_logs' },
      { path: '/withdrawals', label: '提现审核', permission: 'menu.withdrawals' },
    ],
  },
  {
    path: '/order-work',
    label: '刷单管理',
    icon: Operation,
    children: [
      { path: '/goods', label: '产品管理', permission: 'menu.goods' },
      { path: '/tasks', label: '任务管理', permission: 'menu.tasks' },
      { path: '/partners', label: '合作网站', permission: 'menu.partners' },
      { path: '/settings', label: '参数设置', permission: 'menu.settings' },
    ],
  },
]

const rawQuickTabs = [
  // 平台首页始终显示，不参与用户权限配置。
  { path: '/', label: '平台首页' },
  { path: '/site-settings', label: '站点管理', permission: 'menu.site' },
  { path: '/task-system', label: '任务系统功能', permission: 'menu.task_system' },
  { path: '/permissions', label: '用户权限', permission: 'menu.permission' },
  { path: '/users', label: '会员信息', permission: 'menu.users' },
  { path: '/withdrawals', label: '提现审核', permission: 'menu.withdrawals' },
  { path: '/goods', label: '产品管理', permission: 'menu.goods' },
]

const filterMenu = (items) => items
  .map((item) => {
    if (!hasPermission(item.permission)) return null
    if (!item.children) return item
    const children = filterMenu(item.children)
    return children.length ? { ...item, children } : null
  })
  .filter(Boolean)

const menu = computed(() => filterMenu(rawMenu))
const quickTabs = computed(() => rawQuickTabs.filter((tab) => hasPermission(tab.permission)))

const isGroupActive = (item) => item.children?.some((child) => route.path === child.path)

const logout = () => {
  localStorage.removeItem('admin_token')
  localStorage.removeItem('admin_user')
  router.replace('/login')
}
</script>

<style scoped>
.admin-shell {
  height: 100vh;
  overflow: hidden;
  display: grid;
  grid-template-columns: 200px minmax(0, 1fr);
  background: #edf1f5;
}

.sidebar {
  height: 100vh;
  overflow-y: auto;
  background: #fff;
  border-right: 1px solid #e3e8ef;
}

.profile {
  height: 74px;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 0 14px;
}

.avatar {
  position: relative;
  width: 50px;
  height: 50px;
  overflow: hidden;
  border-radius: 50%;
  background: #e9eaec;
}

.avatar::before {
  content: '';
  position: absolute;
  top: 9px;
  left: 15px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
}

.avatar::after {
  content: '';
  position: absolute;
  right: 8px;
  bottom: -7px;
  left: 8px;
  height: 30px;
  border-radius: 50% 50% 0 0;
  background: #fff;
}

.account strong,
.account span {
  display: block;
}

.account strong {
  color: #29313a;
  font-size: 16px;
}

.account span {
  margin-top: 6px;
  color: #4f5d6a;
  font-size: 14px;
}

.account i {
  display: inline-block;
  width: 13px;
  height: 13px;
  margin-right: 8px;
  border-radius: 50%;
  background: #20c76b;
  vertical-align: -2px;
}

.menu-title {
  padding: 0 10px 22px;
  color: #7a8794;
  font-size: 18px;
}

.menu {
  display: grid;
  gap: 4px;
}

.menu-item {
  height: 58px;
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 0 14px;
  color: #3f454c;
  text-decoration: none;
  border-left: 4px solid transparent;
}

.menu-item .el-icon {
  font-size: 20px;
}

.menu-item span {
  font-size: 18px;
  font-weight: 700;
}

.menu-item:hover,
.menu-item.router-link-active,
.menu-group.active > .menu-parent {
  color: #1f2a37;
  background: #f5f7fa;
  border-left-color: #3c8dbc;
}

.menu-parent {
  cursor: default;
}

.menu-arrow {
  margin-left: auto;
  transition: transform 0.2s ease;
}

.menu-group.active .menu-arrow {
  transform: rotate(180deg);
}

.submenu {
  display: grid;
  background: #fff;
}

.submenu-item {
  min-height: 42px;
  display: flex;
  align-items: center;
  padding: 0 18px 0 48px;
  color: #6b7785;
  font-size: 17px;
  text-decoration: none;
  border-left: 4px solid transparent;
}

.submenu-item:hover,
.submenu-item.router-link-active {
  color: #1f2a37;
  background: #f0f4f8;
  border-left-color: #3c8dbc;
}

.main {
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-width: 0;
}

.topbar {
  flex-shrink: 0;
  min-height: 56px;
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 14px;
  background: #fff;
  border-bottom: 1px solid #e3e8ef;
}

.content-wrapper {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}

.tabs {
  display: flex;
  min-width: 0;
  overflow-x: auto;
}

.tab {
  min-width: 126px;
  min-height: 56px;
  display: grid;
  place-items: center;
  padding: 0 18px;
  color: #4f5d6a;
  text-decoration: none;
  border-right: 1px solid #e3e8ef;
  border-top: 2px solid transparent;
  white-space: nowrap;
}

.tab.router-link-active {
  color: #1f2a37;
  border-top-color: #3c8dbc;
  background: #f8fafc;
}

.top-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-right: 16px;
}

@media (max-width: 900px) {
  .admin-shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    min-height: auto;
  }

  .profile,
  .menu-title {
    display: none;
  }

  .menu {
    display: flex;
    overflow-x: auto;
  }

  .menu-item {
    height: 48px;
    border-left: 0;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
  }

  .submenu {
    position: absolute;
    z-index: 20;
    min-width: 160px;
    box-shadow: 0 8px 18px rgb(15 23 42 / 16%);
  }

  .submenu-item {
    padding-left: 18px;
  }
}
</style>
