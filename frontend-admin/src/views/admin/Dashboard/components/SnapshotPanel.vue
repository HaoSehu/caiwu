<template>
  <el-card shadow="never" class="panel-card snapshot-panel">
    <template #header>
      <div class="panel-header">
        <div>
          <strong>经营快照</strong>
          <p>今日与本月经营数据。</p>
        </div>
        <span>今日 / 本月</span>
      </div>
    </template>

    <div class="snapshot-grid">
      <article v-for="item in snapshotCards" :key="item.label" class="snapshot-card">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
        <small>{{ item.note }}</small>
      </article>
    </div>

    <div class="progress-cluster">
      <article v-for="item in progressItems" :key="item.label" class="progress-card">
        <div class="progress-head">
          <strong>{{ item.label }}</strong>
          <span>{{ item.percent }}%</span>
        </div>
        <el-progress
          :percentage="item.percent"
          :stroke-width="10"
          :show-text="false"
          :color="item.color"
        />
        <small>{{ item.note }}</small>
      </article>
    </div>
  </el-card>
</template>

<script setup>
defineProps({
  snapshotCards: { type: Array, required: true },
  progressItems: { type: Array, required: true },
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

.snapshot-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.snapshot-card {
  padding: 16px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
}

.snapshot-card span { color: $text-color-secondary; font-size: 12px; }

.snapshot-card strong {
  display: block;
  margin-top: 10px;
  color: $text-color-primary;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.2;
}

.snapshot-card small {
  display: block;
  margin-top: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

.progress-cluster { display: grid; gap: 12px; margin-top: 16px; }

.progress-card {
  padding: 16px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
}

.progress-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-bottom: 10px;
}

.progress-head strong { color: $text-color-primary; font-size: 14px; font-weight: 600; }
.progress-head span { color: $text-color-secondary; font-size: 12px; font-weight: 600; }

.progress-card small {
  display: block;
  margin-top: 10px;
  color: $text-color-placeholder;
  font-size: 12px;
}

@media (max-width: 1280px) {
  .snapshot-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 900px) {
  .snapshot-grid { grid-template-columns: 1fr; }
  .panel-header { flex-direction: column; align-items: flex-start; }
}
</style>
