export const resolveAssetUrlWithBase = (url, apiBaseUrl) => {
  if (!url) return ''
  if (/^(https?:|blob:|data:)/i.test(url)) return url

  const normalizedUrl = url.startsWith('/') ? url : `/${url}`
  const normalizedApiBase = apiBaseUrl.replace(/\/$/, '')
  const uploadPrefixes = ['/uploads/', `${normalizedApiBase}/uploads/`, '/api/admin/uploads/', '/webapi/uploads/']

  for (const prefix of uploadPrefixes) {
    if (prefix && normalizedUrl.startsWith(prefix)) {
      return `/uploads/${normalizedUrl.slice(prefix.length)}`
    }
  }

  return `${normalizedApiBase}${normalizedUrl}`
}
