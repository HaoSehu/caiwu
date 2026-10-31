import { readdir, readFile, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { brotliCompressSync, constants as zlibConstants, gzipSync } from 'node:zlib';

import vue from '@vitejs/plugin-vue';
import vueJsx from '@vitejs/plugin-vue-jsx';
import type { AcceptedPlugin } from 'postcss';
import Components from 'unplugin-vue-components/vite';
import type { ConfigEnv, UserConfig } from 'vite';
import { loadEnv } from 'vite';

import { createApiConnectSrcPlugin } from '../shared/vite/apiConnectSrcPlugin.mjs';

const CWD = process.cwd();
const __dirname = path.dirname(fileURLToPath(import.meta.url));
// 预压缩产物只服务「浏览器首屏会主动请求」的四类资源：js/css 由 <script>/<link> 引用，html 是文档本身，
// svg 走 <img>/background-image。json/map/mjs/txt/xml 在 dist 里只会是 sourcemap 与第三方 vnc 静态包的附属文件，
// 压了也没人从网络拿它们的 .gz/.br，纯占磁盘（PB-14）。
const compressionExtensions = new Set(['.css', '.html', '.js', '.svg']);
// 阈值从 1024 提到 4096：实测 521 个产物里 55 个 js、24 个 css、12 个 svg 都小于 4 KB，
// 它们压缩后普遍只省几百字节，但每个多生成 1~2 个文件，部署时的文件数膨胀比省下的字节更贵（PB-14）。
// 例外是 html：整份 dist 只有 index.html 与 vnc/vnc.html 两个文档文件，数量不随代码增长，
// 而 index.html 是首屏渲染阻塞资源。实测 index.html 2575 字节（gzip 后约 967 字节），
// 被 4096 阈值挡掉后就不再产出 .gz/.br —— 源站若未开 gzip_static 就只能原样传 2575 字节。
// 故 html 单独沿用 1024 阈值，其余扩展名走 4096。
const compressionThreshold = 4096;
const htmlCompressionThreshold = 1024;

// TDesign 组件导出名 → es 子目录。数据取自 tdesign-vue-next/es/index.mjs 的导出语句，
// 因为约四成导出名的目录不是 kebab-case（如 Col→grid、DescriptionsItem→descriptions）。
// 新增模板组件时在此补充，并同步 frontend-admin-v3/vite.config.ts 的同名表。
const TDESIGN_COMPONENT_DIRS: Record<string, string> = {
  Affix: 'affix',
  Alert: 'alert',

  Anchor: 'anchor',
  AnchorItem: 'anchor',
  AnchorTarget: 'anchor',

  Aside: 'layout',
  AutoComplete: 'auto-complete',
  Avatar: 'avatar',

  AvatarGroup: 'avatar',
  BackTop: 'back-top',
  Badge: 'badge',

  BaseTable: 'table',
  Breadcrumb: 'breadcrumb',
  BreadcrumbItem: 'breadcrumb',

  Button: 'button',
  Calendar: 'calendar',
  Card: 'card',

  Cascader: 'cascader',
  CascaderPanel: 'cascader',
  Checkbox: 'checkbox',

  CheckboxGroup: 'checkbox',
  CheckTag: 'tag',
  CheckTagGroup: 'tag',

  Col: 'grid',
  Collapse: 'collapse',
  CollapsePanel: 'collapse',

  ColorPicker: 'color-picker',
  ColorPickerPanel: 'color-picker',
  Comment: 'comment',

  ConfigProvider: 'config-provider',
  Content: 'layout',
  DatePicker: 'date-picker',

  DatePickerPanel: 'date-picker',
  DateRangePicker: 'date-picker',
  DateRangePickerPanel: 'date-picker',

  Descriptions: 'descriptions',
  DescriptionsItem: 'descriptions',
  Dialog: 'dialog',

  DialogCard: 'dialog',
  Divider: 'divider',
  Drawer: 'drawer',

  Dropdown: 'dropdown',
  DropdownItem: 'dropdown',
  DropdownMenu: 'dropdown',

  Empty: 'empty',
  EnhancedTable: 'table',
  Footer: 'layout',

  Form: 'form',
  FormItem: 'form',
  Guide: 'guide',

  Header: 'layout',
  HeadMenu: 'menu',
  HighlightOption: 'auto-complete',

  Icon: 'icon',
  Image: 'image',
  ImageViewer: 'image-viewer',

  Input: 'input',
  InputAdornment: 'input-adornment',
  InputGroup: 'input',

  InputNumber: 'input-number',
  Layout: 'layout',
  Link: 'link',

  List: 'list',
  ListItem: 'list',
  ListItemMeta: 'list',

  Loading: 'loading',
  Menu: 'menu',
  MenuGroup: 'menu',

  MenuItem: 'menu',
  Message: 'message',
  Notification: 'notification',

  Option: 'select',
  OptionGroup: 'select',
  Pagination: 'pagination',

  PaginationMini: 'pagination',
  Paragraph: 'typography',
  Popconfirm: 'popconfirm',

  Popup: 'popup',
  PrimaryTable: 'table',
  Progress: 'progress',

  QRCode: 'qrcode',
  Radio: 'radio',
  RadioButton: 'radio',

  RadioGroup: 'radio',
  RangeInput: 'range-input',
  RangeInputPopup: 'range-input',

  Rate: 'rate',
  Row: 'grid',
  Select: 'select',

  SelectInput: 'select-input',
  Skeleton: 'skeleton',
  Slider: 'slider',

  SliderButton: 'slider',
  Space: 'space',
  Statistic: 'statistic',

  StepItem: 'steps',
  Steps: 'steps',
  StickyItem: 'sticky-tool',

  StickyTool: 'sticky-tool',
  Submenu: 'menu',
  Swiper: 'swiper',

  SwiperItem: 'swiper',
  Switch: 'switch',
  Table: 'table',

  TabPanel: 'tabs',
  Tabs: 'tabs',
  Tag: 'tag',

  TagInput: 'tag-input',
  Text: 'typography',
  Textarea: 'textarea',

  Timeline: 'timeline',
  TimelineItem: 'timeline',
  TimePicker: 'time-picker',

  TimePickerPanel: 'time-picker',
  TimeRangePicker: 'time-picker',
  Title: 'typography',

  Tooltip: 'tooltip',
  Transfer: 'transfer',
  Tree: 'tree',

  TreeSelect: 'tree-select',
  Typography: 'typography',
  Upload: 'upload',

  Watermark: 'watermark',
};

// 函数式插件在 es 下是独立入口，不经组件目录。
const TDESIGN_PLUGIN_DIRS: Record<string, string> = {
  DialogPlugin: 'dialog',
  LoadingPlugin: 'loading',
  MessagePlugin: 'message',
  NotifyPlugin: 'notification',
};

// 把模板里的 <t-xxx> 解析到 tdesign-vue-next/es/<dir>/index：子入口自带 import './style/css.mjs'，
// 只带入该组件样式；官方 TDesignResolver 的 from 恒为全量 barrel，会拉齐全量 CSS。
function createTDesignResolver() {
  return {
    type: 'component' as const,
    resolve(name: string) {
      const pluginDir = TDESIGN_PLUGIN_DIRS[name];
      if (pluginDir) {
        return { name, from: `tdesign-vue-next/es/${pluginDir}/plugin` };
      }
      if (!/^T[A-Z]/.test(name)) {
        return;
      }
      const importName = name.slice(1);
      const dir = TDESIGN_COMPONENT_DIRS[importName];
      if (!dir) {
        return;
      }
      return { name: importName, from: `tdesign-vue-next/es/${dir}/index` };
    },
  };
}

function resolveAssetBase(rawValue = '') {
  const normalized = String(rawValue || '').trim();

  if (!normalized) {
    return '/';
  }

  if (/^https?:\/\//i.test(normalized)) {
    return normalized.endsWith('/') ? normalized : `${normalized}/`;
  }

  if (normalized.startsWith('/')) {
    return normalized.endsWith('/') ? normalized : `${normalized}/`;
  }

  return `/${normalized.replace(/^\/+/, '').replace(/\/+$/, '')}/`;
}

function resolveHintOrigin(assetBase: string) {
  if (!/^https?:\/\//i.test(assetBase)) {
    return '';
  }

  try {
    return new URL(assetBase).origin;
  } catch {
    return '';
  }
}

function resolveManualChunk(id: string) {
  const normalized = id.split(path.sep).join('/');

  if (!normalized.includes('/node_modules/')) {
    return undefined;
  }

  if (normalized.includes('/axios/')) {
    return 'vendor-axios';
  }

  if (
    normalized.includes('/vue/') ||
    normalized.includes('/vue-router/') ||
    normalized.includes('/pinia/') ||
    normalized.includes('/@vue/')
  ) {
    return 'vendor-vue';
  }

  if (normalized.includes('/qrcode.vue/')) {
    return 'vendor-qrcode';
  }

  if (
    normalized.includes('/markdown-it/') ||
    normalized.includes('/entities/') ||
    normalized.includes('/linkify-it/') ||
    normalized.includes('/mdurl/') ||
    normalized.includes('/uc.micro/')
  ) {
    return 'vendor-content';
  }

  return undefined;
}

async function collectOutputFiles(rootDirectory: string): Promise<string[]> {
  const entries = await readdir(rootDirectory, { withFileTypes: true });
  const files = await Promise.all(
    entries.map(async (entry) => {
      const nextPath = path.join(rootDirectory, entry.name);

      if (entry.isDirectory()) {
        return collectOutputFiles(nextPath);
      }

      return [nextPath];
    }),
  );

  return files.flat();
}

function createPrecompressedAssetsPlugin() {
  return {
    name: 'client-precompressed-assets',
    apply: 'build' as const,
    async closeBundle() {
      const distDirectory = path.resolve(__dirname, 'dist');
      const distStats = await stat(distDirectory).catch(() => null);

      if (!distStats?.isDirectory()) {
        return;
      }

      const files = await collectOutputFiles(distDirectory);

      await Promise.all(
        files.map(async (filePath) => {
          const extension = path.extname(filePath).toLowerCase();

          if (!compressionExtensions.has(extension)) {
            return;
          }

          const buffer = await readFile(filePath);
          const threshold = extension === '.html' ? htmlCompressionThreshold : compressionThreshold;

          if (buffer.byteLength < threshold) {
            return;
          }

          const gzipBuffer = gzipSync(buffer, { level: 9 });
          const brotliBuffer = brotliCompressSync(buffer, {
            params: {
              [zlibConstants.BROTLI_PARAM_QUALITY]: 11,
            },
          });

          if (gzipBuffer.byteLength < buffer.byteLength) {
            await writeFile(`${filePath}.gz`, gzipBuffer);
          }

          if (brotliBuffer.byteLength < buffer.byteLength) {
            await writeFile(`${filePath}.br`, brotliBuffer);
          }
        }),
      );
    },
  };
}

// PB-09：TDesign 的 es/style/index.css（main.ts:15 全量引入）自带一整块
// `:root.dark, :root[theme-mode='dark']` 暗色变量（源文件 6,044 字节，minify 后约 5,601 字节）。
// 本仓库是纯浅色主题：theme.css:124 明确写「原暗色变量块已删除，禁止重新引入」，
// src/store/modules/setting.ts 的 displayMode 恒为 'light'、changeMode 只 setAttribute('theme-mode','')，
// 全仓没有任何代码会写入 dark —— 这块规则永远命中不了，属于确定性死重。
// 不能改 node_modules，也不该在 src 里再引一份覆盖样式（那只会加字节），
// 所以在构建产物层面整块剔除：只删「选择器完全由 :root.dark / :root[theme-mode=dark] 组成」的顶层块，
// :root[theme-mode='light'] 与其它任何选择器原样保留。
const DARK_ROOT_SELECTOR = /^:root(?:\.dark|\[theme-mode=['"]?dark['"]?\])$/;

function isDarkOnlySelector(selectorText: string): boolean {
  const parts = selectorText
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean);

  return parts.length > 0 && parts.every((part) => DARK_ROOT_SELECTOR.test(part));
}

// 走 postcss 而不是 generateBundle 改产物文本：generateBundle 时 asset 的文件名已经定稿，
// 改完内容不会重算 [hash]，产物会出现「文件名不变、内容变了」——CDN 与浏览器都按文件名缓存，
// 线上会继续发旧内容。postcss 在 Vite 计算 hash 之前执行，hash 与内容始终对应。
const lightThemeOnlyPostcssPlugin = {
  postcssPlugin: 'client-light-theme-only',
  Once(root: { walkRules: (visitor: (rule: { selector: string; remove: () => void }) => void) => void }) {
    root.walkRules((rule) => {
      if (isDarkOnlySelector(rule.selector)) {
        rule.remove();
      }
    });
  },
};

function createIndexNetworkHintsPlugin(assetBase: string) {
  return {
    name: 'client-index-network-hints',
    apply: 'build' as const,
    transformIndexHtml(html: string) {
      const hintOrigin = resolveHintOrigin(assetBase);

      if (!hintOrigin) {
        return html;
      }

      const hintHost = new URL(hintOrigin).host;
      const dnsPrefetchTag = `    <link rel="dns-prefetch" href="//${hintHost}" />`;
      const preconnectTag = `    <link rel="preconnect" href="${hintOrigin}" crossorigin="anonymous" />`;

      return html.replace('</head>', `${dnsPrefetchTag}\n${preconnectTag}\n  </head>`);
    },
  };
}

function resolveAssetFileName(assetInfo: { name?: string }) {
  const name = String(assetInfo.name || '');
  const extension = path.extname(name).toLowerCase();

  if (extension === '.css') {
    return 'assets/css/[name]-[hash][extname]';
  }

  if (['.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg', '.avif'].includes(extension)) {
    return 'assets/img/[name]-[hash][extname]';
  }

  if (['.woff', '.woff2', '.ttf', '.otf', '.eot'].includes(extension)) {
    return 'assets/fonts/[name]-[hash][extname]';
  }

  return 'assets/misc/[name]-[hash][extname]';
}

// https://vitejs.dev/config/
export default ({ mode }: ConfigEnv): UserConfig => {
  const env = loadEnv(mode, CWD, '');
  const assetBase = resolveAssetBase(
    env.VITE_CONSOLE_ASSET_BASE_URL || env.VITE_CDN_ASSET_HOST || env.VITE_ASSET_BASE_URL || env.VITE_BASE_URL || '',
  );
  return {
    base: assetBase,
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
        '@caiwu/shared': path.resolve(__dirname, '../shared'),
      },
      dedupe: ['vue'],
    },

    css: {
      // PB-09：剔除 TDesign 自带的 :root.dark / :root[theme-mode='dark'] 变量块。
      // 放在 postcss 而不是 generateBundle，是因为 postcss 在 Vite 计算 [hash] 之前跑完，
      // 文件名与内容始终对应，不会出现「文件没改名、内容变了」的缓存事故。
      postcss: {
        plugins: [lightThemeOnlyPostcssPlugin as unknown as AcceptedPlugin],
      },
      preprocessorOptions: {
        less: {
          modifyVars: {
            hack: `true; @import (reference) "${path.resolve('src/style/variables.less')}";`,
          },
          math: 'strict',
          javascriptEnabled: true,
        },
      },
    },

    plugins: [
      vue(),
      vueJsx(),
      Components({
        // 仅按需解析 TDesign 组件（含其样式），替代 main.ts 的全量 app.use(TDesign)。
        // 图标仍由各页面显式 import（tdesign-icons-vue-next），故不做图标组件解析。
        dts: false,
        resolvers: [createTDesignResolver()],
      }),
      // 控制台的极验脚本由后端代理（/api/v2/client/auth/captcha-script）下发，
      // 四端独立域名下它来自 API 源站；script-src 没有 `https:` 兜底，必须精确放行。
      createApiConnectSrcPlugin(env, { scriptDirective: 'script-src' }),
      createIndexNetworkHintsPlugin(assetBase),
      createPrecompressedAssetsPlugin(),
    ],

    server: {
      headers: {
        'X-Content-Type-Options': 'nosniff',
      },
      fs: {
        allow: [path.resolve(__dirname, '..')],
      },
      port: 5173,
      host: '127.0.0.1',
    },
    build: {
      // PB-18：从 es2018 提到 es2020。仓库里唯一有书面记录的浏览器基线是官网的
      // browserslist（frontend-user-v3-www/package.json: chrome>=84 / edge>=88 /
      // firefox>=89 / safari>=13.4 / ios_saf>=13.4，docs/DESIGN.md:240 亦有记载），
      // 控制台与官网面向同一批访客，该基线完整覆盖 ?? 与 ?.（Chrome 80+ / Safari 13.1+）。
      // es2018 会把入口里 894 处 void 0 与 145 处 !==void 0 的比较全部降级出来，
      // 实测首屏 gzip 从 204,360 降到 203,476 字节。控制台自身没有 browserslist 声明，
      // 若日后确认需支持更旧的浏览器，把这里调回 'es2018' 即可，不需要改别处。
      target: 'es2020',
      sourcemap: false,
      minify: 'esbuild',
      cssMinify: 'esbuild',
      cssCodeSplit: true,
      reportCompressedSize: false,
      // 统一为 600：与 backend/scripts/frontend_budget.mjs 的首屏/分片门禁同向，超限时构建告警才有信息量（审查条目 D7）。
      chunkSizeWarningLimit: 600,
      rollupOptions: {
        output: {
          entryFileNames: 'assets/js/[name]-[hash].js',
          chunkFileNames: 'assets/js/[name]-[hash].js',
          assetFileNames: resolveAssetFileName,
          manualChunks: resolveManualChunk,
        },
      },
    },
    optimizeDeps: {
      noDiscovery: true,
      include: ['vue', 'vue-router', 'pinia', 'axios', 'tdesign-icons-vue-next', 'nprogress'],
    },
  };
};
