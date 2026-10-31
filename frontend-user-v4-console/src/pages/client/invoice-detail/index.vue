<template>
  <section class="invoice-pay-page">
    <div class="pay-toolbar">
      <t-button variant="outline" @click="router.push('/client/invoices')">返回账单</t-button>
      <div class="pay-toolbar__actions">
        <t-button variant="outline" :loading="loading" @click="() => loadDetail()">
          <template #icon><refresh-icon /></template>
          刷新详情
        </t-button>
        <t-button v-if="showPayActions" theme="danger" variant="outline" :loading="canceling" @click="handleCancel">
          取消账单
        </t-button>
      </div>
    </div>

    <data-state
      :loading="loading"
      :error="loadError"
      :error-text="loadErrorText"
      :empty="!detail"
      description="未找到账单详情"
      @retry="() => loadDetail()"
    >
      <template v-if="detail">
        <section class="pay-shell">
          <main class="pay-main-column">
            <t-card v-if="isRenewInvoiceView" class="renew-info-card" :bordered="false">
              <template #title>续费信息</template>

              <div class="renew-service-panel">
                <div>
                  <span>续费服务</span>
                  <strong>{{ renewServiceName(detail) }}</strong>
                  <p>支付完成后系统会自动处理续费，到期时间以服务控制台更新为准。</p>
                </div>
                <t-button
                  v-if="renewServiceId(detail)"
                  variant="outline"
                  theme="primary"
                  @click="router.push(`/client/services/${renewServiceId(detail)}`)"
                >
                  查看服务
                </t-button>
              </div>

              <div class="renew-kv-grid">
                <div v-for="item in renewInfoItemsView" :key="item.label" class="renew-kv-item">
                  <span>{{ item.label }}</span>
                  <strong>{{ item.value }}</strong>
                </div>
              </div>

              <section class="renew-flow-section">
                <h4>支付后处理</h4>
                <div class="renew-flow-list">
                  <div v-for="step in renewFlowItems" :key="step.label" class="renew-flow-item">
                    <span class="renew-flow-item__mark">{{ step.index }}</span>
                    <div>
                      <strong>{{ step.label }}</strong>
                      <p>{{ step.description }}</p>
                    </div>
                  </div>
                </div>
              </section>
            </t-card>

            <t-card v-if="isNewPurchaseInvoice(detail)" class="order-info-card" :bordered="false">
              <template #title>账单信息</template>
              <div class="order-summary-grid">
                <div class="order-kv-item">
                  <span>账单号</span>
                  <strong>{{ detail.invoice_no || '--' }}</strong>
                </div>
                <div class="order-kv-item">
                  <span>账单类型</span>
                  <strong>{{ detail.type_label || (detail.type === 'renew' ? '续费' : '新购') }}</strong>
                </div>
                <div class="order-kv-item">
                  <span>计费周期</span>
                  <strong>{{ orderBillingCycle(detail) }}</strong>
                </div>
                <div class="order-kv-item order-summary-grid__wide">
                  <span>产品链路</span>
                  <strong>{{ productPathView }}</strong>
                </div>
              </div>

              <div v-if="pricingItemsView.length" class="order-pricing-block">
                <section class="order-section">
                  <h4>配置定价</h4>
                  <div class="snapshot-line-list">
                    <div v-for="item in pricingItemsView" :key="`pricing-${item.label}`" class="snapshot-line-item">
                      <span>{{ item.label }}</span>
                      <strong>{{ item.value }}</strong>
                    </div>
                  </div>
                </section>
              </div>
            </t-card>

            <t-card v-if="detail.payment_summary" class="payment-record-card" :bordered="false">
              <template #title>关联支付记录</template>
              <div class="payment-record-grid">
                <div>
                  <span>支付方式</span>
                  <strong>{{
                    detail.payment_summary.gateway_label ||
                    detail.payment_summary.gateway_key ||
                    detail.payment_summary.gateway ||
                    '--'
                  }}</strong>
                </div>
                <div>
                  <span>支付状态</span>
                  <strong>{{ resolvePaymentStatusLabel(detail.payment_summary.status) }}</strong>
                </div>
                <div>
                  <span>商家订单号</span>
                  <strong>{{ detail.payment_summary.payment_no || '--' }}</strong>
                </div>
                <div>
                  <span>第三方订单号</span>
                  <strong>{{ detail.payment_summary.trade_no || '--' }}</strong>
                </div>
                <div>
                  <span>支付金额</span>
                  <strong>{{ formatMoneyText(detail.payment_summary.amount) }}</strong>
                </div>
                <div v-if="hasAppliedBalanceDeduction">
                  <span>余额抵扣</span>
                  <strong>¥{{ appliedDeductionAmountText }}</strong>
                </div>
              </div>
            </t-card>
          </main>

          <aside class="invoice-summary-column">
            <t-card class="invoice-summary-card" :bordered="false">
              <template #title>账单摘要</template>
              <div class="summary-amount">
                <div class="summary-amount__header">
                  <span class="summary-amount__label">本次应付</span>
                  <!-- 金额统一走 formatMoneyText：币符与负号的处理收敛在共享实现里，
                       页面不再手写 ¥ 前缀（utils/format.ts 的注释口径）。 -->
                  <div class="summary-amount__price">
                    <strong class="summary-amount__value">{{ formatMoneyText(detail.payable_amount) }}</strong>
                  </div>
                </div>
                <div v-if="sessionExpiresAt" class="summary-session">
                  <span class="summary-session__label">
                    <svg
                      viewBox="0 0 16 16"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                      class="summary-session__icon"
                    >
                      <circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.25" />
                      <path d="M8 4.5V8L10.5 9.5" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" />
                    </svg>
                    截止 {{ formattedSessionExpiresAt }}
                  </span>
                  <span class="summary-session__countdown" :class="{ 'is-expired': sessionExpired }">
                    {{ sessionCountdownText }}
                  </span>
                </div>
              </div>

              <div class="summary-list">
                <div class="summary-item">
                  <span class="summary-item__label">账单号</span>
                  <strong class="summary-item__value font-mono">{{ resolveInvoiceNo(detail) }}</strong>
                </div>
                <div class="summary-item">
                  <span class="summary-item__label">账单类型</span>
                  <strong class="summary-item__value">{{ detail.type_label || '--' }}</strong>
                </div>
                <div class="summary-item summary-status-row">
                  <span class="summary-item__label">状态</span>
                  <status-tag
                    class="summary-status-tag"
                    :status-map="INVOICE_STATUS_MAP"
                    :status="Number(detail.status)"
                  />
                </div>

                <div class="summary-divider"></div>

                <!-- 优惠金额展示规范 §1.3 / §1.5：账单只回答付了多少钱、怎么付的、关联哪张账单与哪笔支付单。
                     原价与逐项优惠属于订单详情的费用明细区块，账单侧不出现任何金额拆分，
                     这里只留实际支付金额一个金额位。 -->
                <div class="summary-item summary-paid-row">
                  <span class="summary-item__label">实际支付金额</span>
                  <strong class="summary-item__value">{{ formatMoneyText(detail.paid_amount) }}</strong>
                </div>
              </div>
            </t-card>

            <t-card v-if="showPayActions" class="pay-work-card" :bordered="false">
              <div class="pay-work-head">
                <div>
                  <h2>选择支付方式</h2>
                  <p>确认渠道后继续完成当前账单支付</p>
                </div>
              </div>

              <t-alert
                v-if="!hasPayMethods"
                theme="warning"
                message="当前账单暂无可用支付方式，请联系客服开通后再完成支付。"
              />

              <div v-if="hasPayMethods" class="pay-method-list">
                <button
                  v-for="method in payMethodViews"
                  :key="method.key"
                  type="button"
                  class="pay-method-card"
                  :class="{ 'is-active': method.isActive }"
                  :disabled="!canPay || paying"
                  :aria-pressed="method.isActive"
                  :aria-label="method.name"
                  :title="method.name"
                  @click="selectPayMethod(method.key)"
                >
                  <span class="pay-method-card__icon">
                    <component :is="method.icon" />
                  </span>
                  <span class="pay-method-card__text">
                    <strong>{{ method.name }}</strong>
                    <small>{{ method.subLabel }}</small>
                  </span>
                  <span class="pay-method-card__check">
                    <check-circle-icon v-if="method.isActive" />
                    <span v-else />
                  </span>
                </button>
              </div>

              <div v-if="hasPayMethods && showBalanceDeductionOption" class="deduction-panel">
                <div class="deduction-panel__head">
                  <strong>余额抵扣</strong>
                  <span>最多可抵扣 ¥{{ autoDeductionAmountText }}</span>
                </div>
                <t-checkbox v-model="allowBalanceDeduction" @change="handleDeductionToggle">
                  支付宝支付时使用余额抵扣
                </t-checkbox>
                <div v-if="allowBalanceDeduction" class="deduction-summary">
                  <span>当前余额 {{ balanceText }}</span>
                  <span>预计抵扣 ¥{{ autoDeductionAmountText }}</span>
                  <span>支付宝待付 ¥{{ estimatedAlipayAmountText }}</span>
                </div>
              </div>

              <div class="pay-actions">
                <t-button
                  v-if="hasPayMethods && selectedPayMethod === 'balance'"
                  theme="primary"
                  size="large"
                  :loading="paying"
                  :disabled="!canPay"
                  @click="handlePayByBalance"
                >
                  支付
                </t-button>
                <t-button
                  v-else-if="hasPayMethods && selectedPayMethod && selectedPayMethod !== 'free'"
                  theme="primary"
                  size="large"
                  :loading="paying"
                  :disabled="!canPay"
                  @click="handlePayByAlipay"
                >
                  {{
                    allowBalanceDeduction && balanceAmount >= payableAmount
                      ? '支付'
                      : `生成${selectedPayMethodName}二维码`
                  }}
                </t-button>
                <t-button
                  v-else-if="hasPayMethods && selectedPayMethod === 'free'"
                  theme="primary"
                  size="large"
                  disabled
                >
                  零元账单无需操作
                </t-button>
                <t-button variant="outline" size="large" :disabled="paying || loading" @click="() => loadDetail()"
                  >刷新</t-button
                >
              </div>

              <t-alert v-if="payTip" theme="info" :message="payTip" />
            </t-card>
          </aside>
        </section>
      </template>

      <template #empty-action>
        <t-button variant="outline" @click="router.push('/client/invoices')">返回账单列表</t-button>
      </template>
    </data-state>

    <t-dialog
      v-model:visible="alipayDialogVisible"
      header="支付宝支付"
      width="min(24rem, calc(100vw - 2rem))"
      destroy-on-close
    >
      <div class="dialog-qrcode">
        <qrcode-vue v-if="alipayQrCode" :value="alipayQrCode" :size="180" level="H" render-as="svg" />
      </div>
      <div class="dialog-meta">
        <p>金额 ¥{{ alipayPayableAmount }}</p>
        <p>商家订单号 {{ alipayPaymentNo || '--' }}</p>
      </div>
      <template #footer>
        <t-button theme="primary" :loading="polling" :disabled="!alipayPollingReady" @click="pollAlipayStatus(false)">
          我已完成支付，刷新状态
        </t-button>
      </template>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import { CheckCircleIcon, RefreshIcon } from 'tdesign-icons-vue-next';
