<template>
  <div class="result-container" :data-mobile-view="isMobile ? 'detail' : undefined">
    <div class="result-bg-img">
      <component :is="dynamicComponent"></component>
    </div>
    <div class="result-title">{{ title }}</div>
    <div class="result-tip">{{ tip }}</div>
    <slot />
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';

import Result403Icon from '@/assets/assets-result-403.svg?component';
import Result404Icon from '@/assets/assets-result-404.svg?component';
import Result500Icon from '@/assets/assets-result-500.svg?component';
import ResultIeIcon from '@/assets/assets-result-ie.svg?component';
import ResultMaintenanceIcon from '@/assets/assets-result-maintenance.svg?component';
import ResultWifiIcon from '@/assets/assets-result-wifi.svg?component';
import { MOBILE_MEDIA_QUERY, useMediaQuery } from '@/hooks/useMediaQuery';

const { type } = defineProps({
  bgUrl: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: '',
  },
  tip: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: '',
  },
});

// 与下方 scoped 样式的 `max-width: 767.98px` 同源；结果页手机端为单列详情形态。
const isMobile = useMediaQuery(MOBILE_MEDIA_QUERY);

const dynamicComponent = computed(() => {
  switch (type) {
    case '403':
      return Result403Icon;
    case '404':
      return Result404Icon;
    case '500':
      return Result500Icon;
    case 'ie':
      return ResultIeIcon;
    case 'wifi':
      return ResultWifiIcon;
    case 'maintenance':
      return ResultMaintenanceIcon;
    default:
      return Result403Icon;
  }
});
</script>
<style lang="less" scoped>
.result {
  &-link {
    color: var(--td-brand-color);
    text-decoration: none;
    cursor: pointer;

    &:hover {
      color: var(--td-brand-color);
    }

    &:active {
      color: var(--td-brand-color);
    }

    &--active {
      color: var(--td-brand-color);
    }

    &:focus {
      text-decoration: none;
    }
  }

  &-container {
    min-height: 400px;
    height: 75vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  &-bg-img {
    width: 200px;
    color: var(--td-brand-color);
  }

  &-title {
    font: var(--td-font-title-large);
    font-style: normal;
    margin-top: var(--td-comp-margin-l);
    color: var(--td-text-color-primary);
  }

  &-tip {
    margin: var(--td-comp-margin-s) 0 var(--td-comp-margin-xxxl);
    font: var(--td-font-body-medium);
    color: var(--td-text-color-secondary);
  }

  // 这层容器由使用页通过默认插槽传入（403/404/500 三页），DOM 带的是父组件的
  // data-v 属性，scoped 直接写 .result-actions 永远命中不了 → 必须用 :deep 穿透。
  :deep(.result-actions) {
    display: flex;
    flex-wrap: wrap;
    gap: var(--td-comp-margin-s);
    justify-content: center;
  }

  // 手机端：75vh 固定高度在横屏与地址栏占用后会把插图与按钮挤出视口
  @media (max-width: 767.98px) {
    &-container {
      height: auto;
      min-height: calc(100dvh - 160px);
      padding: var(--td-comp-margin-xl) 0;
    }

    &-bg-img {
      width: 140px;
    }

    :deep(.result-actions) {
      width: 100%;

      .t-button {
        flex: 1 1 100%;
        min-width: 0;
      }
    }
  }
}
</style>
