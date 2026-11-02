import autoprefixer from "autoprefixer";

import { lightThemeOnlyPostcssPlugin } from "./scripts/light-theme-only-postcss.mjs";

// 用数组形式而不是对象形式：对象形式的键会被 postcss-load-config 当成模块名去 require，
// 本地插件的伪名字解析不到。数组形式直接给插件实例，顺序也显式可控。
export default {
  plugins: [
    // TDesign 基础样式自带一块永远命中不了的暗色变量，构建前剔除（详见插件文件头）。
    lightThemeOnlyPostcssPlugin,
    autoprefixer(),
  ],
};
