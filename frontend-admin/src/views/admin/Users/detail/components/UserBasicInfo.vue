<template>
  <div class="basic-grid">
    <section class="basic-panel">
      <header class="basic-panel__head">
        <h4>基础信息</h4>
        <span class="basic-panel__count">共 {{ infoItems.length }} 项</span>
      </header>
      <dl class="info-dl">
        <div v-for="item in infoItems" :key="item.label" class="info-row">
          <dt>{{ item.label }}</dt>
          <dd :class="item.tone ? `text-${item.tone}` : ''">{{ item.value || '--' }}</dd>
        </div>
      </dl>
    </section>

    <section class="basic-panel basic-panel--note">
      <header class="basic-panel__head">
        <h4>管理员备注</h4>
      </header>
      <div class="note-panel" :class="{ 'is-empty': !adminNote }">
        {{ adminNote || '暂无备注' }}
      </div>
    </section>
  </div>
</template>

<script setup>
defineProps({
  infoItems: {
    type: Array,
    default: () => [],
  },
  adminNote: {
    type: String,
    default: '',
  },
})
</script>

<style lang="scss" scoped>
.basic-grid {
  display: grid;
  grid-template-columns: 1.35fr 1fr;
  gap: 14px;
}

.basic-panel {
  padding: 14px 18px 18px;
  border: 1px solid $divider-color;
  border-radius: $lg-border-radius;
  background: $bg-color-card;
}

.basic-panel__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 10px;
  margin-bottom: 6px;
  border-bottom: 1px solid $divider-color;

  h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: $text-color-primary;
    letter-spacing: -0.2px;
  }
}

.basic-panel__count {
  font-size: 12px;
  color: $text-color-placeholder;
}

.info-dl {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 20px;
  row-gap: 2px;
}

.info-row {
  display: grid;
  grid-template-columns: 84px 1fr;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px dashed $divider-color;
  min-width: 0;

  &:nth-last-child(-n+2) {
    border-bottom: none;
  }

  dt {
    color: $text-color-placeholder;
    font-size: 12px;
    line-height: 1.5;
    white-space: nowrap;
  }

  dd {
    margin: 0;
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
    word-break: break-all;
    font-variant-numeric: tabular-nums;
  }
}

.note-panel {
  padding: 14px 16px;
  border: 1px dashed $divider-color;
  border-radius: $base-border-radius;
  background: $bg-color-soft;
  color: $text-color-primary;
  font-size: 13px;
  line-height: 1.75;
  white-space: pre-wrap;
  min-height: 120px;

  &.is-empty {
    color: $text-color-placeholder;
    font-style: italic;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

.text-success { color: $color-success; }
.text-danger  { color: $color-danger; }
.text-warning { color: $color-warning; }
.text-primary { color: $color-primary; }

@include desktop-lg-and-below {
  .basic-grid {
    grid-template-columns: 1fr;
  }
}

@include tablet-and-below {
  .info-dl {
    grid-template-columns: 1fr;
  }

  .info-row {
    grid-template-columns: 88px 1fr;

    &:nth-last-child(-n+2) {
      border-bottom: 1px dashed $divider-color;
    }

    &:last-child {
      border-bottom: none;
    }
  }
}
</style>
