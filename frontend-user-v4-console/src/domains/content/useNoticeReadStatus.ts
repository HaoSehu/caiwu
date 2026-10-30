import clientApi from '@/api/client';
import { getErrorMessage } from '@/utils/error';

import { createUnreadCounter } from './createUnreadCounter';

// 公告未读（只统计公告），与铃铛未读（公告+通知）是两个语义不同的数字，
// 共用 createUnreadCounter 的 TTL/静默失败策略，但各自持有状态。
const counter = createUnreadCounter(async () => {
  const res = await clientApi.noticeUnreadCount();
  return Number(res.data?.count || 0);
});

export function useNoticeReadStatus() {
  // 只导出实际被消费的能力：公告角标只有「整批已读」这一个写入路径
  // （MenuContent.vue:121 取 unreadCount、ContentListPage.vue:163 取 markAllRead）。
  // 单条已读发生在铃铛侧（useInbox），走那边自己的 counter。
  const { unreadCount, fetchUnreadCount, setUnreadCount } = counter;

  async function markAllRead() {
    try {
      await clientApi.markAllNoticesRead();
      setUnreadCount(0);
    } catch (error: unknown) {
      throw new Error(getErrorMessage(error, '批量标记已读失败'));
    }
  }

  return {
    unreadCount,
    fetchUnreadCount,
    markAllRead,
  };
}
