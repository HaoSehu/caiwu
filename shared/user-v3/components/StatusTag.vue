<template>
  <t-tag
    :theme="theme"
    :variant="variant"
    :size="size"
    :class="purpleClass"
    :style="purpleStyle"
  >
    {{ label }}
  </t-tag>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { getStatusConfig, resolveTdTagTheme } from '@caiwu/shared/statusConfig';

interface StatusConfig {
  label?: string;
  tagType?: string;
  color?: string;
}

const props = withDefaults(
  defineProps<{
    statusMap: Record<string, unknown>;
    status: unknown;
    variant?: 'light' | 'dark' | 'outline' | 'light-outline';
    size?: 'small' | 'medium' | 'large';
  }>(),
  {
    variant: 'light',
    size: 'small',
  },
);

const config = computed<StatusConfig>(() => (getStatusConfig(props.statusMap, props.status as string | number) || {}) as StatusConfig);
const label = computed(() => config.value.label || String(props.status ?? '-'));
// TDesign theme 映射统一走 shared/statusConfig，与管理端 StatusTag 共用同一套规则。
const theme = computed(() => resolveTdTagTheme(String(config.value.tagType || config.value.color || 'default')) as 'success' | 'warning' | 'danger' | 'primary' | 'default');
// purple 不在 TDesign 内置 theme 里，只靠 theme 会退化成灰色；
// 这里额外输出自定义 class 与色值，由下面的样式块着色，避免三端各写一份紫色样式。
const isPurple = computed(() => String(config.value.tagType || '') === 'purple');
const purpleClass = computed(() => (isPurple.value ? 'status-tag--purple' : ''));
const purpleStyle = computed(() =>
  isPurple.value && config.value.color ? { '--status-tag-color': config.value.color } : undefined,
);
</script>

<style>
/* purple（已冻结/已暂停）不在 TDesign 内置主题内，需自绘；用 CSS 变量承接 statusConfig 的色值。
   color-mix() 需 Chrome 111+ / Safari 16.2+，而三端 browserslist 最低到 chrome 84 / safari 13.4，
   故先给一档静态底色与描边兜底；支持 color-mix 的内核会用后一条声明覆盖它。 */
.t-tag.status-tag--purple {
  --status-tag-color: #8b5cf6;
  color: var(--status-tag-color);
  background-color: #f3eefe;
  border-color: #ddd0fb;
  background-color: color-mix(in srgb, var(--status-tag-color) 12%, #fff);
  border-color: color-mix(in srgb, var(--status-tag-color) 25%, #fff);
}
</style>
