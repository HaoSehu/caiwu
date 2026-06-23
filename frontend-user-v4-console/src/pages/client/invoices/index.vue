<template>
  <section class="record-page client-invoices">
    <!-- 快捷筛选标签 -->
    <div class="quick-filter-tags">
      <t-tag
        v-for="item in quickFilters"
        :key="item.key"
        :variant="filters.quickFilter === item.key ? 'outline' : 'light'"
        :theme="filters.quickFilter === item.key ? 'primary' : 'default'"
        class="quick-filter-tag"
        @click="applyQuickFilter(item.key)"
      >
        {{ item.label }}
      </t-tag>
    </div>

    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索账单号、商家订单号或第三方订单号"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffixIcon>
            <SearchIcon />
          </template>
        </t-input>

        <t-select v-model="filters.status" clearable placeholder="全部状态" @change="handleSearch">
          <t-option v-for="item in INVOICE_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <t-select v-if="showTypeSelector" v-model="filters.type" clearable placeholder="全部类型" @change="handleSearch">
          <t-option v-for="item in INVOICE_TYPE_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>

        <div class="record-actions">
          <t-button theme="primary" @click="handleSearch">
            <template #icon><SearchIcon /></template>
            搜索
          </t-button>
          <t-button variant="outline" @click="resetFilters">重置</t-button>
        </div>
      </div>
    </t-card>

    <section class="record-list-card">
      <DataState :loading="loading" :empty="!list.length" description="暂无账单记录">
        <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
              <template #invoice="{ row }">
                <div class="stack-cell">
                  <strong>{{ resolveInvoiceNo(row) }}</strong>
                  <span>{{ row.type_label || '--' }}</span>
                </div>
              </template>
              <template #amount="{ row }">
                <span class="invoice-money">¥{{ formatMoney(row.amount) }}</span>
              </template>
              <template #paid="{ row }">
                <span class="invoice-money">¥{{ formatMoney(row.paid_amount) }}</span>
              </template>
              <template #payment="{ row }">
                <div class="stack-cell">
                  <strong>商家：{{ paymentRecordNo(row) }}</strong>
                  <span>第三方：{{ paymentTradeNo(row) }}</span>
                  <span>{{ paymentRecordSummary(row) }}</span>
                </div>
              </template>
              <template #status="{ row }">
                <StatusTag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
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
            <article v-for="row in list" :key="row.id" class="record-mobile-card">
              <div class="record-mobile-card__head">
                <strong>{{ resolveInvoiceNo(row) }}</strong>
                <StatusTag :status-map="INVOICE_STATUS_MAP" :status="Number(row.status)" />
              </div>

              <div class="stack-cell">
                <strong>{{ row.type_label || '--' }}</strong>
                <span>账单金额：¥{{ formatMoney(row.amount) }}</span>
                <span>已付金额：¥{{ formatMoney(row.paid_amount) }}</span>
              </div>

              <div class="record-mobile-card__meta">
                <span>商家：{{ paymentRecordNo(row) }}</span>
                <span>第三方：{{ paymentTradeNo(row) }}</span>
                <span>{{ row.created_at || '--' }}</span>
              </div>

              <div class="record-mobile-card__actions">
                <t-button size="small" theme="primary" variant="text" @click="goToDetail(row)">查看</t-button>
                <t-button v-if="isPayableInvoice(row)" size="small" theme="primary" variant="outline" @click="goToPay(row)">
                  去支付
                </t-button>
              </div>
            </article>
          </div>
      </DataState>
    </section>

    <div v-if="total > 0" class="record-pagination">
      <t-pagination
        v-model="filters.page"
        v-model:pageSize="filters.page_size"
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
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { SearchIcon } from 'tdesign-icons-vue-next';
import { useRouter } from 'vue-router';

import DataState from '@shared/user-v3/components/DataState.vue';
import StatusTag from '@shared/user-v3/components/StatusTag.vue';
import { INVOICE_STATUS_MAP } from '@shared/statusConfig';
import {
  formatMoney,
  INVOICE_STATUS_OPTIONS,
  INVOICE_TYPE_OPTIONS,
  isPayableInvoice,
  resolveInvoiceNo,
  useInvoiceList,
} from '@/domains/finance/useInvoices';
import type { InvoiceRecord } from '@/types/client';

const router = useRouter();

const {
  loading,
  list,
  total,
  filters,
  loadList,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
  applyQuickFilter,
  goToPay,
  showTypeSelector,
} = useInvoiceList();

const quickFilters = [
  { key: '', label: '全部' },
  { key: 'week', label: '最近7天' },
  { key: 'month', label: '本月' },
  { key: 'pending', label: '待支付' },
];

function goToDetail(row: InvoiceRecord) {
  router.push(`/client/invoices/${row.id}`);
}

const columns: PrimaryTableCol[] = [
  { colKey: 'invoice', title: '账单号', minWidth: '12rem' },
  { colKey: 'amount', title: '账单金额', width: '9rem' },
  { colKey: 'paid', title: '已付金额', width: '9rem' },
  { colKey: 'payment', title: '关联支付', minWidth: '14rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'created_at', title: '创建时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '10rem', fixed: 'right', align: 'right' },
];

function paymentRecord(row: Record<string, any>) {
  return row.payment_summary && typeof row.payment_summary === 'object' ? row.payment_summary : {};
}

function paymentRecordNo(row: Record<string, any>) {
  const payment = paymentRecord(row);
  return payment.payment_no || '--';
}

function paymentTradeNo(row: Record<string, any>) {
  const payment = paymentRecord(row);
  return payment.trade_no || '--';
}

function paymentRecordSummary(row: Record<string, any>) {
  const payment = paymentRecord(row);
  const parts = [payment.gateway].filter(Boolean);
  return parts.length ? parts.join(' / ') : '--';
}
</script>

<style scoped lang="less">
@import '../record-page.less';

.quick-filter-tags {
  display: flex;
  gap: var(--td-comp-margin-s);
  margin-bottom: var(--td-comp-margin-m);
  flex-wrap: wrap;
}

.quick-filter-tag {
  cursor: pointer;
  user-select: none;
}

.invoice-money {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
}
</style>
