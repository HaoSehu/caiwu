<template>
  <section class="record-page client-orders">
    <!-- 快捷筛选标签 -->
    <record-quick-filters v-model="filters.quickFilter" @change="applyQuickFilter" />

    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索订单号"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <!-- O-02：suffixIcon 槽在 clearable 且有内容时会被 TDesign 换成清除图标，
                放大镜随鼠标悬停消失、点下去变成清空。改用 #suffix 槽放可点按钮，位置稳定。 -->
          <template #suffix>
            <search-field-button @search="handleSearch" />
          </template>
        </t-input>

        <t-select v-model="filters.status" clearable placeholder="全部状态" @change="handleSearch">
          <t-option v-for="item in ORDER_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <t-select v-model="filters.type" clearable placeholder="全部类型" @change="handleSearch">
          <t-option v-for="item in ORDER_TYPE_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <t-date-range-picker v-model="dateRange" class="record-toolbar__range" clearable value-type="YYYY-MM-DD" />
      </div>
    </t-card>

    <section class="record-card">
      <data-state
        :loading="loading"
        :empty="!loading && !loadError && !hasRows"
        :error="!loading && loadError"
        :error-text="loadErrorText"
        :description="hasActiveFilters ? '未找到匹配结果' : '暂无订单记录'"
        @retry="loadList"
      >
        <!-- W-17：空态要给出路。命中筛选但 0 条时给「清除筛选」，真无数据时给业务主行动 -->
        <template #empty-action>
          <t-button v-if="hasActiveFilters" theme="primary" variant="outline" @click="resetFilters"
            >清除筛选条件</t-button
          >
          <template v-else
            ><t-button theme="primary" @click="router.push('/client/catalog')">去产品目录选购</t-button></template
          >
        </template>
        <!-- 桌面走表格、手机走卡片：用 JS 断点二选一，避免两套模板同时存在于 DOM -->
        <t-table
          v-if="isDesktop"
          class="record-table"
          row-key="id"
          :data="list"
          :columns="columns"
          :pagination="null"
          hover
        >
          <template #order="{ row }">
            <div class="stack-cell">
              <strong :title="row.order_no">{{ row.order_no || '--' }}</strong>
              <span>{{ row.type_label || '--' }}</span>
            </div>
          </template>
          <template #product="{ row }">
            <div class="stack-cell">
              <strong>{{ orderProductDisplay(row) }}</strong>
              <span v-if="row.service_name">{{ row.service_name }}</span>
            </div>
          </template>
          <template #amount="{ row }">
            <span class="order-money">¥{{ vm(row).amount }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="ORDER_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #channel="{ row }">
            <span class="order-channel">{{ orderChannelLabel(row) }}</span>
          </template>
          <template #created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #operation="{ row }">
            <t-space>
              <t-button size="small" theme="primary" variant="text" @click="router.push(`/client/orders/${row.id}`)">
                详情
              </t-button>
              <t-button
                v-if="vm(row).canPay"
                size="small"
                theme="primary"
                variant="outline"
                @click="router.push(`/client/invoices/${row.invoice?.id}/pay`)"
              >
                去支付
              </t-button>
              <span v-else-if="vm(row).payWindowClosed" class="order-window-closed">支付窗口已关闭</span>
              <span v-else-if="vm(row).payUnavailable" class="order-window-closed">无可支付账单</span>
              <t-button
                v-if="vm(row).cancellable"
                size="small"
                theme="danger"
                variant="text"
                :loading="vm(row).canceling"
                @click="cancelOrder(row)"
              >
                取消
              </t-button>
            </t-space>
          </template>
        </t-table>

        <div v-else class="record-mobile-list">
          <article
            v-for="row in list"
            :key="row.id"
            class="record-mobile-card"
            role="link"
            tabindex="0"
            :aria-label="`查看订单 ${row.order_no || row.id} 详情`"
            @click="router.push(`/client/orders/${row.id}`)"
            @keydown.enter.self.prevent="router.push(`/client/orders/${row.id}`)"
            @keydown.space.self.prevent="router.push(`/client/orders/${row.id}`)"
          >
            <div class="record-mobile-card__head">
              <strong>{{ row.order_no || '--' }}</strong>
              <status-tag :status-map="ORDER_STATUS_MAP" :status="Number(row.status)" />
            </div>
            <div class="stack-cell">
              <strong>{{ orderProductDisplay(row) }}</strong>
              <span>{{ row.type_label || '--' }}</span>
              <span class="stack-money">¥{{ vm(row).amount }}</span>
              <span v-if="vm(row).couponDiscount > 0" class="stack-money"
                >优惠券减免：¥{{ vm(row).couponDiscountText
                }}{{ row.coupon_name ? `（${row.coupon_name}）` : '' }}</span
              >
              <span v-if="vm(row).memberDiscount > 0" class="stack-money"
                >会员折扣：¥{{ vm(row).memberDiscountText }}</span
              >
            </div>
            <div class="record-mobile-card__meta">
              <span>支付渠道：{{ orderChannelLabel(row) }}</span>
              <span>{{ formatDateTime(row.created_at) }}</span>
            </div>
            <div class="record-mobile-card__actions">
              <t-button
                size="small"
                theme="primary"
                variant="text"
                @click.stop="router.push(`/client/orders/${row.id}`)"
              >
                详情
              </t-button>
              <t-button
                v-if="vm(row).canPay"
                size="small"
                theme="primary"
                variant="outline"
                @click.stop="router.push(`/client/invoices/${row.invoice?.id}/pay`)"
              >
                去支付
              </t-button>
              <span v-else-if="vm(row).payWindowClosed" class="order-window-closed">支付窗口已关闭</span>
              <span v-else-if="vm(row).payUnavailable" class="order-window-closed">无可支付账单</span>
              <t-button
                v-if="vm(row).cancellable"
                size="small"
                theme="danger"
                variant="text"
                :loading="vm(row).canceling"
                @click.stop="cancelOrder(row)"
              >
                取消
              </t-button>
            </div>
          </article>
        </div>
      </data-state>
    </section>

    <div v-if="total > 0" class="record-pagination">
      <t-pagination
        v-model="filters.page"
        v-model:page-size="filters.page_size"
        :page-size-options="[10, 20, 50]"
        :total="total"
        show-total
        @change="loadList"
        @page-size-change="handlePageSizeChange"
      />
    </div>
  </section>
</template>
<script setup lang="ts">
import { ORDER_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import SearchFieldButton from '@/components/common/SearchFieldButton.vue';
import { useDeviceLayout } from '@/composables/useDeviceLayout';
import {
  canPayOrderRow,
  isPayUnavailableOrderRow,
  isPayWindowClosedOrderRow,
  ORDER_STATUS_OPTIONS,
  ORDER_TYPE_OPTIONS,
  orderProductDisplay,
  useOrderList,
} from '@/domains/finance/useOrders';
import RecordQuickFilters from '@/pages/client/components/RecordQuickFilters.vue';
import type { OrderRecord } from '@/types/client';
import { formatDateTime, formatMoney } from '@/utils/format';

const router = useRouter();
// 表格与移动卡片只渲染一套，切换点与 record-page.less 的平板断点保持一致
const { isDesktop } = useDeviceLayout();

const {
  loading,
  isCanceling,
  list,
  total,
  filters,
  hasRows,
  hasActiveFilters,
  loadError,
  loadErrorText,
  loadList,
  handleSearch,
  handlePageSizeChange,
  applyQuickFilter,
  resetFilters,
  dateRange,
  cancelOrder,
} = useOrderList();

// 优惠券减免与会员折扣来源不同，手机卡片内分行展示，避免混成一个数字无法区分。
function couponDiscountOf(row: Record<string, unknown>): number {
  return Number(row.discount || 0);
}

function memberDiscountOf(row: Record<string, unknown>): number {
  return Number(row.member_discount_amount || 0);
}

/**
 * 行视图模型：桌面表格与手机卡片共用同一份派生值。
 * 原先同一条订单的金额与折扣在模板里被反复格式化（券折扣一处就要格式化两次），
 * 支付/取消的可用状态也每行现算，列表换引用即全部重算。
 */
interface OrderRowViewModel {
  amount: string;
  couponDiscount: number;
  memberDiscount: number;
  couponDiscountText: string;
  memberDiscountText: string;
  canPay: boolean;
  payWindowClosed: boolean;
  payUnavailable: boolean;
  cancellable: boolean;
  canceling: boolean;
}

const rowViewModels = computed(() => {
  const map = new Map<number, OrderRowViewModel>();

  for (const row of list.value) {
    const couponDiscount = couponDiscountOf(row);
    const memberDiscount = memberDiscountOf(row);

    map.set(row.id, {
      amount: formatMoney(row.amount),
      couponDiscount,
      memberDiscount,
      couponDiscountText: formatMoney(couponDiscount),
      memberDiscountText: formatMoney(memberDiscount),
      canPay: canPayOrderRow(row),
      payWindowClosed: isPayWindowClosedOrderRow(row),
      payUnavailable: isPayUnavailableOrderRow(row),
      cancellable: Number(row.status) === 0,
      canceling: isCanceling(row.id),
    });
  }

  return map;
});

const emptyRowViewModel: OrderRowViewModel = {
  amount: '0.00',
  couponDiscount: 0,
  memberDiscount: 0,
  couponDiscountText: '0.00',
  memberDiscountText: '0.00',
  canPay: false,
  payWindowClosed: false,
  payUnavailable: false,
  cancellable: false,
  canceling: false,
};

function vm(row: { id: number }): OrderRowViewModel {
  return rowViewModels.value.get(row.id) ?? emptyRowViewModel;
}

/**
 * 订单的支付渠道名（支付宝 / 微信支付 …）。
 * 只读渠道字段：插件名（易支付）属于管理端与日志维度，用户端不得出现。
 * 未支付订单后端返回空串，统一兜底为占位符。
 */
function orderChannelLabel(row: OrderRecord): string {
  return row.channel_name || row.gateway_label || '--';
}

// I-04 同类：侧边布局下 1366 视口的可用宽度约 68rem
// （1366 − 侧栏 232 − padding 48 − 常驻滚动条 8 像素 ≈ 1078 像素）。
// 各列内容实际是等宽数字与短标签，7 列合计 60rem，不再横向滚动；
// 账单记录页三张表的收口口径保持一致。
const columns: PrimaryTableCol[] = [
  { colKey: 'order', title: '订单号', minWidth: '10rem' },
  { colKey: 'product', title: '产品/服务', minWidth: '10rem' },
  { colKey: 'amount', title: '金额', width: '7.5rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '6.5rem' },
  { colKey: 'channel', title: '支付渠道', width: '7rem' },
  { colKey: 'created_at', title: '创建时间', minWidth: '10rem' },
  { colKey: 'operation', title: '操作', width: '9rem', fixed: 'right', align: 'right' },
];
</script>
<style scoped lang="less">
@import '../record-page.less';

.order-money {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.order-channel {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  white-space: nowrap;
}

.order-window-closed {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
  white-space: nowrap;
}
</style>
