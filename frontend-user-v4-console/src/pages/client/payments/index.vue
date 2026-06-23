<template>
  <section class="record-page">
    <t-card class="record-card" :bordered="false">
      <div class="record-toolbar">
        <t-input v-model="filters.keyword" clearable placeholder="搜索商家订单号或第三方订单号" @enter="handleSearch" @clear="handleSearch">
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
      <DataState :loading="loading" :empty="!hasRows" description="暂无第三方支付记录">
          <t-table class="record-table" row-key="id" :data="list" :columns="columns" :pagination="null" hover>
            <template #payment="{ row }">
              <div class="stack-cell">
                <strong>商家：{{ row.payment_no || '--' }}</strong>
                <span>第三方：{{ row.trade_no || '--' }}</span>
              </div>
            </template>
            <template #gateway="{ row }">{{ row.gateway || '--' }}</template>
            <template #amount="{ row }">¥{{ formatMoney(row.amount) }}</template>
            <template #status="{ row }">
              <StatusTag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
            </template>
            <template #time="{ row }">{{ row.paid_at || row.created_at || '--' }}</template>
            <template #operation="{ row }">
              <t-button size="small" theme="primary" variant="text" @click="goToDetail(row)">查看</t-button>
            </template>
          </t-table>

          <div class="record-mobile-list">
            <article v-for="row in list" :key="row.id" class="record-mobile-card" @click="goToDetail(row)">
              <div class="record-mobile-card__head">
                <strong>商家：{{ row.payment_no || '--' }}</strong>
                <StatusTag :status-map="PAYMENT_STATUS_MAP" :status="Number(row.status)" />
              </div>
              <div class="stack-cell">
                <strong>¥{{ formatMoney(row.amount) }}</strong>
                <span>{{ row.gateway || '--' }}</span>
              </div>
              <div class="record-mobile-card__meta">
                <span>第三方：{{ row.trade_no || '等待渠道回调' }}</span>
                <span>{{ row.paid_at || row.created_at || '--' }}</span>
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
import { PAYMENT_STATUS_MAP } from '@shared/statusConfig';

import DataState from '@shared/user-v3/components/DataState.vue';
import StatusTag from '@shared/user-v3/components/StatusTag.vue';
import {
  formatMoney,
  PAYMENT_GATEWAY_OPTIONS,
  PAYMENT_STATUS_OPTIONS,
  recordApi,
  useRecordList,
} from '@/domains/finance/useRecords';
import type { PaymentRecord } from '@/types/client';

const router = useRouter();

const {
  loading,
  list,
  total,
  filters,
  hasRows,
  loadList,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
} = useRecordList(recordApi.payments, '第三方支付记录加载失败');

function goToDetail(row: PaymentRecord) {
  router.push(`/client/payments/${row.id}`);
}

const columns: PrimaryTableCol[] = [
  { colKey: 'payment', title: '支付订单号', minWidth: '16rem' },
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
