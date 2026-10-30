import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import { resolveServiceStatusLabel, resolveTdesignStatusTheme } from '@/domains/services/useServiceCenter';
import { formatMoney } from '@/utils/format';

import { copyText, DEFAULT_TAB, findSpecValue, isNatConsole, normalizeConsoleDetail } from './console/useConsoleCore';
import { useConsoleDetail } from './console/useConsoleDetail';
import { useConsoleDialogs } from './console/useConsoleDialogs';
import { useConsoleAutoRenew, useConsolePower } from './console/useConsolePower';
import { useConsoleReinstall } from './console/useConsoleReinstall';
import { useConsoleRenew } from './console/useConsoleRenew';
import { useConsoleSecurity } from './console/useConsoleSecurity';
import { useConsoleTabs } from './console/useConsoleTabs';
import { useConsoleTrafficPackages } from './console/useConsoleTrafficPackages';
import { useConsoleVnc } from './console/useConsoleVnc';

export function useServiceConsole() {
  const router = useRouter();
  const showPassword = ref(true);

  // 服务详情核心状态
  const detailComposable = useConsoleDetail();
  const {
    detail,
    detailLoading,
    detailError,
    statusSyncing,
    powerLoading,
    reinstallLoading,
    vncLoading,
    passwordLoading,
    autoRenewLoading,
    activeTab,
    operationStatus,
    serviceId,
    availableTabs,
    canManageConsole,
    canSyncStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
    setOperationStatus,
    clearOperationStatus,
    mergeDetail,
    loadRemoteStatus,
    refreshHostStatus,
    bootstrap,
  } = detailComposable;
  const route = detailComposable.route;

  // 电源管理
  const { handlePowerAction } = useConsolePower({
    serviceId,
    detail,
    powerLoading,
    setOperationStatus,
    clearOperationStatus,
    loadRemoteStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
  });

  // 自动续费开关
  const { handleToggleAutoRenew } = useConsoleAutoRenew({
    serviceId,
    detail,
    autoRenewLoading,
    mergeDetail,
  });

  // 续费弹窗
  const {
    renewVisible,
    renewLoading,
    renewSubmitting,
    renewData,
    renewForm,
    renewAmount,
    renewCoupons,
    renewCouponBinding,
    openRenewDialog,
    handleRenewCycleChange,
    handleRenewCouponChange,
    submitRenew,
  } = useConsoleRenew({ serviceId });

  const {
    trafficVisible,
    trafficLoading,
    trafficQuoting,
    trafficSubmitting,
    trafficData,
    trafficQuote,
    trafficForm,
    trafficPackages,
    selectedTrafficPackage,
    trafficPayableAmount,
    openTrafficPackageDialog,
    handleTrafficPackageChange,
    submitTrafficPackageOrder,
  } = useConsoleTrafficPackages({ serviceId });

  // 安全组
  const {
    securityState,
    activeSecurityGroup,
    groupVisible,
    groupForm,
    ruleVisible,
    ruleForm,
    isPortDisabled,
    isAllPortProtocol,
    onProtocolChange,
    loadSecurityGroups,
    loadSecurityGroupRules,
    selectSecurityGroup,
    openSecurityGroupDialog,
    openSecurityRuleDialog,
    submitSecurityGroup,
    applySecurityGroup,
    deleteSecurityGroup,
    submitSecurityRule,
    deleteSecurityRule,
  } = useConsoleSecurity({ serviceId });

  // 重装系统弹窗
  const {
    reinstallVisible,
    reinstallState,
    reinstallGroupedOptions,
    currentReinstallOptions,
    openReinstallDialog,
    handleReinstallGroupChange,
    submitReinstall,
  } = useConsoleReinstall({
    serviceId,
    reinstallLoading,
    setOperationStatus,
    loadRemoteStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
    normalizeDetail: normalizeConsoleDetail,
    mergeDetail,
  });

  // VNC 远程
  const { vncUrl, handleOpenVnc } = useConsoleVnc({
    serviceId,
    vncLoading,
    activeTab,
  });

  // 简易弹窗（名称、备注、密码）
  const {
    nameVisible,
    nameSubmitting,
    nameForm,
    openNameDialog,
    submitName,
    remarkVisible,
    remarkSubmitting,
    remarkForm,
    openRemarkDialog,
    submitRemark,
    passwordVisible,
    passwordForm,
    openPasswordDialog,
    generateStrongPassword,
    submitResetPassword,
  } = useConsoleDialogs({
    serviceId,
    detail,
    passwordLoading,
    setOperationStatus,
    loadRemoteStatus,
    clearStatusSyncTimer,
    scheduleStatusSync,
    mergeDetail,
  });

  // 各标签页数据加载
  const {
    monitorState,
    natState,
    logsState,
    financeState,
    loadedTabs,
    resetLazyTabs,
    loadMonitor,
    loadNatForwardings,
    natVisible,
    natForm,
    openNatForwardingDialog,
    submitNatForwarding,
    deleteNatForwarding,
    loadLogs,
    loadFinanceLogs,
    handleLogsPageSizeChange,
    handleFinancePageSizeChange,
  } = useConsoleTabs({ serviceId });

  // 计算属性：同步状态处理
  async function handleSyncStatus() {
    statusSyncing.value = true;
    try {
      await refreshHostStatus();
      MessagePlugin.success(detail.value.actions?.module_status ? '实例状态已同步' : '实例状态已刷新');
    } catch (error: unknown) {
      const runtimeError = error as { message?: string };
      MessagePlugin.error(String(runtimeError?.message || '').trim() || '同步实例状态失败');
    } finally {
      statusSyncing.value = false;
    }
  }

  // 计算属性：详情派生数据
  const serviceRegion = computed(
    () =>
      String(detail.value.machine_category?.label || '').trim() ||
      findSpecValue(detail, ['区域', '地区', '机房', 'region'], '--'),
  );
  const serviceOs = computed(
    () => String(detail.value.upstream?.os || '').trim() || findSpecValue(detail, ['操作系统', 'os'], '--'),
  );
  const primaryConnectionLabel = computed(() => (isNatConsole(detail.value) ? '远程地址' : '公网 IP'));
  const publicIpValues = computed(() => {
    const connection = detail.value.connection || {};

    return normalizeConnectionValues([
      connection.dedicated_ip,
      detail.value.upstream?.dedicated_ip,
      connection.assigned_ips,
    ]);
  });
  const primaryConnectionValues = computed(() => {
    const connection = detail.value.connection || {};

    if (isNatConsole(detail.value)) {
      return normalizeConnectionValues([connection.nat_remote_address || connection.nat_remote_host]);
    }

    return publicIpValues.value;
  });
  const primaryConnectionText = computed(() =>
    primaryConnectionValues.value.length ? primaryConnectionValues.value.join(' / ') : '--',
  );
  const connectionEndpointText = computed(
    () => String(detail.value.connection?.hostname || detail.value.domain || '').trim() || '--',
  );
  const connectionPortText = computed(() => {
    const port = Number(detail.value.connection?.nat_remote_port || detail.value.connection?.port || 0);
    return Number.isFinite(port) && port > 0 ? String(port) : '--';
  });
  const instanceStatusText = computed(() => {
    const operationLabel = String(operationStatus.label || '').trim();
    if (operationLabel) return operationLabel;
    const runtimeLabel = String(detail.value.runtime?.power_label || '').trim();
    if (runtimeLabel) return runtimeLabel;
    const runtimeDescription = String(detail.value.runtime?.description || '').trim();
    if (runtimeDescription) return runtimeDescription;
    return resolveServiceStatusLabel(detail.value.status);
  });
  const instanceStatusTheme = computed(() => {
    const text = instanceStatusText.value;
    if (/运行中|运行|正常|已开通|开机|成功|完成/.test(text)) return 'success';
    if (/创建中|开机中|关机中|重启中|启动中|重置密码中|重装系统中|处理中|同步中|执行中/.test(text)) return 'warning';
    if (/失败|错误|异常|超时|欠费|锁定/.test(text)) return 'danger';
    if (/已关机|关机|已停止|停止|已暂停|暂停|未开通|待开通/.test(text)) return 'default';
    return resolveTdesignStatusTheme(detail.value);
  });
  const serviceIpCount = computed(() => {
    if (publicIpValues.value.length) return `${publicIpValues.value.length} 个`;
    return findSpecValue(detail, ['IP数量', 'IP 数量', 'ip'], primaryConnectionText.value !== '--' ? '1 个' : '--');
  });
  const bandwidthText = computed(() => findSpecValue(detail, ['带宽', '宽带', 'bandwidth'], '--'));
  const renewPriceText = computed(() => `¥${formatMoney(detail.value.amount)}`);
  const autoRenewLabel = computed(() => (Number(detail.value.auto_renew) === 1 ? '已开启' : '未开启'));
  const resolvedPassword = computed(() => {
    const password = String(detail.value.connection?.password || '').trim();

    if (password !== '') {
      return showPassword.value ? password : '••••••••';
    }

    return detail.value.connection?.has_password ? '已设置' : '--';
  });

  // 带提示的复制辅助函数
  async function copyTextWithToast(value: unknown) {
    await copyText(value, {
      successMsg: '内容已复制',
      errorMsg: '当前浏览器不支持自动复制，请手动复制',
    });
  }

  // 路由监听
  watch(
    () => route.params.id,
    () => {
      resetLazyTabs();
      void bootstrap();
    },
    { immediate: true },
  );

  // 标签懒加载：首次进入某个标签立即请求，节流窗口内连续切换只保留最后一次。
  // 若改成纯延迟（trailing）防抖，用户进监控后立刻改区间，首屏那次请求会被整批丢弃，
  // 页面上就只剩改区间后的那一次，看不到默认区间的数据（e2e 已覆盖该时序）。
  const ACTIVE_TAB_THROTTLE_MS = 300;
  let activeTabSeq = 0;
  let activeTabTimer: number | null = null;
  let activeTabLastRunAt = 0;

  const clearActiveTabTimer = () => {
    if (activeTabTimer !== null) {
      window.clearTimeout(activeTabTimer);
      activeTabTimer = null;
    }
  };

  const resolveTabLoader = (tab: string): (() => Promise<boolean>) | null => {
    if (tab === 'monitor') return loadMonitor;
    if (tab === 'security') return loadSecurityGroups;
    if (tab === 'nat') return loadNatForwardings;
    if (tab === 'logs') return loadLogs;
    if (tab === 'finance') return loadFinanceLogs;
    return null;
  };

  watch(activeTab, (tab) => {
    if (!availableTabs.value.includes(tab)) {
      activeTab.value = DEFAULT_TAB;
      return;
    }
    const loader = resolveTabLoader(tab);
    if (!loader || loadedTabs[tab as keyof typeof loadedTabs]) return;

    const seq = (activeTabSeq += 1);
    clearActiveTabTimer();
    const runLoader = () => {
      activeTabLastRunAt = Date.now();
      if (seq !== activeTabSeq || activeTab.value !== tab) return;
      // 懒加载成功后才置位 loadedTabs：失败时下次切换自动重拉，避免标签永久停留在错误态
      void loader().then((ok) => {
        if (ok && seq === activeTabSeq) loadedTabs[tab as keyof typeof loadedTabs] = true;
      });
    };
    const wait = ACTIVE_TAB_THROTTLE_MS - (Date.now() - activeTabLastRunAt);
    if (wait <= 0) {
      runLoader();
      return;
    }
    activeTabTimer = window.setTimeout(() => {
      activeTabTimer = null;
      runLoader();
    }, wait);
  });

  onUnmounted(() => {
    clearStatusSyncTimer();
    clearActiveTabTimer();
  });

  return {
    // 状态引用
    detail,
    detailLoading,
    detailError,
    statusSyncing,
    powerLoading,
    reinstallLoading,
    vncLoading,
    passwordLoading,
    autoRenewLoading,
    showPassword,
    activeTab,
    vncUrl,
    renewVisible,
    renewLoading,
    renewSubmitting,
    renewData,
    renewForm,
    trafficVisible,
    trafficLoading,
    trafficQuoting,
    trafficSubmitting,
    trafficData,
    trafficQuote,
    trafficForm,
    nameVisible,
    nameSubmitting,
    nameForm,
    remarkVisible,
    remarkSubmitting,
    remarkForm,
    passwordVisible,
    passwordForm,
    reinstallVisible,
    reinstallState,
    monitorState,
    securityState,
    activeSecurityGroup,
    groupVisible,
    groupForm,
    ruleVisible,
    ruleForm,
    isPortDisabled,
    isAllPortProtocol,
    onProtocolChange,
    natState,
    natVisible,
    natForm,
    logsState,
    financeState,

    // 计算属性
    serviceId,
    availableTabs,
    canManageConsole,
    canSyncStatus,
    serviceRegion,
    serviceOs,
    primaryConnectionLabel,
    primaryConnectionValues,
    primaryConnectionText,
    connectionEndpointText,
    connectionPortText,
    instanceStatusText,
    instanceStatusTheme,
    serviceIpCount,
    bandwidthText,
    renewPriceText,
    autoRenewLabel,
    resolvedPassword,
    renewAmount,
    renewCoupons,
    renewCouponBinding,
    trafficPackages,
    selectedTrafficPackage,
    trafficPayableAmount,
    reinstallGroupedOptions,
    currentReinstallOptions,

    // 辅助函数
    findSpecValue: (aliases: string[], fallback = '--') => findSpecValue(detail, aliases, fallback),
    resolveServiceStatusLabel,
    resolveTdesignStatusTheme,
    formatMoney,
    router,
    copyText: copyTextWithToast,

    // 动作处理
    bootstrap,
    handleSyncStatus,
    handlePowerAction,
    openRenewDialog,
    handleRenewCycleChange,
    handleRenewCouponChange,
    submitRenew,
    openTrafficPackageDialog,
    handleTrafficPackageChange,
    submitTrafficPackageOrder,
    openNameDialog,
    submitName,
    openRemarkDialog,
    submitRemark,
    openPasswordDialog,
    generateStrongPassword,
    submitResetPassword,
    openReinstallDialog,
    handleReinstallGroupChange,
    submitReinstall,
    handleToggleAutoRenew,
    loadMonitor,
    loadSecurityGroups,
    loadSecurityGroupRules,
    selectSecurityGroup,
    openSecurityGroupDialog,
    openSecurityRuleDialog,
    submitSecurityGroup,
    applySecurityGroup,
    deleteSecurityGroup,
    submitSecurityRule,
    deleteSecurityRule,
    loadNatForwardings,
    openNatForwardingDialog,
    submitNatForwarding,
    deleteNatForwarding,
    loadLogs,
    loadFinanceLogs,
    handleLogsPageSizeChange,
    handleFinancePageSizeChange,
    handleOpenVnc,
  };
}

// 转发工具函数供外部使用
export { mergeConsoleDetail, normalizeConsoleDetail } from './console/useConsoleCore';

function normalizeConnectionValues(values: unknown[]): string[] {
  const normalized: string[] = [];

  for (const value of values) {
    const items = Array.isArray(value) ? value : [value];
    for (const item of items) {
      const candidates = String(item || '').split(/[\s,，;；、]+/);
      for (const candidate of candidates) {
        const text = candidate.trim();
        if (text !== '' && text !== '--' && !normalized.includes(text)) {
          normalized.push(text);
        }
      }
    }
  }

  return normalized;
}
