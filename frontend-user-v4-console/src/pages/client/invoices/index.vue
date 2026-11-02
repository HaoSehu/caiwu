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
          :loading="loading"
          table-layout="auto"
          hover
        >
          <template #invoice="{ row }">
            <div class="stack-cell">
              <!-- 优惠金额展示规范 1.2：首列是订单号，账单号同列次行；行主键仍是账单号 -->
              <strong :title="vm(row).orderNo">{{ vm(row).orderNo }}</strong>
              <span>{{ vm(row).no }}</span>
            </div>
          </template>
          <template #item="{ row }">
            <div class="stack-cell">
              <strong>{{ vm(row).itemName }}</strong>
            </div>
          </template>
          <template #amount="{ row }">
            <span class="invoice-money">{{ vm(row).paidAmount }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #created_at="{ row }">{{ vm(row).createdAt }}</template>
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
              <strong>{{ vm(row).itemName }}</strong>
              <span>订单号：{{ vm(row).orderNo }}</span>
              <!-- 优惠金额展示规范 1.2 / 3.2：列表只展示实付金额，不展开优惠明细 -->
              <span class="stack-money stack-money--paid">实付金额：{{ vm(row).paidAmount }}</span>
            </div>

            <div class="record-mobile-card__meta">
              <span>下单时间：{{ vm(row).createdAt }}</span>
              <span>{{ row.type_label || '--' }}</span>
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
  resolveInvoiceTitle,
  useInvoiceList,
} from '@/domains/finance/useInvoices';
import RecordQuickFilters from '@/pages/client/components/RecordQuickFilters.vue';
import type { InvoiceRecord } from '@/types/client';
import { formatDateTime, formatMoneyText } from '@/utils/format';

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

/**
 * 行视图模型：桌面表格与手机卡片共用同一份算好的派生值。
 * 优惠金额展示规范 2.1：列表金额只取 paid_amount，由同一格式化函数输出；
 * 优惠构成只在账单详情页展开，列表不保留原价、优惠券与会员折扣字段。
 */
interface InvoiceRowViewModel {
  no: string;
  /** 订单号：账单与订单一一对应，列表必备字段，与账单号同列双层展示 */
  orderNo: string;
  /** 商品/服务名称：优惠前目录名，缺省回落账单类型文案 */
  itemName: string;
  /** 下单时间：订单创建时间格式化结果 */
  createdAt: string;
  /** 实付金额：已格式化并自带货币符号，列表唯一金额 */
  paidAmount: string;
  canPay: boolean;
  payWindowClosed: boolean;
}

const rowViewModels = computed(() => {
  const map = new Map<number, InvoiceRowViewModel>();

  for (const row of list.value) {
    map.set(row.id, {
      no: resolveInvoiceNo(row),
      orderNo: row.order?.order_no || '—',
      itemName: resolveInvoiceTitle(row) || '—',
      createdAt: formatDateTime(row.created_at),
      paidAmount: formatMoneyText(row.paid_amount || 0),
      canPay: canPayInvoiceRow(row),
      payWindowClosed: isPayWindowClosedRow(row),
    });
  }

  return map;
});

const emptyRowViewModel: InvoiceRowViewModel = {
  no: '--',
  orderNo: '—',
  itemName: '—',
  createdAt: '—',
  paidAmount: '¥0.00',
  canPay: false,
  payWindowClosed: false,
};

function vm(row: { id: number }): InvoiceRowViewModel {
  return rowViewModels.value.get(row.id) ?? emptyRowViewModel;
}

// 优惠金额展示规范 1.2 列序：订单号、商品/服务名称、下单时间、实付金额、状态、操作。
// 列宽只作下限，内容超宽时由表格容器横向滚动；实付金额是定长等宽数字，给死宽度避免列宽抖动。
const columns: PrimaryTableCol[] = [
  { colKey: 'invoice', title: '订单号', minWidth: '10rem' },
  { colKey: 'item', title: '商品/服务名称', minWidth: '10rem' },
  { colKey: 'created_at', title: '下单时间', minWidth: '10rem' },
  { colKey: 'amount', title: '实付金额', width: '8.5rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '6.5rem' },
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

// 优惠金额展示规范 1.2：窄屏卡片的实付金额与桌面列同为 14px、字重 600
.stack-cell .stack-money--paid {
  font-size: var(--td-font-size-body-medium);
}

.invoice-window-closed {
  color: var(--td-text-color-placeholder);
  font: var(--td-font-body-small);
  white-space: nowrap;
}
</style>
