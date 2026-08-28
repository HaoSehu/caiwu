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
          <template #suffixIcon>
            <search-icon />
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

    <section class="record-list-card">
      <data-state
        :loading="loading"
        :empty="!loading && !loadError && !hasRows"
        :error="!loading && loadError"
        :error-text="loadErrorText"
        description="暂无订单记录"
        @retry="loadList"
      >
        <template #empty-action>
          <t-button theme="primary" @click="router.push('/client/catalog')">去产品目录选购</t-button>
        </template>
        <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
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
            <span class="order-money">¥{{ formatMoney(row.amount) }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="ORDER_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #invoice_ref="{ row }">
            <div v-if="row.invoice" class="stack-cell">
              <t-button
                variant="text"
                theme="primary"
                size="small"
                @click="router.push(`/client/invoices/${row.invoice.id}`)"
              >
                {{ row.invoice.invoice_no || '--' }}
              </t-button>
              <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(row.invoice.status)" size="small" />
            </div>
            <span v-else>--</span>
          </template>
          <template #created_at="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #operation="{ row }">
            <t-space>
              <t-button size="small" theme="primary" variant="text" @click="router.push(`/client/orders/${row.id}`)">
                详情
              </t-button>
              <t-button
                v-if="Number(row.status) === 0"
                size="small"
                theme="primary"
                variant="outline"
                @click="router.push(`/client/invoices/${row.invoice?.id}/pay`)"
              >
                去支付
              </t-button>
              <t-button
                v-if="Number(row.status) === 0"
                size="small"
                theme="danger"
                variant="text"
                :loading="canceling"
                @click="cancelOrder(row)"
              >
                取消
              </t-button>
            </t-space>
          </template>
        </t-table>

        <div class="record-mobile-list">
          <article
            v-for="row in list"
            :key="row.id"
            class="record-mobile-card"
            role="link"
            tabindex="0"
            :aria-label="`查看订单 ${row.order_no || row.id} 详情`"
            @click="router.push(`/client/orders/${row.id}`)"
            @keydown.enter.self.prevent="router.push(`/client/orders/${row.id}`)"
          >
            <div class="record-mobile-card__head">
              <strong>{{ row.order_no || '--' }}</strong>
              <status-tag :status-map="ORDER_STATUS_MAP" :status="Number(row.status)" />
            </div>
            <div class="stack-cell">
              <strong>{{ orderProductDisplay(row) }}</strong>
              <span>{{ row.type_label || '--' }}</span>
              <span class="stack-money">¥{{ formatMoney(row.amount) }}</span>
            </div>
            <div class="record-mobile-card__meta">
              <span v-if="row.invoice">账单: {{ row.invoice.invoice_no || '--' }}</span>
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
                v-if="Number(row.status) === 0"
                size="small"
                theme="primary"
                variant="outline"
                @click.stop="router.push(`/client/invoices/${row.invoice?.id}/pay`)"
              >
                去支付
              </t-button>
              <t-button
                v-if="Number(row.status) === 0"
                size="small"
                theme="danger"
                variant="text"
                :loading="canceling"
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
import { INVOICE_STATUS_MAP, ORDER_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@shared/user-v3/components/DataState.vue';
import StatusTag from '@shared/user-v3/components/StatusTag.vue';
import { SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { useRouter } from 'vue-router';

import {
  formatMoney,
  ORDER_STATUS_OPTIONS,
  ORDER_TYPE_OPTIONS,
  orderProductDisplay,
  useOrderList,
} from '@/domains/finance/useOrders';
import RecordQuickFilters from '@/pages/client/components/RecordQuickFilters.vue';
import { formatDateTime } from '@/utils/format';

const router = useRouter();

const {
  loading,
  canceling,
  list,
  total,
  filters,
  hasRows,
  loadError,
  loadErrorText,
  loadList,
  handleSearch,
  handlePageSizeChange,
  applyQuickFilter,
  dateRange,
  cancelOrder,
} = useOrderList();

const columns: PrimaryTableCol[] = [
  { colKey: 'order', title: '订单号', minWidth: '12rem' },
  { colKey: 'product', title: '产品/服务', minWidth: '12rem' },
  { colKey: 'amount', title: '金额', width: '9rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'invoice_ref', title: '关联账单', minWidth: '12rem' },
  { colKey: 'created_at', title: '创建时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '14rem', fixed: 'right', align: 'right' },
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
</style>
