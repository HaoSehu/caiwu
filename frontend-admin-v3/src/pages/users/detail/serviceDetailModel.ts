/**
 * 用户详情页「产品/服务」领域模型。
 *
 * 这些函数只做纯数据整形，不持有响应式状态、不发请求，从 index.vue 抽出以便单测与复用。
 */
type Row = Record<string, any>;
type LockedPricingForm = Record<
  string,
  { enabled: boolean; base_amount?: number | string | null; manual_amount?: number | null | '' }
>;

/** 服务详情缺省结构，保证下游访问 product/order/invoice 等嵌套字段时不会取到 undefined。 */
export function normalizeServiceDetail(payload: Row = {}) {
  const empty = {
    id: 0,
    name: '',
    domain: '',
    status: 0,
    status_tone: 'default',
    billing_cycle: '',
    billing_cycle_label: '',
    amount: 0,
    expires_at: '',
    created_at: '',
    product: {},
    order: {},
    invoice: {},
    upstream: {},
    runtime: {},
    connection: {},
    actions: { refresh: true, password_reset: false, available: [] as string[] },
    specs: [] as Row[],
    custom_service_name: '',
  };
  return {
    ...empty,
    ...payload,
    product: { ...empty.product, ...(payload.product || {}) },
    order: { ...empty.order, ...(payload.order || {}) },
    invoice: { ...empty.invoice, ...(payload.invoice || {}) },
    upstream: { ...empty.upstream, ...(payload.upstream || {}) },
    runtime: { ...empty.runtime, ...(payload.runtime || {}) },
    connection: { ...empty.connection, ...(payload.connection || {}) },
    actions: { ...empty.actions, ...(payload.actions || {}) },
    specs: Array.isArray(payload.specs) ? payload.specs : [],
  };
}

/** 增量更新：嵌套对象逐层合并，避免局部刷新把其它字段清空。 */
export function mergeServiceDetail(current: Row = {}, patch: Row = {}) {
  return normalizeServiceDetail({
    ...current,
    ...patch,
    product: { ...(current.product || {}), ...(patch.product || {}) },
    order: { ...(current.order || {}), ...(patch.order || {}) },
    invoice: { ...(current.invoice || {}), ...(patch.invoice || {}) },
    upstream: { ...(current.upstream || {}), ...(patch.upstream || {}) },
    runtime: { ...(current.runtime || {}), ...(patch.runtime || {}) },
    connection: { ...(current.connection || {}), ...(patch.connection || {}) },
    actions: { ...(current.actions || {}), ...(patch.actions || {}) },
  });
}

/**
 * 计算列表项应更新成的新值，由调用方决定是否写回响应式列表。
 * 返回 null 表示该服务不在当前页列表中。
 */
export function buildServiceListItem(list: Row[], detail: Row = {}): { index: number; item: Row } | null {
  const normalized = normalizeServiceDetail(detail);
  const index = list.findIndex((item) => Number(item.id) === Number(normalized.id));
  if (index < 0) return null;
  const base = list[index];
  return {
    index,
    item: {
      ...base,
      name: normalized.name,
      domain: normalized.domain,
      status: normalized.status,
      status_tone: normalized.status_tone,
      amount: normalized.amount,
      created_at: normalized.created_at,
      expires_at: normalized.expires_at,
      billing_cycle_label: normalized.billing_cycle_label,
      upstream: {
        ...(base.upstream || {}),
        ...(normalized.upstream || {}),
        dedicated_ip: normalized.connection?.dedicated_ip || normalized.upstream?.dedicated_ip || '',
      },
      product: {
        ...(base.product || {}),
        ...(normalized.product || {}),
      },
      custom_service_name: normalized.custom_service_name || '',
    },
  };
}

/** 由后端的 renew_pricing_cycles 生成计费周期表单初值。 */
export function createLockedPricingForm(detail: Row = {}): LockedPricingForm {
  const cycles = Array.isArray(detail.renew_pricing_cycles) ? detail.renew_pricing_cycles : [];
  return cycles.reduce((result, item) => {
    const cycle = String(item?.billing_cycle || '').trim();
    if (!cycle) return result;
    result[cycle] = {
      enabled: Boolean(item?.enabled),
      base_amount: item?.base_amount || null,
      manual_amount: item?.manual_amount || '',
    };
    return result;
  }, {} as LockedPricingForm);
}
