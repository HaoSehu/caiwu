<template>
  <section class="record-page">
    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input v-model="filters.keyword" clearable placeholder="搜索支付号或交易号" @enter="handleSearch" @clear="handleSearch">
          <template #suffixIcon><SearchIcon /></template>
        </t-input>
        <t-select v-model="filters.status" clearable placeholder="全部状态" @change="handleSearch">
          <t-option v-for="item in PAYMENT_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select v-model="filters.gateway" clearable placeholder="全部渠道" @change="handleSearch">
          <t-option v-for="item in PAYMENT_GATEWAY_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
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
      <t-loading :loading="loading" text="正在加载第三方支付记录">
        <template v-if="hasRows">
          <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
            <template #payment="{ row }">
              <div class="stack-cell">
                <strong>{{ row.payment_no || `#${row.id}` }}</strong>
                <span>{{ row.trade_no || '--' }}</span>
              </div>
            </template>
            <template #gateway="{ row }">{{ row.gateway_label || row.gateway || '--' }}</template>
            <template #amount="{ row }">¥{{ formatMoney(row.amount) }}</template>
            <template #status="{ row }">
              <t-tag :theme="resolvePaymentTagTheme(row.status)" variant="light">{{ row.status_label || '--' }}</t-tag>
            </template>
            <template #time="{ row }">{{ row.paid_at || row.created_at || '--' }}</template>
            <template #operation="{ row }">
              <t-button size="small" theme="primary" variant="text" @click="openDetail(row)">查看</t-button>
            </template>
          </t-table>

          <div class="record-mobile-list">
            <article v-for="row in list" :key="row.id" class="record-mobile-card" @click="openDetail(row)">
              <div class="record-mobile-card__head">
                <strong>{{ row.payment_no || `#${row.id}` }}</strong>
                <t-tag :theme="resolvePaymentTagTheme(row.status)" variant="light">{{ row.status_label || '--' }}</t-tag>
              </div>
              <div class="stack-cell">
                <strong>¥{{ formatMoney(row.amount) }}</strong>
                <span>{{ row.gateway_label || row.gateway || '--' }}</span>
              </div>
              <div class="record-mobile-card__meta">
                <span>{{ row.trade_no || '等待渠道回调' }}</span>
                <span>{{ row.paid_at || row.created_at || '--' }}</span>
              </div>
            </article>
          </div>
        </template>
        <t-empty v-else description="暂无第三方支付记录" />
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
      header="第三方支付详情"
      size="min(30rem, calc(100vw - 2rem))"
      destroy-on-close
      :close-btn="true"
      @close="closeDetail"
    >
      <div v-if="currentRow" class="record-detail-body">
        <section class="record-detail-section">
          <h4>第三方支付信息</h4>
          <div class="record-kv-grid">
            <div class="record-kv-item">
              <span>支付金额</span>
              <strong>¥{{ formatMoney(currentRow.amount) }}</strong>
            </div>
            <div class="record-kv-item">
              <span>支付渠道</span>
              <strong>{{ currentRow.gateway_label || currentRow.gateway || '--' }}</strong>
            </div>
            <div class="record-kv-item">
              <span>状态</span>
              <t-tag :theme="resolvePaymentTagTheme(currentRow.status)" variant="light">
                {{ currentRow.status_label || '--' }}
              </t-tag>
            </div>
            <div class="record-kv-item">
              <span>支付号</span>
              <strong>{{ currentRow.payment_no || `#${currentRow.id}` }}</strong>
            </div>
            <div class="record-kv-item">
              <span>渠道交易号</span>
              <strong>{{ currentRow.trade_no || '--' }}</strong>
            </div>
            <div class="record-kv-item">
              <span>到账时间</span>
              <strong>{{ currentRow.paid_at || '--' }}</strong>
            </div>
            <div class="record-kv-item">
              <span>创建时间</span>
              <strong>{{ currentRow.created_at || '--' }}</strong>
            </div>
          </div>
        </section>
      </div>
    </t-drawer>
  </section>
</template>

<script setup lang="ts">
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { SearchIcon } from 'tdesign-icons-vue-next';

import {
  formatMoney,
  PAYMENT_GATEWAY_OPTIONS,
  PAYMENT_STATUS_OPTIONS,
  recordApi,
  resolvePaymentTagTheme,
  useRecordList,
} from '@/domains/finance/useRecords';

const {
  loading,
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
  openDetail,
  closeDetail,
} = useRecordList(recordApi.payments, '充值记录加载失败');

const columns: PrimaryTableCol[] = [
  { colKey: 'payment', title: '支付号', minWidth: '14rem' },
  { colKey: 'gateway', title: '支付渠道', width: '8rem' },
  { colKey: 'amount', title: '支付金额', width: '9rem' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'time', title: '时间', minWidth: '12rem' },
  { colKey: 'operation', title: '操作', width: '6rem', fixed: 'right', align: 'right' },
];
</script>

<style scoped lang="less">
@import '../record-page.less';
</style>
