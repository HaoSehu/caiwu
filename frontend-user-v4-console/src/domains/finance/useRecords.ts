import {
  ACCOUNT_TRANSACTION_EVENT_MAP,
  getStatusLabel,
  PAYMENT_STATUS_MAP,
  toSelectOptions,
} from '@caiwu/shared/statusConfig';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref, shallowRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { ApiEnvelope, ClientFinanceListParams, PagedList } from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { formatMoney } from '@/utils/format';

import { matchQuickFilterByRange, resolveQuickDateRange } from './dateFilters';

type AnyRecord = Record<string, unknown>;

export interface RecordListOptions<T> {
  fetcher: (params?: ClientFinanceListParams) => Promise<ApiEnvelope<PagedList<T>>>;
  errorMessage: string;
  pageSize?: number;
}

const PAGE_SIZE_OPTIONS = [10, 20, 50];
const QUERY_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

export const PAYMENT_STATUS_OPTIONS = toSelectOptions(PAYMENT_STATUS_MAP, false);

export const PAYMENT_GATEWAY_OPTIONS = [
  { label: '支付宝', value: 'alipay' },
  { label: '易支付', value: 'yipay' },
  { label: '微信支付', value: 'wechat' },
];

export const BALANCE_EVENT_OPTIONS = toSelectOptions(ACCOUNT_TRANSACTION_EVENT_MAP, false);

export { formatMoney };

export function fieldValue(value: unknown) {
  if (value === null || value === undefined || value === '') return '--';
  return String(value);
}

export { formatDateTime } from '@/utils/format';

const BILLING_CYCLE_LABEL_MAP: Record<string, string> = {
  monthly: '月付',
  quarterly: '季付',
  semiannually: '半年付',
  biennially: '两年付',
  triennially: '三年付',
  annually: '年付',
  yearly: '年付',
  one_time: '一次性',
  onetime: '一次性',
  free: '免费',
};

const BILLING_CYCLE_KEYS = new Set(['billing_cycle', 'billingcycle', 'billingcycle_zh', 'period']);
const SNAPSHOT_DISPLAY_META_KEYS = new Set([
  'product_full_path',
  'product_path',
  'product_display_path',
  'product_path_segments',
  'first_product_group_name',
  'second_product_group_name',
  'third_product_group_name',
]);

export function formatBillingCycle(value: unknown) {
  const raw = String(value ?? '').trim();
  if (!raw) return '--';
  return BILLING_CYCLE_LABEL_MAP[raw.toLowerCase()] || raw;
}

export function toRecord(value: unknown): AnyRecord {
  return value && typeof value === 'object' ? (value as AnyRecord) : {};
}

const SNAPSHOT_LABEL_MAP: Record<string, string> = {
  bw: '带宽',
  in_bw: '下行带宽',
  out_bw: '上行带宽',
  os: '操作系统',
  cpu: 'CPU',
  area: '区域',
  region: '区域',
  node: '节点',
  node_group: '节点分组',
  ip: 'IP数量',
  ip_num: 'IP数量',
  ipv6_num: 'IPv6数量',
  memory: '内存',
  hostname: '主机名',
  quantity: '数量',
  setup_fee: '初装费',
  base_amount: '基础金额',
  total_amount: '合计金额',
  config_amount: '配置金额',
  subtotal_amount: '小计金额',
  discount_amount: '优惠金额',
  amount: '金额',
  price: '价格',
  pricing: '价格',
  items: '配置项',
  meta: '扩展信息',
  configoption: '配置参数',
  kind: '类型',
  mode: '模式',
  target_label: '目标服务',
  target_service_id: '目标服务ID',
  product_id: '产品ID',
  product_name: '产品名称',
  billing_cycle: '周期',
  billingcycle: '周期',
  billingcycle_zh: '周期',
  period: '周期',
  remark: '备注',
  data_disk_size: '数据盘',
};

export function snapshotLabel(value: unknown) {
  const key = String(value || '').trim();
  if (!key) return '--';
  return SNAPSHOT_LABEL_MAP[key] || key;
}

