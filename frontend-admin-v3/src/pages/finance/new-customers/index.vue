<template>
  <div class="finance-new-customers-page">
    <t-card :bordered="false">
      <div class="report-filter">
        <t-date-range-picker
          v-model="dateRange"
          clearable
          format="YYYY-MM-DD"
          value-type="YYYY-MM-DD"
          placeholder="选择日期范围"
          @change="loadData"
        />
        <div class="filter-actions">
          <t-button variant="outline" size="small" @click="resetCurrentMonth">本月</t-button>
          <t-button variant="outline" size="small" @click="resetLastMonth">上月</t-button>
          <t-button variant="outline" size="small" @click="resetLast30Days">近30天</t-button>
        </div>
      </div>
    </t-card>

    <!-- 周期汇总指标卡 -->
    <div v-if="summaryMetrics.length" class="report-metrics-grid">
      <t-card v-for="metric in summaryMetrics" :key="metric.label" :bordered="false" class="metric-card">
        <span class="metric-label">{{ metric.label }}</span>
        <strong class="metric-value">{{ metric.value }}</strong>
      </t-card>
    </div>

    <!-- 列表展示：桌面端表格 / 移动端卡片 -->
    <t-card :bordered="false">
      <div v-if="!isMobile" class="table-scroll">
        <t-table
          row-key="date"
          :data="dailyList"
          :columns="columns"
          :loading="loading"
          hover
          table-layout="fixed"
        />
      </div>

      <div v-else class="record-mobile-list">
        <t-loading :loading="loading" size="small">
          <div v-if="dailyList.length" class="record-mobile-stack">
            <t-card
              v-for="row in dailyList"
              :key="row.date"
              class="mobile-daily-card"
              :bordered="false"
            >
              <div class="mobile-daily-card__head">
                <strong>{{ row.date }}</strong>
                <t-tag theme="primary" variant="light">新客：{{ row.new_customers || 0 }}</t-tag>
              </div>
              <div class="mobile-daily-grid">
                <div><span>新订单</span><strong>{{ row.new_orders || 0 }}</strong></div>
                <div><span>已支付</span><strong>{{ row.completed_orders || 0 }}</strong></div>
                <div><span>新工单</span><strong>{{ row.new_tickets || 0 }}</strong></div>
                <div><span>工单回复</span><strong>{{ row.ticket_replies || 0 }}</strong></div>
                <div><span>取消请求</span><strong>{{ row.cancel_requests || 0 }}</strong></div>
              </div>
            </t-card>
          </div>
          <t-empty v-else description="暂无新客户日报数据" />
        </t-loading>
      </div>
    </t-card>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import type { PrimaryTableCol } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, ref } from 'vue';

import type { NewCustomerDailyRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminFinanceNewCustomers',
});

const isMobile = useMediaQuery('(max-width: 768px)');
const loading = ref(false);
const dailyList = ref<NewCustomerDailyRecord[]>([]);
const summaryData = ref<Record<string, number | string>>({});
const dateRange = ref<string[]>(currentMonthRange());

const columns: PrimaryTableCol<NewCustomerDailyRecord>[] = [
  { colKey: 'date', title: '日期', minWidth: 130 },
  { colKey: 'new_customers', title: '新增客户', minWidth: 110, align: 'right' },
  { colKey: 'new_orders', title: '新订单', minWidth: 100, align: 'right' },
  { colKey: 'completed_orders', title: '已支付', minWidth: 100, align: 'right' },
  { colKey: 'new_tickets', title: '新建工单', minWidth: 110, align: 'right' },
  { colKey: 'ticket_replies', title: '回复工单', minWidth: 110, align: 'right' },
  { colKey: 'cancel_requests', title: '取消请求', minWidth: 110, align: 'right' },
];

const summaryMetrics = computed(() => {
  const sum = summaryData.value;
  if (!sum || Object.keys(sum).length === 0) return [];
  return [
    { label: '新增客户', value: String(sum.new_customers || 0) },
    { label: '新下单', value: String(sum.new_orders || 0) },
    { label: '已完成订单', value: String(sum.completed_orders || 0) },
    { label: '新建工单', value: String(sum.new_tickets || 0) },
    { label: '工单回复', value: String(sum.ticket_replies || 0) },
    { label: '取消请求', value: String(sum.cancel_requests || 0) },
  ];
});

async function loadData() {
  if (!dateRange.value || dateRange.value.length !== 2 || !dateRange.value[0] || !dateRange.value[1]) {
    MessagePlugin.warning('请选择完整的日期范围');
    return;
  }

  loading.value = true;
  try {
    const response = await adminApi.financeMenu.newCustomerDailySummary({
      start_date: dateRange.value[0],
      end_date: dateRange.value[1],
    });
    dailyList.value = response.list || [];
    summaryData.value = (response.summary as Record<string, number | string>) || {};
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载新客户日报失败'));
  } finally {
    loading.value = false;
  }
}

function resetCurrentMonth() {
  dateRange.value = currentMonthRange();
  void loadData();
}

function resetLastMonth() {
  dateRange.value = lastMonthRange();
  void loadData();
}

function resetLast30Days() {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - 30);
  dateRange.value = [formatDate(start), formatDate(end)];
  void loadData();
}

function currentMonthRange(): [string, string] {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  return [formatDate(start), formatDate(end)];
}

function lastMonthRange(): [string, string] {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const end = new Date(now.getFullYear(), now.getMonth(), 0);
  return [formatDate(start), formatDate(end)];
}

function formatDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

onMounted(() => {
  void loadData();
});
</script>
