<template>
  <div class="client-page balance-logs-page">
    <section class="summary-grid">
      <article class="summary-card">
        <span>当前余额</span>
        <strong>¥ {{ summary.balance || '0.00' }}</strong>
      </article>
      <article class="summary-card">
        <span>累计入账</span>
        <strong>¥ {{ summary.total_in || '0.00' }}</strong>
      </article>
      <article class="summary-card">
        <span>累计支出</span>
        <strong>¥ {{ summary.total_out || '0.00' }}</strong>
      </article>
    </section>

    <section class="panel-card">
      <div class="toolbar-grid">
        <el-select v-model="eventType" placeholder="全部类型" clearable>
          <el-option label="充值" value="recharge" />
          <el-option label="消费" value="consume" />
          <el-option label="退款" value="refund" />
          <el-option label="调整" value="adjust" />
        </el-select>
        <div class="toolbar-actions">
          <el-button @click="resetFilters">重置</el-button>
          <el-button type="primary" @click="loadData">筛选</el-button>
        </div>
      </div>

      <el-table :data="list" v-loading="loading">
        <el-table-column label="类型" min-width="120" prop="event_type_label" />
        <el-table-column label="变动金额" min-width="140">
          <template #default="{ row }">
            <span :class="Number(row.change_amount || row.amount || 0) >= 0 ? 'amount-up' : 'amount-down'">
              {{ row.change_amount || row.amount || '0.00' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="说明" min-width="220" prop="remark" />
        <el-table-column label="时间" min-width="180" prop="created_at" />
      </el-table>

      <el-empty v-if="!loading && !list.length" description="暂无流水记录" />

      <div v-if="total > 0" class="pager-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next"
          @current-change="loadList"
          @size-change="handlePageSizeChange"
        />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const eventType = ref('')
const summary = reactive({
  balance: '0.00',
  total_in: '0.00',
  total_out: '0.00',
})

async function loadSummary() {
  const response = await clientApi.balanceLogsSummary({
    event_type: eventType.value || undefined,
  })
  Object.assign(summary, response.data || {})
}

async function loadList() {
  loading.value = true
  try {
    const response = await clientApi.balanceLogs({
      page: page.value,
      page_size: pageSize.value,
      event_type: eventType.value || undefined,
    })
    list.value = Array.isArray(response.data?.list) ? response.data.list : []
    total.value = Number(response.data?.total || 0)
  } catch (error: any) {
    if (!error?.__handled) ElMessage.error(error?.message || '余额流水加载失败')
  } finally {
    loading.value = false
  }
}

async function loadData() {
  await Promise.all([loadSummary(), loadList()])
}

function handlePageSizeChange() {
  page.value = 1
  void loadList()
}

function resetFilters() {
  eventType.value = ''
  page.value = 1
  void loadData()
}

onMounted(() => {
  void loadData()
})
</script>

<style scoped lang="scss">
.balance-logs-page {
  gap: 20px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.summary-card,
.panel-card {
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}

.summary-card {
  padding: 18px 20px;

  span {
    display: block;
    color: $text-color-secondary;
    font-size: 13px;
  }

  strong {
    display: block;
    margin-top: 10px;
    font-size: 28px;
    font-weight: 700;
  }
}

.panel-card {
  padding: 20px;
}

.toolbar-grid {
  display: grid;
  grid-template-columns: 180px auto;
  gap: 16px;
  margin-bottom: 18px;
}

.toolbar-actions,
.pager-wrap {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.pager-wrap {
  margin-top: 16px;
}

.amount-up {
  color: $color-success;
}

.amount-down {
  color: $color-danger;
}

@media (max-width: 767px) {
  .summary-grid,
  .toolbar-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-actions,
  .pager-wrap {
    justify-content: flex-start;
  }
}
</style>
