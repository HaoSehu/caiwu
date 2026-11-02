<template>
  <section class="invoice-detail-page">
    <!-- 面包屑导航 -->
    <t-breadcrumb class="invoice-breadcrumb">
      <!-- O-23：用组件自带的 to 而不是 @click，这样渲染成可聚焦的 <a>。 -->
      <t-breadcrumb-item to="/client/dashboard">财务中心</t-breadcrumb-item>
      <t-breadcrumb-item to="/client/invoices">账单记录</t-breadcrumb-item>
      <t-breadcrumb-item>账单详情</t-breadcrumb-item>
    </t-breadcrumb>

    <!-- 操作栏 -->
    <div class="detail-toolbar">
      <t-button variant="outline" @click="router.push('/client/invoices')">返回列表</t-button>
      <div class="detail-toolbar__actions">
        <t-button variant="outline" :loading="loading" @click="loadInvoice">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
        <t-button
          v-if="detail && isPayable(detail)"
          theme="primary"
          @click="router.push(`/client/invoices/${detail.id}/pay`)"
        >
          去支付
        </t-button>
        <!-- 三态齐全：可支付 / 窗口已关闭 / 状态本身不支持支付（已支付、已取消、已退款）。
             原先只有前两态，status !== 0 时操作区什么都不显示，用户看不出「为什么没有去支付」。 -->
        <span v-else-if="detail && isPayWindowClosed(detail)" class="invoice-window-closed">支付窗口已关闭</span>
        <span v-else-if="detail" class="invoice-window-closed">{{
          resolveNonPayableInvoiceMessage(Number(detail.status))
        }}</span>
      </div>
    </div>

    <data-state
      :loading="loading"
      :error="loadError"
      :error-text="loadErrorText"
      :empty="!detail"
      description="未找到该账单"
      @retry="loadInvoice"
    >
      <template v-if="detail">
        <!-- I-03：账单详情与支付页是同一张单据的两个面，金额必须都站在同一个位置。
             金额口径按 docs/设计文档/前端/优惠金额展示规范-2026-10-31.md §1.3/§1.5：
             账单只回答「付了多少钱、怎么付的、关联哪张账单与哪笔支付单」，摘要条只呈现
             「实际支付金额」paid_amount，与订单页、账单列表页同源同值；
             原价与逐项优惠属于订单详情的费用明细区块，账单侧不出现任何金额拆分。 -->
        <section class="invoice-amount-summary" aria-label="账单金额摘要">
          <div class="invoice-amount-summary__main">
            <span class="invoice-amount-summary__label">实际支付金额</span>
            <strong class="invoice-amount-summary__value">{{ formatMoneyText(detail.paid_amount) }}</strong>
          </div>
          <div class="invoice-amount-summary__meta">
            <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(detail.status)" />
            <span class="invoice-amount-summary__no">{{ detail.invoice_no || '--' }}</span>
          </div>
        </section>

        <!-- 标签页 -->
        <t-card :bordered="false" class="invoice-detail-tabs-card">
          <t-tabs v-model="activeTab">
            <t-tab-panel value="basic" label="基础信息">
              <div class="detail-kv-grid">
                <div class="detail-kv-item">
                  <span>账单号</span>
                  <strong>{{ detail.invoice_no || '--' }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>账单类型</span>
                  <strong>{{ detail.type_label || typeLabel(detail.type) }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>账单状态</span>
                  <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(detail.status)" />
                </div>
                <div class="detail-kv-item">
                  <span>计费周期</span>
                  <strong>{{ billingCycleLabel(detail.billing_cycle) }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>创建时间</span>
                  <strong>{{ formatDateTime(detail.created_at) }}</strong>
                </div>
                <!-- 截止时间取 invoices.due_date，是纯日期列（schema 注释「账单到期日」），
                     不走 formatDateTime，否则会被补成 2026-11-07 00:00:00。 -->
                <div v-if="detail.due_date" class="detail-kv-item">
                  <span>截止时间</span>
                  <strong>{{ detail.due_date }}</strong>
                </div>
                <div v-if="detail.paid_at" class="detail-kv-item">
                  <span>支付时间</span>
                  <strong>{{ formatDateTime(detail.paid_at) }}</strong>
                </div>
              </div>
            </t-tab-panel>

            <t-tab-panel value="related" label="关联订单">
              <!-- 关联订单 -->
              <section v-if="detail.order" class="related-section">
                <h4>关联订单</h4>
                <div class="detail-kv-grid">
                  <div class="detail-kv-item">
                    <span>订单号</span>
                    <t-button
                      variant="text"
                      theme="primary"
                      size="small"
                      @click="router.push(`/client/orders/${detail.order.id}`)"
                    >
                      {{ detail.order.order_no || '--' }}
                    </t-button>
                  </div>
                  <div class="detail-kv-item">
                    <span>订单类型</span>
                    <strong>{{ detail.order.type_label || '--' }}</strong>
                  </div>
                  <div class="detail-kv-item">
                    <span>订单状态</span>
                    <status-tag :status-map="ORDER_STATUS_MAP" :status="Number(detail.order.status)" />
                  </div>
                </div>
              </section>
              <t-empty v-else class="related-empty-state" size="small" description="暂无关联订单" />
            </t-tab-panel>

            <t-tab-panel value="payments" label="关联支付">
              <div v-if="paymentRecords.length" class="payment-list">
                <t-table
                  :data="paymentRecords"
                  :columns="paymentColumns"
                  :loading="loading"
                  :pagination="null"
                  row-key="id"
                  size="small"
                  hover
                >
                  <template #amount="{ row }">
                    <span class="payment-money">{{ formatMoneyText(row.amount) }}</span>
                  </template>
                  <template #status="{ row }">
                    <status-tag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
                  </template>
                  <template #gateway="{ row }">{{ paymentGatewayDisplay(row) }}</template>
                </t-table>
              </div>
              <t-empty v-else class="related-empty-state" size="small" description="暂无支付记录" />
            </t-tab-panel>
          </t-tabs>
        </t-card>
      </template>

      <template #empty-action>
        <t-button variant="outline" @click="router.push('/client/invoices')">返回账单列表</t-button>
      </template>
    </data-state>
  </section>
</template>
<script setup lang="ts">
import { INVOICE_STATUS_MAP, ORDER_STATUS_MAP, PAYMENT_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import { RefreshIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { resolveInvoiceTypeLabel } from '@/domains/finance/constants';
import { isPayableInvoice, resolveNonPayableInvoiceMessage } from '@/domains/finance/useInvoices';
import { formatBillingCycle } from '@/domains/finance/useRecords';
import type { InvoiceRecord } from '@/types/client';
import { getErrorMessage } from '@/utils/error';
import { formatDateTime, formatMoneyText } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const detail = ref<InvoiceRecord | null>(null);
const activeTab = ref('basic');

// 路由参数响应式：同路由换 id 时组件会被复用，必须靠 watch 重新拉取
const invoiceId = computed(() => Number(route.params.id || 0));
const loadError = ref(false);
const loadErrorText = ref('');

// 支付入口与支付页同一判据：后端在账单详情里下发 payment_security.can_pay（续费账单与挂账单跟随
// due_date，其余按 5 分钟支付会话窗口），窗口已关闭时不再给出必然被拦下的支付入口。
function isPayable(row: InvoiceRecord | null) {
  if (!isPayableInvoice(row)) return false;
  return row?.payment_security?.can_pay !== false;
}

function isPayWindowClosed(row: InvoiceRecord | null) {
  return isPayableInvoice(row) && row?.payment_security?.can_pay === false;
}

function billingCycleLabel(value?: string) {
  return formatBillingCycle(value);
}

function typeLabel(type?: string) {
  return resolveInvoiceTypeLabel(type);
}

const paymentRecords = computed(() => {
  const payments = detail.value?.payments;
  if (!Array.isArray(payments)) return [];
  return payments;
});

function paymentGatewayDisplay(row: Record<string, unknown>) {
  return String(row.gateway_label || row.gateway_key || row.gateway || '--');
}

const paymentColumns: PrimaryTableCol[] = [
  { colKey: 'payment_no', title: '商家订单号', minWidth: '10rem' },
  { colKey: 'trade_no', title: '第三方订单号', minWidth: '12rem' },
  { colKey: 'gateway', title: '渠道', width: '8rem' },
  { colKey: 'amount', title: '支付金额', width: '8rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'paid_at', title: '支付时间', minWidth: '12rem' },
];

// 请求序号：同路由快速换 id 或反复点重试时，只让最后一次响应落地，
// 避免先发后到的旧响应覆盖新详情（照抄 useRecords 的 requestSeq 范式）。
let requestSeq = 0;

async function loadInvoice() {
  const id = invoiceId.value;
  const seq = ++requestSeq;

  if (!id) {
    detail.value = null;
    loadError.value = false;
    loadErrorText.value = '';
    return;
  }
  loading.value = true;
  loadError.value = false;
  loadErrorText.value = '';
  try {
    const res = await clientApi.invoiceDetail(id);
    if (seq !== requestSeq) return;
    detail.value = res.data || null;
  } catch (error: unknown) {
    if (seq !== requestSeq) return;
    // 错误交给 DataState 呈现（含重试），不再静默置空导致用户只看到空态
    detail.value = null;
    loadError.value = true;
    loadErrorText.value = getErrorMessage(error, '账单详情加载失败');
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
}

watch(
  invoiceId,
  () => {
    void loadInvoice();
  },
  { immediate: true },
);
</script>
<style scoped lang="less">
// O-20：根容器统一管纵向间距，子块不再各自带下边距。
.invoice-detail-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.invoice-breadcrumb {
  margin-bottom: 0;
}

.detail-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0;

  &__actions {
    display: flex;
    gap: var(--td-comp-margin-s);
  }
}

// I-03：紧凑金额摘要条（不是统计卡：单行、贴左、与页面同一基线）
.invoice-amount-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--td-comp-margin-s) var(--td-comp-margin-l);
  // 左右内边距取 24px 与下方卡片正文对齐：卡片正文左基线 = 卡片 1px 边框 + 24px 内边距 = 距页 25px，
  // 摘要条用 --td-comp-paddingLR-l（20px）+ 1px 边框会落在 21px，上下两块文字左边缘差 4px。
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-xl);
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);

  &__main {
    display: flex;
    align-items: baseline;
    gap: var(--td-comp-margin-s);
  }

  &__label {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
  }

  &__value {
    color: var(--td-brand-color);
    font: var(--td-font-headline-small);
    font-variant-numeric: tabular-nums;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--td-comp-margin-s);
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
  }

  &__no {
    font-variant-numeric: tabular-nums;
  }
}

// O-22：与控制台统一的移动断点对齐（原 @screen-xs-rem 只到 40rem）。
@media (max-width: @screen-mobile-max) {
  .invoice-amount-summary {
    flex-direction: column;
    align-items: flex-start;
  }
}

.invoice-detail-tabs-card {
  min-height: 18.75rem;
}

.detail-kv-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-m) 0;
}

