import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { reactive, ref } from 'vue';

import clientApi from '@/api/client';
import type {
  ConsoleSelectOption,
  FinanceLedgerRecord,
  FinanceLedgerSummary,
  MonitorChartRecord,
  MonitorRangePayload,
  NatForwardingRecord,
  PagedList,
  ServiceOperationLogRecord,
  ServiceOperationLogSummary,
} from '@/types/client';

import { resolveErrorMessage } from './useConsoleCore';

type MonitorRangePreset = '3h' | '24h' | '7d' | '30d';

function createEmptyLogsState() {
  return {
    loading: false,
    list: [] as ServiceOperationLogRecord[],
    total: 0,
    page: 1,
    page_size: 10,
    category: '',
    keyword: '',
    summary: {
      total: 0,
      today_total: 0,
      latest_created_at: '',
      service_name: '',
    } as ServiceOperationLogSummary,
  };
}

function createEmptyFinanceState() {
  return {
    loading: false,
    list: [] as FinanceLedgerRecord[],
    total: 0,
    page: 1,
    page_size: 10,
    summary: {
      total_in: '0.00',
      total_out: '0.00',
      refund_in: '0.00',
      total_count: 0,
    } as FinanceLedgerSummary,
  };
}

export interface UseConsoleTabsOptions {
  serviceId: { value: number };
}

