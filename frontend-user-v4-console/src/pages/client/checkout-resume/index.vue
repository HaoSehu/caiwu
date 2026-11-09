<template>
  <section class="checkout-resume-page">
    <t-card class="checkout-resume-card" :bordered="false">
      <loading-state :loading="status === 'loading'" text="正在创建账单">
        <div class="checkout-resume-state">
          <t-tag :theme="statusTheme" variant="light">{{ statusLabel }}</t-tag>
          <h1>{{ title }}</h1>
          <p>{{ description }}</p>

          <div v-if="payloadRows.length" class="checkout-payload">
            <div v-for="item in payloadRows" :key="item.label" class="checkout-payload__row">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>

          <div class="checkout-actions">
            <t-button
              v-if="status === 'confirm'"
              theme="primary"
              :loading="retrying"
              @click="resumeCheckout"
            >
              确认创建账单
            </t-button>
            <t-button v-if="status === 'error'" theme="primary" :loading="retrying" @click="resumeCheckout">
              重新创建账单
            </t-button>
            <t-button v-if="status === 'error'" variant="outline" @click="backToProducts">
              返回商品页重新选择
            </t-button>
            <t-button v-if="status === 'empty'" theme="primary" @click="openProducts">去选购产品</t-button>
            <t-button v-if="status !== 'loading' && status !== 'confirm'" variant="outline" @click="openInvoices">
              前往资金中心
            </t-button>
          </div>
        </div>
      </loading-state>
    </t-card>
  </section>
</template>
<script setup lang="ts">
import LoadingState from '@caiwu/shared/user-v3/components/LoadingState.vue';
import type { PendingWebsiteCheckout } from '@caiwu/shared/utils/websiteCheckout';
import {
  clearPendingWebsiteCheckout,
  decodePendingWebsiteCheckout,
  getPendingWebsiteCheckout,
  savePendingWebsiteCheckout,
} from '@caiwu/shared/utils/websiteCheckout';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import clientApi from '@/api/client';
import { formatBillingCycle } from '@/domains/finance/useRecords';
import { getErrorMessage } from '@/utils/error';

type ResumeStatus = 'loading' | 'empty' | 'error' | 'confirm';

const route = useRoute();
const router = useRouter();

const status = ref<ResumeStatus>('loading');
const retrying = ref(false);
const errorMessage = ref('');
const pendingCheckout = ref<PendingWebsiteCheckout | null>(null);

const orderPayload = computed(() => {
  const payload = pendingCheckout.value?.orderPayload;
  return payload && typeof payload === 'object' ? payload : null;
});

const statusTheme = computed(() => {
  if (status.value === 'error') return 'danger';
  if (status.value === 'empty') return 'warning';
  if (status.value === 'confirm') return 'warning';
  return 'primary';
});

const statusLabel = computed(() => {
  if (status.value === 'error') return '创建失败';
  if (status.value === 'empty') return '等待创建账单';
  if (status.value === 'confirm') return '待确认';
  return '创建账单中';
});

const title = computed(() => {
  if (status.value === 'error') return '恢复购买信息失败';
  if (status.value === 'empty') return '没有待恢复的购买信息';
  if (status.value === 'confirm') return '请确认购买信息';
  return '正在创建账单';
});

const description = computed(() => {
  if (status.value === 'error') {
    return errorMessage.value || '请稍后重试，或前往资金中心查看账单记录。';
  }
  if (status.value === 'empty') {
    return '请从产品页重新选择规格并创建账单。';
  }
  if (status.value === 'confirm') {
    // URL 查询串是外部可写输入：链接被转发/重放时不能在对方不知情的情况下自动建单，
    // 必须由当前用户看过购买信息后主动确认（M-12）。
    return '链接中带有待恢复的购买信息，确认后将创建账单并进入支付页。请核对下方信息后再提交。';
  }
  return '创欧云正在恢复你刚才的购买信息，请不要关闭页面。';
});

const payloadRows = computed(() => {
  const payload = orderPayload.value || {};
  const rows = [
    { label: '来源', value: pendingCheckout.value?.source },
    { label: '产品', value: payload.product_id },
    { label: '周期', value: formatBillingCycle(payload.billing_cycle) },
    { label: '数量', value: payload.quantity },
  ];

  return rows
    .filter((item) => item.value !== undefined && item.value !== null && String(item.value).trim() !== '')
    .map((item) => ({ label: item.label, value: String(item.value) }));
});

/**
 * 从 URL 读取待使用的券 id。
 *
 * 只做格式校验（正整数）：**券的归属、可用性与最终抵扣金额一律以服务端为准**，
 * 前端不据此做任何价格或权限判断——即便被构造出不属于当前账号的 id，
 * 服务端建单时也会拒绝或按服务端结果计费。
 */
function resolveQueryPendingCouponId() {
  const raw = Array.isArray(route.query.pending_coupon_id)
    ? route.query.pending_coupon_id[0]
    : route.query.pending_coupon_id;
  const id = Number(raw || 0);
  return Number.isInteger(id) && id > 0 ? id : 0;
}

function applyPendingCouponFromQuery(payload: PendingWebsiteCheckout | null) {
  const couponId = resolveQueryPendingCouponId();

  if (!couponId || !payload?.orderPayload || typeof payload.orderPayload !== 'object') {
    return payload;
  }

  return {
    ...payload,
    orderPayload: {
      ...payload.orderPayload,
      user_coupon_id: couponId,
    },
  };
}

