import { computed } from 'vue';

import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useUserStore } from '@/store';

/**
 * 退款到余额能力判定（唯一退款通道，2026-10-20）。
 *
 * 只提供**权限**判定；单条账单能否退款一律以服务端详情下发的
 * `can_refund_to_balance` 为准（守卫链唯一真源：类型白名单 + 状态 + 可退额），
 * 前端不做本地规则推导，避免前后端两套规则漂移。
 */
export function useRefundMark() {
  const userStore = useUserStore();

  const hasRefundPermission = computed(() => {
    const permissions = (userStore.userInfo as { permissions?: string[] } | undefined)?.permissions || [];
    return hasPermissionInList(permissions, AdminPermissions.FINANCE_REFUND);
  });

  return { hasRefundPermission };
}
