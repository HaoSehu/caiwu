<template>
  <div class="client-page balance-logs-page">
    <div class="toolbar-grid">
        <el-select v-model="eventType" placeholder="全部类型" clearable @change="loadData">
          <el-option label="充值" value="recharge" />
          <el-option label="消费" value="consume" />
          <el-option label="退款" value="refund" />
          <el-option label="调整" value="adjust" />
        </el-select>
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
  await loadList()
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
.toolbar-grid {
  display: grid;
  grid-template-columns: 180px auto;
  gap: 16px;
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
  .toolbar-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-actions,
  .pager-wrap {
    justify-content: flex-start;
  }
}
</style>
