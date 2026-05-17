<template>
  <section class="headline-grid">
    <article v-for="item in cards" :key="item.label" class="headline-card" :class="item.tone">
      <div class="headline-icon">
        <el-icon><component :is="item.icon" /></el-icon>
      </div>
      <div class="headline-copy">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
        <small>{{ item.note }}</small>
      </div>
    </article>
  </section>
</template>

<script setup>
defineProps({
  cards: {
    type: Array,
    required: true,
  },
})
</script>

<style lang="scss" scoped>
.headline-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.headline-card {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  padding: 18px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-xs;
  transition: border-color 0.2s, box-shadow 0.2s;

  &:hover {
    border-color: $color-primary-border;
    box-shadow: $shadow-sm;
  }
}

.headline-card.blue { border-top: 2px solid $color-primary; }
.headline-card.green { border-top: 2px solid $color-success; }
.headline-card.orange { border-top: 2px solid $color-warning; }
.headline-card.slate { border-top: 2px solid $color-info; }

.headline-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: $sm-border-radius;
  font-size: 18px;
  flex-shrink: 0;
}

.headline-card.blue .headline-icon { background: $color-primary-soft; color: $color-primary; }
.headline-card.green .headline-icon { background: $color-success-soft; color: $color-success; }
.headline-card.orange .headline-icon { background: $color-warning-soft; color: $color-warning; }
.headline-card.slate .headline-icon { background: $color-info-soft; color: $color-info; }

.headline-copy span {
  display: block;
  color: $text-color-secondary;
  font-size: 12px;
}

.headline-copy strong {
  display: block;
  margin-top: 8px;
  color: $text-color-primary;
  font-size: 24px;
  font-weight: 600;
  line-height: 1.2;
}

.headline-copy small {
  display: block;
  margin-top: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

@include desktop-lg-and-below {
  .headline-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@include tablet-and-below {
  .headline-grid { grid-template-columns: 1fr; }
}
</style>
