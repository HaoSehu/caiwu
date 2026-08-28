<template>
  <section class="record-page">
    <div class="quick-filter-tags">
      <t-tag
        v-for="item in quickFilters"
        :key="item.key"
        :variant="filters.quickFilter === item.key ? 'outline' : 'light'"
        :theme="filters.quickFilter === item.key ? 'primary' : 'default'"
        class="quick-filter-tag"
        role="button"
        tabindex="0"
        :aria-pressed="filters.quickFilter === item.key"
        @click="applyQuickFilter(item.key)"
        @keydown.enter.prevent="applyQuickFilter(item.key)"
        @keydown.space.prevent="applyQuickFilter(item.key)"
      >
        {{ item.label }}
      </t-tag>
    </div>

    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input
          v-model="filters.keyword"
          clearable
          placeholder="搜索商家订单号或第三方订单号"
          @enter="handleSearch"
          @clear="handleSearch"
        >
          <template #suffixIcon><search-icon /></template>
        </t-input>
        <t-select v-model="filters.status" clearable placeholder="全部状态" @change="handleSearch">
          <t-option v-for="item in PAYMENT_STATUS_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-select v-model="filters.type" clearable placeholder="全部渠道" @change="handleSearch">
          <t-option v-for="item in PAYMENT_GATEWAY_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
      </div>
    </t-card>

    <section class="record-list-card">
      <data-state
        :loading="loading"
        :empty="!loading && !loadError && !hasRows"
        :error="!loading && loadError"
        :error-text="loadErrorText"
        description="暂无第三方支付记录"
        @retry="loadList"
      >
        <template #empty-action>
          <t-button theme="primary" @click="router.push('/client/recharge')">去充值</t-button>
        </template>
        <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
          <template #payment="{ row }">
            <div class="stack-cell">
              <strong :title="row.payment_no">商家：{{ row.payment_no || '--' }}</strong>
              <span :title="row.trade_no">第三方：{{ row.trade_no || '--' }}</span>
            </div>
          </template>
          <template #gateway="{ row }">{{ gatewayDisplay(row) }}</template>
          <template #amount="{ row }">
            <span class="payment-money">¥{{ formatMoney(row.amount) }}</span>
          </template>
          <template #status="{ row }">
            <status-tag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
          </template>
          <template #time="{ row }">{{ formatDateTime(row.paid_at || row.created_at) }}</template>
          <template #operation="{ row }">
            <t-button size="small" theme="primary" variant="text" @click="goToDetail(row)">查看</t-button>
          </template>
        </t-table>

        <div class="record-mobile-list">
          <article
            v-for="row in list"
            :key="row.id"
            class="record-mobile-card"
            role="link"
            tabindex="0"
            :aria-label="`查看支付记录 ${row.payment_no || row.id} 详情`"
            @click="goToDetail(row)"
            @keydown.enter.prevent="goToDetail(row)"
          >
            <div class="record-mobile-card__head">
              <strong>商家：{{ row.payment_no || '--' }}</strong>
              <status-tag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
            </div>
            <div class="stack-cell">
              <span class="stack-money">¥{{ formatMoney(row.amount) }}</span>
              <span>{{ gatewayDisplay(row) }}</span>
            </div>
            <div class="record-mobile-card__meta">
              <span>第三方：{{ row.trade_no || '等待渠道回调' }}</span>
              <span>{{ formatDateTime(row.paid_at || row.created_at) }}</span>
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
import { PAYMENT_STATUS_MAP } from '@shared/statusConfig';
import DataState from '@shared/user-v3/components/DataState.vue';
import StatusTag from '@shared/user-v3/components/StatusTag.vue';
import { SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { useRouter } from 'vue-router';

import {
  formatMoney,
  PAYMENT_GATEWAY_OPTIONS,
  PAYMENT_STATUS_OPTIONS,
  recordApi,
  useRecordList,
} from '@/domains/finance/useRecords';
import type { PaymentRecord } from '@/types/client';
import { formatDateTime } from '@/utils/format';

const router = useRouter();

const { loading, list, total, filters, hasRows, loadError, loadErrorText, loadList, handleSearch, handlePageSizeChange, applyQuickFilter } =
  useRecordList(recordApi.payments, '第三方支付记录加载失败');

const quickFilters = [
  { key: '', label: '全部' },
  { key: 'week', label: '最近7天' },
  { key: 'month', label: '本月' },
  { key: 'pending', label: '待支付' },
];

function goToDetail(row: PaymentRecord) {
  router.push(`/client/payments/${row.id}`);
}

function gatewayDisplay(row: PaymentRecord) {
  return row.gateway_label || row.gateway_key || row.gateway || '--';
}

const columns: PrimaryTableCol[] = [
  { colKey: 'payment', title: '支付订单号', minWidth: '16rem' },
  { colKey: 'gateway', title: '支付渠道', width: '8rem' },
  { colKey: 'amount', title: '支付金额', width: '9rem', align: 'right' },
  { colKey: 'status', title: '状态', width: '8rem' },
  { colKey: 'time', title: '时间', minWidth: '12rem' },
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
</style>