import { computed, defineAsyncComponent } from 'vue';

const QrcodeVue = defineAsyncComponent(() => import('qrcode.vue'));

import { INVOICE_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';

import { resolveInvoiceNo, useInvoiceDetail } from '@/domains/finance/useInvoices';
import { resolvePaymentStatusLabel } from '@/domains/finance/useRecords';
import { formatMoneyText } from '@/utils/format';

import {
  isNewPurchaseInvoice,
  orderBillingCycle,
  paymentMethodIcon,
  paymentOptionKey,
  renewFlowItems,
  renewServiceId,
  renewServiceName,
  useInvoiceDetailHelpers,
} from './composables/useInvoiceDetailHelpers';

const {
  router,
  loading,
  canceling,
  paying,
  polling,
  detail,
  loadError,
  loadErrorText,
  selectedPayMethod,
  allowBalanceDeduction,
  alipayDialogVisible,
  alipayQrCode,
  alipayPaymentNo,
  appliedDeductionAmountText,
  hasAppliedBalanceDeduction,
  alipayPayableAmount,
  payMethods,
  hasPayMethods,
  canPay,
  alipayPollingReady,
  balanceAmount,
  payableAmount,
  balanceText,
  autoDeductionAmountText,
  estimatedAlipayAmountText,
  selectedPayMethodName,
  showBalanceDeductionOption,
  showPayActions,
  payTip,
  loadDetail,
  handleCancel,
  handlePayByBalance,
  handlePayByAlipay,
  handleDeductionToggle,
  pollAlipayStatus,
  selectPayMethod,
} = useInvoiceDetail();

const {
  sessionExpiresAt,
  formattedSessionExpiresAt,
  sessionExpired,
  sessionCountdownText,
  isRenewInvoiceView,
  renewInfoItemsView,
  pricingItemsView,
  productPathView,
} = useInvoiceDetailHelpers(detail);

/**
 * 支付方式视图模型：原先每个 method 在模板里被 paymentOptionKey 调用 5 次
 * （key、选中态、aria-pressed、点击参数、勾选图标），换一次列表引用就要重算一遍。
 */
const payMethodViews = computed(() =>
  payMethods.value.map((method) => {
    const key = paymentOptionKey(method);

    // 用户端只展示渠道名（channel_name），插件名（name）禁止上屏；
    // 主标题已是渠道名，副标题不再重复它，统一为支付方式说明。
    return {
      key,
      name: method.channel_name || method.label || method.key || '支付',
      subLabel: '扫码支付',
      icon: paymentMethodIcon(method),
      isActive: selectedPayMethod.value === key,
    };
  }),
);
</script>
<style src="./index.less" lang="less" scoped></style>
