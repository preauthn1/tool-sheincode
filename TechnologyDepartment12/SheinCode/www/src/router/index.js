import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  { path: '/welcome', component: () => import('@/views/Welcome.vue') },
  { path: '/language', component: () => import('@/views/Language.vue') },
  { path: '/login', component: () => import('@/views/Login.vue') },
  { path: '/register', component: () => import('@/views/Register.vue') },
  { path: '/support', component: () => import('@/views/Support.vue') },
  { path: '/', component: () => import('@/views/Home.vue'), meta: { requiresAuth: true } },
  { path: '/about', component: () => import('@/views/About.vue'), meta: { requiresAuth: true } },
  { path: '/terms', component: () => import('@/views/Terms.vue'), meta: { requiresAuth: true } },
  { path: '/tasks', component: () => import('@/views/Tasks.vue'), meta: { requiresAuth: true } },
  { path: '/orders', component: () => import('@/views/Orders.vue'), meta: { requiresAuth: true } },
  { path: '/profile', component: () => import('@/views/Profile.vue'), meta: { requiresAuth: true } },
  { path: '/my-account', component: () => import('@/views/MyAccount.vue'), meta: { requiresAuth: true } },
  { path: '/settings', component: () => import('@/views/Settings.vue'), meta: { requiresAuth: true } },
  { path: '/settings/login-password', component: () => import('@/views/ChangeLoginPassword.vue'), meta: { requiresAuth: true } },
  { path: '/settings/withdrawal-password', component: () => import('@/views/ChangeWithdrawalPassword.vue'), meta: { requiresAuth: true } },
  { path: '/bill-details', component: () => import('@/views/BillDetails.vue'), meta: { requiresAuth: true } },
  { path: '/bind-wallet', component: () => import('@/views/BindWallet.vue'), meta: { requiresAuth: true } }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  if (to.meta.requiresAuth && !token) {
    next('/welcome')
  } else if ((to.path === '/login' || to.path === '/register' || to.path === '/welcome') && token) {
    next('/')
  } else {
    next()
  }
})

export default router
