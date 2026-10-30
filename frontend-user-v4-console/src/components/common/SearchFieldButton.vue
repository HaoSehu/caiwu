<template>
  <!-- O-02：关键词输入框右侧的放大镜原先只是 <search-icon />，既不可点也没有任何
       语义，用户输入后只能猜「要按 Enter」。这里换成真正的按钮。
       两个关键决定：
       1) 必须 @click.stop 截断冒泡 —— t-input 根 div 的 onRootClick 会无条件把焦点
          抢回输入框（node_modules/tdesign-vue-next/es/input/hooks/useInput.mjs），
          与 PasswordToggle 遇到的是同一个坑。
       2) 由调用方放进 #suffix 槽（而不是 #suffixIcon）：suffixIcon 在 clearable 且
          有内容时会被 TDesign 换成清除图标，放大镜会随鼠标悬停消失、点下去变成清空，
          这正是「找不到搜索入口」的另一种形态。suffix 槽不会被替换。 -->
  <button type="button" class="search-field-button" :aria-label="label" @click.stop="$emit('search')">
    <search-icon aria-hidden="true" />
  </button>
</template>
<script setup lang="ts">
import { SearchIcon } from 'tdesign-icons-vue-next';

withDefaults(defineProps<{ label?: string }>(), { label: '搜索' });
defineEmits<{ search: [] }>();
</script>
<style scoped>
.search-field-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 100%;

  /* 命中区以输入框高度为上限：输入框是 --td-comp-size-m（32），且 .t-input 自身 overflow: hidden，
     向外扩会被整块裁掉（与 PasswordToggle 同一限制）。因此这里做到 32 见方、不再追 44 触控基线。 */
  min-width: var(--td-comp-size-m);
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
  color: var(--td-text-color-placeholder);
  border-radius: var(--td-radius-small);
}

.search-field-button:hover {
  color: var(--td-brand-color);
}

.search-field-button:focus-visible {
  /* 描边宽度直接写：TDesign 没有 border-width 令牌（--td-border-level-* 是颜色） */
  outline: 0.125rem solid var(--td-brand-color);
  outline-offset: 0.0625rem;
}
</style>
