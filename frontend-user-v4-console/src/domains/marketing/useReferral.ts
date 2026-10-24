import { ACCOUNT_TRANSACTION_EVENT_MAP, getStatusLabel } from '@caiwu/shared/statusConfig';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';

import { clientAuthApi } from '@/api/auth';
import clientApi from '@/api/client';
import { useUserStore } from '@/store';
import type {
  ClientAlipayAccount,
  ReferralAccountLogRecord,
  ReferralOverviewPayload,
  ReferralRewardRecord,
  ReferralUserBrief,
  ReferralWithdrawalRecord,
} from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { copyText, formatMoney } from '@/utils/format';

type TagTheme = 'default' | 'success' | 'warning' | 'primary' | 'danger';

interface BindFormState {
  real_name: string;
  account: string;
  code: string;
  password: string;
}

interface WithdrawFormState {
  amount: string;
}

/** 金额文案：复用 shared 的千分位实现，与 admin formatMoney 同口径（docs/DESIGN.md §6）。 */
export function money(value: unknown) {
  return formatMoney(value);
}

/** 费率文案：百分比数值不带千分位，保留两位小数。 */
export function ratePercent(value: unknown) {
  const amount = Number(value || 0);
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00';
}

export function rewardStatus(status: unknown) {
  const map: Record<number, { label: string; theme: TagTheme }> = {
    0: { label: '冻结中', theme: 'warning' },
    1: { label: '已释放', theme: 'success' },
    2: { label: '已冲正', theme: 'danger' },
  };
  return map[Number(status)] || { label: String(status ?? '--'), theme: 'default' };
}

export function withdrawStatus(status: unknown) {
  const map: Record<number, { label: string; theme: TagTheme }> = {
    0: { label: '审核中', theme: 'warning' },
    1: { label: '已通过', theme: 'success' },
    2: { label: '已拒绝', theme: 'danger' },
  };
  return map[Number(status)] || { label: String(status ?? '--'), theme: 'default' };
}

export function accountEventLabel(eventType: unknown) {
  return getStatusLabel(ACCOUNT_TRANSACTION_EVENT_MAP, String(eventType || ''));
}

function resolveList<T>(list: T[] | undefined) {
  return Array.isArray(list) ? list : [];
}

