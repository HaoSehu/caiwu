<template>
  <section class="record-page">
    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input v-model="filters.keyword" clearable placeholder="搜索订单号、账单号或服务名" @enter="handleSearch" @clear="handleSearch">
          <template #suffixIcon><SearchIcon /></template>
        </t-input>
        <t-select v-model="filters.status" clearable placeholder="全部状态" @change="handleSearch">
          <t-option v-for="item in ORDER_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select v-model="filters.type" clearable placeholder="全部类型" @change="handleSearch">
          <t-option v-for="item in ORDER_TYPE_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
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
      <t-loading :loading="loading" text="正在加载订单记录">
        <template v-if="hasRows">
          <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
            <template #order="{ row }">
              <div class="stack-cell">
                <strong>{{ row.order_no || `#${row.id}` }}</strong>
                <span>{{ row.type_label || row.type || '--' }}</span>
              </div>
            </template>
            <template #service="{ row }">
              <div class="stack-cell">
                <strong>{{ serviceIdLabel(row) }}</strong>
                <span>{{ serviceName(row) }}</span>
              </div>
            </template>
            <template #product="{ row }">
              <div class="stack-cell">
                <strong>{{ row.product_full_path || row.product_name || '--' }}</strong>
                <span>{{ formatBillingCycle(row.billing_cycle) }}</span>
              </div>
            </template>
            <template #amount="{ row }">
              <div class="stack-cell">
                <strong>¥{{ formatMoney(row.amount) }}</strong>
                <span>已付 ¥{{ formatMoney(row.paid_amount) }}</span>
              </div>
            </template>
            <template #status="{ row }">
              <t-tag :theme="resolveOrderTagTheme(row.status)" variant="light">{{ row.status_label || '--' }}</t-tag>
            </template>
            <template #operation="{ row }">
              <t-button size="small" theme="primary" variant="text" @click="openDetail(row)">查看</t-button>
            </template>
          </t-table>

          <div class="record-mobile-list">
            <article v-for="row in list" :key="row.id" class="record-mobile-card" @click="openDetail(row)">
              <div class="record-mobile-card__head">
                <strong>{{ row.order_no || `#${row.id}` }}</strong>
                <t-tag :theme="resolveOrderTagTheme(row.status)" variant="light">{{ row.status_label || '--' }}</t-tag>
              </div>
              <div class="stack-cell">
                <strong>{{ row.product_full_path || row.product_name || '--' }}</strong>
                <span>{{ serviceIdLabel(row) }}</span>
              </div>
              <div class="record-mobile-card__meta">
                <span>订单：¥{{ formatMoney(row.amount) }}</span>
                <span>{{ row.created_at || '--' }}</span>
              </div>
            </article>
          </div>
        </template>
        <t-empty v-else description="暂无订单记录" />
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
      header="订单详情"
      size="min(46rem, calc(100vw - 2rem))"
      destroy-on-close
      :close-btn="true"
      @close="closeDetail"
    >
      <t-loading :loading="detailLoading" text="正在加载订单详情">
        <div v-if="currentRow" class="record-detail-body">
          <section class="record-detail-section">
            <h4>订单信息</h4>
            <div class="record-kv-grid record-kv-grid--two">
              <div class="record-kv-item record-kv-item--span-2">
                <span>订单号</span>
                <strong>{{ currentRow.order_no || `#${currentRow.id}` }}</strong>
              </div>
              <div class="record-kv-item">
                <span>订单类型</span>
                <strong>{{ currentRow.type_label || currentRow.type || '--' }}</strong>
              </div>
              <div class="record-kv-item">
                <span>状态</span>
                <t-tag :theme="resolveOrderTagTheme(currentRow.status)" variant="light">{{ currentRow.status_label || '--' }}</t-tag>
              </div>
              <div class="record-kv-item">
                <span>订单金额</span>
                <strong>¥{{ formatMoney(currentRow.amount) }}</strong>
              </div>
              <div class="record-kv-item">
                <span>实付金额</span>
                <strong>¥{{ formatMoney(currentRow.paid_amount) }}</strong>
              </div>
              <div class="record-kv-item">
                <span>计费周期</span>
                <strong>{{ formatBillingCycle(currentRow.billing_cycle) }}</strong>
              </div>
              <div class="record-kv-item">
                <span>数量</span>
                <strong>{{ currentRow.quantity || 1 }}</strong>
              </div>
              <div class="record-kv-item">
                <span>创建时间</span>
                <strong>{{ formatDateTime(currentRow.created_at) }}</strong>
              </div>
              <div class="record-kv-item">
                <span>支付时间</span>
                <strong>{{ formatDateTime(currentRow.paid_at) }}</strong>
              </div>
            </div>
          </section>

          <section class="record-detail-section">
            <h4>服务实体</h4>
            <div class="record-kv-grid record-kv-grid--two">
              <div class="record-kv-item">
                <span>服务 ID</span>
                <strong>{{ serviceIdLabel(currentRow) }}</strong>
              </div>
              <div class="record-kv-item">
                <span>服务名称</span>
                <strong>{{ serviceName(currentRow) }}</strong>
              </div>
              <div class="record-kv-item">
                <span>服务域名</span>
                <strong>{{ currentRow.service?.domain || '--' }}</strong>
              </div>
              <div class="record-kv-item">
                <span>到期时间</span>
                <strong>{{ formatDateTime(currentRow.service?.expires_at) }}</strong>
              </div>
              <div class="record-kv-item record-kv-item--span-2">
                <span>关联账单</span>
                <div class="record-inline-action">
                  <strong>{{ currentRow.invoice_no || '--' }}</strong>
                  <t-button v-if="currentRow.invoice_id" size="small" theme="primary" variant="text" @click="goToInvoice(currentRow)">查看</t-button>
                </div>
              </div>
            </div>
          </section>

          <section class="record-detail-section">
            <h4>产品链路</h4>
            <div class="record-kv-grid">
              <div class="record-kv-item">
                <span>链路</span>
                <strong>{{ currentRow.product_full_path || currentRow.product_name || '--' }}</strong>
              </div>
            </div>
          </section>

          <section v-if="configItems(currentRow).length" class="record-detail-section">
            <h4>配置快照</h4>
            <div class="record-line-list">
              <div v-for="item in configItems(currentRow)" :key="item.label" class="record-line-item">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
          </section>

          <section v-if="pricingItems(currentRow).length" class="record-detail-section">
            <h4>配置定价</h4>
            <div class="record-line-list">
              <div v-for="item in pricingItems(currentRow)" :key="item.label" class="record-line-item">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
          </section>

          <section v-if="paymentRecords(currentRow).length" class="record-detail-section">
            <h4>支付记录</h4>
            <div class="record-payment-list">
              <div v-for="payment in paymentRecords(currentRow)" :key="String(payment.id || payment.payment_no)" class="record-payment-item">
                <div class="record-payment-item__head">
                  <div>
                    <span>支付单号</span>
                    <strong>{{ payment.payment_no || `#${payment.id}` }}</strong>
                  </div>
                  <t-tag :theme="resolvePaymentTagTheme(payment.status)" variant="light">{{ payment.status_label || '--' }}</t-tag>
                </div>
                <div class="record-kv-grid record-kv-grid--two">
                  <div class="record-kv-item">
                    <span>方式</span>
                    <strong>{{ payment.gateway_label || payment.gateway || '--' }}</strong>
                  </div>
                  <div class="record-kv-item">
                    <span>金额</span>
                    <strong>¥{{ formatMoney(payment.amount) }}</strong>
                  </div>
                  <div class="record-kv-item">
                    <span>第三方单号</span>
                    <strong>{{ payment.trade_no || '--' }}</strong>
                  </div>
                  <div class="record-kv-item">
                    <span>时间</span>
                    <strong>{{ formatDateTime(payment.paid_at || payment.created_at) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </t-loading>
    </t-drawer>
  </section>
</template>

<script setup lang="ts">
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { SearchIcon } from 'tdesign-icons-vue-next';

import {
  configValueLabelMap,
  flattenSnapshot,
  formatBillingCycle,
  formatDateTime,
  formatMoney,
  ORDER_STATUS_OPTIONS,
  ORDER_TYPE_OPTIONS,
  recordApi,
  resolveOrderTagTheme,
  resolvePaymentTagTheme,
  toRecord,
  useRecordList,
} from '@/domains/finance/useRecords';

const {
  loading,
  detailLoading,
  list,
  total,
  filters,
  hasRows,
  detailVisible,
  currentRow,
  loadList,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
  goToInvoice,
  openDetail,
  closeDetail,
} = useRecordList(recordApi.orders, '订单记录加载失败', { detailFetcher: recordApi.orderDetail });

const columns: PrimaryTableCol[] = [
  { colKey: 'order', title: '订单号', minWidth: '12rem' },
  { colKey: 'service', title: '服务实体', minWidth: '12rem' },
  { colKey: 'product', title: '产品链路', minWidth: '20rem' },
  { colKey: 'amount', title: '金额', width: '10rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'created_at', title: '创建时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '6rem', fixed: 'right', align: 'right' },
];

function serviceIdLabel(row: Record<string, any>) {
  const service = toRecord(row.service);
  const id = service.id || row.service_id;
  return id ? `服务 #${id}` : '--';
}

function serviceName(row: Record<string, any>) {
  const service = toRecord(row.service);
  return service.name || row.service_name || '--';
}

function configItems(row: Record<string, any>) {
  return flattenSnapshot(row.config_snapshot, configValueLabelMap(row));
}

function pricingItems(row: Record<string, any>) {
  return flattenSnapshot(row.config_pricing_snapshot);
}

function paymentRecords(row: Record<string, any>) {
  return Array.isArray(row.payments) ? row.payments : [];
}
</script>

<style scoped lang="less">
@import '../record-page.less';
</style>
