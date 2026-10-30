import { defineStore } from 'pinia';

import { store } from '@/store';
import type { TRouterInfo, TTabRouterType } from '@/types/interface';

// 首页标签必须与路由表一致：仪表盘在 adminRoutes 下注册为 /admin/dashboard，
// 这里若写 /dashboard/base 会命中 404 兜底（RouterNotFound）。
const homeRoute: Array<TRouterInfo> = [
  {
    path: '/admin/dashboard',
    routeIdx: 0,
    title: { zh_CN: '仪表盘', en_US: 'Dashboard' },
    name: 'DashboardBase',
    isHome: true,
  },
];

const state = {
  tabRouterList: homeRoute,
  isRefreshing: false,
};

// 不需要做多标签tabs页缓存的列表 值为每个页面对应的name 如 DashboardDetail
// const ignoreCacheRoutes = ['DashboardDetail'];
const ignoreCacheRoutes = ['login'];
/** 标签页最大数量，超出后移除最早的非常驻标签 */
const MAX_TAB_COUNT = 20;

// 调用方（LayoutContent.vue）遍历的是「过滤掉非保活标签」后的列表，其 index 与 tabRouterList
// 的真实下标不对齐（keepAlive:false 的路由 isAlive 恒为 false，会被过滤掉）。
// 因此所有按下标写入的 action 都必须用 path 回查真实下标，只有在拿不到 path 时才回落到调用方下标。
const resolveIndex = (list: Array<TRouterInfo>, path: string | undefined, fallback: number | undefined): number =>
  path ? list.findIndex((route: TRouterInfo) => route.path === path) : (fallback ?? -1);

export const useTabsRouterStore = defineStore('tabsRouter', {
  state: () => state,
  getters: {
    tabRouters: (state: TTabRouterType) => state.tabRouterList,
    refreshing: (state: TTabRouterType) => state.isRefreshing,
  },
  actions: {
    // 处理刷新
    // 入参必须是 path 而不是索引：调用方（LayoutContent.vue）遍历的是 tabRouters 过滤掉
    // 已失活标签后的列表，其 index 与 tabRouterList 的下标并不对齐，按下标写入会刷新错的标签。
    toggleTabRouterAlive(path: string) {
      this.isRefreshing = !this.isRefreshing;
      const target = this.tabRouterList.find((route: TRouterInfo) => route.path === path);
      if (target) {
        target.isAlive = !target.isAlive;
      }
    },
    // 处理新增
    appendTabRouterList(newRoute: TRouterInfo) {
      // 不要将判断条件newRoute.meta.keepAlive !== false修改为newRoute.meta.keepAlive，starter默认开启保活，所以meta.keepAlive未定义时也需要进行保活，只有显式说明false才禁用保活。
      const needAlive = !ignoreCacheRoutes.includes(newRoute.name as string) && newRoute.meta?.keepAlive !== false;
      if (!this.tabRouters.some((route: TRouterInfo) => route.path === newRoute.path)) {
        // 超过最大标签数时，移除最早的非首页标签
        if (this.tabRouterList.length >= MAX_TAB_COUNT) {
          const removeIdx = this.tabRouterList.findIndex((r: TRouterInfo) => !r.isHome);
          if (removeIdx !== -1) {
            this.tabRouterList = this.tabRouterList.slice(0, removeIdx).concat(this.tabRouterList.slice(removeIdx + 1));
          }
        }
        this.tabRouterList = this.tabRouterList.concat({ ...newRoute, isAlive: needAlive });
      }
    },
    // 处理关闭当前
    subtractCurrentTabRouter(newRoute: TRouterInfo) {
      const routeIdx = resolveIndex(this.tabRouterList, newRoute.path, newRoute.routeIdx);
      if (routeIdx < 0) return;
      this.tabRouterList = this.tabRouterList.slice(0, routeIdx).concat(this.tabRouterList.slice(routeIdx + 1));
    },
    // 处理关闭右侧
    subtractTabRouterBehind(newRoute: TRouterInfo) {
      const routeIdx = resolveIndex(this.tabRouterList, newRoute.path, newRoute.routeIdx);
      if (routeIdx < 0) return;
      const homeIdx: number = this.tabRouters.findIndex((route: TRouterInfo) => route.isHome);
      let tabRouterList: Array<TRouterInfo> = this.tabRouterList.slice(0, routeIdx + 1);
      if (routeIdx < homeIdx) {
        tabRouterList = tabRouterList.concat(homeRoute);
      }
      this.tabRouterList = tabRouterList;
    },
    // 处理关闭左侧
    subtractTabRouterAhead(newRoute: TRouterInfo) {
      const routeIdx = resolveIndex(this.tabRouterList, newRoute.path, newRoute.routeIdx);
      if (routeIdx < 0) return;
      const homeIdx: number = this.tabRouters.findIndex((route: TRouterInfo) => route.isHome);
      let tabRouterList: Array<TRouterInfo> = this.tabRouterList.slice(routeIdx);
      if (routeIdx > homeIdx) {
        tabRouterList = homeRoute.concat(tabRouterList);
      }
      this.tabRouterList = tabRouterList;
    },
    // 处理关闭其他
    subtractTabRouterOther(newRoute: TRouterInfo) {
      const routeIdx = resolveIndex(this.tabRouterList, newRoute.path, newRoute.routeIdx);
      if (routeIdx < 0) return;
      const homeIdx: number = this.tabRouters.findIndex((route: TRouterInfo) => route.isHome);
      this.tabRouterList = routeIdx === homeIdx ? homeRoute : homeRoute.concat([this.tabRouterList?.[routeIdx]]);
    },
    // 处理拖拽换位：按 path 定位，在 tabRouterList 上做不可变换位后写回
    moveTabRouter(fromPath: string, toPath: string) {
      const fromIdx = this.tabRouterList.findIndex((route: TRouterInfo) => route.path === fromPath);
      const toIdx = this.tabRouterList.findIndex((route: TRouterInfo) => route.path === toPath);
      if (fromIdx < 0 || toIdx < 0 || fromIdx === toIdx) return;

      const next = this.tabRouterList.slice();
      const [moved] = next.splice(fromIdx, 1);
      next.splice(toIdx, 0, moved);
      this.tabRouterList = next;
    },
    removeTabRouterList() {
      this.tabRouterList = [];
    },
    initTabRouterList(newRoutes: TRouterInfo[]) {
      newRoutes?.forEach((route: TRouterInfo) => this.appendTabRouterList(route));
    },
  },
  persist: {
    pick: ['tabRouterList'],
    serializer: {
      serialize: (state) => {
        // 必须连 query 与 keep-alive 相关的 meta 一起持久化：刷新后 keep-alive 的 include 由
        // meta.keepAliveName / meta.keepAlive 决定（layouts/components/Content.vue），
        // 只存 name 会让多路由共组件的页面在刷新后静默失去缓存；query 决定切回标签时的页内状态。
        const list = (state.tabRouterList || []).map((route: TRouterInfo) => ({
          path: route.path,
          query: route.query,
          name: route.name,
          title: route.title,
          isAlive: route.isAlive,
          isHome: route.isHome,
          routeIdx: route.routeIdx,
          meta: {
            keepAlive: route.meta?.keepAlive,
            keepAliveName: route.meta?.keepAliveName,
            frameSrc: route.meta?.frameSrc,
          },
        }));
        return JSON.stringify({ tabRouterList: list });
      },
      deserialize: (raw) => JSON.parse(raw),
    },
  },
});

export function getTabsRouterStore() {
  return useTabsRouterStore(store);
}
