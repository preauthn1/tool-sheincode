import axios from 'axios'
import { ElMessage } from 'element-plus'
import { API_BASE_URL } from '@/utils/url'

const request = axios.create({
  baseURL: `${API_BASE_URL}`,
  timeout: 10000,
})

let isUnauthorizedHandled = false

const redirectToLogin = () => {
  if (isUnauthorizedHandled) return
  isUnauthorizedHandled = true
  localStorage.removeItem('admin_token')
  localStorage.removeItem('admin_user')
  const redirect = window.location.hash?.replace(/^#/, '') || '/'
  const currentPage = `${window.location.pathname}${window.location.search}`
  const loginRoute = redirect && redirect !== '/login'
    ? `/login?redirect=${encodeURIComponent(redirect)}`
    : '/login'
  const loginUrl = `${currentPage}#${loginRoute}`
  window.location.replace(loginUrl)
}

request.interceptors.request.use((config) => {
  const token = localStorage.getItem('admin_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

request.interceptors.response.use(
  (response) => {
    if (response.data?.code === 401) {
      redirectToLogin()
      return new Promise(() => {})
    }
    if (response.data?.code === 403) {
      ElMessage.error(response.data.msg || '没有操作权限')
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      redirectToLogin()
      return new Promise(() => {})
    }
    if (error.response?.status === 403) {
      ElMessage.error(error.response.data?.msg || '没有操作权限')
    }
    return Promise.reject(error)
  }
)

export default request
