/**
 * 拆分自 src/pages/client/dashboard/index.vue
 * 拆分日期：2026-10-11（纯机械搬移，零行为变化）
 * 职责：次屏数据加载（消费图表、帮助文章）及相关状态与重试
 */
import { ref } from 'vue';

import clientApi from '@/api/client';
import type { BalanceLog, ContentArticleRecord, InvoiceRecord } from '@/types/client';

export function resolvePagedList<T>(payload: { list?: T[] } | T[] | null | undefined): T[] {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.list)) return payload.list;
  return [];
}

export function toNumber(value: unknown) {
  const normalized = Number(value);
  return Number.isFinite(normalized) ? normalized : 0;
}

export function toDateText(value: Date) {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function last7DaysRange() {
  const now = new Date();
  const start = new Date(now);
  start.setDate(start.getDate() - 6);
  return {
    start_date: toDateText(start),
    end_date: toDateText(now),
  };
}

export function useDashboardSecondary(fetchUnreadCount: (silent?: boolean) => Promise<unknown>) {
  const balanceLogsLoadFailed = ref(false);
  const invoicesLoadFailed = ref(false);
  const balanceLogsDaily = ref<BalanceLog[]>([]);
  const paidInvoices = ref<InvoiceRecord[]>([]);
  const balanceLogsLoaded = ref(false);
  const invoicesLoaded = ref(false);
  const recentHelpArticles = ref<ContentArticleRecord[]>([]);

  // 分页穷尽拉取：图表聚合依赖全量数据，单页截断会导致金额少算（上限防失控）
  async function fetchAllPagedList<T>(
    fetcher: (params: {
      page: number;
      page_size: number;
    }) => Promise<{ data: { list?: T[] } | T[] | null | undefined }>,
    extraParams: Record<string, unknown> = {},
    maxPages = 20,
  ): Promise<T[]> {
    // 后端各列表接口 page_size 上限为 100（invoices 校验 max:100 且服务端再截断），取最小公约值
    const pageSize = 100;
    const rows: T[] = [];
    for (let page = 1; page <= maxPages; page += 1) {
      const res = await fetcher({ page, page_size: pageSize, ...extraParams });
      const list = resolvePagedList(res.data);
      rows.push(...list);
      if (list.length < pageSize) break;
    }
    return rows;
  }

  // 消费图表数据（今年账单 + 近 7 天流水）：失败时置 failed 标志展示“加载失败 + 重试”，不再无限转圈
  async function loadConsumptionCharts() {
    invoicesLoadFailed.value = false;
    balanceLogsLoadFailed.value = false;
    invoicesLoaded.value = false;
    balanceLogsLoaded.value = false;
    const currentYear = new Date().getFullYear();
    const [balanceLogsRows, paidInvoicesRows] = await Promise.allSettled([
      fetchAllPagedList<BalanceLog>((params) => clientApi.balanceLogs({ ...last7DaysRange(), ...params }), {}, 10),
      fetchAllPagedList<InvoiceRecord>(
        (params) =>
          clientApi.invoices({
            status: 1,
            start_date: `${currentYear}-01-01`,
            end_date: `${currentYear}-12-31`,
            ...params,
          }),
        {},
        20,
      ),
    ]);

    if (balanceLogsRows.status === 'fulfilled') {
      balanceLogsDaily.value = balanceLogsRows.value;
    } else {
      balanceLogsLoadFailed.value = true;
    }
    balanceLogsLoaded.value = true;

    if (paidInvoicesRows.status === 'fulfilled') {
      paidInvoices.value = paidInvoicesRows.value;
    } else {
      invoicesLoadFailed.value = true;
    }
    invoicesLoaded.value = true;
  }

  // 次屏延迟加载：图表数据、帮助文章、未读数
  // 这些数据不阻塞首屏显示，提升感知速度
  async function loadSecondaryData() {
    try {
      const [helpRes, _unreadRes] = await Promise.allSettled([
        clientApi.helpArticles({ page: 1, page_size: 10 }),
        fetchUnreadCount(true),
      ]);

      if (helpRes.status === 'fulfilled') {
        recentHelpArticles.value = resolvePagedList(helpRes.value.data);
      }
    } catch (error) {
      // 静默失败，不影响首屏显示
      console.error('Failed to load secondary data:', error);
    }
    void loadConsumptionCharts();
  }

  return {
    balanceLogsLoadFailed,
    invoicesLoadFailed,
    balanceLogsDaily,
    paidInvoices,
    balanceLogsLoaded,
    invoicesLoaded,
    recentHelpArticles,
    loadConsumptionCharts,
    loadSecondaryData,
  };
}
