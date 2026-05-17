<template>
  <section class="insight-row">
    <el-card shadow="never" class="panel-card">
      <template #header>
        <div class="panel-header">
          <strong>账单状态分布</strong>
          <span>最近 10 条</span>
        </div>
      </template>

      <div class="distribution-list" v-if="statusDistribution.length">
        <article v-for="item in statusDistribution" :key="item.label" class="distribution-item">
          <div class="distribution-main">
            <strong>{{ item.label }}</strong>
            <small>{{ item.count }} 条</small>
          </div>
          <div class="distribution-bar">
            <span :class="item.tone" :style="{ width: `${item.percent}%` }" />
          </div>
          <em>{{ item.percent }}%</em>
        </article>
      </div>

      <div v-else class="panel-empty">暂无账单数据</div>
    </el-card>

    <el-card shadow="never" class="panel-card">
      <template #header>
        <div class="panel-header">
          <strong>经营进度</strong>
          <span>今日 / 本月</span>
        </div>
      </template>

      <div class="progress-list">
        <article v-for="item in progressItems" :key="item.label" class="progress-item">
          <div class="progress-head">
            <strong>{{ item.label }}</strong>
            <span>{{ item.percent }}%</span>
          </div>
          <el-progress :percentage="item.percent" :stroke-width="8" :show-text="false" :color="item.color" />
          <small>{{ item.note }}</small>
        </article>
      </div>
    </el-card>
  </section>
</template>

<script setup>
defineProps({
  statusDistribution: { type: Array, required: true },
  progressItems: { type: Array, required: true },
})
</script>

<style lang="scss" scoped>
.insight-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(280px, 0.7fr);
  gap: 16px;
}

.panel-card { border-radius: $base-border-radius; }

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.panel-header strong { color: $text-color-primary; font-size: 15px; font-weight: 600; }
.panel-header span { color: $text-color-placeholder; font-size: 12px; white-space: nowrap; }

.distribution-list { display: grid; gap: 14px; }

.distribution-item {
  display: grid;
  grid-template-columns: minmax(0, 110px) minmax(0, 1fr) 44px;
  gap: 12px;
  align-items: center;
}

.distribution-main strong { display: block; color: $text-color-primary; font-size: 13px; font-weight: 600; }
.distribution-main small { display: block; margin-top: 4px; color: $text-color-placeholder; font-size: 12px; }

.distribution-bar { height: 8px; background: $bg-color-soft; overflow: hidden; border-radius: 4px; }
.distribution-bar span { display: block; height: 100%; border-radius: 4px; }
.distribution-bar span.blue { background: $color-primary; }
.distribution-bar span.green { background: $color-success; }
.distribution-bar span.orange { background: $color-warning; }
.distribution-bar span.red { background: $color-danger; }
.distribution-bar span.slate { background: $color-info; }

.distribution-item em {
  color: $text-color-secondary;
  font-size: 12px;
  font-style: normal;
  font-weight: 600;
  text-align: right;
}

.progress-list { display: grid; gap: 16px; }

.progress-item {
  padding: 14px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
}

.progress-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.progress-head strong { color: $text-color-primary; font-size: 13px; font-weight: 600; }
.progress-head span { color: $text-color-secondary; font-size: 12px; font-weight: 600; }

.progress-item small {
  display: block;
  margin-top: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.panel-empty {
  padding: 32px 0;
  text-align: center;
  color: $text-color-placeholder;
  font-size: 13px;
}

@media (max-width: 1280px) {
  .insight-row { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
  .distribution-item { grid-template-columns: 1fr; }
  .distribution-item em { text-align: left; }
}
</style>
