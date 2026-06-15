import { computed, onMounted, reactive, ref } from 'vue';
import { MessagePlugin } from 'tdesign-vue-next';

import { clientAuthApi } from '@/api/auth';
import clientApi from '@/api/client';
import { useUserStore } from '@/store';

type AnyRecord = Record<string, any>;
type TagTheme = 'default' | 'success' | 'warning' | 'primary' | 'danger';

export function money(value: unknown) {
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

export function useReferral() {
  const userStore = useUserStore();
  const loading = ref(false);
  const activeTab = ref<'rewards' | 'withdrawals' | 'logs'>('rewards');
  const bindDialogVisible = ref(false);
  const withdrawSubmitting = ref(false);
  const bindSubmitting = ref(false);
  const overview = reactive<AnyRecord>({});
  const rewards = ref<AnyRecord[]>([]);
  const accountLogs = ref<AnyRecord[]>([]);
  const withdrawals = ref<AnyRecord[]>([]);
  const alipayAccount = reactive({ real_name: '', account: '', is_bound: false });
  const withdrawForm = reactive({ amount: '' });
  const bindForm = reactive({ real_name: '', account: '', code: '' });

  const availableAmountText = computed(() => money(overview.referral_available_amount || overview.available_amount));
  const frozenAmountText = computed(() => money(overview.referral_frozen_amount || overview.pending_amount));
  const totalRewardText = computed(() => money(overview.total_reward_amount || overview.total_amount));
  const withdrawnAmountText = computed(() => money(overview.referral_withdrawn_amount || overview.withdrawn_amount));
  const withdrawMinAmountText = computed(() => money(overview.withdraw_min_amount || 20));
  const rewardRateText = computed(() => `${money(overview.reward_rate || overview.current_member_level?.reward_rate || 0)}%`);
  const freezeDaysText = computed(() => String(Number(overview.reward_freeze_days || 0)));
  const levelName = computed(() => overview.current_member_level?.name || 'v1');
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
    { key: 'orders', label: '推荐账单数', value: `${Number(overview.rewarded_orders_count || 0)} 单` },
  ]);

  function resolveList(response: unknown) {
    const payload = (response as AnyRecord)?.data || {};
    return Array.isArray(payload.list) ? payload.list : [];
  }

  async function loadAlipayAccount() {
    try {
      const response = await clientAuthApi.alipayAccount();
      Object.assign(alipayAccount, (response as AnyRecord).data || {});
    } catch {
      Object.assign(alipayAccount, userStore.info?.alipay_account || {});
    }
  }

  async function loadAll() {
    loading.value = true;
    try {
      const [overviewRes, rewardsRes, logsRes, withdrawalsRes] = await Promise.all([
        clientApi.referralOverview(),
        clientApi.referralRewards({ page: 1, page_size: 10 }),
        clientApi.referralAccountLogs({ page: 1, page_size: 10 }),
        clientApi.referralWithdrawals({ page: 1, page_size: 10 }),
      ]);
      Object.assign(overview, (overviewRes as AnyRecord).data || {});
      rewards.value = resolveList(rewardsRes);
      accountLogs.value = resolveList(logsRes);
      withdrawals.value = resolveList(withdrawalsRes);
    } catch (error: any) {
      MessagePlugin.error(error?.message || '推荐奖励数据加载失败');
    } finally {
      loading.value = false;
    }
  }

  async function copyReferralLink() {
    if (!referralLink.value || referralLink.value === '--') {
      MessagePlugin.warning('推荐链接暂不可用');
      return;
    }
    try {
      await navigator.clipboard.writeText(referralLink.value);
      MessagePlugin.success('推荐链接已复制');
    } catch {
      MessagePlugin.warning('复制失败，请手动复制');
    }
  }

  function openBindDialog() {
    bindForm.real_name = alipayAccount.real_name || String(userStore.info?.real_name || '');
    bindForm.account = alipayAccount.account || String(userStore.info?.phone || '');
    bindForm.code = '';
    bindDialogVisible.value = true;
  }

  async function submitBindAlipay() {
    bindSubmitting.value = true;
    try {
      const response = await clientAuthApi.updateAlipayAccount({ ...bindForm });
      Object.assign(alipayAccount, (response as AnyRecord).data || {});
      bindDialogVisible.value = false;
      MessagePlugin.success('支付宝绑定成功');
      await userStore.getUserInfo();
    } catch (error: any) {
      MessagePlugin.error(error?.message || '支付宝绑定失败');
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
    } catch (error: any) {
      MessagePlugin.error(error?.message || '提现申请提交失败');
    } finally {
      withdrawSubmitting.value = false;
    }
  }

  onMounted(() => {
    void Promise.all([loadAll(), loadAlipayAccount()]);
  });

  return {
    userStore,
    loading,
    activeTab,
    overview,
    rewards,
    accountLogs,
    withdrawals,
    alipayAccount,
    withdrawForm,
    bindForm,
    bindDialogVisible,
    withdrawSubmitting,
    bindSubmitting,
    availableAmountText,
    withdrawMinAmountText,
    rewardRateText,
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
