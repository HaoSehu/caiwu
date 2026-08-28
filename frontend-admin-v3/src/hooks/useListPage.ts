import type { Ref } from 'vue';
import { computed, onActivated, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { matchQuickFilterByRange, resolveQuickDateRange } from '@/domains/finance/dateFilters';

export interface ListPageResponse<T> {
  list?: T[];
  total?: number;
  /** 后端回传的当前页码（可选，用于同步分页器） */
  page?: number;
  /** 后端回传的每页条数（可选，用于同步分页器） */
  page_size?: number;
}

export interface UseListPageOptions<F, T> {
  /** 列表请求函数，接收 { ...filters, page, page_size }，返回列表分页响应 */
  fetch: (params: F & { page: number; page_size: number }) => Promise<ListPageResponse<T>>;
  /** 默认筛选值 */
  defaultFilters: F;
  /** 默认每页条数 */
  defaultPageSize?: number;
  /** 是否 onMounted 自动加载 */
  immediate?: boolean;
  /** 自定义错误处理，默认吞掉错误 */
  onError?: (error: unknown) => void;
  /** 每次加载成功后回调（如清空表格多选、并发拉取汇总数据） */
  afterLoad?: (list: T[], total: number) => void;
  /** 是否将筛选/分页同步到 URL 并在进入页面时恢复（与已有查询参数如 tab 共存） */
  syncUrl?: boolean;
  /** 是否启用日期快捷标签（全部/最近7天/本月/待支付）与日期范围选择器联动 */
  enableQuickFilter?: boolean;
}

const PAGE_SIZE_OPTIONS = [20, 50, 100];
const QUERY_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
/** URL 同步管理的通用筛选键（start_date/end_date/quick 按页面实际字段动态加入） */
const BASE_QUERY_KEYS = ['page', 'page_size', 'keyword', 'status', 'type'];

export function useListPage<F extends Record<string, any>, T>(options: UseListPageOptions<F, T>) {
  const {
    fetch,
    defaultFilters,
    defaultPageSize = 20,
    immediate = true,
    onError,
    afterLoad,
    syncUrl = false,
    enableQuickFilter = false,
  } = options;
  const route = useRoute();
  const router = useRouter();

  const filters = reactive({ ...defaultFilters }) as F;
  // 泛型 F 上的具名字段访问统一经 Record 视图，避免 TS 对泛型索引签名的限制
  const model = filters as Record<string, any>;
  const list: Ref<T[]> = ref([]);
  const total = ref(0);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const pagination = reactive({
    page: 1,
    page_size: defaultPageSize,
    pageSizeOptions: PAGE_SIZE_OPTIONS,
  });

  let requestSeq = 0;

  function hasFilterKey(key: string) {
    return key in model;
  }

  function quickFilterValue(): string {
    return enableQuickFilter ? String(model.quickFilter ?? '') : '';
  }

  function setQuickFilter(value: string) {
    if (enableQuickFilter) model.quickFilter = value;
  }

  function restoreFiltersFromQuery() {
    const query = route.query;
    const page = Number(query.page);
    if (Number.isFinite(page) && page > 0) pagination.page = page;
    const pageSize = Number(query.page_size);
    if (Number.isFinite(pageSize) && PAGE_SIZE_OPTIONS.includes(pageSize)) pagination.page_size = pageSize;
    if (hasFilterKey('keyword') && typeof query.keyword === 'string') model.keyword = query.keyword;
    if (hasFilterKey('status') && typeof query.status === 'string' && query.status !== '') {
      model.status = query.status;
    }
    if (hasFilterKey('type') && typeof query.type === 'string') model.type = query.type;
    if (
      hasFilterKey('start_date') &&
      typeof query.start_date === 'string' &&
      QUERY_DATE_PATTERN.test(query.start_date)
    ) {
      model.start_date = query.start_date;
    }
    if (hasFilterKey('end_date') && typeof query.end_date === 'string' && QUERY_DATE_PATTERN.test(query.end_date)) {
      model.end_date = query.end_date;
    }
    if (!enableQuickFilter) return;
    const quick = typeof query.quick === 'string' ? query.quick : '';
    if (!quick) return;
    // 快捷标签与显式状态冲突时（标签 pending 但 status 已被手动改为其它值），以显式状态为准、熄灭标签
    const pendingConflict = quick === 'pending' && model.status !== '' && model.status !== '0';
    setQuickFilter(pendingConflict ? '' : quick);
    if (!pendingConflict && quick === 'pending' && model.status === '') model.status = '0';
    // 显式日期优先：URL 同时带 quick 与日期时，采纳日期，标签选中态由区间匹配回写，避免跨天漂移
    const hasExplicitDates =
      typeof query.start_date === 'string' &&
      QUERY_DATE_PATTERN.test(query.start_date) &&
      typeof query.end_date === 'string' &&
      QUERY_DATE_PATTERN.test(query.end_date);
    if (hasExplicitDates && typeof query.start_date === 'string' && typeof query.end_date === 'string') {
      model.start_date = query.start_date;
      model.end_date = query.end_date;
      if (quickFilterValue()) setQuickFilter(matchQuickFilterByRange(model.start_date, model.end_date));
    } else {
      const range = resolveQuickDateRange(quick);
      model.start_date = range.start_date || '';
      model.end_date = range.end_date || '';
    }
  }

  function syncFiltersToQuery() {
    const managed = new Set<string>(BASE_QUERY_KEYS);
    if (hasFilterKey('start_date')) managed.add('start_date');
    if (hasFilterKey('end_date')) managed.add('end_date');
    if (enableQuickFilter) managed.add('quick');

    const next: Record<string, string> = {};
    if (pagination.page > 1) next.page = String(pagination.page);
    if (pagination.page_size !== defaultPageSize) next.page_size = String(pagination.page_size);
    if (hasFilterKey('keyword') && String(model.keyword ?? '').trim()) next.keyword = String(model.keyword).trim();
    if (hasFilterKey('status') && model.status !== '' && model.status !== undefined && model.status !== null) {
      next.status = String(model.status);
    }
    if (hasFilterKey('type') && model.type !== '' && model.type !== undefined && model.type !== null) {
      next.type = String(model.type);
    }
    if (managed.has('start_date') && model.start_date && QUERY_DATE_PATTERN.test(String(model.start_date))) {
      next.start_date = String(model.start_date);
    }
    if (managed.has('end_date') && model.end_date && QUERY_DATE_PATTERN.test(String(model.end_date))) {
      next.end_date = String(model.end_date);
    }
    if (enableQuickFilter && quickFilterValue()) next.quick = quickFilterValue();

    // 保留非筛选查询参数（如订单页 tab），并清除已管理键的过期值
    const final: Record<string, string> = {};
    for (const [key, value] of Object.entries(route.query)) {
      if (managed.has(key)) continue;
      if (value !== undefined && value !== null) final[key] = String(value);
    }
    Object.assign(final, next);

    const current = route.query;
    const changed =
      Object.keys(current).length !== Object.keys(final).length ||
      Object.keys(final).some((key) => String(current[key] ?? '') !== final[key]);
    if (!changed) return;
    void router.replace({ query: final });
  }

  async function loadList() {
    const currentSeq = ++requestSeq;
    loading.value = true;
    try {
      const res = await fetch({
        ...filters,
        page: pagination.page,
        page_size: pagination.page_size,
      });
      if (currentSeq !== requestSeq) return;
      error.value = null;
      list.value = res.list || [];
      total.value = Number(res.total || 0);
      if (res.page) pagination.page = Number(res.page);
      if (res.page_size) pagination.page_size = Number(res.page_size);
      if (afterLoad) afterLoad(list.value, total.value);
      if (syncUrl) syncFiltersToQuery();
    } catch (err) {
      if (currentSeq !== requestSeq) return;
      error.value = err instanceof Error ? err.message : '加载失败，请稍后重试';
      console.error('[useListPage] 列表加载失败:', err);
      if (onError) onError(err);
    } finally {
      if (currentSeq === requestSeq) loading.value = false;
    }
  }

  function handleSearch() {
    // 手动切换状态与"待支付"快捷标签语义冲突时熄灭标签，避免 URL 持久化矛盾组合
    if (enableQuickFilter && quickFilterValue() === 'pending' && model.status !== '' && model.status !== '0') {
      setQuickFilter('');
    }
    pagination.page = 1;
    return loadList();
  }

  function resetFilters() {
    Object.assign(filters, defaultFilters);
    pagination.page = 1;
    return loadList();
  }

  function handlePageChange(page: number) {
    pagination.page = page;
    return loadList();
  }

  function handlePageSizeChange(size: number) {
    pagination.page_size = size;
    pagination.page = 1;
    return loadList();
  }

  /** 兼容 t-pagination 的 change 事件回调，入参为 { current, pageSize } */
  function handlePaginationChange(data: { current: number; pageSize: number }) {
    pagination.page = data.current;
    pagination.page_size = data.pageSize;
    return loadList();
  }

  /** 点击快捷标签：物化日期区间并清除显式筛选，标签为 pending 时带入 status=0 */
  function applyQuickFilter(key: string) {
    if (!enableQuickFilter) return;
    setQuickFilter(key);
    pagination.page = 1;
    model.status = '';
    model.type = '';
    model.start_date = '';
    model.end_date = '';
    if (key === 'pending') model.status = '0';
    const range = resolveQuickDateRange(key);
    model.start_date = range.start_date || '';
    model.end_date = range.end_date || '';
    return loadList();
  }

  // 日期范围选择器与快捷标签双向联动：标签选中时日期物化到 filters；
  // 手动改日期时若区间与某快捷标签折算一致则回写选中态，否则视为自定义区间
  const dateRange = computed<string[]>({
    get() {
      if (!enableQuickFilter) return [];
      if (model.start_date && model.end_date) return [String(model.start_date), String(model.end_date)];
      return [];
    },
    set(value) {
      if (!enableQuickFilter) return;
      const range = Array.isArray(value) ? value : [];
      const prevQuick = quickFilterValue();
      // 选择器正常产出双值或空数组；清空或半开区间按空处理
      const [start, end] = range.length === 2 ? range : [];
      model.start_date = start || '';
      model.end_date = end || '';
      setQuickFilter(matchQuickFilterByRange(model.start_date, model.end_date));
      // 由"待支付"标签带入的 status=0 随标签熄灭复位，避免显式状态残留
      if (prevQuick === 'pending' && quickFilterValue() !== 'pending' && model.status === '0') {
        model.status = '';
      }
      pagination.page = 1;
      return loadList();
    },
  });

  if (syncUrl) restoreFiltersFromQuery();

  if (immediate) {
    onMounted(loadList);
    // 列表页被 keep-alive 缓存后，再次激活时刷新数据；首次激活跳过（onMounted 已加载）
    let skipFirstActivate = true;
    onActivated(() => {
      if (skipFirstActivate) {
        skipFirstActivate = false;
        return;
      }
      loadList();
    });
  }

  return {
    filters,
    list,
    total,
    loading,
    error,
    pagination,
    loadList,
    clearError: () => {
      error.value = null;
    },
    handleSearch,
    resetFilters,
    handlePageChange,
    handlePageSizeChange,
    handlePaginationChange,
    applyQuickFilter,
    dateRange,
  };
}
