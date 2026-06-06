<template>
  <div class="page-container admin-page finance-report-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>新客户</h2>
        <p>按月份查看每日新增客户、新订单、完成订单、工单与取消请求。</p>
      </div>
    </section>

    <section class="filter-panel">
      <div class="search-bar">
        <div class="date-range-filter">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            value-format="YYYY-MM-DD"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            clearable
            unlink-panels
            @change="handleDateChange"
          />
        </div>
      </div>
    </section>

    <div class="admin-metric-strip">
      <div v-for="item in metrics" :key="item.key" class="metric-card">
        <div class="metric-label">{{ item.label }}</div>
        <div class="metric-value">{{ item.value }}</div>
      </div>
    </div>

    <el-card shadow="never" class="table-card">
      <el-table :data="list" v-loading="loading" stripe row-key="date">
        <el-table-column prop="date" label="日期" min-width="130" />
        <el-table-column prop="new_customers" label="新增客户" min-width="110" />
        <el-table-column prop="new_orders" label="新订单" min-width="100" />
        <el-table-column prop="completed_orders" label="完成" min-width="100" />
        <el-table-column prop="new_tickets" label="新建工单" min-width="110" />
        <el-table-column prop="ticket_replies" label="回复工单" min-width="110" />
        <el-table-column prop="cancel_requests" label="取消请求" min-width="110" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import adminApi from '@/api/admin'

const loading = ref(false)
const dateRange = ref(currentMonthRange())
const summary = ref({})
const list = ref([])

const metrics = computed(() => [
  { key: 'new_customers', label: '新增客户', value: summary.value.new_customers || 0 },
  { key: 'new_orders', label: '新订单', value: summary.value.new_orders || 0 },
  { key: 'completed_orders', label: '完成', value: summary.value.completed_orders || 0 },
  { key: 'new_tickets', label: '新建工单', value: summary.value.new_tickets || 0 },
  { key: 'ticket_replies', label: '回复工单', value: summary.value.ticket_replies || 0 },
  { key: 'cancel_requests', label: '取消请求', value: summary.value.cancel_requests || 0 },
])

function formatDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function currentMonthRange() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)

  return [formatDate(start), formatDate(end)]
}

function handleDateChange() {
  if (Array.isArray(dateRange.value) && dateRange.value.length === 2) {
    void loadData()
  }
}

async function loadData() {
  const [startDate, endDate] = Array.isArray(dateRange.value) ? dateRange.value : []
  if (!startDate || !endDate) {
    ElMessage.warning('请选择起始时间和终止时间')
    return
  }

  loading.value = true
  try {
    const res = await adminApi.financeMenu.newCustomerDailySummary({
      start_date: startDate,
      end_date: endDate,
    })
    summary.value = res.data?.summary || {}
    list.value = Array.isArray(res.data?.list) ? res.data.list : []
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载新客户日报失败')
  } finally {
    loading.value = false
  }
}

function resetCurrentMonth() {
  dateRange.value = currentMonthRange()
  void loadData()
}

onMounted(() => loadData())
</script>

<style lang="scss" scoped>
.finance-report-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.date-range-filter {
  width: 360px;
  max-width: 100%;
}

.date-range-filter :deep(.el-date-editor) {
  width: 100%;
}

@media (max-width: 768px) {
  .date-range-filter {
    width: 100%;
  }
}
</style>
