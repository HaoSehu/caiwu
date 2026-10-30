import path from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig, loadEnv } from "vite";

import { createApiConnectSrcPlugin } from "../shared/vite/apiConnectSrcPlugin.mjs";
import vue from "@vitejs/plugin-vue";
import AutoImport from "unplugin-auto-import/vite";
import Components from "unplugin-vue-components/vite";

import { createElementPlusSubpathResolvers } from "./scripts/element-plus-subpath-resolver.mjs";
import { precompressAssets } from "./scripts/precompress-assets.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// 静态资源与站点同源，不再使用外部 CDN，统一以根路径发布。
// 如未来需要部署到子路径，可在 .env 中设置 VITE_WWW_ASSET_BASE_URL=/子路径/。
function resolveAssetBase(rawValue = "") {
  const normalized = String(rawValue || "").trim();

  if (!normalized) {
    return "/";
  }

  if (normalized.startsWith("/")) {
    return normalized.endsWith("/") ? normalized : `${normalized}/`;
  }

  return `/${normalized.replace(/^\/+/, "").replace(/\/+$/, "")}/`;
}

function resolveManualChunk(id) {
  const normalized = id.split(path.sep).join("/");

  if (normalized.includes("/node_modules/")) {
    if (normalized.includes("/axios/")) {
      return "vendor-axios";
    }

    // 注意：不要把 element-plus 及其子路径整体分桶。manualChunks 返回固定名字时，
    // 该名字下的所有模块会被合并成同一个 chunk；只要入口静态引用了其中一个模块
    // （bootstrap.ts 就静态引用了 config-provider 与 message 样式），整个 chunk 都会被
    // 提前到首屏，实测首屏 gzip 从约 106KB 涨到约 400KB。历史上试过
    // /element-plus/ 与 /@element-plus/icons-vue/ 两条规则，构建通过但首屏超预算 3 倍，
    // 已回退；要压缩 select/input 的体积应走按需 import 或 router 级拆分，不是整包分桶。

    if (
      normalized.includes("/vue/") ||
      normalized.includes("/vue-router/") ||
      normalized.includes("/pinia/") ||
      normalized.includes("/@vue/")
    ) {
      return "vendor-vue";
    }

    if (
      normalized.includes("/markdown-it/") ||
      normalized.includes("/entities/") ||
      normalized.includes("/linkify-it/") ||
      normalized.includes("/mdurl/") ||
      normalized.includes("/uc.micro/")
    ) {
      return "vendor-content";
    }
  }

  return undefined;
}

function createPrecompressedAssetsPlugin() {
  let outputDirectory = path.resolve(__dirname, "dist");

  return {
    name: "client-precompressed-assets",
    apply: "build",
    // 从 resolvedConfig 取真实 outDir：build:e2e 用 --outDir dist-e2e，
    // 硬编码 dist 会让每次 e2e 构建都在无谓重压一版陈旧的 dist（并且掩盖 dist 的陈旧度）。
    configResolved(config) {
      outputDirectory = path.resolve(__dirname, config.build.outDir);
    },
    // 这里只覆盖 vite 自己产出的 assets 与根 index.html。
    // 预渲染会在这之后重写根 index.html 并新增十多个路由 HTML，
    // 那部分由 scripts/prerender-www.mjs 结束时再调用一次同一个函数补齐，
    // 否则预压缩产物会永久落后一版（见 scripts/precompress-assets.mjs 文件头说明）。
    async closeBundle() {
      await precompressAssets(outputDirectory);
    },
  };
}

