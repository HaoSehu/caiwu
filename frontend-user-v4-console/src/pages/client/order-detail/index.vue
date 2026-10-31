<template>
  <section class="order-detail-page">
    <!-- 面包屑导航 -->
    <t-breadcrumb class="order-breadcrumb">
      <!-- O-23：用组件自带的 to 而不是 @click。@click 版渲染成 <span>，键盘不可聚焦、
           读屏不当链接；t-breadcrumb-item 有 to 时会渲染 <a> 并自行 router.push。 -->
      <t-breadcrumb-item to="/client/dashboard">财务中心</t-breadcrumb-item>
      <t-breadcrumb-item to="/client/orders">订单记录</t-breadcrumb-item>
      <t-breadcrumb-item>订单详情</t-breadcrumb-item>
    </t-breadcrumb>

    <!-- 操作栏 -->
    <div class="detail-toolbar">
      <t-button variant="outline" @click="router.push('/client/orders')">返回列表</t-button>
      <div class="detail-toolbar__actions">
        <t-button variant="outline" :loading="loading" @click="loadDetail(orderId)">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
        <t-button
          v-if="detail && canPayOrderRow(detail)"
          theme="primary"
          @click="router.push(`/client/invoices/${detail.invoice?.id}/pay`)"
        >
          去支付
        </t-button>
        <span v-else-if="detail && isPayWindowClosedOrderRow(detail)" class="order-window-closed">支付窗口已关闭</span>
        <span v-else-if="detail && isPayUnavailableOrderRow(detail)" class="order-window-closed">无可支付账单</span>
        <t-button
          v-if="detail && Number(detail.status) === 0"
          theme="danger"
          variant="outline"
          :loading="canceling"
          @click="cancelOrder()"
        >
          取消订单
        </t-button>
      </div>
    </div>

    <data-state
      :loading="loading"
      :error="loadError"
      :error-text="loadErrorText"
      :empty="!detail"
      description="未找到该订单"
      @retry="loadDetail(orderId)"
    >
      <template v-if="detail">
        <!-- 优惠金额展示规范 §1.1/§3.5：实付金额是全页唯一金额位，独立成条置于订单信息区顶部，
             与下方订单信息卡并列成块。不嵌进字段栅格——栅格是「12px 次要标签 + 14px 主文本值」
             的字段区，24px 金额塞进去既撑不开行高，也抢不到层级；也不放进页签内，
             否则切到「产品配置」时金额位消失。 -->
        <div class="order-paid-amount">
          <span class="order-paid-amount__label">实付金额</span>
          <strong class="order-paid-amount__value">{{ formatMoneyText(detail.paid_amount || 0) }}</strong>
        </div>

        <!-- 标签页 -->
        <t-card :bordered="false" class="order-detail-tabs-card">
          <t-tabs v-model="activeTab">
            <t-tab-panel value="basic" label="基础信息">
              <div class="detail-kv-grid">
                <div class="detail-kv-item">
                  <span>订单号</span>
                  <strong>{{ detail.order_no || '--' }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>订单类型</span>
                  <strong>{{ detail.type_label || '--' }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>订单状态</span>
                  <status-tag :status-map="ORDER_STATUS_MAP" :status="Number(detail.status)" />
                </div>
                <div class="detail-kv-item">
                  <span>开通商品</span>
                  <strong>{{ orderProductDisplay(detail) }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>计费周期</span>
                  <strong>{{ billingCycleLabel(detail.billing_cycle) }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>数量</span>
                  <strong>{{ detail.quantity ?? 1 }}</strong>
                </div>
                <div class="detail-kv-item">
                  <span>创建时间</span>
                  <strong>{{ formatDateTime(detail.created_at) }}</strong>
                </div>
                <div v-if="detail.paid_at" class="detail-kv-item">
                  <span>支付时间</span>
                  <strong>{{ formatDateTime(detail.paid_at) }}</strong>
                </div>
                <div v-if="detail.remark" class="detail-kv-item detail-kv-item--wide">
                  <span>备注</span>
                  <strong>{{ detail.remark }}</strong>
                </div>
              </div>
            </t-tab-panel>

            <t-tab-panel v-if="showConfigTab" value="config" label="产品配置">
              <div v-if="configSnapshotView.length" class="order-pricing-block">
                <section class="order-section">
                  <h4 class="order-section-title">配置快照</h4>
                  <div class="snapshot-line-list">
                    <div v-for="item in configSnapshotView" :key="`cfg-${item.label}`" class="snapshot-line-item">
                      <span class="snapshot-line-label">{{ item.label }}</span>
                      <strong class="snapshot-line-value">{{ item.value }}</strong>
                    </div>
                  </div>
                </section>
              </div>
              <div v-if="pricingSnapshotView.length" class="order-pricing-block">
                <section class="order-section">
                  <h4 class="order-section-title">配置定价</h4>
                  <div class="snapshot-line-list">
                    <div v-for="item in pricingSnapshotView" :key="`price-${item.label}`" class="snapshot-line-item">
                      <span class="snapshot-line-label">{{ item.label }}</span>
                      <strong class="snapshot-line-value">{{ item.value }}</strong>
                    </div>
                  </div>
                </section>
              </div>
              <t-empty
                v-if="!configSnapshotView.length && !pricingSnapshotView.length"
                class="related-empty-state"
                size="small"
                description="暂无配置快照"
              />
            </t-tab-panel>

            <t-tab-panel value="related" label="关联信息">
              <!-- 关联账单 -->
              <section v-if="detail.invoice" class="related-section">
                <h4>关联账单</h4>
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
                </div>
              </section>
              <t-empty v-else class="related-empty-state" size="small" description="暂无关联账单" />

              <!-- 关联服务 -->
              <section v-if="detail.service" class="related-section">
                <h4>关联服务</h4>
                <div class="detail-kv-grid">
                  <div class="detail-kv-item">
                    <span>实例ID</span>
                    <strong>{{ detail.service.instance_id || '--' }}</strong>
                  </div>
                  <div class="detail-kv-item">
                    <span>主机名</span>
                    <strong>{{ detail.service.hostname || '--' }}</strong>
                  </div>
                </div>
              </section>
              <t-empty v-else class="related-empty-state" size="small" description="暂无关联服务" />
            </t-tab-panel>
          </t-tabs>
        </t-card>

        <!-- 费用明细：优惠金额展示规范 §1.4 固定为订单详情页面最下方一个区块，
             不隶属任何页签、不随页签切换隐藏。行序固定为
             原价（计算式）→ 优惠券 → 会员折扣；某项没有优惠时整行不渲染、行序顺延。 -->
        <section class="order-fee-detail" aria-label="费用明细">
          <h4 class="order-fee-detail__title">费用明细</h4>
          <dl class="order-fee-detail__list">
            <div class="order-fee-detail__row">
              <dt>原价</dt>
              <dd>{{ catalogPriceText }}</dd>
            </div>
            <div v-if="couponDiscountAmount > 0" class="order-fee-detail__row order-fee-detail__row--discount">
              <dt>{{ couponDiscountLabel }}</dt>
              <dd>{{ formatMoneyText(-couponDiscountAmount) }}</dd>
            </div>
            <div v-if="memberDiscountAmount > 0" class="order-fee-detail__row order-fee-detail__row--discount">
              <dt>{{ memberDiscountLabel }}</dt>
              <dd>{{ formatMoneyText(-memberDiscountAmount) }}</dd>
            </div>
          </dl>
        </section>
      </template>

      <template #empty-action>
        <t-button variant="outline" @click="router.push('/client/orders')">返回订单列表</t-button>
      </template>
    </data-state>
  </section>
</template>
<script setup lang="ts">
import { INVOICE_STATUS_MAP, ORDER_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import { RefreshIcon } from 'tdesign-icons-vue-next';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import {
  canPayOrderRow,
  isPayUnavailableOrderRow,
  isPayWindowClosedOrderRow,
  orderProductDisplay,
  useOrderDetail,
} from '@/domains/finance/useOrders';
import {
  configValueLabelMap,
  flattenSnapshot,
  formatBillingCycle,
  pricingSnapshotItems,
} from '@/domains/finance/useRecords';
import { formatDateTime, formatMoneyText } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const activeTab = ref('basic');
const showConfigTab = computed(() => {
  const type = String(detail.value?.type || '').toLowerCase();
  return ['new', 'normal'].includes(type);
});
const configSnapshotView = computed(() => {
  const row = detail.value;
  if (!row) return [];
  return flattenSnapshot(row.config_snapshot as Record<string, unknown> | undefined, configValueLabelMap(row));
});
const pricingSnapshotView = computed(() => pricingSnapshotItems(detail.value?.config_pricing_snapshot));

// 费用明细区块（docs/设计文档/前端/优惠金额展示规范-2026-10-31.md §1.4/§2.2）：
// 原价是目录价，还原公式与后端 Money::catalogAmountOf 一致——应付价 + 优惠券减免 + 会员折扣减免。
// 优惠只含优惠券与会员折扣两项，余额抵扣、积分、退款冲抵都不属于优惠，不进入本区块。
const couponDiscountAmount = computed(() => Math.max(0, Number(detail.value?.discount || 0)));
const memberDiscountAmount = computed(() => Math.max(0, Number(detail.value?.member_discount_amount || 0)));
const catalogAmount = computed(
  () => Number(detail.value?.amount || 0) + couponDiscountAmount.value + memberDiscountAmount.value,
);

// 计价周期词表：单价后缀与数量量词分开，拼出「单价/周期 × 数量 量词 = 原价」的计算式。
// 月与年沿用规范给的「个月」「年」，其余周期统一用「期」，避免「个半年」这类不通顺的量词。
const CYCLE_UNIT_WORDS: Record<string, { price: string; count: string }> = {
  monthly: { price: '月', count: '个月' },
  quarterly: { price: '季', count: '期' },
  semiannually: { price: '半年', count: '期' },
  semiannual: { price: '半年', count: '期' },
  semi_annually: { price: '半年', count: '期' },
  annually: { price: '年', count: '年' },
  yearly: { price: '年', count: '年' },
  one_time: { price: '', count: '项' },
  onetime: { price: '', count: '项' },
};

// 原价行文案：单价取自下单时的计价快照 unit_total_amount，乘数量后必须与原价对得上才输出计算式；
// 快照缺失或乘积对不上时只输出原价金额，宁可不给计算式也不拼一个自相矛盾的式子。
const catalogPriceText = computed(() => {
  const catalog = catalogAmount.value;
  const catalogText = formatMoneyText(catalog);
  const snapshot = detail.value?.config_pricing_snapshot;
  if (!snapshot || typeof snapshot !== 'object') return catalogText;
  const snap = snapshot as Record<string, unknown>;
  const unitAmount = Number(snap.unit_total_amount);
  const quantity = Number(snap.quantity ?? detail.value?.quantity ?? 1);
  const cycle = String(detail.value?.billing_cycle || snap.billing_cycle || '').toLowerCase();
  const words = CYCLE_UNIT_WORDS[cycle];
  if (!words || !Number.isFinite(unitAmount) || !Number.isFinite(quantity) || quantity <= 0) return catalogText;
  const expected = Math.round(unitAmount * quantity * 100) / 100;
  if (Math.abs(expected - catalog) > 0.005) return catalogText;
  const unitText = words.price ? `${formatMoneyText(unitAmount)}/${words.price}` : formatMoneyText(unitAmount);
  return `${unitText} × ${quantity} ${words.count} = ${catalogText}`;
});

// 优惠券行左侧直接用具名券名（规范 §3.2）；没有券名时退回通用名，不留空标签。
const couponDiscountLabel = computed(() => String(detail.value?.coupon_name || '').trim() || '优惠券');

// 会员折扣行左侧用会员等级名（需求口径「v1 -¥60.00」）；订单接口未下发快照时退回通用名。
const memberDiscountLabel = computed(() => {
  const snapshot = detail.value?.member_discount_snapshot as Record<string, unknown> | null | undefined;
  if (!snapshot || typeof snapshot !== 'object') return '会员折扣';
  return String(snapshot.member_level_name || snapshot.group_name || '').trim() || '会员折扣';
});

const { loading, canceling, detail, loadError, loadErrorText, loadDetail, cancelOrder } = useOrderDetail();

// 路由参数响应式：同路由换 id（详情↔详情跳转）时组件会被复用，必须靠 watch 重新拉取
const orderId = computed(() => Number(route.params.id || 0));

function billingCycleLabel(value?: string) {
  return formatBillingCycle(value);
}

watch(
  orderId,
  (id) => {
    void loadDetail(id);
  },
  { immediate: true },
);
</script>
<style scoped lang="less">
.order-window-closed {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
  white-space: nowrap;
}

// O-20：页面根类原先在 scoped 内没有任何规则，区块间距全靠零散 margin-bottom 拼接。
// 改为根容器统一管纵向间距，子块不再各自带下边距（改一处间距只改一处）。
.order-detail-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

// DataState 把默认插槽整体包进 .data-state 一层，页根容器的 gap 管不到插槽内的子块：
// 实付金额条、订单信息卡、费用明细会首尾相贴，间距全靠零散 margin 拼。
// 给这一层补同一套纵向间距，三块的间距才一致。子组件根元素带父组件作用域标记，scoped 能命中。
.order-detail-page :deep(.data-state) {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.order-breadcrumb {
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

.order-detail-tabs-card {
  min-height: 18.75rem;
}

// 实付金额条（规范 §1.1/§3.5）：订单详情唯一金额位，与管理端同形态——
// 容器占满内容宽、浅灰底 + 1px 边框 + 圆角，金额品牌色 24px/600，
// 与「12px 次要标签 + 14px 主文本值」的字段区拉开一个完整层级。
// 不设 margin-bottom：根容器 .order-detail-page 的 gap 统一管纵向间距（见 O-20），
// 这里再写下边距会与 gap 叠加，和下方卡片的间距比别处大一倍。
// 左右内边距取 24px：与下方卡片正文的左基线对齐（卡片 1px 边框 + 24px 内边距），
// 用 16px 会比卡片文字左移 8px，上下两块文字的左边缘错开肉眼可见。
.order-paid-amount {
  display: flex;
  align-items: baseline;
  gap: var(--td-comp-margin-s);
  padding: 14px 24px;
  background: var(--td-bg-color-secondarycontainer);
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-medium);

  &__label {
    color: var(--td-text-color-secondary);
    font-size: var(--td-font-size-body-medium);
  }

  &__value {
    color: var(--td-brand-color);
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
  }
}

// 费用明细区块（规范 §1.4/§3.5）：与管理端同形态的独立卡片，与上方订单信息卡保留页面统一区块间距；
// 名称左对齐、金额右对齐共用同一右基线；优惠行相对原价行左缩进 16px、金额用成功色，
// 原价行与优惠行同为 14px/400，层级靠缩进与颜色区分，不靠字号抢层级。
// 左右内边距取 24px：与上方卡片内的字段左基线对齐（卡片 1px 边框 + 24px 内边距）。
.order-fee-detail {
  // 左右内边距固定 24px 而不是 --td-comp-paddingLR-l（20px）：
  // 上方卡片正文左基线 = 卡片 1px 边框 + 24px 内边距，区块要对齐同一条左基线，差 4px 肉眼可见
  padding: 16px 24px;
  background: var(--td-bg-color-container);
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-large);
  box-shadow: var(--td-shadow-1);

  &__title {
    margin: 0 0 var(--td-comp-margin-s);
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-body-medium);
    font-weight: 600;
  }

  &__list {
    display: flex;
    flex-direction: column;
    gap: var(--td-comp-margin-xs);
    margin: 0;
  }

  &__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--td-comp-margin-l);
    min-height: 1.75rem;

    // min-width: 0 + overflow-wrap：按量计费的计算式（如 ¥0.50/GB × 720 GB = ¥360.00）
    // 在窄屏会超过半栏宽，flex 项默认 min-width: auto 不肯收缩，会把右侧金额顶出可视区。
    dt {
      min-width: 0;
      overflow-wrap: anywhere;
      color: var(--td-text-color-primary);
      font-size: var(--td-font-size-body-medium);
      font-weight: 400;
    }

    dd {
      flex-shrink: 0;
      margin: 0;
      color: var(--td-text-color-primary);
      font-size: var(--td-font-size-body-medium);
      font-weight: 400;
      font-variant-numeric: tabular-nums;
      text-align: right;
    }
  }

  &__row--discount {
    padding-left: 1rem;

    dd {
      color: var(--td-success-color);
    }
  }
}

.order-pricing-block {
  display: grid;
  margin-top: var(--td-comp-margin-l);
  padding-top: var(--td-comp-paddingTB-m);
}

.order-section {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-s);
  min-width: 0;
}

.order-section-title {
  position: relative;
  margin: 0;
  padding-left: 0.625rem;
  color: var(--td-text-color-primary);
  font: var(--td-font-title-small);
  font-weight: 600;

  &::before {
    position: absolute;
    top: 0.25rem;
    bottom: 0.25rem;
    left: 0;
    width: 0.1875rem;
    background: var(--td-brand-color);
    // C-24：0.125rem 与 --td-radius-small 等值
    border-radius: var(--td-radius-small);
    content: '';
  }
}

.snapshot-line-list {
  display: flex;
  flex-direction: column;
  gap: 0.0625rem;
  overflow: hidden;
  background: var(--td-bg-color-component);
  border: thin solid var(--td-component-stroke);
  border-radius: var(--td-radius-medium);
}

.snapshot-line-item {
  display: grid;
  grid-template-columns: minmax(8rem, 11rem) minmax(0, 1fr);
  gap: var(--td-comp-margin-s);
  align-items: start;
  min-width: 0;
  padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-m);
  background: var(--td-bg-color-container);
}

.snapshot-line-label {
  color: var(--td-text-color-secondary);
  font: var(--td-font-body-small);
}

.snapshot-line-value {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
  overflow-wrap: anywhere;
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

  &--wide {
    grid-column: 1 / -1;
  }
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

// O-22：详情页原先用 @screen-xs-rem（40rem），40rem 到 @screen-mobile-max 之间
// 仍排桌面版，与控制台其余页面的移动断点不一致。
@media (max-width: @screen-mobile-max) {
  .detail-kv-grid {
    grid-template-columns: 1fr;
  }

  .detail-toolbar {
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    align-items: flex-start;
  }

  .snapshot-line-item {
    grid-template-columns: 1fr;
    gap: var(--td-comp-margin-xxs);
  }
}
</style>
