<template>
  <div class="finance-new-customers-page">
    <t-card :bordered="false">
      <div class="report-filter">
        <t-date-picker
          v-model="dateRange.start_date"
          clearable
          mode="date"
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="开始日期"
          @change="loadData"
        />
        <t-date-picker
          v-model="dateRange.end_date"
          clearable
          mode="date"
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="结束日期"
          @change="loadData"
        />
        <t-button theme="primary" @click="loadData">
          <template #icon><search-icon /></template>
          搜索
        </t-button>
        <t-button variant="outline" @click="resetCurrentMonth">
          <template #icon><refresh-icon /></template>
          本月
        </t-button>
        <t-button variant="outline" :loading="loading" @click="loadData">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>
    </t-card>

    <t-card :bordered="false">
      <div class="table-scroll">
        <t-table row-key="date" :data="dailyList" :columns="columns" :loading="loading" hover table-layout="fixed" />
      </div>
    </t-card>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';

import { adminApi, type NewCustomerDailyRecord } from '@/api/admin';

import './index.less';

const loading = ref(false);
const dailyList = ref<NewCustomerDailyRecord[]>([]);
const dateRange = reactive(currentMonthRange());

const columns: PrimaryTableCol<NewCustomerDailyRecord>[] = [
  { colKey: 'date', title: '日期', minWidth: 130 },
  { colKey: 'new_customers', title: '新增客户', minWidth: 110 },
  { colKey: 'new_orders', title: '新订单', minWidth: 100 },
  { colKey: 'completed_orders', title: '完成', minWidth: 100 },
  { colKey: 'new_tickets', title: '新建工单', minWidth: 110 },
  { colKey: 'ticket_replies', title: '回复工单', minWidth: 110 },
  { colKey: 'cancel_requests', title: '取消请求', minWidth: 110 },
];

async function loadData() {
  if (!dateRange.start_date || !dateRange.end_date) {
    MessagePlugin.warning('请选择起始时间和终止时间');
    return;
  }

  loading.value = true;
  try {
    const response = await adminApi.financeMenu.newCustomerDailySummary({
      start_date: dateRange.start_date,
      end_date: dateRange.end_date,
    });
    dailyList.value = response.list || [];
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载新客户日报失败'));
  } finally {
    loading.value = false;
  }
}

function resetCurrentMonth() {
  Object.assign(dateRange, currentMonthRange());
  loadData();
}

function currentMonthRange() {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  return {
    start_date: formatDate(start),
    end_date: formatDate(end),
  };
}

function formatDate(date: Date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function errorMessage(error: unknown, fallback: string) {
  const record = toRecord(error);
  const response = toRecord(record.response);
  const data = toRecord(response.data);
  return String(data.message || record.message || fallback);
}

onMounted(() => loadData());
</script>
