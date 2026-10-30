<template>
  <t-breadcrumb v-if="crumbs.length" max-item-width="150" class="tdesign-breadcrumb">
    <t-breadcrumbItem
      v-for="(item, index) in crumbs"
      :key="item.to"
      :to="index === crumbs.length - 1 ? undefined : item.to"
    >
      {{ item.title }}
    </t-breadcrumbItem>
  </t-breadcrumb>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

// 面包屑直接由 route.matched 生成：matched 就是路由层级本身，path 与 meta 天然对齐。
// 原实现把 route.path 按 / 拆段后再用同一下标取 route.matched[idx]，两层下标含义不同，会出两类错误：
//   ① 参数段（如 /services/:id）之后所有段与 matched 全部错位，标题与路径串到别的层级；
//   ② 某些段被 hiddenBreadcrumb 跳过时，用 breadcrumbArray[idx - 1] 拼上级路径会拼错（L-13）。
const crumbs = computed(() =>
  route.matched
    .filter((record) => Boolean(record.meta?.title) && record.meta?.hiddenBreadcrumb !== true)
    .map((record) => ({ to: record.path, title: String(record.meta?.title ?? '') })),
);
</script>
<style scoped>
.tdesign-breadcrumb {
  margin-bottom: var(--td-comp-margin-xxl);
}
</style>
