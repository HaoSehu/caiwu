import { defineStore } from 'pinia';

import STYLE_CONFIG from '@/config/style';
import { store } from '@/store';
import type { ModeType } from '@/types/interface';

const state: Record<string, any> = {
  ...STYLE_CONFIG,
  isMobileSidebarVisible: false,
};

export type TState = typeof state;
export type TStateKey = keyof typeof state;

export const useSettingStore = defineStore('setting', {
  state: () => state,
  getters: {
    showSidebar: (state) => state.layout !== 'top',
    showSidebarLogo: (state) => state.layout === 'side',
    showHeaderLogo: (state) => state.layout !== 'side',
    // docs/DESIGN.md §2：系统仅提供浅色（Light）模式
    displayMode: (): ModeType => 'light',
    displaySideMode: (): ModeType => 'light',
  },
  actions: {
    async changeMode(_mode?: ModeType | 'auto') {
      // docs/DESIGN.md §2：系统仅提供浅色（Light）模式，暂时不提供暗黑（Dark）主题
      document.documentElement.setAttribute('theme-mode', '');
    },
    async changeSideMode(_mode?: ModeType) {
      document.documentElement.setAttribute('side-mode', '');
    },
    updateConfig(payload: Partial<TState>) {
      for (const key in payload) {
        if (payload[key as TStateKey] !== undefined) {
          this[key as TStateKey] = payload[key as TStateKey];
        }
        if (key === 'mode') {
          this.changeMode(payload[key] as ModeType);
        }
        if (key === 'sideMode') {
          this.changeSideMode(payload[key] as ModeType);
        }
      }
    },
  },
  persist: {
    pick: [...Object.keys(STYLE_CONFIG)],
  },
});

export function getSettingStore() {
  return useSettingStore(store);
}
