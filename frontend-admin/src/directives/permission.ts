/**
 * v-permission 权限指令。
 * 用法：<el-button v-permission="AdminPermissions.USER_EDIT">编辑</el-button>
 *       <el-button v-permission:all="[AdminPermissions.USER_EDIT, AdminPermissions.USER_DELETE]">批量操作</el-button>
 *
 * arg: 空或 'any' 表示任一权限满足即显示，'all' 表示全部满足才显示。
 */
import type { Directive, DirectiveBinding } from 'vue'
import { useUserStore } from '@/stores/user'

function checkPermission(binding: DirectiveBinding<string | string[]>): boolean {
  const userStore = useUserStore()
  const userPermissions = userStore.permissions

  if (userPermissions.includes('*')) return true

  const required = binding.value
  if (!required) return true

  const requiredList = Array.isArray(required) ? required : [required]
  if (requiredList.length === 0) return true

  const mode = binding.arg === 'all' ? 'all' : 'any'
  if (mode === 'all') {
    return requiredList.every((p) => userPermissions.includes(p))
  }
  return requiredList.some((p) => userPermissions.includes(p))
}

export const vPermission: Directive<HTMLElement, string | string[]> = {
  mounted(el: HTMLElement, binding: DirectiveBinding<string | string[]>) {
    if (!checkPermission(binding)) {
      el.parentNode?.removeChild(el)
    }
  },
}
