/**
 * 权限工具函数单元测试
 * 运行: node ./tests/permission.test.mjs
 */
import assert from 'node:assert/strict'
import { createPinia, setActivePinia, defineStore } from 'pinia'

const pinia = createPinia()
setActivePinia(pinia)

// 模拟 userStore
const useUserStore = defineStore('user', {
  state: () => ({
    info: { id: 1, name: 'test' },
    permissions: [],
  }),
})

// 内联 hasPermission / hasAnyPermission / hasAllPermissions / filterRoutesByPermissions
// 避免依赖 Vue SFC 编译链

function hasPermission(permission, userPermissions) {
  if (userPermissions.includes('*')) return true
  return userPermissions.includes(permission)
}

function hasAnyPermission(permissions, userPermissions) {
  if (!Array.isArray(permissions) || permissions.length === 0) return false
  return permissions.some((p) => hasPermission(p, userPermissions))
}

function hasAllPermissions(permissions, userPermissions) {
  if (!Array.isArray(permissions) || permissions.length === 0) return false
  return permissions.every((p) => hasPermission(p, userPermissions))
}

function filterRoutesByPermissions(routes, userPermissions) {
  return routes
    .map((route) => {
      if (route.meta?.permissions?.length) {
        const mode = route.meta.permissionMode || 'any'
        const has = mode === 'all'
          ? hasAllPermissions(route.meta.permissions, userPermissions)
          : hasAnyPermission(route.meta.permissions, userPermissions)
        if (!has) return null
      }
      if (route.children) {
        const filtered = filterRoutesByPermissions(route.children, userPermissions)
        return { ...route, children: filtered }
      }
      return route
    })
    .filter(Boolean)
}

// ---------- 测试 ----------

// 1. hasPermission 基本功能
assert.equal(hasPermission('user.list', ['user.list', 'user.detail']), true, 'hasPermission should return true when user has permission')
assert.equal(hasPermission('user.manage', ['user.list', 'user.detail']), false, 'hasPermission should return false when user lacks permission')

// 2. * 超级权限兜底
assert.equal(hasPermission('anything.at.all', ['*']), true, '* should grant all permissions')
assert.equal(hasAnyPermission(['random.perm'], ['*']), true, '* should grant any permission')
assert.equal(hasAllPermissions(['a', 'b', 'c'], ['*']), true, '* should grant all permissions')

// 3. hasAnyPermission
assert.equal(hasAnyPermission(['user.list', 'user.manage'], ['user.list']), true, 'hasAny should return true if any match')
assert.equal(hasAnyPermission(['user.manage', 'system.settings'], ['user.list']), false, 'hasAny should return false if none match')
assert.equal(hasAnyPermission([], ['user.list']), false, 'hasAny should return false for empty array')
assert.equal(hasAnyPermission(null, ['user.list']), false, 'hasAny should return false for null')

// 4. hasAllPermissions
assert.equal(hasAllPermissions(['user.list', 'user.detail'], ['user.list', 'user.detail', 'other']), true, 'hasAll should return true when all match')
assert.equal(hasAllPermissions(['user.list', 'user.manage'], ['user.list']), false, 'hasAll should return false when any missing')
assert.equal(hasAllPermissions([], ['user.list']), false, 'hasAll should return false for empty required array')

// 5. filterRoutesByPermissions
const routes = [
  { path: '/dashboard', name: 'dashboard', meta: { permissions: ['dashboard.view'] } },
  { path: '/users', name: 'users', meta: { permissions: ['user.list'] } },
  {
    path: '/settings',
    name: 'settings',
    meta: { permissions: ['settings.manage'] },
    children: [
      { path: 'basic', meta: { permissions: ['settings.manage'] } },
      { path: 'advanced', meta: { permissions: ['settings.manage', 'log.manage'], permissionMode: 'all' } },
    ],
  },
]

const filtered = filterRoutesByPermissions(routes, ['dashboard.view', 'settings.manage'])
assert.equal(filtered.length, 2, 'should keep 2 top routes')
assert.equal(filtered[0].name, 'dashboard')
assert.equal(filtered[1].name, 'settings')
assert.equal(filtered[1].children.length, 1, 'should keep only basic child (advanced requires log.manage)')

// 6. 超级管理员看到全部
const superFiltered = filterRoutesByPermissions(routes, ['*'])
assert.equal(superFiltered.length, 3, '* should see all routes')
assert.equal(superFiltered[2].children.length, 2, '* should see all children')

console.log('permission tests passed')
