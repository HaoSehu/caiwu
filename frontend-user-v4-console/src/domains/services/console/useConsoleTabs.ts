import { reactive } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';

import clientApi from '@/api/client';
import { resolveErrorMessage } from './useConsoleCore';

type AnyRecord = Record<string, any>;

function createEmptyLogsState() {
  return {
    loading: false,
    list: [] as AnyRecord[],
    total: 0,
    page: 1,
    page_size: 10,
    category: '',
    keyword: '',
    summary: { total: 0, today_total: 0, latest_created_at: '', service_name: '' },
  };
}

function createEmptyFinanceState() {
  return {
    loading: false,
    list: [] as AnyRecord[],
    total: 0,
    page: 1,
    page_size: 10,
    summary: {
      total_in: '0.00',
      total_out: '0.00',
      refund_in: '0.00',
      total_count: 0,
    } as AnyRecord,
  };
}

export interface UseConsoleTabsOptions {
  serviceId: { value: number };
}

export function useConsoleTabs(options: UseConsoleTabsOptions) {
  const { serviceId } = options;

  const monitorState = reactive({
    loading: false,
    range: '3h',
    window: null as AnyRecord | null,
    supported: true,
    message: '',
    error: '',
    charts: [] as AnyRecord[],
  });

  const natState = reactive({
    loading: false,
    supported: true,
    message: '',
    error: '',
    can_create: false,
    protocols: [] as AnyRecord[],
    list: [] as AnyRecord[],
  });

  const logsState = reactive(createEmptyLogsState());
  const financeState = reactive(createEmptyFinanceState());
  const loadedTabs = reactive({ monitor: false, security: false, nat: false, logs: false, finance: false });

  function resetLazyTabs() {
    loadedTabs.monitor = false;
    loadedTabs.security = false;
    loadedTabs.nat = false;
    loadedTabs.logs = false;
    loadedTabs.finance = false;
  }

  async function loadMonitor(force = false) {
    monitorState.loading = true;
    monitorState.error = '';
    try {
      const params: AnyRecord = { range: monitorState.range };
      if (force) params.fresh = 1;
      const res = await clientApi.serviceMonitorBatch(serviceId.value, params, { timeout: 12000 });
      const payload = (res as AnyRecord).data || {};
      monitorState.supported = payload.supported !== false;
      monitorState.message = String(payload.message || '');
      monitorState.window = payload.range || null;
      monitorState.charts = Array.isArray(payload.charts) ? payload.charts : [];
    } catch (error: any) {
      monitorState.error = resolveErrorMessage(error, '加载监控数据失败');
    } finally {
      monitorState.loading = false;
    }
  }

  async function loadNatForwardings() {
    natState.loading = true;
    try {
      const res = await clientApi.serviceNatForwardings(serviceId.value);
      const payload = (res as AnyRecord).data || {};
      natState.supported = payload.supported !== false;
      natState.message = String(payload.message || '');
      natState.error = String(payload.error || '');
      natState.can_create = Boolean(payload.can_create);
      natState.protocols = Array.isArray(payload.protocols) ? payload.protocols : [];
      natState.list = Array.isArray(payload.list) ? payload.list : [];
    } catch (error: any) {
      natState.error = resolveErrorMessage(error, '加载 NAT 转发失败');
    } finally {
      natState.loading = false;
    }
  }

  async function loadLogs() {
    logsState.loading = true;
    try {
      const params: AnyRecord = { page: logsState.page, page_size: logsState.page_size };
      if (logsState.category) params.category = logsState.category;
      if (logsState.keyword.trim()) params.keyword = logsState.keyword.trim();
      const res = await clientApi.serviceOperationLogs(serviceId.value, params);
      const payload = (res as AnyRecord).data || {};
      logsState.list = Array.isArray(payload.list) ? payload.list : [];
      logsState.total = Number(payload.total || 0);
      logsState.summary = { ...logsState.summary, ...(payload.summary || {}) };
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '加载操作日志失败'));
    } finally {
      logsState.loading = false;
    }
  }

  async function loadFinanceLogs() {
    financeState.loading = true;
    try {
      const params: AnyRecord = {
        page: financeState.page,
        page_size: financeState.page_size,
        tab: 'invoices',
        service_id: serviceId.value,
      };
      const [listRes, summaryRes] = await Promise.all([
        clientApi.financeLedger(params),
        clientApi.financeLedgerSummary({ tab: 'invoices', service_id: serviceId.value }),
      ]);
      const listPayload = (listRes as AnyRecord).data || {};
      const summaryPayload = (summaryRes as AnyRecord).data || {};
      financeState.list = Array.isArray(listPayload.list) ? listPayload.list : [];
      financeState.total = Number(listPayload.total || 0);
      financeState.summary = { ...financeState.summary, ...summaryPayload };
    } catch (error: any) {
      MessagePlugin.error(resolveErrorMessage(error, '加载财务日志失败'));
    } finally {
      financeState.loading = false;
    }
  }

  return {
    monitorState,
    natState,
    logsState,
    financeState,
    loadedTabs,
    resetLazyTabs,
    loadMonitor,
    loadNatForwardings,
    loadLogs,
    loadFinanceLogs,
  };
}