export function flattenSnapshot(
  obj: unknown,
  valueLabelMap: Record<string, string> = {},
): { label: string; value: string }[] {
  const source = toRecord(obj);
  const result: { label: string; value: string }[] = [];

  for (const [key, val] of Object.entries(source)) {
    if (SNAPSHOT_DISPLAY_META_KEYS.has(key)) continue;
    if (
      [
        'unit_setup_fee',
        'unit_base_amount',
        'unit_total_amount',
        'unit_config_amount',
        '_schema_version',
        '_schema_type',
      ].includes(key)
    ) {
      continue;
    }
    if (val === null || val === undefined || val === '') continue;
    if (key === 'items' && Array.isArray(val)) {
      val.forEach((item, index) => {
        const record = toRecord(item);
        result.push({
          label: snapshotLabel(
            record.label || record.name || record.option_name || record.spec_key || `${key}.${index + 1}`,
          ),
          value: formatSnapshotItem(record),
        });
      });
      continue;
    }

    if (val && typeof val === 'object' && !Array.isArray(val)) {
      const nested = flattenSnapshot(val, valueLabelMap);
      nested.forEach((item) => result.push({ label: `${snapshotLabel(key)} / ${item.label}`, value: item.value }));
    } else {
      result.push({ label: snapshotLabel(key), value: formatSnapshotValue(valueLabelMap[key] || val, key) });
    }
  }

  return result;
}

export function configValueLabelMap(row: AnyRecord | null | undefined): Record<string, string> {
  const snapshot = toRecord(row?.config_pricing_snapshot);
  const items = Array.isArray(snapshot.items) ? snapshot.items : [];
  return items.reduce(
    (result, item) => {
      const record = toRecord(item);
      const field = String(record.field || '').trim();
      const label = String(
        record.value_label || record.suboption_name || record.option_name || record.value || '',
      ).trim();
      if (field && label) result[field] = label;
      return result;
    },
    {} as Record<string, string>,
  );
}

function formatSnapshotItem(record: AnyRecord) {
  const value = fieldValue(
    record.value_label ||
      record.option_value_label ||
      record.suboption_label ||
      record.value ||
      record.option_value ||
      record.suboption_name ||
      record.suboption_name_first ||
      record.option_name_first ||
      record.version ||
      record.label ||
      record.name,
  );
  const amount = record.amount ?? record.total_amount ?? record.price ?? record.pricing ?? record.fee;
  const setupFee = record.setup_fee ?? record.setupfee;
  const parts = [value];
  if (amount !== null && amount !== undefined && amount !== '') parts.push(`金额 ¥${formatMoney(amount)}`);
  if (setupFee !== null && setupFee !== undefined && setupFee !== '') parts.push(`初装费 ¥${formatMoney(setupFee)}`);
  return parts.join(' / ');
}

function formatSnapshotValue(value: unknown, key = ''): string {
  if (value === null || value === undefined || value === '') return '--';
  if (Array.isArray(value)) {
    return value
      .map((item, index) => {
        if (item && typeof item === 'object') return `${index + 1}. ${formatSnapshotItem(toRecord(item))}`;
        return fieldValue(item);
      })
      .join('；');
  }
  if (value && typeof value === 'object') {
    return Object.entries(toRecord(value))
      .filter(([, childValue]) => childValue !== null && childValue !== undefined && childValue !== '')
      .map(([childKey, childValue]) => `${snapshotLabel(childKey)}：${formatSnapshotValue(childValue, childKey)}`)
      .join('；');
  }
  const raw = String(value);
  if (BILLING_CYCLE_KEYS.has(key)) return formatBillingCycle(raw);
  if (['bw', 'in_bw', 'out_bw'].includes(key) && /^\d+(?:\.\d+)?$/.test(raw)) return `${raw} Mbps`;
  if (key === 'memory' && /^\d+(?:\.\d+)?$/.test(raw)) return `${raw} MB`;
  if (['ip_num', 'ipv6_num', 'quantity'].includes(key) && /^\d+(?:\.\d+)?$/.test(raw)) return `${raw} 个`;
  return raw;
}

export function resolveOrderTagTheme(status: unknown) {
  const value = Number(status);
  if (value === 1 || value === 3) return 'success';
  if (value === 0 || value === 2) return 'warning';
  if (value === 4) return 'default';
  return 'danger';
}

export function resolvePaymentStatusLabel(status: unknown) {
  return getStatusLabel(PAYMENT_STATUS_MAP, Number(status));
}

export function resolveAccountTransactionEventLabel(eventType: unknown) {
  return getStatusLabel(ACCOUNT_TRANSACTION_EVENT_MAP, String(eventType || ''));
}

