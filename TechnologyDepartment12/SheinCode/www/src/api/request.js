import axios from 'axios'
import { API_BASE_URL } from '@/utils/url'

const request = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
})

let isUnauthorizedHandled = false

const redirectToLogin = () => {
  if (isUnauthorizedHandled) return
  isUnauthorizedHandled = true
  localStorage.removeItem('token')
  const redirect = window.location.hash?.replace(/^#/, '') || '/'
  const currentPage = `${window.location.pathname}${window.location.search}`
  const loginRoute = redirect && redirect !== '/login'
    ? `/login?redirect=${encodeURIComponent(redirect)}`
    : '/login'
  window.location.replace(`${currentPage}#${loginRoute}`)
}

request.interceptors.request.use((config) => {
  const useRawAuthorization = Boolean(config.rawAuthorization)
  delete config.rawAuthorization
  const appToken = localStorage.getItem('token')
  if (appToken && !config.headers.Authorization) {
    config.headers.Authorization = useRawAuthorization ? appToken : `Bearer ${appToken}`
  }
  return config
})

request.interceptors.response.use(
  (response) => {
    if (response.data?.code === 401 && !response.config?.skipAuthRedirect) {
      redirectToLogin()
      return new Promise(() => {})
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401 && !error.config?.skipAuthRedirect) {
      redirectToLogin()
      return new Promise(() => {})
    }
    return Promise.reject(error)
  }
)

export default request
