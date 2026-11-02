<template>
  <section class="referral-page">
    <h1 class="sr-only">推广大使</h1>

    <data-state v-if="loadError && !loading" error error-text="推广大使数据加载失败，请重试" @retry="loadAll" />
    <t-loading v-else :loading="loading" text="数据加载中…">
      <div class="referral-stats">
        <t-card v-for="item in summaryCards" :key="item.key" class="referral-stat" :bordered="false">
          <span>{{ item.label }}</span>
          <strong :class="{ primary: item.primary }">{{ item.value }}</strong>
        </t-card>
      </div>

      <t-card class="referral-card" :bordered="false">
        <template #title>我的推广链接</template>
        <template #actions>
          <t-tag variant="light">{{ levelName }} - 新购 {{ rewardRateText }} / 续费 {{ renewalRewardRateText }}</t-tag>
        </template>
        <div class="referral-link-row">
          <t-input :value="referralLink" readonly>
            <template #prefixIcon><link-icon /></template>
          </t-input>
          <t-button @click="copyReferralLink">复制链接</t-button>
        </div>
        <p>
          好友通过此链接注册，新购可获得 {{ rewardRateText }} 佣金、续费可获得 {{ renewalRewardRateText }} 佣金，冻结
          {{ freezeDaysText }} 天后自动释放。最低提现金额 ¥{{ withdrawMinAmountText }}。
        </p>
      </t-card>

      <t-card class="referral-card" :bordered="false">
        <template #title>提现支付宝</template>
        <template #actions>
          <t-tag :theme="isAlipayBound ? 'success' : 'warning'" variant="light">{{
            isAlipayBound ? '已绑定' : '提现前需先绑定'
          }}</t-tag>
        </template>
        <div v-if="!isAlipayBound" class="referral-empty">
          <t-empty description="提现前需要先绑定支付宝，并完成该手机号的短信验证，无需上传支付宝图片。" />
          <t-button theme="primary" @click="openBindDialog">立即绑定</t-button>
        </div>
        <t-form v-else class="withdraw-form" layout="inline" label-align="right">
          <t-form-item label="收款账户">
            <span>{{ alipayAccount.real_name || '--' }} {{ alipayAccount.account || '--' }}</span>
          </t-form-item>
          <t-form-item label="提现金额">
            <t-input v-model="withdrawForm.amount" placeholder="请输入提现金额" />
          </t-form-item>
          <t-form-item>
            <t-button theme="primary" :loading="withdrawSubmitting" @click="submitWithdrawal">提交提现</t-button>
          </t-form-item>
        </t-form>
      </t-card>

      <t-card class="referral-card" :bordered="false">
        <t-tabs v-model="activeTab">
          <t-tab-panel value="direct" label="被邀请人列表">
            <t-table
              row-key="id"
              :data="directReferrals"
              :columns="directColumns"
              :pagination="directReferralsTablePagination"
              :loading="loading"
              @page-change="handleDirectReferralsPageChange"
            >
              <template #empty>
                <t-empty description="暂无被邀请用户，复制上方推广链接邀请好友" />
              </template>
              <template #user="{ row }">{{ directVm(row).user }}</template>
              <template #referred_at="{ row }">{{ directVm(row).referredAt }}</template>
              <template #consumption="{ row }"
                ><span class="referral-money">¥{{ directVm(row).consumption }}</span></template
              >
              <template #earnings="{ row }"
                ><span class="referral-money">¥{{ directVm(row).earnings }}</span></template
              >
            </t-table>
          </t-tab-panel>
          <t-tab-panel value="rewards" label="奖励明细" lazy>
            <t-table
              row-key="id"
              :data="rewards"
              :columns="rewardColumns"
              :pagination="rewardsTablePagination"
              :loading="loading"
              @page-change="handleRewardsPageChange"
            >
              <template #empty>
                <t-empty description="暂无奖励记录" />
              </template>
              <template #user="{ row }">{{ rewardVm(row).user }}</template>
              <template #product="{ row }">{{ rewardVm(row).product }}</template>
              <template #order_type="{ row }">{{ rewardVm(row).orderType }}</template>
              <template #order_amount="{ row }"
                ><span class="referral-money">¥{{ rewardVm(row).orderAmount }}</span></template
              >
              <template #amount="{ row }"
                ><span class="referral-money">¥{{ rewardVm(row).amount }}</span></template
              >
              <template #status="{ row }">
                <t-tag :theme="rewardVm(row).statusTheme" variant="light">{{ rewardVm(row).statusLabel }}</t-tag>
              </template>
            </t-table>
          </t-tab-panel>
          <t-tab-panel value="withdrawals" label="提现记录" lazy>
            <t-table
              row-key="id"
              :data="withdrawals"
              :columns="withdrawColumns"
              :pagination="withdrawalsTablePagination"
              :loading="loading"
              @page-change="handleWithdrawalsPageChange"
            >
              <template #empty>
                <t-empty description="暂无提现记录" />
              </template>
              <template #method="{ row }">{{ withdrawalVm(row).method }}</template>
              <template #amount="{ row }"
                ><span class="referral-money">¥{{ withdrawalVm(row).amount }}</span></template
              >
              <template #account="{ row }">{{ withdrawalVm(row).account }}</template>
              <template #status="{ row }">
                <t-tag :theme="withdrawalVm(row).statusTheme" variant="light">{{
                  withdrawalVm(row).statusLabel
                }}</t-tag>
              </template>
            </t-table>
          </t-tab-panel>
          <t-tab-panel value="logs" label="账户流水" lazy>
            <t-table
              row-key="id"
              :data="accountLogs"
              :columns="logColumns"
              :pagination="accountLogsTablePagination"
              :loading="loading"
              @page-change="handleAccountLogsPageChange"
            >
              <template #empty>
                <t-empty description="暂无账户流水" />
              </template>
              <template #event_type="{ row }">{{ logVm(row).eventLabel }}</template>
              <template #amount="{ row }"
                ><span class="referral-money">¥{{ logVm(row).amount }}</span></template
              >
            </t-table>
          </t-tab-panel>
        </t-tabs>
      </t-card>
    </t-loading>

    <t-dialog v-model:visible="bindDialogVisible" header="绑定提现支付宝" width="min(30rem, calc(100vw - 2rem))">
      <t-form label-align="right">
        <p class="bind-dialog-tip">请填写实名、支付宝绑定手机号、短信验证码与登录密码，用于提现打款。</p>
        <t-form-item label="真实姓名"
          ><t-input v-model="bindForm.real_name" placeholder="请输入支付宝实名姓名"
        /></t-form-item>
        <t-form-item label="支付宝手机号"
          ><t-input v-model="bindForm.account" placeholder="请输入支付宝绑定手机号"
        /></t-form-item>
        <t-form-item label="短信验证码"><t-input v-model="bindForm.code" placeholder="请输入短信验证码" /></t-form-item>
        <t-form-item label="登录密码"
          ><t-input v-model="bindForm.password" type="password" placeholder="请输入登录密码进行身份校验"
        /></t-form-item>
      </t-form>
      <template #footer>
        <t-button variant="outline" @click="bindDialogVisible = false">取消</t-button>
        <t-button theme="primary" :loading="bindSubmitting" @click="submitBindAlipay">确定</t-button>
      </template>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { LinkIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed } from 'vue';

