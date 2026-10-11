import { computed } from 'vue';

import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useUserStore } from '@/store';

/**
 * 退款标记能力判定（退款标记批 2026-10-11）。
 *
 * 只提供**权限**判定；单条账单能否标记一律以服务端详情下发的 `can_mark_refund`
 * 为准（守卫链唯一真源：类型白名单 + 状态 + 剩余可退额），前端不做本地规则
 * 推导，避免前后端两套规则漂移。
 */
export function useRefundMark() {
  const userStore = useUserStore();

  const hasRefundPermission = computed(() => {
    const permissions = (userStore.userInfo as { permissions?: string[] } | undefined)?.permissions || [];
    return hasPermissionInList(permissions, AdminPermissions.FINANCE_REFUND);
  });

  return { hasRefundPermission };
}
