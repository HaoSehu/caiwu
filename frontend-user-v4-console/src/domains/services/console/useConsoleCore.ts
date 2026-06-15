import type { Ref } from 'vue';

type AnyRecord = Record<string, any>;

export const DEFAULT_TAB = 'overview';
export const CLOUD_TABS = ['overview', 'monitor', 'security', 'power', 'logs', 'finance', 'vnc'];
export const NAT_TABS = ['overview', 'monitor', 'security', 'nat', 'power', 'logs', 'finance', 'vnc'];
export const VNC_CREDENTIAL_STORAGE_PREFIX = 'caiwu:vnc-credentials:';

export function emptyDetail(): AnyRecord {
  return {
    id: 0,
    name: '',
    custom_service_name: '',
    combined_display_name: '',
    product_display_name: '',
    remark: '',
    domain: '',
    status: 0,
    status_label: '',
    status_tone: 'info',
    billing_cycle: '',
    billing_cycle_label: '',
    amount: '0.00',
    expires_at: '',
    created_at: '',
    auto_renew: 0,
    console_mode: '',
    can_manage: false,
    machine_category: { key: '', label: '' },
    product: { id: 0, name: '', type: '', type_label: '', display_name: '', catalog_type: '' },
    order: { id: 0, order_no: '', status: 0, status_label: '' },
    invoice: { id: 0, invoice_no: '', status: 0, status_label: '' },
    upstream: { provider: '', host_id: 0, status: '', status_label: '', remote_error: '', os: '', dedicated_ip: '' },
    runtime: { power_state: '', power_label: '', description: '' },
    traffic: {
      usage: '0',
      limit: 0,
      remaining: '',
      usage_label: '0G',
      limit_label: '不限',
      remaining_label: '不限',
      usage_percent: null as number | null,
      limited: false,
      button_text: '购买流量包',
      purchase_enabled: false,
    },
    connection: {
      hostname: '',
      username: '',
      password: '',
      has_password: false,
      port: 0,
      dedicated_ip: '',
      internal_ip: '',
      assigned_ips: [] as string[],
      nat_remote_address: '',
      nat_remote_host: '',
      nat_remote_port: 0,
    },
    specs: [] as AnyRecord[],
    actions: {
      refresh: true,
      power: false,
      module_status: false,
      password_reset: false,
      reinstall: false,
      traffic_package: false,
      available: [] as string[],
    },
  };
}

export function normalizeConsoleDetail(payload: AnyRecord = {}): AnyRecord {
  const base = emptyDetail();
  return {
    ...base,
    ...payload,
    machine_category: normalizeMachineCategory(payload.machine_category),
    product: { ...base.product, ...(payload.product || {}) },
    order: { ...base.order, ...(payload.order || {}) },
    invoice: { ...base.invoice, ...(payload.invoice || {}) },
    upstream: { ...base.upstream, ...(payload.upstream || {}) },
    runtime: { ...base.runtime, ...(payload.runtime || {}) },
    traffic: { ...base.traffic, ...(payload.traffic || {}) },
    connection: { ...base.connection, ...(payload.connection || {}) },
    actions: { ...base.actions, ...(payload.actions || {}) },
    specs: Array.isArray(payload.specs) ? payload.specs : [],
  };
}

export function mergeConsoleDetail(current: AnyRecord = {}, patch: AnyRecord = {}): AnyRecord {
  return normalizeConsoleDetail({
    ...current,
    ...patch,
    product: { ...(current.product || {}), ...(patch.product || {}) },
    order: { ...(current.order || {}), ...(patch.order || {}) },
    invoice: { ...(current.invoice || {}), ...(patch.invoice || {}) },
    upstream: { ...(current.upstream || {}), ...(patch.upstream || {}) },
    runtime: { ...(current.runtime || {}), ...(patch.runtime || {}) },
    traffic: { ...(current.traffic || {}), ...(patch.traffic || {}) },
    connection: { ...(current.connection || {}), ...(patch.connection || {}) },
    actions: { ...(current.actions || {}), ...(patch.actions || {}) },
  });
}

export function normalizeMachineCategory(value: unknown): { key: string; label: string } {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const source = value as AnyRecord;
    return { key: String(source.key || '').trim(), label: String(source.label || '').trim() };
  }
  return { key: '', label: String(value || '').trim() };
}

export function normalizeToken(value: unknown): string {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '');
}

export function resolveErrorMessage(error: any, fallback: string): string {
  return String(error?.message || '').trim() || fallback;
}

export function isNatConsole(detail: AnyRecord): boolean {
  const type = normalizeToken(detail?.product?.catalog_type || detail?.product?.type || '');
  return ['nat', 'clouddesktop', 'cloudpc', 'natconsole'].includes(type) || String(detail?.console_mode || '').toLowerCase() === 'nat';
}

export function findSpecValue(detail: Ref<AnyRecord>, aliases: string[], fallback = '--'): string {
  const specs = Array.isArray(detail.value.specs) ? detail.value.specs : [];
  for (const alias of aliases) {
    const token = normalizeToken(alias);
    const matched = specs.find((spec: AnyRecord) => {
      const keys = [spec.key, spec.label].map(normalizeToken);
      return keys.some((item) => item.includes(token) || token.includes(item));
    });
    const value = String(matched?.value || '').trim();
    if (value) return value;
  }
  return fallback;
}

export function copyText(value: unknown): Promise<void> {
  const text = String(value || '').trim();
  if (!text || text === '--') return Promise.resolve();
  return navigator.clipboard.writeText(text);
}