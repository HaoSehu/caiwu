<template>
  <l-side-nav
    v-if="settingStore.showSidebar"
    :show-logo="settingStore.showSidebarLogo"
    :layout="settingStore.layout"
    :is-fixed="settingStore.isSidebarFixed"
    :menu="sideMenu"
    :theme="settingStore.displaySideMode"
    :is-compact="settingStore.isSidebarCompact"
  />
</template>
<script setup lang="ts">
import { storeToRefs } from 'pinia';
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import { usePermissionStore, useSettingStore } from '@/store';
import type { MenuRoute } from '@/types/interface';

import LSideNav from './SideNav.vue';

const route = useRoute();
const permissionStore = usePermissionStore();
const settingStore = useSettingStore();
const { routers: menuRouters } = storeToRefs(permissionStore);

const sideMenu = computed(() => {
  const { layout, splitMenu } = settingStore;
  const routers = menuRouters.value as Array<MenuRoute>;
  if (layout !== 'mix' || !splitMenu) {
    return routers;
  }
  // 一次定值，不再在 forEach 里边遍历边给 newMenuRouters 重新赋值（原实现还漏了 children 判空：
  // 叶子菜单没有 children，.map 会直接抛 TypeError，L-17）
  const activeMenu = routers.find((menu) => route.path.indexOf(menu.path) === 0);
  if (!activeMenu?.children?.length) {
    return routers;
  }
  return activeMenu.children.map((subMenu) => ({ ...subMenu, path: `${activeMenu.path}/${subMenu.path}` }));
});
</script>
