<template>
  <div class="page-container admin-page finance-income-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">财务</span>
        <h2>产品收入</h2>
        <p>按月份统计所有产品的新订购收入、续费收入、数量与总金额。</p>
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
      <el-table :data="list" v-loading="loading" stripe row-key="product_id">
        <el-table-column label="产品" min-width="220">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.product_name || `产品 #${row.product_id}` }}</strong>
              <span>{{ row.product_type || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="新订购收入" min-width="130">
          <template #default="{ row }">{{ formatMoney(row.new_income) }}</template>
        </el-table-column>
        <el-table-column prop="new_quantity" label="新订购数量" min-width="120" />
        <el-table-column label="续费收入" min-width="130">
          <template #default="{ row }">{{ formatMoney(row.renew_income) }}</template>
        </el-table-column>
        <el-table-column prop="renew_quantity" label="续费数量" min-width="110" />
        <el-table-column label="总金额" min-width="130">
          <template #default="{ row }">{{ formatMoney(row.total_amount) }}</template>
        </el-table-column>
        <el-table-column prop="total_quantity" label="总数量" min-width="100" />
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
  { key: 'new_income', label: '新订购收入', value: formatMoney(summary.value.new_income) },
  { key: 'new_quantity', label: '新订购数量', value: summary.value.new_quantity || 0 },
  { key: 'renew_income', label: '续费收入', value: formatMoney(summary.value.renew_income) },
  { key: 'renew_quantity', label: '续费数量', value: summary.value.renew_quantity || 0 },
  { key: 'total_amount', label: '总金额', value: formatMoney(summary.value.total_amount) },
  { key: 'total_quantity', label: '总数量', value: summary.value.total_quantity || 0 },
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

function formatMoney(value) {
  return `¥${Number(value || 0).toFixed(2)}`
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
    const res = await adminApi.financeMenu.productIncomeSummary({
      start_date: startDate,
      end_date: endDate,
    })
    summary.value = res.data?.summary || {}
    list.value = Array.isArray(res.data?.list) ? res.data.list : []
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || error?.message || '加载产品收入失败')
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
.finance-income-page {
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

.stack-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;

  strong {
    color: $text-color-primary;
    font-weight: 600;
  }

  span {
    color: $text-color-placeholder;
    font-size: 12px;
  }
}
</style>
