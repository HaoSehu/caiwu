<template>
  <section class="record-page client-invoices">
    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索账单号或支付号"
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
      <t-loading :loading="loading" text="正在加载账单记录">
        <template v-if="list.length">
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
                  <strong>{{ paymentRecordNo(row) }}</strong>
                  <span>{{ paymentRecordSummary(row) }}</span>
                </div>
              </template>
              <template #status="{ row }">
                <t-tag :theme="resolveInvoiceTagTheme(row.status)" variant="light">
                  {{ resolveInvoiceStatusLabel(row) }}
                </t-tag>
              </template>
              <template #operation="{ row }">
                <t-space>
                  <t-button size="small" theme="primary" variant="text" @click="openDetail(row)">查看</t-button>
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
                <t-tag :theme="resolveInvoiceTagTheme(row.status)" variant="light">
                  {{ resolveInvoiceStatusLabel(row) }}
                </t-tag>
              </div>

              <div class="stack-cell">
                <strong>{{ row.type_label || '--' }}</strong>
                <span>账单金额：¥{{ formatMoney(row.amount) }}</span>
                <span>已付金额：¥{{ formatMoney(row.paid_amount) }}</span>
              </div>

              <div class="record-mobile-card__meta">
                <span>支付：{{ paymentRecordNo(row) }}</span>
                <span>{{ row.created_at || '--' }}</span>
              </div>

              <div class="record-mobile-card__actions">
                <t-button size="small" theme="primary" variant="text" @click="openDetail(row)">查看</t-button>
                <t-button v-if="isPayableInvoice(row)" size="small" theme="primary" variant="outline" @click="goToPay(row)">
                  去支付
                </t-button>
              </div>
            </article>
          </div>
        </template>

        <t-empty v-else description="暂无账单记录" />
      </t-loading>
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

    <t-drawer
      v-model:visible="detailVisible"
      header="账单详情"
      size="min(32rem, calc(100vw - 2rem))"
      destroy-on-close
      :close-btn="true"
      @close="closeDetail"
    >
      <div v-if="currentRow" class="invoice-drawer-body">
        <t-descriptions :column="1" bordered>
          <t-descriptions-item label="账单号">{{ resolveInvoiceNo(currentRow) }}</t-descriptions-item>
          <t-descriptions-item label="账单类型">{{ currentRow.type_label || '--' }}</t-descriptions-item>
          <t-descriptions-item label="状态">
            <t-tag :theme="resolveInvoiceTagTheme(currentRow.status)" variant="light">
              {{ resolveInvoiceStatusLabel(currentRow) }}
            </t-tag>
          </t-descriptions-item>
          <t-descriptions-item label="账单金额">¥{{ formatMoney(currentRow.amount) }}</t-descriptions-item>
          <t-descriptions-item label="已付金额">¥{{ formatMoney(currentRow.paid_amount) }}</t-descriptions-item>
          <t-descriptions-item label="待付金额">¥{{ formatMoney(currentRow.payable_amount) }}</t-descriptions-item>
          <t-descriptions-item label="关联支付">{{ paymentRecordNo(currentRow) }}</t-descriptions-item>
          <t-descriptions-item label="支付渠道">{{ paymentRecordSummary(currentRow) }}</t-descriptions-item>
          <t-descriptions-item label="创建时间">{{ currentRow.created_at || '--' }}</t-descriptions-item>
          <t-descriptions-item label="截止时间">{{ currentRow.due_date || '--' }}</t-descriptions-item>
          <t-descriptions-item v-if="currentRow.paid_at" label="支付时间">{{ currentRow.paid_at }}</t-descriptions-item>
        </t-descriptions>

        <div v-if="isPayableInvoice(currentRow)" class="invoice-drawer-actions">
          <t-button theme="primary" @click="goToPay(currentRow)">去支付</t-button>
          <t-button theme="danger" variant="outline" :loading="canceling" @click="cancelInvoice(currentRow)">
            取消账单
          </t-button>
        </div>
      </div>
    </t-drawer>
  </section>
</template>

<script setup lang="ts">
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { SearchIcon } from 'tdesign-icons-vue-next';

import {
  formatMoney,
  INVOICE_STATUS_OPTIONS,
  INVOICE_TYPE_OPTIONS,
  isPayableInvoice,
  resolveInvoiceNo,
  resolveInvoiceStatusLabel,
  resolveInvoiceTagTheme,
  useInvoiceList,
} from '@/domains/finance/useInvoices';

const {
  loading,
  canceling,
  list,
  total,
  filters,
  detailVisible,
  currentRow,
  showTypeSelector,
  loadList,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
  openDetail,
  closeDetail,
  goToPay,
  cancelInvoice,
} = useInvoiceList();

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

function paymentRecordSummary(row: Record<string, any>) {
  const payment = paymentRecord(row);
  const parts = [payment.gateway_label || payment.gateway, payment.status_label].filter(Boolean);
  return parts.length ? parts.join(' / ') : '--';
}
</script>

<style scoped lang="less">
@import '../record-page.less';

.invoice-money {
  color: var(--td-text-color-primary);
  font: var(--td-font-body-medium);
  font-weight: 600;
}

.invoice-drawer-body {
  display: grid;
  gap: var(--td-comp-margin-m);
}

.invoice-drawer-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--td-comp-margin-s);
  justify-content: flex-end;
}

@media (max-width: 48rem) {
  .invoice-drawer-actions {
    justify-content: flex-start;
  }
}
</style>