export function resolveBalanceTheme(value: unknown) {
  return Number(value || 0) >= 0 ? 'success' : 'danger';
}

function compactParams(params: ClientFinanceListParams): ClientFinanceListParams {
  return Object.fromEntries(
    Object.entries(params)
      .map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value])
      .filter(([, value]) => value !== '' && value !== undefined && value !== null),
  ) as ClientFinanceListParams;
}

function resolveListPayload<T>(response: ApiEnvelope<PagedList<T>> | null | undefined) {
  const payload = response?.data;
  if (!payload || Array.isArray(payload)) {
    return { list: [] as T[], total: 0 };
  }
  const parsedTotal = Number(payload.total);
  return {
    list: Array.isArray(payload.list) ? payload.list : ([] as T[]),
    total: Number.isFinite(parsedTotal) ? parsedTotal : 0,
  };
}

// 财务记录列表统一引擎：筛选状态、URL 同步、快捷标签、日期范围联动与竞态防护的唯一实现。
// 订单/账单/充值三个域 composable 仅注入 fetcher 与错误文案，页面不再各自维护列表逻辑。
export function useRecordList<T>(options: RecordListOptions<T>) {
  const { fetcher, errorMessage } = options;
  const defaultPageSize = Number(options.pageSize) > 0 ? Number(options.pageSize) : 10;
  const route = useRoute();
  const router = useRouter();
  const loading = ref(false);
  const list = shallowRef<T[]>([]);
  const total = ref(0);
  const loadError = ref(false);
  const loadErrorText = ref('');
  // 请求序号防竞态：快速切换筛选时，晚返回的旧响应不会覆盖新结果
  let requestSeq = 0;
  const filters = reactive({
    page: 1,
    page_size: defaultPageSize,
    keyword: '',
    status: '' as string | number,
    type: '',
    start_date: '',
    end_date: '',
    quickFilter: '',
  });

  const hasRows = computed(() => list.value.length > 0);

  // 筛选与页码同步到 URL，刷新/回退不丢状态
  restoreFiltersFromQuery();

  function restoreFiltersFromQuery() {
    const query = route.query;
    const page = Number(query.page);
    if (Number.isFinite(page) && page > 0) filters.page = page;
    const pageSize = Number(query.page_size);
    if (Number.isFinite(pageSize) && PAGE_SIZE_OPTIONS.includes(pageSize)) filters.page_size = pageSize;
    if (typeof query.keyword === 'string') filters.keyword = query.keyword;
    if (typeof query.status === 'string' && query.status !== '') {
      const status = Number(query.status);
      if (Number.isFinite(status)) filters.status = status;
    }
    if (typeof query.type === 'string') filters.type = query.type;
    if (typeof query.quick === 'string' && query.quick) {
      // 快捷标签与显式状态冲突时（标签 pending 但 status 已被手动改为其它值），以显式状态为准、熄灭标签
      const pendingConflict = query.quick === 'pending' && filters.status !== '' && filters.status !== 0;
      filters.quickFilter = pendingConflict ? '' : query.quick;
      if (!pendingConflict && query.quick === 'pending' && filters.status === '') filters.status = 0;
      // 显式日期优先：手选区间命中快捷折算时 URL 同时带 quick 与日期，跨天后再按 quick 重算会漂移，
      // 因此只要 URL 有合法日期就采纳之，标签选中态由区间匹配回写
      const hasExplicitDates =
        typeof query.start_date === 'string' &&
        QUERY_DATE_PATTERN.test(query.start_date) &&
        typeof query.end_date === 'string' &&
        QUERY_DATE_PATTERN.test(query.end_date);
      if (hasExplicitDates && typeof query.start_date === 'string' && typeof query.end_date === 'string') {
        filters.start_date = query.start_date;
        filters.end_date = query.end_date;
        if (filters.quickFilter) {
          filters.quickFilter = matchQuickFilterByRange(filters.start_date, filters.end_date);
        }
      } else {
        const range = resolveQuickDateRange(query.quick);
        filters.start_date = range.start_date || '';
        filters.end_date = range.end_date || '';
      }
    } else {
      if (typeof query.start_date === 'string' && QUERY_DATE_PATTERN.test(query.start_date)) {
        filters.start_date = query.start_date;
      }
      if (typeof query.end_date === 'string' && QUERY_DATE_PATTERN.test(query.end_date)) {
        filters.end_date = query.end_date;
      }
    }
  }

  function syncFiltersToQuery() {
    const next: Record<string, string> = {};
    if (filters.page > 1) next.page = String(filters.page);
    if (filters.page_size !== defaultPageSize) next.page_size = String(filters.page_size);
    if (filters.keyword?.trim()) next.keyword = filters.keyword.trim();
    if (filters.status !== '' && filters.status !== null && filters.status !== undefined)
      next.status = String(filters.status);
    if (filters.type) next.type = filters.type;
    if (filters.quickFilter) {
      next.quick = filters.quickFilter;
    }
    // 日期区间独立持久化：quick 仅表达标签语义，日期始终以显式值入库（写入侧复用读取侧的格式校验）
    if (filters.start_date && QUERY_DATE_PATTERN.test(filters.start_date)) next.start_date = filters.start_date;
    if (filters.end_date && QUERY_DATE_PATTERN.test(filters.end_date)) next.end_date = filters.end_date;

    const current = route.query;
    const currentKeys = Object.keys(current);
    const changed =
      currentKeys.length !== Object.keys(next).length ||
      currentKeys.some((key) => String(current[key] ?? '') !== next[key]);
    if (!changed) return;
    void router.replace({ query: next });
  }

  function buildParams(): ClientFinanceListParams {
    return compactParams({
      page: filters.page,
      page_size: filters.page_size,
      keyword: filters.keyword,
      status: filters.status,
      type: filters.type,
      start_date: filters.start_date,
      end_date: filters.end_date,
    });
  }

  async function loadList() {
    const seq = ++requestSeq;
    loading.value = true;
    loadError.value = false;
    try {
      const response = await fetcher(buildParams());
      if (seq !== requestSeq) return;
      const payload = resolveListPayload<T>(response);
      list.value = payload.list;
      total.value = payload.total;
      syncFiltersToQuery();
    } catch (error: unknown) {
      if (seq !== requestSeq) return;
      loadError.value = true;
      loadErrorText.value = getErrorMessage(error, errorMessage);
      list.value = [];
      total.value = 0;
      MessagePlugin.error(loadErrorText.value);
    } finally {
      if (seq === requestSeq) loading.value = false;
    }
  }

  function handleSearch() {
    // 手动切换状态时，若与"待支付"标签语义冲突则熄灭标签，避免 URL 持久化矛盾组合
    if (filters.quickFilter === 'pending' && filters.status !== '' && filters.status !== 0) {
      filters.quickFilter = '';
    }
    filters.page = 1;
    void loadList();
  }

  function handlePageSizeChange() {
    filters.page = 1;
    void loadList();
  }

  function applyQuickFilter(key: string) {
    filters.quickFilter = key;
    filters.page = 1;
    filters.status = '';
    filters.type = '';
    filters.start_date = '';
    filters.end_date = '';

    if (key === 'pending') {
      filters.status = 0;
    }
    const range = resolveQuickDateRange(key);
    filters.start_date = range.start_date || '';
    filters.end_date = range.end_date || '';
    void loadList();
  }

  // 日期范围选择器与快捷标签双向联动：
  // 快捷标签选中时日期由 applyQuickFilter 物化到 filters，选择器直接展示；
  // 手动改日期时若区间与某快捷标签折算结果一致则回写选中态，否则视为自定义区间
  const dateRange = computed<string[]>({
    get() {
      if (filters.start_date && filters.end_date) return [filters.start_date, filters.end_date];
      return [];
    },
    set(value) {
      const range = Array.isArray(value) ? value : [];
      const prevQuick = filters.quickFilter;
      // 选择器正常产出双值或空数组；清空或半开区间按空处理
      const [start, end] = range.length === 2 ? range : [];
      filters.start_date = start || '';
      filters.end_date = end || '';
      filters.quickFilter = matchQuickFilterByRange(filters.start_date, filters.end_date);
      // 由"待支付"标签带入的 status=0 随标签熄灭复位，避免显式状态残留
      if (prevQuick === 'pending' && filters.quickFilter !== 'pending' && filters.status === 0) {
        filters.status = '';
      }
      filters.page = 1;
      void loadList();
    },
  });

  onMounted(() => {
    void loadList();
  });

  return {
    loading,
    list,
    total,
    filters,
    hasRows,
    loadError,
    loadErrorText,
    loadList,
    handleSearch,
    handlePageSizeChange,
    applyQuickFilter,
    dateRange,
  };
}
