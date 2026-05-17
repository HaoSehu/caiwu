<template>
  <!-- 订单面板已被 RecentInvoicesPanel 替代，保留占位。 -->
  <div class="recent-orders-panel--deprecated" />
</template>

<script setup>
// 废弃组件：请改用 RecentInvoicesPanel.vue。此文件未被任何页面引入。
</script>

<style lang="scss" scoped>
.panel-card { border-radius: $base-border-radius; }

.orders-panel :deep(.el-card__body) { padding-top: 16px; }

.panel-header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.panel-header strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header p { margin-top: 6px; color: $text-color-secondary; font-size: 12px; line-height: 1.6; }

.table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 40px 0;
}

.table-empty strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.table-empty p { color: $text-color-secondary; font-size: 13px; }
</style>