function resolveAssetFileName(assetInfo) {
  const name = String(assetInfo.name || "");
  const extension = path.extname(name).toLowerCase();

  if (extension === ".css") {
    return "assets/css/[name]-[hash][extname]";
  }

  if (
    [".png", ".jpg", ".jpeg", ".webp", ".gif", ".svg", ".avif"].includes(
      extension,
    )
  ) {
    return "assets/img/[name]-[hash][extname]";
  }

  if ([".woff", ".woff2", ".ttf", ".otf", ".eot"].includes(extension)) {
    return "assets/fonts/[name]-[hash][extname]";
  }

  return "assets/misc/[name]-[hash][extname]";
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  // 资源与页面同源，去掉外部 CDN 相关变量（VITE_CDN_ASSET_HOST / VITE_ASSET_BASE_URL）。
  const assetBase = resolveAssetBase(env.VITE_WWW_ASSET_BASE_URL || "");

  return {
    base: assetBase,
    plugins: [
      vue(),
      AutoImport({
        // 声明文件属构建产物，入库会与源码漂移：与另两端一致关闭生成。
        dts: false,
        imports: ["vue", "vue-router", "pinia"],
      }),
      Components({
        // 同上。
        dts: false,
        // 用本地封装的子路径解析器替代原生 ElementPlusResolver：
        // 原生实现一律生成 from "element-plus/es"（全量 barrel），会把 barrel 顶层
        // 裸 import 的 dayjs 变成每个 chunk 的副作用依赖。详见 scripts/element-plus-subpath-resolver.mjs。
        resolvers: createElementPlusSubpathResolvers({
          importStyle: "sass",
          directives: true,
        }),
      }),
      createPrecompressedAssetsPlugin(),
      createApiConnectSrcPlugin({
        ...env,
        // e2e / preview 走 /api 相对代理，接口请求同源；但后端返回的 site_logo、site_favicon
        // 是 http://<后端>/media/* 的绝对地址，不经代理也不在 'self' 范围内。
        // 这里把代理目标显式交给 CSP 插件，保证允许的正是后端真实源站。
        //
        // 但「开发默认值」绝不能漏进生产产物：.gitignore 忽略 www 的整个 .env*，
        // 干净 clone / CI 上没有任何 VITE_API_BASE_URL 时，兜底会把
        // http://127.0.0.1:8000 写进 dist/index.html 的 img-src/connect-src/media-src，
        // 既放行了不该放行的源，也让「生产 CSP 只允许 https」这条约束失效。
        // 因此仅非 production 构建才允许取兜底值；生产构建只认显式配置的变量。
        VITE_CSP_API_ORIGIN:
          env.VITE_CSP_API_ORIGIN ||
          env.VITE_PREVIEW_API_TARGET ||
          (mode === "production" ? "" : "http://127.0.0.1:8000"),
      }),
      {
        // element-plus use-locale 用相对路径静态 import en 语言包作默认 fallback，
        // 站点始终提供 zh-cn locale，用最小 stub 替代完整 en 语言包，消除入口 chunk 死代码。
        // 用 resolveId 钩子返回绝对路径，避免 Vite alias 对相对路径 import 的拼接问题。
        // enforce: 'pre' 保证先于内置 vite:resolve 运行，否则内置解析已返回 en.mjs 的结果。
        name: "strip-element-plus-en-locale",
        enforce: "pre",
        resolveId(source) {
          if (
            source === "element-plus/es/locale/lang/en.mjs" ||
            /(?:^|[\\/])locale[\\/]lang[\\/]en\.mjs$/.test(source)
          ) {
            return path.resolve(__dirname, "src/shims/en-locale.mjs");
          }
          return null;
        },
      },
      {
        name: "client-first-screen-hints",
        apply: "build",
        transformIndexHtml(html, ctx) {
          // 为主入口 script 标签添加 fetchpriority="high"，优化首屏 LCP
          const withPriority = html.replace(
            /<script type="module" crossorigin src="\/assets\/js\/index-([^"]+)\.js"><\/script>/g,
            '<script type="module" crossorigin fetchpriority="high" src="/assets/js/index-$1.js"></script>',
          );

          // 首屏还依赖两个懒加载 chunk：布局（WebsiteLayout）与首页路由 chunk。
          // 它们由入口 chunk 里的动态 import 触发，必须等入口下载+执行完才开始请求，
          // 实测这段串行额外付出 32,449 B gzip。用 modulepreload 让它们与入口并行下载。
          // 不写死文件名（hash 会变）：按 facadeModuleId 从本次构建的 bundle 里定位。
          const bundle = ctx && ctx.bundle;
          if (!bundle) {
            return withPriority;
          }

          // rollup 的 facadeModuleId 在 Windows 上是正斜杠形式，和 path.resolve 的反斜杠不等，
          // 统一归一化后再比较，否则匹配恒为空、预加载静默失效。
          const normalizeId = (id) => String(id || "").split(path.sep).join("/");
          // 两组必须分开：layout 是全部路由共用，首页 chunk 只有 / 需要。
          // index.html 是全站唯一模板，prerender 会把它复制给 12 条预渲染路由，
          // 若不分组，SEO 落地页这类外部直入的营销页会白白多下约 17 KB gzip。
          // 分组标记写在 data-first-screen 上，由 scripts/prerender-www.mjs 按路由裁剪。
          const FIRST_SCREEN_CHUNK_GROUPS = [
            { scope: "layout", id: normalizeId(path.resolve(__dirname, "src/layout/WebsiteLayout.vue")) },
            { scope: "home", id: normalizeId(path.resolve(__dirname, "src/pages/website/home/index.vue")) },
          ];
          // 去重初值必须同时覆盖两类「HTML 已经会加载」的资源：
          // modulepreload（入口 vendor 分片）与 stylesheet（入口 CSS）。
          // 只看 modulepreload 会把入口 CSS 重复声明成 <link rel="preload" as="style">。
          const alreadyPreloaded = new Set();
          const existingLinks = /<link[^>]*rel="(?:modulepreload|stylesheet)"[^>]*href="\/([^"]+)"/g;
          let match = existingLinks.exec(withPriority);
          while (match) {
            alreadyPreloaded.add(match[1]);
            match = existingLinks.exec(withPriority);
          }

          // 入口 chunk 本身已经是 <script type="module"> 直引，不该再预加载，
          // 也不该顺带把它的 imports 拉进来（入口的静态 import 就是上面那些 vendor 分片）。
          const entryChunkFile = Object.entries(bundle).find(
            ([, chunk]) => chunk.type === "chunk" && /[\\/]index\.html$/.test(String(chunk.facadeModuleId || "")),
          )?.[0];
          if (entryChunkFile) {
            alreadyPreloaded.add(entryChunkFile);
          }

          const preloadFiles = [];
          const visitedChunks = new Set();
          const collectChunk = (fileName, scope) => {
            if (visitedChunks.has(fileName)) return;
            const chunk = bundle[fileName];
            if (!chunk || chunk.type !== "chunk") return;
            visitedChunks.add(fileName);
            if (!alreadyPreloaded.has(fileName)) {
              alreadyPreloaded.add(fileName);
              preloadFiles.push({ file: fileName, kind: "script", scope });
            }
            for (const cssFile of chunk.viteMetadata?.importedCss || []) {
              if (alreadyPreloaded.has(cssFile)) continue;
              alreadyPreloaded.add(cssFile);
              preloadFiles.push({ file: cssFile, kind: "style", scope });
            }
            // 只沿静态依赖展开：dynamicImports 属于「点开才需要」的部分，不能预加载。
            (chunk.imports || []).forEach((child) => collectChunk(child, scope));
          };

          // 按 layout → home 的固定顺序收集：先收的归 layout 组，
          // 后收的只补首页独有增量，避免同一资源在两组里重复出现。
          for (const group of FIRST_SCREEN_CHUNK_GROUPS) {
            Object.entries(bundle)
              .filter(([, chunk]) => chunk.type === "chunk" && normalizeId(chunk.facadeModuleId) === group.id)
              .forEach(([fileName]) => collectChunk(fileName, group.scope));
          }

          if (preloadFiles.length === 0) {
            return withPriority;
          }

          // CSS 直接给 stylesheet 而不是 preload as=style：
          // 后者下载完不会自动应用，要靠 chunk 加载时再插一份 <link>，
          // 期间浏览器会报「preload 的资源未被使用」，且首屏样式仍要等 JS。
          const links = preloadFiles
            .map(({ file, kind, scope }) => {
              const href = `/${file}`;
              return kind === "style"
                ? `<link rel="stylesheet" href="${href}" data-first-screen="${scope}" />`
                : `<link rel="modulepreload" crossorigin href="${href}" data-first-screen="${scope}" />`;
            })
            .join("\n  ");

          return withPriority.replace("</head>", `  ${links}\n</head>`);
        },
      },
    ],
    resolve: {
      dedupe: ["vue", "element-plus"],
      alias: {
        "@": path.resolve(__dirname, "src"),
        "@caiwu/shared": path.resolve(__dirname, "../shared"),
        "element-plus": path.resolve(__dirname, "../node_modules/element-plus"),
      },
    },
    server: {
      headers: {
        "X-Content-Type-Options": "nosniff",
      },
      fs: {
        allow: [path.resolve(__dirname, "..")],
      },
      host: "127.0.0.1",
      port: 5175,
    },
    preview: {
      // e2e 冒烟（.env.e2e 的 VITE_API_BASE_URL=/api）经此处代理到本地后端，规避跨源。
      // 端口不写死：后端端口在本地/CI 可能不同，用 VITE_PREVIEW_API_TARGET 覆盖，默认仍是 8000。
      proxy: {
        "/api": {
          target: env.VITE_PREVIEW_API_TARGET || "http://127.0.0.1:8000",
          changeOrigin: true,
        },
      },
    },
    optimizeDeps: {
      // 与另两端一致关闭依赖扫描：只预构建下面显式列出的包，避免首次启动时
      // 因扫描到深层目录（如 public/ 下的静态站点）而多做一轮无谓预构建。
      // 列出的包必须覆盖 shared 的运行时依赖：axios 由 @caiwu/shared/runtime 的 http 使用。
      noDiscovery: true,
      include: [
        "vue",
        "vue-router",
        "pinia",
        "axios",
        "@element-plus/icons-vue",
        "markdown-it",
      ],
    },
    build: {
      // 本文件 package.json 的 browserslist（chrome>=84 / safari>=13.4）完整覆盖 ?? 与 ?.，
      // es2018 只会多降级出一批无意义的 void 0。三端统一 es2020，改基线时三处一起改。
      target: "es2020",
      sourcemap: false,
      minify: "esbuild",
      cssMinify: "esbuild",
      cssCodeSplit: true,
      reportCompressedSize: false,
      // 统一为 600：与 backend/scripts/frontend_budget.mjs 的首屏门禁同向（审查条目 D7）。
      // www 当前构建最大分片实测 153,833 B（vendor-vue，原注释写的 165,257 B 是旧产物），
      // 600 KB 只作异常放大的哨兵，不用于压体积。
      chunkSizeWarningLimit: 600,
      // 首屏门禁按 manifest 求静态闭包：www 的布局 chunk 与首页路由 chunk 都是入口 chunk 的
      // 动态 import，只看 index.html 直引资源会漏算它们（详见 backend/scripts/frontend_budget.mjs
      // 的 FIRST_SCREEN_MANIFEST_ENTRIES 注释）。manifest 是几十 KB 的构建元数据，随后续
      // 预压缩一并产出 .gz/.br。
      manifest: true,
      // 与 frontend-admin-v3 对齐：小于 4 KB 的资源内联进产物，减少首屏请求数。
      // www 的图片都在 public/（不经资源管线），这里只影响未来新增的小图标。
      assetsInlineLimit: 4096,
      rollupOptions: {
        output: {
          entryFileNames: "assets/js/[name]-[hash].js",
          chunkFileNames: "assets/js/[name]-[hash].js",
          assetFileNames: resolveAssetFileName,
          manualChunks: resolveManualChunk,
        },
      },
    },
    css: {
      preprocessorOptions: {
        scss: {
          additionalData: `@use "@/assets/styles/element/index.scss"; @use "@/assets/styles/variables.scss" as *;`,
        },
      },
    },
  };
});
