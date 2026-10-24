import { trim } from 'lodash-es';
import { Color } from 'tvision-color';

import type { TColorToken } from '@/config/color';

/**
 * 依据主题类型获取颜色
 *
 * @export
 * @returns {Array<string>} themeColorList
 */
export function getColorFromTheme(): Array<string> {
  const theme = trim(getComputedStyle(document.documentElement).getPropertyValue('--td-brand-color'));
  const themeColorList = Color.getRandomPalette({
    color: theme,
    colorGamut: 'bright',
    number: 8,
  });

  return themeColorList;
}

/** 图表颜色 */
export function getChartListColor(): Array<string> {
  return getColorFromTheme();
}

/**
 * 根据当前主题色、模式等情景 计算最后生成的色阶
 */
export function generateColorMap(theme: string, colorPalette: Array<string>, brandColorIdx: number) {
  // 仅浅色模式（docs/DESIGN.md §2）：原 dark 分支与 mode 参数已删除。
  const colorMap: TColorToken = {
    '--td-brand-color': colorPalette[brandColorIdx], // 主题色
    '--td-brand-color-1': colorPalette[0], // light
    '--td-brand-color-2': colorPalette[1], // focus
    '--td-brand-color-3': colorPalette[2], // disabled
    '--td-brand-color-4': colorPalette[3],
    '--td-brand-color-5': colorPalette[4],
    '--td-brand-color-6': colorPalette[5],
    '--td-brand-color-7': brandColorIdx > 0 ? colorPalette[brandColorIdx - 1] : theme, // hover
    '--td-brand-color-8': colorPalette[brandColorIdx], // 主题色
    '--td-brand-color-9': brandColorIdx > 8 ? theme : colorPalette[brandColorIdx + 1], // click
    '--td-brand-color-10': colorPalette[9],
  };
  return colorMap;
}

/**
 * 将生成的样式嵌入头部
 */
export function insertThemeStylesheet(theme: string, colorMap: TColorToken) {
  // 仅浅色模式：选择器固定为 :root[theme-color]，不再拼接 [theme-mode='dark']
  const root = `:root[theme-color='${theme}']`;

  const styleSheet = document.createElement('style');
  styleSheet.type = 'text/css';
  styleSheet.textContent = `${root}{
    --td-brand-color: ${colorMap['--td-brand-color']};
    --td-brand-color-1: ${colorMap['--td-brand-color-1']};
    --td-brand-color-2: ${colorMap['--td-brand-color-2']};
    --td-brand-color-3: ${colorMap['--td-brand-color-3']};
    --td-brand-color-4: ${colorMap['--td-brand-color-4']};
    --td-brand-color-5: ${colorMap['--td-brand-color-5']};
    --td-brand-color-6: ${colorMap['--td-brand-color-6']};
    --td-brand-color-7: ${colorMap['--td-brand-color-7']};
    --td-brand-color-8: ${colorMap['--td-brand-color-8']};
    --td-brand-color-9: ${colorMap['--td-brand-color-9']};
    --td-brand-color-10: ${colorMap['--td-brand-color-10']};
  }`;

  document.head.appendChild(styleSheet);
}
