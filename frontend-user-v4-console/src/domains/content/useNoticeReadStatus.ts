import { ref } from 'vue';

import clientApi from '@/api/client';

const unreadCount = ref(0);
let lastFetchTime = 0;
const CACHE_TTL = 30_000;

export function useNoticeReadStatus() {
  async function fetchUnreadCount(force = false) {
    const now = Date.now();
    if (!force && now - lastFetchTime < CACHE_TTL) return;
    try {
      const res = await clientApi.noticeUnreadCount();
      unreadCount.value = (res as any).data?.count ?? 0;
      lastFetchTime = Date.now();
    } catch {
      // 静默失败
    }
  }

  async function markAllRead() {
    await clientApi.markAllNoticesRead();
    unreadCount.value = 0;
    lastFetchTime = Date.now();
  }

  function decrementUnread() {
    if (unreadCount.value > 0) unreadCount.value--;
    lastFetchTime = 0;
  }

  return {
    unreadCount,
    fetchUnreadCount,
    markAllRead,
    decrementUnread,
  };
}
