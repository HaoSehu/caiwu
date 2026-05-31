import { readFile, readdir, stat, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { brotliCompressSync, constants as zlibConstants, gzipSync } from 'node:zlib'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'

const backendProxyTarget = process.env.BACKEND_PROXY_TARGET || 'http://127.0.0.1:8000'
const backendWsProxyTarget = process.env.BACKEND_WS_PROXY_TARGET || 'ws://127.0.0.1:8000'
const compressionExtensions = new Set([
  '.css',
  '.html',
  '.js',
  '.json',
  '.map',
  '.mjs',
  '.svg',
  '.txt',
  '.xml',
])
const compressionThreshold = 1024

function resolveAssetBase(rawValue = '') {
  const normalized = String(rawValue || '').trim()

  if (!normalized) {
    return '/'
  }

  if (/^https?:\/\//i.test(normalized)) {
    return normalized.endsWith('/') ? normalized : `${normalized}/`
  }

  if (normalized.startsWith('/')) {
    return normalized.endsWith('/') ? normalized : `${normalized}/`
  }

  return `/${normalized.replace(/^\/+/, '').replace(/\/+$/, '')}/`
}

function resolveHintOrigin(assetBase) {
  if (!/^https?:\/\//i.test(assetBase)) {
    return ''
  }

  try {
    return new URL(assetBase).origin
  } catch {
    return ''
  }
}

function resolveManualChunk(id) {
  const normalized = id.split(path.sep).join('/')

  if (normalized.includes('/node_modules/')) {
    if (normalized.includes('/axios/')) {
      return 'vendor-axios'
    }

    if (normalized.includes('/element-plus/')) {
      return 'vendor-element-plus'
    }

    if (normalized.includes('/@element-plus/icons-vue/')) {
      return 'vendor-icons'
    }

    if (
      normalized.includes('/vue/')
      || normalized.includes('/vue-router/')
      || normalized.includes('/pinia/')
      || normalized.includes('/@vue/')
    ) {
      return 'vendor-vue'
    }

    if (normalized.includes('/three/') || normalized.includes('/@types/three/')) {
      return 'vendor-three'
    }

    if (normalized.includes('/@novnc/novnc/')) {
      return 'vendor-vnc'
    }

    if (
      normalized.includes('/qrcode.vue/')
      || normalized.includes('/markdown-it/')
      || normalized.includes('/entities/')
      || normalized.includes('/linkify-it/')
      || normalized.includes('/mdurl/')
      || normalized.includes('/uc.micro/')
    ) {
      return 'vendor-content'
    }
  }

  return undefined
}

async function collectOutputFiles(rootDirectory) {
  const entries = await readdir(rootDirectory, { withFileTypes: true })
  const files = await Promise.all(entries.map(async (entry) => {
    const nextPath = path.join(rootDirectory, entry.name)

    if (entry.isDirectory()) {
      return collectOutputFiles(nextPath)
    }

    return [nextPath]
  }))

  return files.flat()
}

function createPrecompressedAssetsPlugin() {
  return {
    name: 'client-precompressed-assets',
    apply: 'build',
    async closeBundle() {
      const distDirectory = path.resolve(__dirname, 'dist')
      const distStats = await stat(distDirectory).catch(() => null)

      if (!distStats?.isDirectory()) {
        return
      }

      const files = await collectOutputFiles(distDirectory)

      await Promise.all(files.map(async (filePath) => {
        const extension = path.extname(filePath).toLowerCase()

        if (!compressionExtensions.has(extension)) {
          return
        }

        const buffer = await readFile(filePath)

        if (buffer.byteLength < compressionThreshold) {
          return
        }

        const gzipBuffer = gzipSync(buffer, { level: 9 })
        const brotliBuffer = brotliCompressSync(buffer, {
          params: {
            [zlibConstants.BROTLI_PARAM_QUALITY]: 11,
          },
        })

        if (gzipBuffer.byteLength < buffer.byteLength) {
          await writeFile(`${filePath}.gz`, gzipBuffer)
        }

        if (brotliBuffer.byteLength < buffer.byteLength) {
          await writeFile(`${filePath}.br`, brotliBuffer)
        }
      }))
    },
  }
}

function createIndexNetworkHintsPlugin(assetBase) {
  return {
    name: 'client-index-network-hints',
    apply: 'build',
    transformIndexHtml(html) {
      const hintOrigin = resolveHintOrigin(assetBase)

      if (!hintOrigin) {
        return html
      }

      const hintHost = new URL(hintOrigin).host
      const dnsPrefetchTag = `  <link rel="dns-prefetch" href="//${hintHost}" />`
      const preconnectTag = `  <link rel="preconnect" href="${hintOrigin}" crossorigin="anonymous" />`

      return html.replace(
        '</head>',
        `${dnsPrefetchTag}\n${preconnectTag}\n</head>`,
      )
    },
  }
}

function resolveAssetFileName(assetInfo) {
  const name = String(assetInfo.name || '')
  const extension = path.extname(name).toLowerCase()

  if (extension === '.css') {
    return 'assets/css/[name]-[hash][extname]'
  }

  if (['.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg', '.avif'].includes(extension)) {
    return 'assets/img/[name]-[hash][extname]'
  }

  if (['.woff', '.woff2', '.ttf', '.otf', '.eot'].includes(extension)) {
    return 'assets/fonts/[name]-[hash][extname]'
  }

  return 'assets/misc/[name]-[hash][extname]'
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const assetBase = resolveAssetBase(env.VITE_CDN_ASSET_HOST || env.VITE_ASSET_BASE_URL || '')

  return {
    base: assetBase,
    plugins: [
      vue(),
      AutoImport({
        resolvers: [ElementPlusResolver({ importStyle: false })],
        imports: ['vue', 'vue-router', 'pinia'],
      }),
      Components({
        resolvers: [ElementPlusResolver({ importStyle: false, directives: true })],
      }),
      createIndexNetworkHintsPlugin(assetBase),
      createPrecompressedAssetsPlugin(),
    ],
    resolve: {
      dedupe: ['vue', 'element-plus'],
      alias: {
        '@': path.resolve(__dirname, 'src'),
        '@shared': path.resolve(__dirname, '../shared'),
        'element-plus': path.resolve(__dirname, '../node_modules/element-plus'),
      },
    },
    server: {
      host: '127.0.0.1',
      port: 5173,
      proxy: {
        '/api': {
          target: backendProxyTarget,
          changeOrigin: true,
        },
        '/uploads': {
          target: backendProxyTarget,
          changeOrigin: true,
        },
        '/ws/vnc': {
          target: backendWsProxyTarget,
          changeOrigin: true,
          ws: true,
          secure: false,
        },
      },
    },
    optimizeDeps: {
      include: [
        'vue',
        'vue-router',
        'pinia',
        'axios',
        'element-plus/es',
        '@element-plus/icons-vue',
        'markdown-it',
        'qrcode.vue',
      ],
    },
    build: {
      target: 'es2018',
      sourcemap: false,
      minify: 'esbuild',
      cssMinify: 'esbuild',
      cssCodeSplit: true,
      reportCompressedSize: false,
      chunkSizeWarningLimit: 1200,
      rollupOptions: {
        output: {
          entryFileNames: 'assets/js/[name]-[hash].js',
          chunkFileNames: 'assets/js/[name]-[hash].js',
          assetFileNames: resolveAssetFileName,
          manualChunks: resolveManualChunk,
        },
      },
    },
    css: {
      preprocessorOptions: {
        scss: {
          additionalData: `@use "@/assets/styles/variables.scss" as *;`,
        },
      },
    },
  }
})
