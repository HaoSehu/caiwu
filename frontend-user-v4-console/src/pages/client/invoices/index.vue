<template>
  <section class="record-page client-invoices">
    <!-- 快捷筛选标签 -->
    <record-quick-filters v-model="filters.quickFilter" @change="applyQuickFilter" />

    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索账单号、关联订单号或充值单号"
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
          <t-option v-for="item in INVOICE_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <t-select v-model="filters.type" clearable placeholder="全部类型" @change="handleSearch">
          <t-option v-for="item in INVOICE_TYPE_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
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
        :description="hasActiveFilters ? '未找到匹配结果' : '暂无账单记录'"
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
          <template #invoice="{ row }">
            <div class="stack-cell">
              <strong :title="vm(row).no">{{ vm(row).no }}</strong>
              <span>{{ row.type_label || '--' }}</span>
            </div>
          </template>
          <template #amount="{ row }">
            <span class="invoice-money">¥{{ vm(row).amount }}</span>
          </template>
          <template #paid="{ row }">
            <span class="invoice-money">¥{{ vm(row).paidAmount }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #channel="{ row }">
            <span class="invoice-channel">{{ invoiceChannelLabel(row) }}</span>
          </template>
          <template #operation="{ row }">
            <t-space>
              <t-button size="small" theme="primary" variant="text" @click="goToDetail(row)">查看</t-button>
              <t-button v-if="vm(row).canPay" size="small" theme="primary" variant="outline" @click="goToPay(row)">
                去支付
              </t-button>
              <span v-else-if="vm(row).payWindowClosed" class="invoice-window-closed">支付窗口已关闭</span>
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
            :aria-label="`查看账单 ${vm(row).no} 详情`"
            @click="goToDetail(row)"
            @keydown.enter.self.prevent="goToDetail(row)"
            @keydown.space.self.prevent="goToDetail(row)"
          >
            <div class="record-mobile-card__head">
              <strong>{{ vm(row).no }}</strong>
              <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
            </div>

            <div class="stack-cell">
              <strong>{{ row.type_label || '--' }}</strong>
              <span class="stack-money">账单金额：¥{{ vm(row).amount }}</span>
              <span v-if="vm(row).couponDiscount > 0" class="stack-money"
                >优惠券减免：¥{{ vm(row).couponDiscountText
                }}{{ row.coupon_name ? `（${row.coupon_name}）` : '' }}</span
              >
              <span v-if="vm(row).memberDiscount > 0" class="stack-money"
                >会员折扣：¥{{ vm(row).memberDiscountText }}</span
              >
              <span class="stack-money">已付金额：¥{{ vm(row).paidAmount }}</span>
            </div>

            <div class="record-mobile-card__meta">
              <span>支付渠道：{{ invoiceChannelLabel(row) }}</span>
              <span>{{ formatDateTime(row.created_at) }}</span>
            </div>

            <div class="record-mobile-card__actions">
              <t-button size="small" theme="primary" variant="text" @click.stop="goToDetail(row)">查看</t-button>
              <t-button v-if="vm(row).canPay" size="small" theme="primary" variant="outline" @click.stop="goToPay(row)">
                去支付
              </t-button>
              <span v-else-if="vm(row).payWindowClosed" class="invoice-window-closed">支付窗口已关闭</span>
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
import { INVOICE_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import SearchFieldButton from '@/components/common/SearchFieldButton.vue';
import { useDeviceLayout } from '@/composables/useDeviceLayout';
import {
  canPayInvoiceRow,
  INVOICE_STATUS_OPTIONS,
  INVOICE_TYPE_OPTIONS,
  isPayableInvoice,
  resolveInvoiceNo,
  useInvoiceList,
} from '@/domains/finance/useInvoices';
import RecordQuickFilters from '@/pages/client/components/RecordQuickFilters.vue';
import type { InvoiceRecord } from '@/types/client';
import { formatDateTime, formatMoney } from '@/utils/format';

const router = useRouter();
// 表格与移动卡片只渲染一套，切换点与 record-page.less 的平板断点保持一致
const { isDesktop } = useDeviceLayout();

const {
  loading,
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
  goToPay,
} = useInvoiceList();

function goToDetail(row: InvoiceRecord) {
  router.push(`/client/invoices/${row.id}`);
}

// 待支付但支付窗口已关闭（后端 payment_window_expired）：列表不再给出「去支付」，
// 改为一句说明，具体引导（续费账单重新发起续费 / 其他账单联系客服）由详情页给出。
function isPayWindowClosedRow(row: InvoiceRecord) {
  return isPayableInvoice(row) && Boolean(row.payment_window_expired);
}

// 优惠券减免与会员折扣来源不同，手机卡片内分行展示，避免混成一个数字无法区分
function couponDiscountOf(row: Record<string, unknown>): number {
  return Number(row.discount || 0);
}

function memberDiscountOf(row: Record<string, unknown>): number {
  return Number(row.member_discount_amount || 0);
}

/**
 * 行视图模型：桌面表格与手机卡片共用同一份算好的派生值。
 * 原先同一条账单在模板里被 formatMoney / couponDiscountOf / memberDiscountOf / resolveInvoiceNo
 * 反复调用（桌面 8 次、手机 6 次），列表换一次引用就重算一遍且每个金额格式化两次。
 */
interface InvoiceRowViewModel {
  no: string;
  amount: string;
  paidAmount: string;
  couponDiscount: number;
  memberDiscount: number;
  couponDiscountText: string;
  memberDiscountText: string;
  canPay: boolean;
  payWindowClosed: boolean;
}

const rowViewModels = computed(() => {
  const map = new Map<number, InvoiceRowViewModel>();

  for (const row of list.value) {
    const couponDiscount = couponDiscountOf(row);
    const memberDiscount = memberDiscountOf(row);

    map.set(row.id, {
      no: resolveInvoiceNo(row),
      amount: formatMoney(row.amount),
      paidAmount: formatMoney(row.paid_amount),
      couponDiscount,
      memberDiscount,
      couponDiscountText: formatMoney(couponDiscount),
      memberDiscountText: formatMoney(memberDiscount),
      canPay: canPayInvoiceRow(row),
      payWindowClosed: isPayWindowClosedRow(row),
    });
  }

  return map;
});

const emptyRowViewModel: InvoiceRowViewModel = {
  no: '--',
  amount: '0.00',
  paidAmount: '0.00',
  couponDiscount: 0,
  memberDiscount: 0,
  couponDiscountText: '0.00',
  memberDiscountText: '0.00',
  canPay: false,
  payWindowClosed: false,
};

function vm(row: { id: number }): InvoiceRowViewModel {
  return rowViewModels.value.get(row.id) ?? emptyRowViewModel;
}

/**
 * 账单的支付渠道名（支付宝 / 微信支付 …）。
 * 取 payment_summary.channel_name，回落既有的 gateway_label；
 * 插件名（易支付）属管理端与日志维度，用户端不得出现。
 */
function invoiceChannelLabel(row: InvoiceRecord): string {
  const payment = row.payment_summary;

  return payment?.channel_name || payment?.gateway_label || '--';
}

// I-04：7 列宽度合计 57.5rem，配固定操作列后，1366 宽笔记本上不会横向滚动。
// 横向滚动会把 fixed 列右侧的「查看 / 去支付」挤出可视区，所以列宽必须收进可用宽度内。
// 可用宽度口径：1366 − 侧栏 232（variables.less 的 @starter-sidebar-width）− 内容区左右 padding 48
// − 常驻滚动条 8 像素 ≈ 1078 像素 ≈ 67rem。
// 金额与时间都是等宽数字，实际内容宽度远小于原列宽：金额 7.5rem（容得下 ¥12,345.67）、
// 状态 6.5rem（三字标签）、渠道 7rem，账单号与创建时间用 minWidth 交给内容撑开。
// 注意：1280 视口下可用宽度只剩约 62rem，仍会横向滚动——本收口只覆盖 1366 及以上。
// 列序把渠道紧贴状态之后，先被挤出可视区的是创建时间，操作列 fixed 保证「查看 / 去支付」始终可见。
const columns: PrimaryTableCol[] = [
  { colKey: 'invoice', title: '账单号', minWidth: '10rem', ellipsis: true },
  { colKey: 'amount', title: '账单金额', width: '7.5rem', align: 'right' },
  { colKey: 'paid', title: '已付金额', width: '7.5rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '6.5rem' },
  { colKey: 'channel', title: '支付渠道', width: '7rem' },
  // created_at / invoice 在 fixed 布局（TDesign 默认 tableLayout='fixed'）下超出列宽会被直接裁掉，
  // 且没有悬浮全文可看，因此统一开启 ellipsis。TDesign 的 ellipsis 会自动用 Tooltip
  // 包住内容（node_modules/tdesign-vue-next/es/table/components/ellipsis.mjs），两列口径统一。
  { colKey: 'created_at', title: '创建时间', minWidth: '10rem', ellipsis: true },
  { colKey: 'operation', title: '操作', width: '9rem', fixed: 'right', align: 'right' },
];
</script>
<style scoped lang="less">
@import '../record-page.less';

.invoice-money {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.invoice-channel {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  white-space: nowrap;
}

.invoice-window-closed {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
  white-space: nowrap;
}
</style>
