/**
 * 权限检查工具函数
 * 统一处理管理端权限判断逻辑。
 * 权限码常量统一从 @/constants/permissions 引入，禁止手写字符串。
 */
import { computed } from 'vue'
import { useUserStore } from '@/stores/user'

export function hasPermission(permission) {
  const userStore = useUserStore()
  if (userStore.permissions.includes('*')) {
    return true
  }
  return userStore.permissions.includes(permission)
}

export function hasAnyPermission(permissions) {
  if (!Array.isArray(permissions) || permissions.length === 0) {
    return false
  }
  return permissions.some((permission) => hasPermission(permission))
}

export function hasAllPermissions(permissions) {
  if (!Array.isArray(permissions) || permissions.length === 0) {
    return false
  }
  return permissions.every((permission) => hasPermission(permission))
}

export function usePermission(permission) {
  return computed(() => hasPermission(permission))
}

export function usePermissions(permissions, mode = 'any') {
  return computed(() => {
    if (mode === 'all') {
      return hasAllPermissions(permissions)
    }
    return hasAnyPermission(permissions)
  })
}

/**
 * 根据路由 meta.permissions 过滤路由表，只保留用户有权限的路由。
 */
export function filterRoutesByPermissions(routes, userPermissions) {
  return routes
    .map((route) => {
      if (route.meta?.permissions?.length) {
        const hasAccess = hasPermissionForMeta(route.meta, userPermissions)
        if (!hasAccess) return null
      }
      if (route.children) {
        const filtered = filterRoutesByPermissions(route.children, userPermissions)
        return { ...route, children: filtered }
      }
      return route
    })
    .filter(Boolean)
}

function hasPermissionForMeta(meta, userPermissions) {
  if (!meta.permissions || meta.permissions.length === 0) return true
  if (userPermissions.includes('*')) return true
  const mode = meta.permissionMode || 'any'
  if (mode === 'all') {
    return meta.permissions.every((p) => userPermissions.includes(p))
  }
  return meta.permissions.some((p) => userPermissions.includes(p))
}

export { AdminPermissions } from '@/constants/permissions'
