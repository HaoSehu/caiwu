/**
 * 拆分自 src/pages/client/services/index.vue
 * 拆分日期：2026-10-11（纯机械搬移，零行为变化）
 * 职责：服务卡片 OS 图标加载失败降级（失败集合 + shallowRef/triggerRef）
 */
import { shallowRef, triggerRef } from 'vue';

import { resolveServiceOsIcon, resolveServiceOsText } from '@/domains/services/useServiceCenter';

export function useServiceOsIconFallback() {
  const failedServiceOsIconKeys = shallowRef(new Set<string>());

  function resolveServiceIconKey(item: Record<string, any>) {
    return `${item?.id || ''}:${resolveServiceOsText(item)}`;
  }

  function shouldShowServiceOsIcon(item: Record<string, any>) {
    return Boolean(resolveServiceOsIcon(item)) && !failedServiceOsIconKeys.value.has(resolveServiceIconKey(item));
  }

  function markServiceOsIconFailed(item: Record<string, any>) {
    // 原地 add + triggerRef，避免每次失败都克隆整个 Set 触发所有卡片重渲染
    failedServiceOsIconKeys.value.add(resolveServiceIconKey(item));
    triggerRef(failedServiceOsIconKeys);
  }

  return {
    failedServiceOsIconKeys,
    shouldShowServiceOsIcon,
    markServiceOsIconFailed,
  };
}
