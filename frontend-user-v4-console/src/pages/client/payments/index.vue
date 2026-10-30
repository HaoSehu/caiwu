<template>
  <section class="record-page client-payments">
    <record-quick-filters v-model="filters.quickFilter" @change="applyQuickFilter" />

    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索支付单号或第三方单号"
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
          <t-option v-for="item in PAYMENT_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select v-model="filters.type" clearable placeholder="全部渠道" @change="handleSearch">
          <t-option v-for="item in PAYMENT_GATEWAY_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
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
        :description="hasActiveFilters ? '未找到匹配结果' : '暂无充值记录'"
        @retry="loadList"
      >
        <!-- W-17：空态要给出路。命中筛选但 0 条时给「清除筛选」，真无数据时给业务主行动 -->
        <template #empty-action>
          <t-button v-if="hasActiveFilters" theme="primary" variant="outline" @click="resetFilters"
            >清除筛选条件</t-button
          >
          <template v-else
            ><t-button theme="primary" @click="router.push('/client/recharge')">去充值</t-button></template
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
          <template #payment="{ row }">
            <div class="stack-cell">
              <strong :title="row.payment_no">商家：{{ row.payment_no || '--' }}</strong>
              <span :title="row.trade_no">第三方：{{ row.trade_no || '--' }}</span>
            </div>
          </template>
          <template #gateway="{ row }">{{ vm(row).gateway }}</template>
          <template #invoice="{ row }">
            <t-button
              v-if="row.invoice_no"
              size="small"
              theme="primary"
              variant="text"
              class="payment-invoice-btn"
              @click.stop="goToInvoice(row)"
            >
              {{ row.invoice_no }}
            </t-button>
            <span v-else>--</span>
          </template>
          <template #amount="{ row }">
            <span class="payment-money">¥{{ vm(row).amount }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #time="{ row }">{{ vm(row).time }}</template>
          <template #operation="{ row }">
            <t-button size="small" theme="primary" variant="text" @click="goToDetail(row)">查看</t-button>
          </template>
        </t-table>

        <div v-else class="record-mobile-list">
          <article
            v-for="row in list"
            :key="row.id"
            class="record-mobile-card"
            role="link"
            tabindex="0"
            :aria-label="`查看支付记录 ${row.payment_no || row.id} 详情`"
            @click="goToDetail(row)"
            @keydown.enter.self.prevent="goToDetail(row)"
            @keydown.space.self.prevent="goToDetail(row)"
          >
            <div class="record-mobile-card__head">
              <strong>商家：{{ row.payment_no || '--' }}</strong>
              <status-tag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
            </div>
            <div class="stack-cell">
              <span class="stack-money">¥{{ vm(row).amount }}</span>
              <span>{{ vm(row).gateway }}</span>
            </div>
            <div class="record-mobile-card__meta">
              <span>第三方：{{ row.trade_no || '等待渠道回调' }}</span>
              <span>{{ vm(row).time }}</span>
            </div>
            <div v-if="row.invoice_no" class="record-mobile-card__meta">
              <span
                >关联账单：<button type="button" class="payment-invoice-link" @click.stop="goToInvoice(row)">
                  {{ row.invoice_no }}
                </button></span
              >
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
import { PAYMENT_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import StatusTag from '@caiwu/shared/user-v3/components/StatusTag.vue';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import clientApi from '@/api/client';
import SearchFieldButton from '@/components/common/SearchFieldButton.vue';
import { useDeviceLayout } from '@/composables/useDeviceLayout';
import { PAYMENT_GATEWAY_OPTIONS, PAYMENT_STATUS_OPTIONS, useRecordList } from '@/domains/finance/useRecords';
import RecordQuickFilters from '@/pages/client/components/RecordQuickFilters.vue';
import type { PaymentRecord } from '@/types/client';
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
} = useRecordList<PaymentRecord>({
  fetcher: (params) => clientApi.payments(params),
  errorMessage: '充值记录加载失败',
});

function goToDetail(row: PaymentRecord) {
  router.push(`/client/payments/${row.id}`);
}

function goToInvoice(row: PaymentRecord) {
  if (row.invoice_id) {
    router.push(`/client/invoices/${row.invoice_id}`);
  }
}

function gatewayDisplay(row: PaymentRecord) {
  return row.gateway_label || row.gateway_key || row.gateway || '--';
}

/*
 * 行视图模型：桌面表格与手机卡片是两套模板，先前各自在模板里重复调
 * gatewayDisplay / formatMoney / formatDateTime，同一行一次渲染要算 2 遍。
 * 这里按行 id 预算一次，模板只取值（PR-18~PR-22 同类）。
 */
interface PaymentRowViewModel {
  gateway: string;
  amount: string;
  time: string;
}
const rowViewModels = computed(() => {
  const map = new Map<number, PaymentRowViewModel>();
  for (const row of list.value) {
    map.set(row.id, {
      gateway: gatewayDisplay(row),
      amount: formatMoney(row.amount),
      time: formatDateTime(row.paid_at || row.created_at),
    });
  }
  return map;
});
const EMPTY_PAYMENT_ROW: PaymentRowViewModel = { gateway: '--', amount: '--', time: '--' };
const vm = (row: { id: number }) => rowViewModels.value.get(row.id) ?? EMPTY_PAYMENT_ROW;

/*
 * I-04 同类：原列宽合计 72rem，而 1366 笔记本在侧栏宽度 + 页面左右边距
 * 之后可用宽度约 68rem，横向必然溢出；fixed 的操作列被挤出可视区，用户要横滚才
 * 看得到「关联账单」。订单号与账单号都是长串，靠 ellipsis 截断 + title 悬浮查看，
 * 不给它们留最大宽度；金额、状态、操作是定长内容，给死宽度避免列宽抖动。
 */
const columns: PrimaryTableCol[] = [
  { colKey: 'payment', title: '支付订单号', minWidth: '12rem', ellipsis: true },
  { colKey: 'gateway', title: '支付渠道', width: '6.5rem' },
  { colKey: 'invoice', title: '关联账单', minWidth: '12rem', ellipsis: true },
  { colKey: 'amount', title: '支付金额', width: '7.5rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '6.5rem' },
  { colKey: 'time', title: '时间', minWidth: '10rem' },
  { colKey: 'operation', title: '操作', width: '6rem', fixed: 'right', align: 'right' },
];
</script>
<style scoped lang="less">
@import '../record-page.less';

.payment-money {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.payment-invoice-btn {
  padding: 0;
  height: auto;
  font: var(--td-font-body-medium);
}

.payment-invoice-link {
  padding: 0;
  color: var(--td-brand-color);
  font: var(--td-font-body-medium);
  cursor: pointer;
  background: none;
  border: 0;
}

.payment-invoice-link:hover,
.payment-invoice-link:focus-visible {
  color: var(--td-brand-color-hover);
  text-decoration: underline;
}
</style>