.detail-kv-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--td-comp-margin-xxs);

  span {
    font-size: var(--td-font-size-body-small);
    color: var(--td-text-color-secondary);
  }

  strong {
    font-weight: 600;
    color: var(--td-text-color-primary);
    word-break: break-all;
    font-variant-numeric: tabular-nums;
  }
}

.payment-money {
  font: var(--td-font-body-medium);
  font-weight: 600;
  color: var(--td-text-color-primary);
  font-variant-numeric: tabular-nums;
}

.related-section {
  padding: var(--td-comp-paddingTB-m) 0;
  border-bottom: thin solid var(--td-component-stroke);

  &:last-of-type {
    border-bottom: none;
  }

  h4 {
    font-size: var(--td-font-size-body-medium);
    font-weight: 600;
    color: var(--td-text-color-primary);
    margin-bottom: var(--td-comp-margin-xs);
  }
}

// 空态统一交给 TDesign 的 t-empty（C-16），这里只补上下留白，不重绘视觉
.related-empty-state {
  padding: var(--td-comp-paddingTB-l) 0;
}

.payment-list {
  padding: var(--td-comp-paddingTB-m) 0;
}

@media (max-width: @screen-mobile-max) {
  .detail-kv-grid {
    grid-template-columns: 1fr;
  }

  .detail-toolbar {
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    align-items: flex-start;
  }
}

.invoice-window-closed {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
  white-space: nowrap;
}
</style>
