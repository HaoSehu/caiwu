import { keys } from 'lodash-es';
import { defineStore } from 'pinia';
import { Color } from 'tvision-color';

import type { TColorSeries } from '@/config/color';
import { LIGHT_CHART_COLORS } from '@/config/color';
import STYLE_CONFIG from '@/config/style';
import { store } from '@/store';
import type { ModeType } from '@/types/interface';
import { generateColorMap, insertThemeStylesheet } from '@/utils/color';

const state: Record<string, any> = {
  ...STYLE_CONFIG,
  showSettingPanel: false,
  isMobileSidebarVisible: false,
  colorList: {} as TColorSeries,
  chartColors: LIGHT_CHART_COLORS,
};

export type TState = typeof state;
export type TStateKey = keyof typeof state;

export const useSettingStore = defineStore('setting', {
  state: () => state,
  getters: {
    showSidebar: (state) => state.layout !== 'top',
    showSidebarLogo: (state) => state.layout === 'side',
    showHeaderLogo: (state) => state.layout !== 'side' && !state.hideHeaderLogo,
    // docs/DESIGN.md §2：系统仅提供浅色（Light）模式，暂时不提供暗黑主题。
    // 与 frontend-user-v4-console 的 setting store 保持同向，禁止按 prefers-color-scheme 自动切暗色。
    displayMode: (): ModeType => 'light',
    displaySideMode: (state): ModeType => {
      return state.sideMode as ModeType;
    },
  },
  actions: {
    async changeMode() {
      // 仅浅色：保留方法作为 updateConfig('mode') 的收敛点，不再写入 theme-mode。
      document.documentElement.setAttribute('theme-mode', '');

      this.chartColors = LIGHT_CHART_COLORS;
    },
    async changeSideMode(mode: ModeType) {
      // sideMode 是侧边菜单配色（深/浅），不是暗色主题，docs/DESIGN.md §2 不约束此项，保持原行为。
      const isDarkMode = mode === 'dark';

      document.documentElement.setAttribute('side-mode', isDarkMode ? 'dark' : '');
    },
    changeBrandTheme(brandTheme: string) {
      // 仅浅色模式：色阶缓存键不再拼接显示模式
      const colorKey = brandTheme;
      let colorMap = this.colorList[colorKey];
      // 如果不存在色阶，就需要计算
      if (colorMap === undefined) {
        const [{ colors: newPalette, primary: brandColorIndex }] = Color.getColorGradations({
          colors: [brandTheme],
          step: 10,
          remainInput: false, // 是否保留输入 不保留会矫正不合适的主题色
        });
        colorMap = generateColorMap(brandTheme, newPalette, brandColorIndex);
        this.colorList[colorKey] = colorMap;
      }
      // TODO 需要解决不停切换时有反复插入 style 的问题
      insertThemeStylesheet(brandTheme, colorMap);
      document.documentElement.setAttribute('theme-color', brandTheme);
    },
    updateConfig(payload: Partial<TState>) {
      for (const key in payload) {
        if (payload[key as TStateKey] !== undefined) {
          this[key as TStateKey] = payload[key as TStateKey];
        }
        if (key === 'mode') {
          this.changeMode();
        }
        if (key === 'sideMode') {
          this.changeSideMode(payload[key] as ModeType);
        }
        if (key === 'brandTheme') {
          this.changeBrandTheme(payload[key]);
        }
      }
    },
  },
  persist: {
    pick: [...keys(STYLE_CONFIG), 'colorList', 'chartColors'],
  },
});

export function getSettingStore() {
  return useSettingStore(store);
}
