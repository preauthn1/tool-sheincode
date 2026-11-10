import { createRouter, createWebHashHistory } from 'vue-router'
import { hasPermission } from '@/utils/permissions'

const fallbackRoutes = [
  // 首页固定开放，用作没有任何菜单权限时的默认落点。
  { path: '/' },
  { path: '/site-settings', permission: 'menu.site' },
  { path: '/task-system', permission: 'menu.task_system' },
  { path: '/permissions', permission: 'menu.permission' },
  { path: '/users', permission: 'menu.users' },
  { path: '/member-levels', permission: 'menu.member_levels' },
  { path: '/balance-logs', permission: 'menu.balance_logs' },
  { path: '/withdrawals', permission: 'menu.withdrawals' },
  { path: '/goods', permission: 'menu.goods' },
  { path: '/tasks', permission: 'menu.tasks' },
  { path: '/partners', permission: 'menu.partners' },
  { path: '/settings', permission: 'menu.settings' },
]

const routes = [
  { path: '/login', component: () => import('@/views/Login.vue') },
  {
    path: '/',
    component: () => import('@/views/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      // 平台首页不配置 permission，登录后所有后台账号都可访问。
      { path: '', component: () => import('@/views/Dashboard.vue'), meta: { title: '平台首页' } },
      { path: 'site-settings', component: () => import('@/views/SystemSettings.vue'), meta: { title: '站点管理', permission: 'menu.site' } },
      { path: 'task-system', component: () => import('@/views/TaskManagement.vue'), meta: { title: '任务系统功能', permission: 'menu.task_system' } },
      { path: 'permissions', component: () => import('@/views/UserPermission.vue'), meta: { title: '用户权限', permission: 'menu.permission' } },
      { path: 'users', component: () => import('@/views/UserManagement.vue'), meta: { title: '会员信息', permission: 'menu.users' } },
      { path: 'member-levels', component: () => import('@/views/MemberLevel.vue'), meta: { title: '会员等级', permission: 'menu.member_levels' } },
      { path: 'balance-logs', component: () => import('@/views/BalanceLog.vue'), meta: { title: '余额日志', permission: 'menu.balance_logs' } },
      { path: 'withdrawals', component: () => import('@/views/WithdrawalReview.vue'), meta: { title: '提现审核', permission: 'menu.withdrawals' } },
      { path: 'goods', component: () => import('@/views/GoodsManagement.vue'), meta: { title: '产品管理', permission: 'menu.goods' } },
      { path: 'tasks', component: () => import('@/views/TaskManagement.vue'), meta: { title: '任务管理', permission: 'menu.tasks' } },
      { path: 'partners', component: () => import('@/views/PartnerSites.vue'), meta: { title: '合作网站', permission: 'menu.partners' } },
      { path: 'settings', component: () => import('@/views/SystemSettings.vue'), meta: { title: '参数设置', permission: 'menu.settings' } },
    ],
  },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

const firstAllowedPath = () => fallbackRoutes.find((item) => hasPermission(item.permission))?.path || ''

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('admin_token')
  if (to.meta.requiresAuth && !token) {
    next({ path: '/login', query: { redirect: to.fullPath } })
  } else if (to.meta.permission && !hasPermission(to.meta.permission)) {
    const fallback = firstAllowedPath()
    if (fallback && fallback !== to.fullPath) next(fallback)
    else next('/')
  } else if (to.path === '/login' && token) {
    next(firstAllowedPath() || '/')
  } else {
    next()
  }
})

export default router
