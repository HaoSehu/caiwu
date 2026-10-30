import { ref } from 'vue';

import clientApi from '@/api/client';
import { getErrorMessage } from '@/utils/error';

import { createUnreadCounter } from './createUnreadCounter';

export interface InboxItem {
  id: string;
  raw_id?: number;
  source: 'notice' | 'message';
  type: string;
  type_label: string;
  title: string;
  summary: string;
  link: string | null;
  read: boolean;
  created_at: string | null;
}

// 铃铛未读 = 公告未读 + 个性化通知未读（GET /notifications/unread-count），
// 与 useNoticeReadStatus 的「公告未读」不是同一个数字，禁止互相复用。
const counter = createUnreadCounter(async () => {
  const res = await clientApi.notificationUnreadCount();
  return Number((res as { data?: { count?: number } })?.data?.count ?? 0);
});

// 模块级单例：未读数与下拉列表在铃铛、侧栏、Dashboard 间共享
const feedItems = ref<InboxItem[]>([]);
const feedLoading = ref(false);

export function useInbox() {
  const { unreadCount, fetchUnreadCount, setUnreadCount, decrementUnread, invalidate } = counter;

  async function fetchFeed(limit = 10) {
    feedLoading.value = true;
    try {
      const res = await clientApi.notificationFeed(limit);
      const data = (res as { data?: { list: InboxItem[]; unread_count: number } })?.data;
      feedItems.value = data?.list ?? [];
      if (typeof data?.unread_count === 'number') {
        setUnreadCount(data.unread_count);
      }
    } catch {
      feedItems.value = [];
    } finally {
      feedLoading.value = false;
    }
  }

  // 标记单条已读：公告进详情页自动已读，这里主要处理个性化消息。
  // 失败必须向上抛出，调用方（Header）要能区分「已标记」与「标记失败」，
  // 否则会出现已提示成功但服务端仍未读的状态漂移。
  async function markRead(item: InboxItem) {
    if (item.read) return;
    try {
      if (item.source === 'message' && item.raw_id != null) {
        await clientApi.markNotificationRead(item.raw_id);
      } else if (item.source === 'notice') {
        const noticeId = item.id.replace('notice-', '');
        await clientApi.markNoticeRead(noticeId);
      }
      item.read = true;
      // 走 counter 的减一：它除了把数字减下去，还会清掉「最近一次拉取时间」，
      // 下一次进面板会真的回源校正；直接改 ref 会让计数在 TTL 内漂移。
      decrementUnread();
    } catch (error: unknown) {
      throw new Error(getErrorMessage(error, '标记已读失败'));
    }
  }

  async function markAllRead() {
    try {
      await clientApi.markAllNotificationsRead();
      setUnreadCount(0);
      feedItems.value = feedItems.value.map((item) => ({ ...item, read: true }));
    } catch (error: unknown) {
      throw new Error(getErrorMessage(error, '批量标记已读失败'));
    }
  }

  return {
    unreadCount,
    feedItems,
    feedLoading,
    fetchUnreadCount,
    fetchFeed,
    markRead,
    markAllRead,
    // 公告详情页打开后已读，需让下一次拉取真正请求
    invalidate,
  };
}
