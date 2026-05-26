<template>
  <div class="headline-grid">
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
  </div>
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
  align-items: center;
  padding: 18px 16px;
  border: 1px solid $border-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;
  box-shadow: $shadow-xs;
  transition: border-color $duration-fast $ease-standard,
              box-shadow $duration-fast $ease-standard,
              transform $duration-fast $ease-standard;

  &:hover {
    box-shadow: $shadow-sm;
    transform: translateY(-1px);
  }
}

.headline-icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: $sm-border-radius;
  font-size: 18px;
  flex-shrink: 0;
}

.headline-icon { background: $bg-color; color: $text-color-secondary; }

.headline-copy { min-width: 0; }

.headline-copy span {
  display: block;
  color: $text-color-secondary;
  font-size: 12px;
}

.headline-copy strong {
  display: block;
  margin-top: 6px;
  color: $text-color-primary;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.2;
}

.headline-copy small {
  display: block;
  margin-top: 6px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

@include desktop-lg-and-below {
  .headline-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@include tablet-and-below {
  .headline-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .headline-card { padding: 14px 12px; gap: 10px; }
  .headline-copy strong { font-size: 18px; margin-top: 4px; }
  .headline-copy small { margin-top: 4px; }
  .headline-icon { width: 32px; height: 32px; }
}

@include mobile-and-below {
  .headline-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .headline-card { flex-direction: column; align-items: flex-start; padding: 12px 10px; gap: 8px; }
  .headline-copy strong { font-size: 16px; }
  .headline-copy span { font-size: 11px; }
  .headline-copy small { display: none; }
}

@include mobile-sm-and-below {
  .headline-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
  .headline-card { flex-direction: column; align-items: flex-start; gap: 6px; padding: 10px 8px; }
  .headline-copy strong { font-size: 15px; }
  .headline-copy span { font-size: 11px; }
  .headline-copy small { display: none; }
  .headline-icon { width: 28px; height: 28px; font-size: 14px; }
}
</style>
