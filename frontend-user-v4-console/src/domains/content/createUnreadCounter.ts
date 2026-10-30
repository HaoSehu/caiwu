import { ref } from 'vue';

/**
 * 未读数计数器：把「模块级单例 + 30 秒 TTL + 静默失败」这套重复逻辑收敛成一处。
 *
 * 站内存在两个语义不同的未读数，不能用同一个数字混用：
 *  - 铃铛未读：公告 + 个性化通知（GET /v2/client/notifications/unread-count）
 *  - 公告未读：只统计公告（GET /v2/client/notices/unread-count），用于公告菜单角标与公告列表
 * 两个计数器各自持有状态，但共享 TTL、刷新与静默失败策略，避免复制粘贴出第二套实现。
 */
export function createUnreadCounter(fetcher: () => Promise<number>, ttlMs = 30_000) {
  const unreadCount = ref(0);
  let lastFetchTime = 0;
  // 冷启动时 lastFetchTime 为 0，30s TTL 拦不住并发调用：公告列表页的
  // useContentList 的 immediate watch 与页面 onMounted 会在同一 tick 各发一次，
  // 首次进页面就打两次 unread-count。用 in-flight Promise 复用把并发收敛成一次。
  let inFlight: Promise<void> | null = null;

  async function fetchUnreadCount(force = false) {
    const now = Date.now();
    if (!force && now - lastFetchTime < ttlMs) return;
    if (inFlight) {
      // 非强制：直接复用进行中的请求。强制（标记已读后必须回源）：
      // 先等它落地再发自己的，否则会拿标记前的旧值当结果。
      if (!force) return inFlight;
      await inFlight;
    }
    inFlight = (async () => {
      try {
        unreadCount.value = await fetcher();
        lastFetchTime = Date.now();
      } catch {
        // 静默失败，避免角标异常影响页面
      } finally {
        inFlight = null;
      }
    })();
    return inFlight;
  }

  function setUnreadCount(value: number) {
    unreadCount.value = Math.max(0, Math.trunc(value));
    lastFetchTime = Date.now();
  }

  function decrementUnread() {
    if (unreadCount.value > 0) unreadCount.value -= 1;
    lastFetchTime = 0;
  }

  /** 让下一次 fetchUnreadCount() 必须真正请求（详情页已读、批量已读后调用）。 */
  function invalidate() {
    lastFetchTime = 0;
  }

  return { unreadCount, fetchUnreadCount, setUnreadCount, decrementUnread, invalidate };
}

export type UnreadCounter = ReturnType<typeof createUnreadCounter>;