import { accountEventLabel, money, rewardStatus, useReferral, withdrawStatus } from '@/domains/marketing/useReferral';
import type { ReferralUserBrief } from '@/types/client';

const {
  loading,
  loadError,
  activeTab,
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
} = useReferral();

const rewardColumns: PrimaryTableCol[] = [
  { colKey: 'rewarded_at', title: '时间', minWidth: '12rem' },
  { colKey: 'user', title: '来源用户', minWidth: '10rem' },
  { colKey: 'product', title: '产品', minWidth: '14rem' },
  { colKey: 'order_type', title: '消费类型', width: '8rem' },
  { colKey: 'order_amount', title: '消费金额', width: '8rem', align: 'right' },
  { colKey: 'amount', title: '奖励金额', width: '8rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '8rem' },
];
const directColumns: PrimaryTableCol[] = [
  { colKey: 'user', title: '用户', minWidth: '10rem' },
  { colKey: 'email', title: '邮箱', minWidth: '14rem' },
  { colKey: 'referred_at', title: '被邀请时间', minWidth: '12rem' },
  { colKey: 'consumption', title: '客户消费', width: '8rem', align: 'right' },
  { colKey: 'earnings', title: '我的收益', width: '8rem', align: 'right' },
];
const withdrawColumns: PrimaryTableCol[] = [
  { colKey: 'created_at', title: '时间', minWidth: '12rem' },
  { colKey: 'method', title: '提现方式', width: '8rem' },
  { colKey: 'amount', title: '提现金额', width: '8rem', align: 'right' },
  { colKey: 'account', title: '收款账户', minWidth: '14rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
];
const logColumns: PrimaryTableCol[] = [
  { colKey: 'created_at', title: '时间', minWidth: '12rem' },
  { colKey: 'event_type', title: '事件', width: '9rem' },
  { colKey: 'amount', title: '变动金额', width: '9rem', align: 'right' },
  { colKey: 'remark', title: '说明', minWidth: '14rem' },
];

/*
 * 四张表的行视图模型：原先每个金额/文案槽都在模板里重复调 money()、
 * accountEventLabel()、rewardStatus() 等纯函数，同一行一次渲染要算好几遍。
 * 这里按行 id 预算一次，模板只做取值（PR-22）。
 */
interface RewardRowViewModel {
  user: string;
  product: string;
  orderType: string;
  orderAmount: string;
  amount: string;
  statusLabel: string;
  statusTheme: string;
}
interface DirectRowViewModel {
  user: string;
  referredAt: string;
  consumption: string;
  earnings: string;
}
interface WithdrawalRowViewModel {
  method: string;
  amount: string;
  account: string;
  statusLabel: string;
  statusTheme: string;
}
interface LogRowViewModel {
  eventLabel: string;
  amount: string;
}
const rewardRowViewModels = computed(() => {
  const map = new Map<number, RewardRowViewModel>();
  for (const row of rewards.value) {
    map.set(row.id, {
      user: row.referred_user?.display_name || row.referred_user?.nickname || row.referred_user?.email || '--',
      product: row.invoice?.product_display_name || row.product?.display_name || row.product?.name || '--',
      orderType: row.order_type || '--',
      orderAmount: money(row.order_amount),
      amount: `${money(row.reward_amount)}(${Number(row.reward_rate || 0)}%)`,
      statusLabel: rewardStatus(row.status).label,
      statusTheme: rewardStatus(row.status).theme,
    });
  }
  return map;
});
/** 被邀请人行的扩展字段：后端在列表里随行下发消费额与收益，类型定义未收录，这里显式声明。 */
type DirectReferralRow = ReferralUserBrief & {
  referred_at?: string;
  customer_consumption?: number | string;
  my_earnings?: number | string;
};
// 用行对象本身做键，而不是 id：接口理论上必带 id，但一旦某行为空则所有无 id 的行
// 都会落到同一个兜底键上互相覆盖，表格里几条不同的记录会显示成同一份文案。
const directRowViewModels = computed(() => {
  const map = new Map<DirectReferralRow, DirectRowViewModel>();
  for (const row of directReferrals.value as DirectReferralRow[]) {
    map.set(row, {
      user: row.display_name || row.nickname || row.email || '--',
      referredAt: row.referred_at || row.created_at || '--',
      consumption: money(row.customer_consumption),
      earnings: money(row.my_earnings),
    });
  }
  return map;
});
const withdrawalRowViewModels = computed(() => {
  const map = new Map<number, WithdrawalRowViewModel>();
  for (const row of withdrawals.value) {
    map.set(row.id, {
      method: row.method === 'balance' ? '转余额' : '支付宝',
      amount: money(row.amount),
      account: `${row.account_name || '--'} ${row.account_no || ''}`,
      statusLabel: withdrawStatus(row.status).label,
      statusTheme: withdrawStatus(row.status).theme,
    });
  }
  return map;
});
const logRowViewModels = computed(() => {
  const map = new Map<number, LogRowViewModel>();
  for (const row of accountLogs.value) {
    map.set(row.id, {
      eventLabel: accountEventLabel(row.event_type),
      amount: money(row.amount),
    });
  }
  return map;
});
const EMPTY_REWARD_ROW: RewardRowViewModel = {
  user: '--',
  product: '--',
  orderType: '--',
  orderAmount: '--',
  amount: '--',
  statusLabel: '--',
  statusTheme: 'default',
};
const EMPTY_DIRECT_ROW: DirectRowViewModel = { user: '--', referredAt: '--', consumption: '--', earnings: '--' };
const EMPTY_WITHDRAWAL_ROW: WithdrawalRowViewModel = {
  method: '--',
  amount: '--',
  account: '--',
  statusLabel: '--',
  statusTheme: 'default',
};
const EMPTY_LOG_ROW: LogRowViewModel = { eventLabel: '--', amount: '--' };
const rewardVm = (row: { id: number }) => rewardRowViewModels.value.get(row.id) ?? EMPTY_REWARD_ROW;
const directVm = (row: DirectReferralRow) => directRowViewModels.value.get(row) ?? EMPTY_DIRECT_ROW;
const withdrawalVm = (row: { id: number }) => withdrawalRowViewModels.value.get(row.id) ?? EMPTY_WITHDRAWAL_ROW;
const logVm = (row: { id: number }) => logRowViewModels.value.get(row.id) ?? EMPTY_LOG_ROW;
</script>
<style scoped lang="less">
/* 金额列等宽数字（DESIGN.md §4.3：金额列不截断 + tabular-nums，右对齐由列定义 align 承担） */
.referral-money {
  font-variant-numeric: tabular-nums;
}

.referral-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  // padding 由 Starter 布局层统一提供
}

.referral-stats {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: var(--td-comp-margin-s);
}

.referral-card,
.referral-stat {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.referral-stat {
  span {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }

  strong {
    display: block;
    margin-top: var(--td-comp-margin-xs);
    color: var(--td-text-color-primary);
    font: var(--td-font-title-medium);
    font-variant-numeric: tabular-nums;

    &.primary {
      color: var(--td-brand-color);
    }
  }
}

.referral-link-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.referral-card p {
  margin: var(--td-comp-margin-s) 0 0;
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.bind-dialog-tip {
  margin: 0 0 var(--td-comp-margin-s);
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.referral-empty {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  align-items: center;
}

@media (max-width: @screen-lg-rem) {
  .referral-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: @screen-mobile-max) {
  // 原先两个同断点块连写，前者被后者完全覆盖；合并为单一规则。
  .referral-stats,
  .referral-link-row {
    grid-template-columns: 1fr;
  }
}
</style>
