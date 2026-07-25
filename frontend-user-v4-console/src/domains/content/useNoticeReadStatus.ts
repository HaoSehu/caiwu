import { ref } from 'vue';

import clientApi from '@/api/client';
import { getErrorMessage } from '@/utils/error';

const unreadCount = ref(0);
let lastFetchTime = 0;
const CACHE_TTL = 30_000;

export function useNoticeReadStatus() {
  async function fetchUnreadCount(force = false) {
    const now = Date.now();
    if (!force && now - lastFetchTime < CACHE_TTL) return;
    try {
      const res = await clientApi.noticeUnreadCount();
      unreadCount.value = Number(res.data?.count || 0);
      lastFetchTime = Date.now();
    } catch {
      // 静默失败
    }
  }

  async function markAllRead() {
    try {
      await clientApi.markAllNoticesRead();
      unreadCount.value = 0;
      lastFetchTime = Date.now();
    } catch (error: unknown) {
      throw new Error(getErrorMessage(error, '批量标记已读失败'));
    }
  }

  function decrementUnread() {
    if (unreadCount.value > 0) unreadCount.value -= 1;
    lastFetchTime = 0;
  }

  return {
    unreadCount,
    fetchUnreadCount,
    markAllRead,
    decrementUnread,
  };
}
