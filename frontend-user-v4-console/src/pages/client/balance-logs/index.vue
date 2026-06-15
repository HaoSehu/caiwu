<template>
  <section class="record-page">
    <t-card class="record-card" :bordered="false">
      <div class="balance-toolbar">
        <t-select v-model="filters.event_type" clearable placeholder="全部类型" @change="handleSearch">
          <t-option v-for="item in BALANCE_EVENT_OPTIONS" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <div class="record-actions">
          <t-button theme="primary" @click="handleSearch">筛选</t-button>
          <t-button variant="outline" @click="resetFilters">重置</t-button>
        </div>
      </div>
    </t-card>

    <section class="record-list-card">
      <t-loading :loading="loading" text="正在加载余额流水">
        <template v-if="hasRows">
          <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
            <template #type="{ row }">{{ row.event_type_label || row.event_type || '--' }}</template>
            <template #change="{ row }">
              <span :class="['balance-amount', resolveBalanceTheme(row.change_amount || row.amount)]">
                {{ row.change_amount || row.amount || '0.00' }}
              </span>
            </template>
            <template #balance="{ row }">¥{{ formatMoney(row.balance_after || row.after_balance) }}</template>
            <template #remark="{ row }">{{ row.remark || '--' }}</template>
          </t-table>

          <div class="record-mobile-list">
            <article v-for="row in list" :key="row.id" class="record-mobile-card">
              <div class="record-mobile-card__head">
                <strong>{{ row.event_type_label || row.event_type || '--' }}</strong>
                <span :class="['balance-amount', resolveBalanceTheme(row.change_amount || row.amount)]">
                  {{ row.change_amount || row.amount || '0.00' }}
                </span>
              </div>
              <div class="stack-cell">
                <strong>{{ row.remark || '--' }}</strong>
                <span>变动后余额：¥{{ formatMoney(row.balance_after || row.after_balance) }}</span>
              </div>
              <div class="record-mobile-card__meta">
                <span>{{ row.created_at || '--' }}</span>
              </div>
            </article>
          </div>
        </template>
        <t-empty v-else description="暂无流水记录" />
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
  </section>
</template>

<script setup lang="ts">
import type { PrimaryTableCol } from 'tdesign-vue-next';

import {
  BALANCE_EVENT_OPTIONS,
  formatMoney,
  recordApi,
  resolveBalanceTheme,
  useRecordList,
} from '@/domains/finance/useRecords';

const { loading, list, total, filters, hasRows, loadList, handleSearch, handlePageSizeChange, resetFilters } =
  useRecordList(recordApi.balanceLogs, '余额流水加载失败');

const columns: PrimaryTableCol[] = [
  { colKey: 'type', title: '类型', minWidth: '8rem' },
  { colKey: 'change', title: '变动金额', minWidth: '9rem' },
  { colKey: 'balance', title: '变动后余额', minWidth: '10rem' },
  { colKey: 'remark', title: '说明', minWidth: '18rem' },
  { colKey: 'created_at', title: '时间', minWidth: '12rem' },
];
</script>

<style scoped lang="less">
@import '../record-page.less';

.balance-toolbar {
  display: grid;
  grid-template-columns: minmax(10rem, 14rem) auto;
  gap: var(--td-comp-margin-s);
  align-items: center;
}

.balance-amount {
  font: var(--td-font-body-medium);
  font-weight: 600;

  &.success {
    color: var(--td-success-color);
  }

  &.danger {
    color: var(--td-error-color);
  }
}

@media (max-width: 48rem) {
  .balance-toolbar {
    grid-template-columns: 1fr;
  }
}
</style>
