export const getAdminUser = () => {
  try {
    const stored = JSON.parse(localStorage.getItem('admin_user') || '{}') || {}
    if (stored.role || stored.permissions) return stored

    const token = localStorage.getItem('admin_token') || ''
    if (!token) return stored

    const payload = JSON.parse(atob(token) || '{}')
    if (!payload.role && Number(payload.admin_id) === 1) {
      return { ...payload, id: payload.admin_id, role: 'super_admin' }
    }
    return { ...payload, id: payload.admin_id }
  } catch (error) {
    return {}
  }
}

export const hasPermission = (permission) => {
  if (!permission) return true
  if (permission.includes('|')) {
    return permission.split('|').some((item) => hasPermission(item.trim()))
  }

  const user = getAdminUser()
  if (user.role === 'super_admin') return true

  const permissions = Array.isArray(user.permissions) ? user.permissions : []
  // 前端只认新的 menu.* 权限，旧操作权限不再做兼容。
  return permissions.includes(permission)
}
