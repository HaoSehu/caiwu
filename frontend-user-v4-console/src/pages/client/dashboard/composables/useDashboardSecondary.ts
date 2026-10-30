/**
 * 拆分自 src/pages/client/dashboard/index.vue
 * 拆分日期：2026-10-11（纯机械搬移，零行为变化）
 * 职责：次屏数据加载（消费图表、帮助文章）及相关状态与重试
 */
import { onActivated, onDeactivated, onUnmounted, ref } from 'vue';

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
  // 并发度 4：串行翻页在「当年全年账单」场景最坏要 20 次往返（首屏后仍持续占用带宽），
  // 并发后往返次数不变但墙钟时间降到约 1/4；遇到短页立即停止，不会多发请求。
  const PAGE_FETCH_CONCURRENCY = 4;
  const pageCache = new Map<string, { at: number; rows: unknown[] }>();
  const PAGE_CACHE_TTL = 120 * 1000;

  // 空闲期任务的取消句柄：requestIdleCallback 与 setTimeout 两种兜底各自要对应的取消函数。
  // 页面被 keep-alive 缓存后失活不会卸载组件；不取消的话，用户在别的页面时
  // 这两个空闲期任务仍会在 2s 兜底后触发整轮分页拉取（最坏 10 + 20 次请求）。
  let idleHandle: number | null = null;
  let idleScheduledAsIdleCallback = false;
  let secondaryRequested = false;
  // 分页拉取的代次：失活/卸载时自增，让在途的那一轮「不再发下一页、也不回写状态」。
  // 这里不发真正的 AbortSignal：clientApi 的调用签名统一不带 signal，改造面会扩散到 api 层；
  // 用代次守卫能在不触碰接口的前提下达到同样效果——最坏只是已发出的那一页请求白跑。
  let fetchGeneration = 0;

  function cancelIdleTask() {
    // 取消空闲期任务的同时作废在途分页，否则用户在别的页面时它还会继续翻页并写回本页状态
    fetchGeneration += 1;
    if (idleHandle === null) return;
    if (idleScheduledAsIdleCallback) {
      window.cancelIdleCallback(idleHandle);
    } else {
      window.clearTimeout(idleHandle);
    }
    idleHandle = null;
  }

  function scheduleIdleTask(task: () => void) {
    cancelIdleTask();
    if (typeof window.requestIdleCallback === 'function') {
      idleScheduledAsIdleCallback = true;
      idleHandle = window.requestIdleCallback(
        () => {
          idleHandle = null;
          task();
        },
        { timeout: 2000 },
      );
      return;
    }
    idleScheduledAsIdleCallback = false;
    idleHandle = window.setTimeout(() => {
      idleHandle = null;
      task();
    }, 300);
  }

  onDeactivated(cancelIdleTask);
  onUnmounted(cancelIdleTask);

  // 失活时被取消的空闲期任务要补回来，否则「首屏没等空闲期就被切走」的这一次会永久丢图表数据；
  // 已经拿到结果（成功或失败）就不再重打，避免每次切回都发一轮分页请求。
  onActivated(() => {
    if (!secondaryRequested) return;
    if (balanceLogsLoaded.value || balanceLogsLoadFailed.value) return;
    scheduleIdleTask(() => void loadConsumptionCharts());
  });

  async function fetchAllPagedList<T>(
    fetcher: (params: {
      page: number;
      page_size: number;
    }) => Promise<{ data: { list?: T[] } | T[] | null | undefined }>,
    extraParams: Record<string, unknown> = {},
    maxPages = 20,
    cacheKey = '',
    force = false,
  ): Promise<T[]> {
    const generation = fetchGeneration;
    if (cacheKey && !force) {
      const cached = pageCache.get(cacheKey);
      if (cached && Date.now() - cached.at < PAGE_CACHE_TTL) return cached.rows as T[];
    }

    // 后端各列表接口 page_size 上限为 100（invoices 校验 max:100 且服务端再截断），取最小公约值
    const pageSize = 100;
    const rows: T[] = [];
    let nextPage = 1;
    let exhausted = false;

    const worker = async () => {
      while (!exhausted) {
        // 已作废的一轮不再发下一页；上一页返回后才发现也立刻退出，不把结果拼进来
        if (generation !== fetchGeneration) return;
        const page = nextPage;
        nextPage += 1;
        if (page > maxPages) return;
        const res = await fetcher({ page, page_size: pageSize, ...extraParams });
        if (generation !== fetchGeneration) return;
        const list = resolvePagedList(res.data);
        rows.push(...list);
        if (list.length < pageSize) exhausted = true;
      }
    };

    const workers: Promise<void>[] = [];
    for (let i = 0; i < PAGE_FETCH_CONCURRENCY; i += 1) workers.push(worker());
    await Promise.all(workers);

    // 作废的一轮不写缓存：它可能只拿到了前几页，写进去会让下一轮命中一份残缺数据
    if (cacheKey && generation === fetchGeneration) pageCache.set(cacheKey, { at: Date.now(), rows });
    return rows;
  }

  // 消费图表数据（今年账单 + 近 7 天流水）：失败时置 failed 标志展示“加载失败 + 重试”，不再无限转圈
  // scope 让两个图表的「重试」各自只重拉自己那一份数据，不再因为局部重试把另一份也置为未加载。
  async function loadConsumptionCharts(scope: 'all' | 'balance' | 'invoices' = 'all', force = false) {
    const currentYear = new Date().getFullYear();
    const tasks: Promise<void>[] = [];
    // 本轮代次：自增即作废上一轮（用户连点重试时只认最后一轮），
    // 失活/卸载后本轮的结果也一律不写回状态，否则回到本页会看到「已加载但是空的图表」
    fetchGeneration += 1;
    const generation = fetchGeneration;

    if (scope !== 'invoices') {
      balanceLogsLoadFailed.value = false;
      balanceLogsLoaded.value = false;
      tasks.push(
        fetchAllPagedList<BalanceLog>(
          (params) => clientApi.balanceLogs({ ...last7DaysRange(), ...params }),
          {},
          10,
          'balance-daily',
          force,
        ).then(
          (rows) => {
            if (generation !== fetchGeneration) return;
            balanceLogsDaily.value = rows;
            balanceLogsLoaded.value = true;
          },
          () => {
            if (generation !== fetchGeneration) return;
            balanceLogsLoadFailed.value = true;
            // 失败也要收尾：*Loaded 的语义是「这一份数据不再等待」。
            // 只置 failed 会让模板的 :loading="!balanceLogsLoaded" 永久为真，
            // t-loading 的遮罩会盖住失败态里的重试按钮，用户点不到。
            balanceLogsLoaded.value = true;
          },
        ),
      );
    }

    if (scope !== 'balance') {
      invoicesLoadFailed.value = false;
      invoicesLoaded.value = false;
      tasks.push(
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
          'paid-invoices',
          force,
        ).then(
          (rows) => {
            if (generation !== fetchGeneration) return;
            paidInvoices.value = rows;
            invoicesLoaded.value = true;
          },
          () => {
            if (generation !== fetchGeneration) return;
            invoicesLoadFailed.value = true;
            invoicesLoaded.value = true;
          },
        ),
      );
    }

    await Promise.all(tasks);
  }

  // 次屏延迟加载：图表数据、帮助文章、未读数
  // 这些数据不阻塞首屏显示，提升感知速度
  async function loadSecondaryData() {
    secondaryRequested = true;
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
    // 图表依赖多次分页请求，让出首帧：等主线程空闲后再启动，避免与首屏渲染争抢。
    // 统一走 scheduleIdleTask —— 它持有取消句柄，失活/卸载时能停下（见上方 cancelIdleTask）。
    scheduleIdleTask(() => void loadConsumptionCharts());
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
