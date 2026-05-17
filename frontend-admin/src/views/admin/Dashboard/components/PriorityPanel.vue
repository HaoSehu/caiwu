<template>
  <el-card shadow="never" class="panel-card priority-panel">
    <template #header>
      <div class="panel-header">
        <div>
          <strong>优先处理</strong>
          <p>当前待优先处理事项。</p>
        </div>
        <span>待处理</span>
      </div>
    </template>

    <div class="priority-list">
      <article v-for="item in items" :key="item.label" class="priority-item" :class="item.tone">
        <div class="priority-main">
          <strong>{{ item.label }}</strong>
          <p>{{ item.note }}</p>
        </div>
        <span>{{ item.value }}</span>
      </article>
    </div>
  </el-card>
</template>

<script setup>
defineProps({
  items: { type: Array, required: true },
})
</script>

<style lang="scss" scoped>
.panel-card { border-radius: $base-border-radius; }

.panel-header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.panel-header strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header p { margin-top: 6px; color: $text-color-secondary; font-size: 12px; line-height: 1.6; }
.panel-header span { color: $text-color-placeholder; font-size: 12px; white-space: nowrap; }

.priority-list { display: grid; gap: 12px; }

.priority-item {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: center;
  padding: 16px;
  border: 1px solid $divider-color;
  border-left-width: 3px;
  border-radius: $sm-border-radius;
}

.priority-item.warning { border-left-color: $color-warning; background: $color-warning-soft; }
.priority-item.primary { border-left-color: $color-primary; background: $color-primary-soft; }
.priority-item.success { border-left-color: $color-success; background: $color-success-soft; }
.priority-item.info { border-left-color: $color-info; background: $color-info-soft; }

.priority-main strong { display: block; color: $text-color-primary; font-size: 14px; font-weight: 600; }
.priority-main p { margin-top: 6px; color: $text-color-secondary; font-size: 12px; line-height: 1.6; }

.priority-item > span {
  color: $text-color-primary;
  font-size: 18px;
  font-weight: 600;
  white-space: nowrap;
}

@media (max-width: 900px) {
  .priority-item { flex-direction: column; align-items: flex-start; }
}
</style>
