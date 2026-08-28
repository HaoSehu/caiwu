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
          <template #suffixIcon>
            <search-icon />
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

    <section class="record-list-card">
      <data-state
        :loading="loading"
        :empty="!loading && !loadError && !hasRows"
        :error="!loading && loadError"
        :error-text="loadErrorText"
        description="暂无账单记录"
        @retry="loadList"
      >
        <template #empty-action>
          <t-button theme="primary" @click="router.push('/client/catalog')">去产品目录选购</t-button>
        </template>
        <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
          <template #invoice="{ row }">
            <div class="stack-cell">
              <strong :title="resolveInvoiceNo(row)">{{ resolveInvoiceNo(row) }}</strong>
              <span>{{ row.type_label || '--' }}</span>
            </div>
          </template>
          <template #amount="{ row }">
            <span class="invoice-money">¥{{ formatMoney(row.amount) }}</span>
          </template>
          <template #paid="{ row }">
            <span class="invoice-money">¥{{ formatMoney(row.paid_amount) }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #operation="{ row }">
            <t-space>
              <t-button size="small" theme="primary" variant="text" @click="goToDetail(row)">查看</t-button>
              <t-button
                v-if="isPayableInvoice(row)"
                size="small"
                theme="primary"
                variant="outline"
                @click="goToPay(row)"
              >
                去支付
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
            :aria-label="`查看账单 ${resolveInvoiceNo(row)} 详情`"
            @click="goToDetail(row)"
            @keydown.enter.self.prevent="goToDetail(row)"
          >
            <div class="record-mobile-card__head">
              <strong>{{ resolveInvoiceNo(row) }}</strong>
              <status-tag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
            </div>

            <div class="stack-cell">
              <strong>{{ row.type_label || '--' }}</strong>
              <span class="stack-money">账单金额：¥{{ formatMoney(row.amount) }}</span>
              <span class="stack-money">已付金额：¥{{ formatMoney(row.paid_amount) }}</span>
            </div>

            <div class="record-mobile-card__meta">
              <span>{{ formatDateTime(row.created_at) }}</span>
            </div>

            <div class="record-mobile-card__actions">
              <t-button size="small" theme="primary" variant="text" @click.stop="goToDetail(row)">查看</t-button>
              <t-button
                v-if="isPayableInvoice(row)"
                size="small"
                theme="primary"
                variant="outline"
                @click.stop="goToPay(row)"
              >
                去支付
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
import { INVOICE_STATUS_MAP } from '@caiwu/shared/statusConfig';
import DataState from '@shared/user-v3/components/DataState.vue';
import StatusTag from '@shared/user-v3/components/StatusTag.vue';
import { SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { useRouter } from 'vue-router';

import {
  formatMoney,
  INVOICE_STATUS_OPTIONS,
  INVOICE_TYPE_OPTIONS,
  isPayableInvoice,
  resolveInvoiceNo,
  useInvoiceList,
} from '@/domains/finance/useInvoices';
import RecordQuickFilters from '@/pages/client/components/RecordQuickFilters.vue';
import type { InvoiceRecord } from '@/types/client';
import { formatDateTime } from '@/utils/format';

const router = useRouter();

const {
  loading,
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
  goToPay,
} = useInvoiceList();

function goToDetail(row: InvoiceRecord) {
  router.push(`/client/invoices/${row.id}`);
}

const columns: PrimaryTableCol[] = [
  { colKey: 'invoice', title: '账单号', minWidth: '12rem' },
  { colKey: 'amount', title: '账单金额', width: '9rem', align: 'right' },
  { colKey: 'paid', title: '已付金额', width: '9rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'created_at', title: '创建时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '10rem', fixed: 'right', align: 'right' },
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
</style>
