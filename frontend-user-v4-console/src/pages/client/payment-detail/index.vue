<template>
  <section class="payment-detail-page">
    <!-- 面包屑导航 -->
    <t-breadcrumb class="payment-breadcrumb">
      <t-breadcrumb-item @click="router.push('/client/dashboard')">财务中心</t-breadcrumb-item>
      <t-breadcrumb-item @click="router.push('/client/payments')">充值记录</t-breadcrumb-item>
      <t-breadcrumb-item>充值详情</t-breadcrumb-item>
    </t-breadcrumb>

    <!-- 操作栏 -->
    <div class="detail-toolbar">
      <t-button variant="outline" @click="router.push('/client/payments')">返回列表</t-button>
      <div class="detail-toolbar__actions">
        <t-button variant="outline" :loading="loading" @click="loadPayment">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>
    </div>

    <data-state
      :loading="loading"
      :error="loadError"
      :error-text="loadErrorText"
      :empty="!detail"
      description="未找到该充值记录"
      @retry="loadPayment"
    >
      <template v-if="detail">
        <!-- 详情卡片 -->
        <t-card :bordered="false" class="payment-detail-card">
          <template #title>支付信息</template>
          <div class="detail-kv-grid">
            <div class="detail-kv-item">
              <span>商家订单号</span>
              <strong>{{ detail.payment_no || '--' }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>第三方订单号</span>
              <strong>{{ detail.trade_no || '--' }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>支付金额</span>
              <strong>¥{{ formatMoney(detail.amount) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>支付渠道</span>
              <strong>{{ detail.gateway_label || detail.gateway_key || detail.gateway || '--' }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>支付状态</span>
              <status-tag :status-map="PAYMENT_STATUS_MAP" :status="Number(detail.status)" />
            </div>
            <div class="detail-kv-item">
              <span>创建时间</span>
              <strong>{{ detail.created_at || '--' }}</strong>
            </div>
            <div v-if="detail.paid_at" class="detail-kv-item">
              <span>支付时间</span>
              <strong>{{ detail.paid_at }}</strong>
            </div>
          </div>
        </t-card>

        <!-- 关联账单 -->
        <t-card v-if="detail.invoice" :bordered="false" class="payment-related-card">
          <template #title>关联账单</template>
          <div class="detail-kv-grid">
            <div class="detail-kv-item">
              <span>账单号</span>
              <t-button
                variant="text"
                theme="primary"
                size="small"
                @click="router.push(`/client/invoices/${detail.invoice.id}`)"
              >
                {{ detail.invoice.invoice_no || '--' }}
              </t-button>
            </div>
            <div class="detail-kv-item">
              <span>账单状态</span>
              <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(detail.invoice.status)" />
            </div>
            <div v-if="detail.invoice.type" class="detail-kv-item">
              <span>账单类型</span>
              <strong>{{ invoiceTypeDisplay(detail.invoice.type) }}</strong>
            </div>
            <div class="detail-kv-item">
              <span>账单金额</span>
              <strong>¥{{ formatMoney(detail.invoice.amount) }}</strong>
            </div>
            <div v-if="detail.invoice.paid_amount" class="detail-kv-item">
              <span>已付金额</span>
              <strong>¥{{ formatMoney(detail.invoice.paid_amount) }}</strong>
            </div>
          </div>
        </t-card>
      </template>

      <template #empty-action>
        <t-button variant="outline" @click="router.push('/client/payments')">返回充值记录</t-button>
      </template>
    </data-state>
  </section>
</template>
<script setup lang="ts">
import { INVOICE_STATUS_MAP, PAYMENT_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import { RefreshIcon } from 'tdesign-icons-vue-next';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { resolveInvoiceTypeLabel } from '@/domains/finance/constants';
import { formatMoney } from '@/domains/finance/useRecords';
import type { PaymentRecord } from '@/types/client';
import { getErrorMessage } from '@/utils/error';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const detail = ref<PaymentRecord | null>(null);
const loadError = ref(false);
const loadErrorText = ref('');

// 路由参数响应式：同路由换 id 时组件会被复用，必须靠 watch 重新拉取
const paymentId = computed(() => Number(route.params.id || 0));

async function loadPayment() {
  const id = paymentId.value;
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
    const res = await clientApi.paymentDetail(id);
    detail.value = res.data || null;
  } catch (error: unknown) {
    // 错误交给 DataState 呈现（含重试），不再静默置空导致用户只看到空态
    detail.value = null;
    loadError.value = true;
    loadErrorText.value = getErrorMessage(error, '充值详情加载失败');
  } finally {
    loading.value = false;
  }
}

function invoiceTypeDisplay(type?: string) {
  return resolveInvoiceTypeLabel(type);
}

watch(
  paymentId,
  () => {
    void loadPayment();
  },
  { immediate: true },
);
</script>
<style scoped lang="less">
.payment-breadcrumb {
  margin-bottom: var(--td-comp-margin-m);
}

.detail-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--td-comp-margin-m);

  &__actions {
    display: flex;
    gap: var(--td-comp-margin-s);
  }
}

.payment-detail-card,
.payment-related-card {
  margin-bottom: var(--td-comp-margin-m);
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

.detail-empty {
  padding: var(--td-comp-paddingTB-xl) 0;
  text-align: center;
}

@media (max-width: @screen-xs-rem) {
  .detail-kv-grid {
    grid-template-columns: 1fr;
  }

  .detail-toolbar {
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    align-items: flex-start;
  }
}
</style>
