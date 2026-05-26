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

    <div v-if="!invoices.length" class="table-empty">
      <strong>暂无账单记录</strong>
      <p>后台产生新账单后，这里会展示最新 10 条业务流水。</p>
    </div>

    <div v-else class="invoice-list">
      <div
        v-for="row in invoices"
        :key="row.id"
        class="invoice-row"
      >
        <div class="invoice-row__top">
          <span class="invoice-no">{{ row.invoice_no }}</span>
          <span class="invoice-amount">{{ formatCurrency(row.amount) }}</span>
        </div>
        <div class="invoice-row__bottom">
          <el-tag effect="light" size="small" :type="statusType(row.status)">
            {{ statusText(row.status) }}
          </el-tag>
          <span class="invoice-time">{{ formatDateTime(row.created_at) }}</span>
        </div>
      </div>
    </div>
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

.invoices-panel :deep(.el-card__body) { padding: 8px 0 0; }

.panel-header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.panel-header strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header p { margin-top: 4px; color: $text-color-secondary; font-size: 12px; line-height: 1.6; }

.invoice-list {
  display: flex;
  flex-direction: column;
}

.invoice-row {
  padding: 10px 16px;
  border-bottom: 1px solid $divider-color;
  transition: background-color 0.15s ease;

  &:last-child { border-bottom: none; }
  &:hover { background: $bg-color-soft; }
}

.invoice-row__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.invoice-no {
  font-size: 13px;
  font-weight: 600;
  color: $text-color-primary;
  font-family: "SF Mono", "Cascadia Code", "Consolas", monospace;
  letter-spacing: .02em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.invoice-amount {
  font-size: 15px;
  font-weight: 700;
  color: $text-color-primary;
  white-space: nowrap;
  flex-shrink: 0;
}

.invoice-row__bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 4px;
}

.invoice-time {
  font-size: 11px;
  color: $text-color-placeholder;
  white-space: nowrap;
}

.table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 48px 0;
}

.table-empty strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.table-empty p { color: $text-color-secondary; font-size: 13px; }
</style>
