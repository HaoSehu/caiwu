<template>
  <img :class="className" :src="imageUrl" :alt="altText" loading="lazy" decoding="async" />
</template>
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
  src: {
    type: String,
    default: '',
  },
  url: {
    type: String,
    default: '',
  },
  alt: {
    type: String,
    default: '布局缩略图',
  },
  type: {
    type: String,
    default: 'layout',
  },
});

const imageUrl = computed(() => props.url || props.src);
const altText = computed(() => props.alt || '布局缩略图');
const className = computed(() => {
  return [
    'thumbnail-container',
    {
      'thumbnail-circle': props.type === 'circle',
      'thumbnail-layout': props.type === 'layout',
    },
  ];
});
</script>
<style lang="less" scoped>
// 全局样式入口由 main.ts 提供，组件内不要再 @import 全量样式，否则 scoped 会把它编译成一份带
// data-v 死选择器的重复副本（实测一份 4.9KB）
.thumbnail {
  &-container {
    display: inline-block;
  }

  &-circle {
    border-radius: var(--td-radius-circle);
  }

  &-layout {
    width: 88px;
    height: 48px;
  }
}
</style>
