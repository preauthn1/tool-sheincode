import { resolveAssetUrlWithBase } from './assetUrl.mjs'

export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/webapi'
export const ASSET_BASE_URL = import.meta.env.VITE_CHAT_ASSET_BASE_URL || import.meta.env.VITE_ASSET_BASE_URL || ''

export const resolveAssetUrl = (url) => {
  return resolveAssetUrlWithBase(url, API_BASE_URL)
}

const originFromUrl = (url) => {
  try {
    return new URL(url).origin
  } catch {
    return ''
  }
}

const resolveAssetOrigin = () => {
  if (ASSET_BASE_URL) return ASSET_BASE_URL.replace(/\/$/, '')
  const apiOrigin = originFromUrl(API_BASE_URL)
  if (apiOrigin) return apiOrigin
  if (typeof window !== 'undefined' && window.location?.origin) return window.location.origin
  return ''
}

export const resolveAbsoluteAssetUrl = (url) => {
  if (!url) return ''
  if (/^(https?:|blob:|data:)/i.test(url)) return url

  const normalizedUrl = resolveAssetUrl(url)
  if (/^(https?:|blob:|data:)/i.test(normalizedUrl)) return normalizedUrl

  const origin = resolveAssetOrigin()
  if (!origin) return normalizedUrl
  const path = normalizedUrl.startsWith('/') ? normalizedUrl : `/${normalizedUrl}`
  return `${origin}${path}`
}