export function useConsoleTabs(options: UseConsoleTabsOptions) {
  const { serviceId } = options;

  const monitorState = reactive({
    loading: false,
    range: '3h' as MonitorRangePreset,
    window: null as MonitorRangePayload | null,
    supported: true,
    message: '',
    error: '',
    charts: [] as MonitorChartRecord[],
  });

  const natState = reactive({
    loading: false,
    submitting: false,
    supported: true,
    message: '',
    error: '',
    can_create: false,
    protocols: [] as ConsoleSelectOption[],
    list: [] as NatForwardingRecord[],
  });
  const natVisible = ref(false);
  const natForm = reactive({ name: '', ext_port: '', int_port: '', protocol: '' });

  const logsState = reactive(createEmptyLogsState());
  const financeState = reactive(createEmptyFinanceState());
  const loadedTabs = reactive({ monitor: false, security: false, nat: false, logs: false, finance: false });

  // 竞态防护：快速切换监控范围/翻页时，慢的旧响应不得覆盖新数据（对齐 useRecords.ts 的 requestSeq 模式）
  let monitorRequestSeq = 0;
  let natRequestSeq = 0;
  let logsRequestSeq = 0;
  let financeRequestSeq = 0;

  function resetLazyTabs() {
    loadedTabs.monitor = false;
    loadedTabs.security = false;
    loadedTabs.nat = false;
    loadedTabs.logs = false;
    loadedTabs.finance = false;
  }

  async function loadMonitor(force = false) {
    const requestSeq = ++monitorRequestSeq;
    monitorState.loading = true;
    monitorState.error = '';
    try {
      const params: Record<string, unknown> = { range: monitorState.range };
      if (force) params.fresh = 1;
      const res = await clientApi.serviceMonitorBatch(serviceId.value, params, { timeout: 12000 });
      if (requestSeq !== monitorRequestSeq) return false;
      const payload = res.data || {};
      monitorState.supported = payload.supported !== false;
      monitorState.message = String(payload.message || '');
      monitorState.window = payload.range || null;
      monitorState.charts = Array.isArray(payload.charts) ? payload.charts : [];
      return true;
    } catch (error: unknown) {
      if (requestSeq !== monitorRequestSeq) return false;
      monitorState.error = resolveErrorMessage(error, '加载监控数据失败');
      return false;
    } finally {
      if (requestSeq === monitorRequestSeq) monitorState.loading = false;
    }
  }

  async function loadNatForwardings() {
    const requestSeq = ++natRequestSeq;
    natState.loading = true;
    natState.error = '';
    try {
      const res = await clientApi.serviceNatForwardings(serviceId.value);
      if (requestSeq !== natRequestSeq) return false;
      const payload = res.data || {};
      natState.supported = payload.supported !== false;
      natState.message = String(payload.message || '');
      natState.error = String(payload.error || '');
      natState.can_create = Boolean(payload.can_create);
      natState.protocols = Array.isArray(payload.protocols) ? payload.protocols : [];
      natState.list = Array.isArray(payload.list) ? payload.list : [];
      return true;
    } catch (error: unknown) {
      if (requestSeq !== natRequestSeq) return false;
      natState.error = resolveErrorMessage(error, '加载 NAT 转发失败');
      return false;
    } finally {
      if (requestSeq === natRequestSeq) natState.loading = false;
    }
  }

  function openNatForwardingDialog() {
    natForm.name = '';
    natForm.ext_port = '';
    natForm.int_port = '';
    natForm.protocol = String(natState.protocols[0]?.value || '');
    natVisible.value = true;
  }

  async function submitNatForwarding() {
    const name = natForm.name.trim();
    const externalPort = natForm.ext_port.trim();
    const internalPort = natForm.int_port.trim();
    const protocol = natForm.protocol.trim();

    if (!name || !internalPort || !protocol) {
      MessagePlugin.warning('请填写完整的端口转发信息');
      return;
    }

    if (!isValidPort(internalPort) || (externalPort !== '' && !isValidPort(externalPort))) {
      MessagePlugin.warning('端口范围应为 1 到 65535');
      return;
    }

    natState.submitting = true;
    try {
      const response = await clientApi.createNatForwarding(serviceId.value, {
        name,
        ext_port: externalPort || undefined,
        int_port: internalPort,
        protocol,
      });
      natVisible.value = false;
      const message = String((response.data as { message?: unknown } | undefined)?.message || '').trim();
      MessagePlugin.success(message || '端口转发创建成功');
      await loadNatForwardings();
    } catch (error: unknown) {
      MessagePlugin.error(resolveErrorMessage(error, '创建端口转发失败'));
    } finally {
      natState.submitting = false;
    }
  }

  function deleteNatForwarding(forwarding: NatForwardingRecord) {
    const forwardingId = Number(forwarding.id || 0);
    if (!forwardingId || forwarding.can_delete === false) return;

    const dialog = DialogPlugin.confirm({
      header: '删除端口转发',
      body: `确定删除端口转发“${String(forwarding.name || forwardingId)}”吗？删除后相关流量将不再转发，且不可恢复。`,
      theme: 'danger',
      confirmBtn: { content: '删除', theme: 'danger' },
      cancelBtn: '取消',
      onConfirm: async () => {
        dialog.setConfirmLoading(true);
        natState.submitting = true;
        try {
          const response = await clientApi.deleteNatForwarding(serviceId.value, forwardingId);
          const message = String((response.data as { message?: unknown } | undefined)?.message || '').trim();
          MessagePlugin.success(message || '端口转发删除成功');
          await loadNatForwardings();
          dialog.hide();
        } catch (error: unknown) {
          MessagePlugin.error(resolveErrorMessage(error, '删除端口转发失败'));
        } finally {
          natState.submitting = false;
          dialog.setConfirmLoading(false);
        }
      },
    });
  }

  async function loadLogs() {
    const requestSeq = ++logsRequestSeq;
    logsState.loading = true;
    try {
      const params: Record<string, unknown> = { page: logsState.page, page_size: logsState.page_size };
      if (logsState.category) params.category = logsState.category;
      if (logsState.keyword.trim()) params.keyword = logsState.keyword.trim();
      const res = await clientApi.serviceOperationLogs(serviceId.value, params);
      if (requestSeq !== logsRequestSeq) return false;
      const payload: PagedList<ServiceOperationLogRecord> & { summary?: ServiceOperationLogSummary } = res.data || {
        list: [],
        total: 0,
      };
      logsState.list = Array.isArray(payload.list) ? payload.list : [];
      logsState.total = Number(payload.total || 0);
      logsState.summary = { ...logsState.summary, ...(payload.summary || {}) };
      return true;
    } catch (error: unknown) {
      if (requestSeq !== logsRequestSeq) return false;
      MessagePlugin.error(resolveErrorMessage(error, '加载操作日志失败'));
      return false;
    } finally {
      if (requestSeq === logsRequestSeq) logsState.loading = false;
    }
  }

  async function loadFinanceLogs() {
    const requestSeq = ++financeRequestSeq;
    financeState.loading = true;
    try {
      const params: Record<string, unknown> = {
        page: financeState.page,
        page_size: financeState.page_size,
        tab: 'invoices',
        service_id: serviceId.value,
      };
      const [listRes, summaryRes] = await Promise.all([
        clientApi.financeLedger(params),
        clientApi.financeLedgerSummary({ tab: 'invoices', service_id: serviceId.value }),
      ]);
      if (requestSeq !== financeRequestSeq) return false;
      const listPayload: PagedList<FinanceLedgerRecord> = listRes.data || { list: [], total: 0 };
      const summaryPayload = summaryRes.data || {};
      financeState.list = Array.isArray(listPayload.list) ? listPayload.list : [];
      financeState.total = Number(listPayload.total || 0);
      financeState.summary = { ...financeState.summary, ...summaryPayload };
      return true;
    } catch (error: unknown) {
      if (requestSeq !== financeRequestSeq) return false;
      MessagePlugin.error(resolveErrorMessage(error, '加载财务日志失败'));
      return false;
    } finally {
      if (requestSeq === financeRequestSeq) financeState.loading = false;
    }
  }

  return {
    monitorState,
    natState,
    natVisible,
    natForm,
    logsState,
    financeState,
    loadedTabs,
    resetLazyTabs,
    loadMonitor,
    loadNatForwardings,
    openNatForwardingDialog,
    submitNatForwarding,
    deleteNatForwarding,
    loadLogs,
    loadFinanceLogs,
  };
}

function isValidPort(value: string): boolean {
  const port = Number(value);

  return Number.isInteger(port) && port >= 1 && port <= 65535;
}
