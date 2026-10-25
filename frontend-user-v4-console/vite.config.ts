import { readdir, readFile, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { brotliCompressSync, constants as zlibConstants, gzipSync } from 'node:zlib';

import vue from '@vitejs/plugin-vue';
import vueJsx from '@vitejs/plugin-vue-jsx';
import Components from 'unplugin-vue-components/vite';
import type { ConfigEnv, UserConfig } from 'vite';
import { loadEnv } from 'vite';
import svgLoader from 'vite-svg-loader';

import { createApiConnectSrcPlugin } from '../shared/vite/apiConnectSrcPlugin.mjs';

const CWD = process.cwd();
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const compressionExtensions = new Set(['.css', '.html', '.js', '.json', '.map', '.mjs', '.svg', '.txt', '.xml']);
const compressionThreshold = 1024;

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

          if (buffer.byteLength < compressionThreshold) {
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
      svgLoader(),
      createApiConnectSrcPlugin(env),
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
      target: 'es2018',
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
      include: [
        'vue',
        'vue-router',
        'pinia',
        'axios',
        'tdesign-icons-vue-next',
        'vue-i18n',
        'dayjs',
        'nprogress',
        'qs',
      ],
    },
  };
};
