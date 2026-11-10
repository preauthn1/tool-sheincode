export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/admin'

export const resolveAssetUrl = (url) => {
  if (!url) return ''
  if (/^(https?:|blob:|data:)/i.test(url)) return url

  const normalizedUrl = url.startsWith('/') ? url : `/${url}`
  const normalizedApiBase = API_BASE_URL.replace(/\/$/, '')

  if (normalizedUrl.startsWith('/uploads/')) return normalizedUrl
  if (normalizedApiBase && normalizedUrl.startsWith(`${normalizedApiBase}/uploads/`)) {
    return normalizedUrl.slice(normalizedApiBase.length)
  }

  return `${normalizedApiBase}${normalizedUrl}`
}
