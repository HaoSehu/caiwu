<template>
  <el-card shadow="never" class="panel-card invoices-panel">
    <template #header>
      <div class="panel-header">
        <div>
          <strong>最近账单</strong>
          <p>展示最近业务流水。</p>
        </div>
        <el-button text type="primary" @click="emit('view-all')">
          查看全部
        </el-button>
      </div>
    </template>

    <el-table :data="invoices" stripe class="recent-invoices-table">
      <template #empty>
        <div class="table-empty">
          <strong>暂无账单记录</strong>
          <p>后台产生新账单后，这里会展示最新 10 条业务流水。</p>
        </div>
      </template>

      <el-table-column prop="invoice_no" label="账单号" min-width="180" />

      <el-table-column label="用户" min-width="160">
        <template #default="{ row }">
          {{ row.user?.nickname || row.user?.email || '-' }}
        </template>
      </el-table-column>

      <el-table-column label="金额" min-width="120" align="right">
        <template #default="{ row }">
          {{ formatCurrency(row.amount) }}
        </template>
      </el-table-column>

      <el-table-column label="状态" min-width="120">
        <template #default="{ row }">
          <el-tag effect="light" size="small" :type="statusType(row.status)">
            {{ statusText(row.status) }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="创建时间" min-width="180">
        <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup>
import { formatDateTime } from '@/utils/datetime'
defineProps({
  invoices: { type: Array, required: true },
  statusText: { type: Function, required: true },
  statusType: { type: Function, required: true },
  formatCurrency: { type: Function, required: true },
})

const emit = defineEmits(['view-all'])
</script>

<style lang="scss" scoped>
.panel-card { border-radius: $base-border-radius; }

.invoices-panel :deep(.el-card__body) { padding-top: 12px; }

.panel-header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.panel-header strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header p { margin-top: 4px; color: $text-color-secondary; font-size: 12px; line-height: 1.6; }

.recent-invoices-table :deep(.el-table__header th) {
  background: $bg-color-soft;
}

.recent-invoices-table :deep(.el-table__row) {
  transition: background-color $duration-fast $ease-standard;
}

.table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 40px 0;
}

.table-empty strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.table-empty p { color: $text-color-secondary; font-size: 13px; }

@include mobile-and-below {
  .invoices-panel :deep(.el-table) {
    font-size: 12px;
  }

  .invoices-panel :deep(.el-table .cell) {
    padding-left: 8px;
    padding-right: 8px;
  }
}
</style>