/** 本次购买信息是否来自 URL 查询串（外部可写，需要人工确认后才建单）。 */
const payloadFromQuery = ref(false);

function resolvePendingCheckout() {
  const queryPayload = typeof route.query.checkout_payload === 'string' ? route.query.checkout_payload : '';
  const decodedPayload = applyPendingCouponFromQuery(decodePendingWebsiteCheckout(queryPayload));

  if (decodedPayload) {
    savePendingWebsiteCheckout(decodedPayload);
    payloadFromQuery.value = true;
    pendingCheckout.value = decodedPayload;
    clearCheckoutQuery();
    return;
  }

  const storedPayload = applyPendingCouponFromQuery(getPendingWebsiteCheckout());
  if (storedPayload) {
    savePendingWebsiteCheckout(storedPayload);
  }
  pendingCheckout.value = storedPayload;
}

/**
 * 解析出载荷后清掉 URL 查询串：链接被刷新/转发时不再重复携带购买参数，
 * 避免同一条链接在别的会话里被再次执行。
 */
function clearCheckoutQuery() {
  const query = { ...route.query };
  const hadPayload = 'checkout_payload' in query;
  delete query.checkout_payload;
  delete query.pending_coupon_id;
  if (!hadPayload) {
    return;
  }
  void router.replace({ path: route.path, query, hash: route.hash });
}

function openProducts() {
  router.push('/products');
}

/**
 * 支付窗口/报价失效后的退路：本地待建单载荷已不可用，清掉并带商品与周期参数
 * 回官网产品页重新选择（M-21）。
 */
function backToProducts() {
  const payload = orderPayload.value || {};
  const productId = Number(payload.product_id || 0);
  const billingCycle = String(payload.billing_cycle || '').trim();
  clearPendingWebsiteCheckout();
  pendingCheckout.value = null;
  if (productId > 0) {
    const query: Record<string, string> = {};
    if (billingCycle) query.billing_cycle = billingCycle;
    router.push({ path: `/products/${productId}`, query });
    return;
  }
  openProducts();
}

function openInvoices() {
  router.push('/client/invoices');
}

async function resumeCheckout() {
  if (!orderPayload.value) {
    status.value = 'empty';
    return;
  }
  // 并发守卫：重复点击「重新创建账单」或挂载期间的自动重试不会叠加建单请求
  if (retrying.value) return;

  retrying.value = true;
  status.value = 'loading';
  errorMessage.value = '';

  try {
    const response = await clientApi.createInvoice(orderPayload.value, {
      headers: pendingCheckout.value?.idempotencyKey
        ? { 'X-Idempotency-Key': pendingCheckout.value.idempotencyKey }
        : undefined,
    });
    const invoiceId = Number(response.data?.id || response.data?.invoice?.id || 0);
    clearPendingWebsiteCheckout();
    MessagePlugin.success('账单创建成功，正在跳转');
    await router.replace(invoiceId > 0 ? `/client/invoices/${invoiceId}/pay` : '/client/invoices');
  } catch (error) {
    status.value = 'error';
    errorMessage.value = getErrorMessage(error, '账单创建失败，请稍后重试');
  } finally {
    retrying.value = false;
  }
}

onMounted(async () => {
  resolvePendingCheckout();
  if (!orderPayload.value) {
    status.value = 'empty';
    return;
  }

  if (payloadFromQuery.value) {
    status.value = 'confirm';
    return;
  }

  await resumeCheckout();
});
</script>
<style scoped lang="less">
.checkout-resume-page {
  display: flex;
  align-items: center;
  justify-content: center;
  // O-19：顶栏实际高度是 comp-size-xxxl（56，见 style/layout.less:131-132/195/217 与 FrameContent.vue:64），
  // 这里原先多用了两个 x 的更大档令牌（72）会多减 16，页面底部凭空少一截。
  min-height: calc(100vh - var(--td-comp-size-xxxl));
  // padding 由 Starter 布局层统一提供
}

.checkout-resume-card {
  width: min(100%, 44rem);
  background: var(--td-bg-color-container);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.checkout-resume-state {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  align-items: center;
  padding: var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-xl);
  text-align: center;

  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-large);
  }

  p {
    max-width: 34rem;
    margin: 0;
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-medium);
    line-height: var(--td-line-height-body-medium);
  }
}

.checkout-payload {
  display: grid;
  width: min(100%, 28rem);
  overflow: hidden;
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
}

.checkout-payload__row {
  display: grid;
  grid-template-columns: minmax(6rem, 0.4fr) minmax(0, 1fr);
  gap: var(--td-comp-margin-s);
  padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
  border-bottom: thin solid var(--td-component-stroke);

  &:last-child {
    border-bottom: 0;
  }

  span {
    color: var(--td-text-color-secondary);
    font: var(--td-font-body-small);
    text-align: left;
  }

  strong {
    min-width: 0;
    overflow-wrap: anywhere;
    color: var(--td-text-color-primary);
    font: var(--td-font-body-small);
    font-weight: 600;
    text-align: right;
  }
}

.checkout-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  justify-content: center;
}

@media (max-width: @screen-mobile-max) {
  .checkout-resume-page {
    align-items: stretch;
    min-height: auto;
  }

  .checkout-resume-state {
    align-items: stretch;
    padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-m);
    text-align: left;
  }

  .checkout-payload {
    width: 100%;
  }

  .checkout-payload__row {
    grid-template-columns: 1fr;
  }

  .checkout-payload__row strong {
    text-align: left;
  }

  .checkout-actions :deep(.t-button) {
    width: 100%;
  }
}
</style>