export function useReferral() {
  const userStore = useUserStore();
  const loading = ref(false);
  const loadError = ref(false);
  const activeTab = ref<'rewards' | 'withdrawals' | 'logs' | 'direct'>('direct');
  const bindDialogVisible = ref(false);
  const withdrawSubmitting = ref(false);
  const bindSubmitting = ref(false);
  const overview = reactive<ReferralOverviewPayload>({});
  const rewards = ref<ReferralRewardRecord[]>([]);
  const accountLogs = ref<ReferralAccountLogRecord[]>([]);
  const withdrawals = ref<ReferralWithdrawalRecord[]>([]);
  const directReferrals = ref<ReferralUserBrief[]>([]);

  // 四张表分页状态（后端已分页，前端接 t-pagination；DESIGN.md §4.5）
  const REFERRAL_PAGE_SIZE = 10;
  const rewardsPagination = reactive({ page: 1, total: 0 });
  const accountLogsPagination = reactive({ page: 1, total: 0 });
  const withdrawalsPagination = reactive({ page: 1, total: 0 });
  const directReferralsPagination = reactive({ page: 1, total: 0 });

  function toTablePagination(p: { page: number; total: number }) {
    if (p.total <= REFERRAL_PAGE_SIZE) return null;
    return { current: p.page, total: p.total, pageSize: REFERRAL_PAGE_SIZE, showPageSize: false };
  }
  const rewardsTablePagination = computed(() => toTablePagination(rewardsPagination));
  const accountLogsTablePagination = computed(() => toTablePagination(accountLogsPagination));
  const withdrawalsTablePagination = computed(() => toTablePagination(withdrawalsPagination));
  const directReferralsTablePagination = computed(() => toTablePagination(directReferralsPagination));
  const alipayAccount = reactive<ClientAlipayAccount & { is_bound: boolean }>({
    real_name: '',
    account: '',
    is_bound: false,
  });
  const withdrawForm = reactive<WithdrawFormState>({ amount: '' });
  const bindForm = reactive<BindFormState>({ real_name: '', account: '', code: '', password: '' });

  const availableAmountText = computed(() => money(overview.referral_available_amount));
  const frozenAmountText = computed(() => money(overview.referral_frozen_amount));
  const totalRewardText = computed(() => money(overview.total_reward_amount));
  const withdrawnAmountText = computed(() => money(overview.referral_withdrawn_amount));
  const withdrawMinAmountText = computed(() => money(overview.withdraw_min_amount || 20));
  const rewardRateText = computed(
    () => `${ratePercent(overview.reward_rate || overview.current_ambassador?.reward_rate || 0)}%`,
  );
  const renewalRewardRateText = computed(
    () => `${ratePercent(overview.renewal_reward_rate || overview.current_ambassador?.renewal_reward_rate || 0)}%`,
  );
  const freezeDaysText = computed(() => String(Number(overview.reward_freeze_days || 0)));
  const levelName = computed(() => overview.current_ambassador?.name || '默认大使');
  const isAlipayBound = computed(() => Boolean(alipayAccount.is_bound && alipayAccount.account));
  const referralLink = computed(() => {
    if (overview.referral_link) return overview.referral_link;
    const code = overview.referral_code || userStore.info?.referral_code || '';
    return code && typeof window !== 'undefined' ? `${window.location.origin}/client/register?ref=${code}` : '--';
  });
  const summaryCards = computed(() => [
    { key: 'available', label: '可提现余额', value: `¥${availableAmountText.value}`, primary: true },
    { key: 'frozen', label: '冻结中', value: `¥${frozenAmountText.value}` },
    { key: 'total', label: '累计奖励', value: `¥${totalRewardText.value}` },
    { key: 'withdrawn', label: '已提现', value: `¥${withdrawnAmountText.value}` },
    { key: 'direct', label: '直推人数', value: `${Number(overview.direct_referral_count || 0)} 人` },
    { key: 'orders', label: '奖励账单数', value: `${Number(overview.rewarded_orders_count || 0)} 单` },
  ]);

  async function loadAlipayAccount() {
    try {
      const response = await clientAuthApi.alipayAccount();
      Object.assign(alipayAccount, response.data || {});
      alipayAccount.is_bound = Boolean(response.data?.is_bound && response.data?.account);
    } catch {
      Object.assign(alipayAccount, userStore.info?.alipay_account || {});
      alipayAccount.is_bound = Boolean(
        userStore.info?.alipay_account?.is_bound && userStore.info?.alipay_account?.account,
      );
    }
  }

  // 竞态防护：快速翻页/翻页与 loadAll 并发时，慢的旧响应不得覆盖新数据（对齐 useConsoleTabs 的 requestSeq 模式）
  let rewardsRequestSeq = 0;
  let accountLogsRequestSeq = 0;
  let withdrawalsRequestSeq = 0;
  let directReferralsRequestSeq = 0;

  function safeTotal(value: unknown) {
    const total = Number(value || 0);
    return Number.isFinite(total) ? total : 0;
  }

  async function loadRewardsPage(page = 1) {
    const requestSeq = ++rewardsRequestSeq;
    try {
      const res = await clientApi.referralRewards({ page, page_size: REFERRAL_PAGE_SIZE });
      if (requestSeq !== rewardsRequestSeq) return;
      rewards.value = resolveList(res.data?.list);
      rewardsPagination.page = page;
      rewardsPagination.total = safeTotal(res.data?.total);
    } catch (error: unknown) {
      if (requestSeq !== rewardsRequestSeq) return;
      throw error; // 交由调用方处理：loadAll → 整页失败态；handleXxxPageChange → toast
    }
  }

  async function loadAccountLogsPage(page = 1) {
    const requestSeq = ++accountLogsRequestSeq;
    try {
      const res = await clientApi.referralAccountLogs({ page, page_size: REFERRAL_PAGE_SIZE });
      if (requestSeq !== accountLogsRequestSeq) return;
      accountLogs.value = resolveList(res.data?.list);
      accountLogsPagination.page = page;
      accountLogsPagination.total = safeTotal(res.data?.total);
    } catch (error: unknown) {
      if (requestSeq !== accountLogsRequestSeq) return;
      throw error;
    }
  }

  async function loadWithdrawalsPage(page = 1) {
    const requestSeq = ++withdrawalsRequestSeq;
    try {
      const res = await clientApi.referralWithdrawals({ page, page_size: REFERRAL_PAGE_SIZE });
      if (requestSeq !== withdrawalsRequestSeq) return;
      withdrawals.value = resolveList(res.data?.list);
      withdrawalsPagination.page = page;
      withdrawalsPagination.total = safeTotal(res.data?.total);
    } catch (error: unknown) {
      if (requestSeq !== withdrawalsRequestSeq) return;
      throw error;
    }
  }

  async function loadDirectReferralsPage(page = 1) {
    const requestSeq = ++directReferralsRequestSeq;
    try {
      const res = await clientApi.referralDirectReferrals({ page, page_size: REFERRAL_PAGE_SIZE });
      if (requestSeq !== directReferralsRequestSeq) return;
      directReferrals.value = resolveList(res.data?.list);
      directReferralsPagination.page = page;
      directReferralsPagination.total = safeTotal(res.data?.total);
    } catch (error: unknown) {
      if (requestSeq !== directReferralsRequestSeq) return;
      throw error;
    }
  }

  async function loadAll() {
    loading.value = true;
    loadError.value = false;
    try {
      await Promise.all([
        clientApi.referralOverview().then((res) => Object.assign(overview, res.data || {})),
        loadRewardsPage(1),
        loadAccountLogsPage(1),
        loadWithdrawalsPage(1),
        loadDirectReferralsPage(1),
      ]);
    } catch (error: unknown) {
      loadError.value = true;
      MessagePlugin.error(getErrorMessage(error, '推广大使数据加载失败'));
    } finally {
      loading.value = false;
    }
  }

  // 翻页失败需给用户反馈（loadAll 的失败走整页失败态，这里兜 toast）
  async function handleRewardsPageChange(info: unknown) {
    try {
      await loadRewardsPage(Number((info as { current?: number })?.current || 1));
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '加载奖励记录失败'));
    }
  }
  async function handleAccountLogsPageChange(info: unknown) {
    try {
      await loadAccountLogsPage(Number((info as { current?: number })?.current || 1));
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '加载账户明细失败'));
    }
  }
  async function handleWithdrawalsPageChange(info: unknown) {
    try {
      await loadWithdrawalsPage(Number((info as { current?: number })?.current || 1));
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '加载提现记录失败'));
    }
  }
  async function handleDirectReferralsPageChange(info: unknown) {
    try {
      await loadDirectReferralsPage(Number((info as { current?: number })?.current || 1));
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '加载直推列表失败'));
    }
  }

  async function copyReferralLink() {
    if (!referralLink.value || referralLink.value === '--') {
      MessagePlugin.warning('推广链接暂不可用');
      return;
    }
    await copyText(referralLink.value, { successMsg: '推广链接已复制' });
  }

  function openBindDialog() {
    bindForm.real_name = alipayAccount.real_name || String(userStore.info?.real_name || '');
    bindForm.account = alipayAccount.account || '';
    bindForm.code = '';
    bindForm.password = '';
    bindDialogVisible.value = true;
  }

  async function submitBindAlipay() {
    bindSubmitting.value = true;
    try {
      const response = await clientAuthApi.updateAlipayAccount({ ...bindForm });
      Object.assign(alipayAccount, response.data || {});
      alipayAccount.is_bound = Boolean(response.data?.is_bound && response.data?.account);
      bindDialogVisible.value = false;
      MessagePlugin.success('支付宝绑定成功');
      await userStore.getUserInfo();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '支付宝绑定失败'));
    } finally {
      bindSubmitting.value = false;
    }
  }

  async function submitWithdrawal() {
    const amount = Number(withdrawForm.amount || 0);
    if (!Number.isFinite(amount) || amount <= 0) {
      MessagePlugin.warning('请输入提现金额');
      return;
    }

    withdrawSubmitting.value = true;
    try {
      await clientApi.applyWithdrawal({
        amount: withdrawForm.amount,
        method: 'alipay',
        account_name: alipayAccount.real_name,
        account_no: alipayAccount.account,
      });
      MessagePlugin.success('提现申请已提交');
      withdrawForm.amount = '';
      await loadAll();
    } catch (error: unknown) {
      MessagePlugin.error(getErrorMessage(error, '提现申请提交失败'));
    } finally {
      withdrawSubmitting.value = false;
    }
  }

  // 注：keep-alive 复用重拉（onActivated）已移除——当前工程 keep-alive include 按组件名匹配，
  // 页面无组件名注入实际不会被缓存（2026-10-11 裁决 A）；每次进入页面由 onMounted 全量拉取兜底。
  onMounted(() => {
    void Promise.all([loadAll(), loadAlipayAccount()]);
  });

  return {
    userStore,
    loading,
    loadError,
    activeTab,
    overview,
    rewards,
    accountLogs,
    withdrawals,
    directReferrals,
    rewardsTablePagination,
    accountLogsTablePagination,
    withdrawalsTablePagination,
    directReferralsTablePagination,
    handleRewardsPageChange,
    handleAccountLogsPageChange,
    handleWithdrawalsPageChange,
    handleDirectReferralsPageChange,
    alipayAccount,
    withdrawForm,
    bindForm,
    bindDialogVisible,
    withdrawSubmitting,
    bindSubmitting,
    availableAmountText,
    withdrawMinAmountText,
    rewardRateText,
    renewalRewardRateText,
    freezeDaysText,
    levelName,
    isAlipayBound,
    referralLink,
    summaryCards,
    copyReferralLink,
    openBindDialog,
    submitBindAlipay,
    submitWithdrawal,
    loadAll,
  };
}
