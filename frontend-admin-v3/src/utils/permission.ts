import { AdminPermissions } from '@/constants/permissions';
import { useUserStore } from '@/store';

export function hasAdminPermission(permission: string) {
  const permissions = useUserStore().userInfo?.permissions || [];
  return permissions.includes(AdminPermissions.ALL) || permissions.includes(permission);
}
