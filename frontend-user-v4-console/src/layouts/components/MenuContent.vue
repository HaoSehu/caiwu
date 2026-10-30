<template>
  <div>
    <template v-for="item in list" :key="item.path">
      <template v-if="!item.children || !item.children.length || item.meta?.single">
        <t-menu-item v-if="getHref(item)" :name="item.path" :value="getPath(item)" @click="openHref(getHref(item)[0])">
          <template v-if="shouldShowIcon(item)" #icon>
            <component :is="menuIcon(item)" class="t-icon"></component>
          </template>
          {{ renderMenuTitle(item.title) }}
        </t-menu-item>
        <t-menu-item v-else :name="item.path" :value="getPath(item)" :to="item.path">
          <template v-if="shouldShowIcon(item)" #icon>
            <component :is="menuIcon(item)" class="t-icon"></component>
          </template>
          {{ renderMenuTitle(item.title) }}
          <span v-if="item.path === '/client/notices' && noticesUnread" class="menu-badge-dot">{{
            noticesUnread
          }}</span>
        </t-menu-item>
      </template>
      <t-submenu v-else :name="item.path" :value="item.path" :title="renderMenuTitle(item.title)">
        <template v-if="shouldShowIcon(item)" #icon>
          <component :is="menuIcon(item)" class="t-icon"></component>
        </template>
        <menu-content v-if="item.children" :nav-data="item.children" :level="props.level + 1" />
      </t-submenu>
    </template>
  </div>
</template>
<script setup lang="tsx">
import { computed } from 'vue';

import { useNoticeReadStatus } from '@/domains/content/useNoticeReadStatus';
import { getActive } from '@/router';
import type { MenuRoute } from '@/types/interface';

type ListItemType = MenuRoute;

const props = withDefaults(
  defineProps<{
    navData?: MenuRoute[];
    level?: number;
  }>(),
  {
    navData: () => [],
    level: 0,
  },
);

const active = computed(() => getActive());

const list = computed(() => {
  return getMenuList(props.navData);
});

const menuIcon = (item: ListItemType) => {
  if (typeof item.icon === 'string') return <t-icon name={item.icon} />;
  const RenderIcon = item.icon;
  return RenderIcon;
};

const shouldShowIcon = (item: ListItemType) => {
  return props.level === 0 && !!item.icon;
};

const renderMenuTitle = (title?: string) => title || '';

function getMenuList(list: MenuRoute[], basePath?: string): MenuRoute[] {
  if (!list || list.length === 0) {
    return [];
  }
  // 如果meta中有orderNo则按照从小到大排序；复制后排序，避免直接改动 store 里的路由数组
  const ordered = [...list].sort((a, b) => {
    return (a.meta?.orderNo || 0) - (b.meta?.orderNo || 0);
  });
  return ordered
    .map((item) => {
      const rawPath = String(item.path);
      const path = rawPath.startsWith('/')
        ? rawPath
        : basePath && !rawPath.includes(basePath)
          ? `${basePath}/${rawPath}`
          : rawPath;

      return {
        path,
        title: item.meta?.title,
        icon: item.meta?.icon,
        children: getMenuList(item.children, path),
        meta: item.meta,
        redirect: item.redirect,
      } as MenuRoute;
    })
    .filter((item) => item.meta && item.meta.hidden !== true);
}

const getHref = (item: MenuRoute) => {
  const { frameSrc, frameBlank } = item.meta;
  if (frameSrc && frameBlank) {
    return frameSrc.match(/(https?):\/\/([\w.-]+)(?:\/\S*)?/);
  }
  return null;
};

const getPath = (item: ListItemType) => {
  const activeLevel = active.value.split('/').length;
  const pathLevel = item.path.split('/').length;
  if (activeLevel > pathLevel && active.value.startsWith(item.path)) {
    return active.value;
  }

  if (active.value === item.path) {
    return active.value;
  }

  return item.meta?.single ? item.redirect : item.path;
};

const openHref = (url: string) => {
  window.open(url, '_blank', 'noopener,noreferrer');
};

const { unreadCount } = useNoticeReadStatus();

// 角标只挂在「系统公告」一项，未读数直接取响应式值。
// 原先模板写成 v-if="menuBadge(item)" 加插值 {{ menuBadge(item) }}，同一渲染里调两次函数（L-27）。
// 现在只留一个 computed：它必须自己表达「无未读 = 不显示」这个空态，否则 0 会渲染成 '99+'。
const noticesUnread = computed(() => {
  if (unreadCount.value <= 0) return '';
  return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
});
</script>
<style scoped lang="less">
.menu-badge-dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  // 角标是 16px 见方的微件；尺寸必须取 --td-comp-size-xxxs（= --td-size-6 = 16），
  // 原来借的是 --td-comp-margin-l（同值 16 但是间距令牌，调间距会把角标一起改肿）。
  // 字号用最小的 body-small + tabular-nums 保证两位数不溢出（L-27 待设计确认）。
  min-width: var(--td-comp-size-xxxs);
  height: var(--td-comp-size-xxxs);
  padding: 0 var(--td-comp-margin-xs);
  margin-left: auto;
  color: var(--td-text-color-anti);
  background: var(--td-error-color);
  border-radius: var(--td-radius-round);
  font: var(--td-font-body-small);
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
</style>
