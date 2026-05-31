import fs from 'node:fs/promises'
import path from 'path'
import { brotliCompressSync, constants as zlibConstants, gzipSync } from 'node:zlib'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'

function normalizeAssetBase(base) {
  if (!base) {
    return '/'
  }

  if (base === './' || base === '/') {
    return base
  }

  return base.endsWith('/') ? base : `${base}/`
}

function emitCompressedAssets(options = {}) {
  const threshold = options.threshold || 1024
  const compressibleAssetPattern = /\.(js|mjs|css|html|svg|json|xml|txt)$/i
  let resolvedConfig

  return {
    name: 'emit-compressed-assets',
    apply: 'build',
    configResolved(config) {
      resolvedConfig = config
    },
    async writeBundle(_, bundle) {
      const outDir = path.resolve(resolvedConfig.root, resolvedConfig.build.outDir)
      const tasks = Object.keys(bundle)
        .filter((fileName) => compressibleAssetPattern.test(fileName))
        .map(async (fileName) => {
          const outputPath = path.join(outDir, fileName)
          const source = await fs.readFile(outputPath)
          if (source.byteLength < threshold) {
            return
          }

          const gzipBuffer = gzipSync(source, { level: 9 })
          if (gzipBuffer.byteLength < source.byteLength) {
            await fs.writeFile(`${outputPath}.gz`, gzipBuffer)
          }

          const brotliBuffer = brotliCompressSync(source, {
            params: {
              [zlibConstants.BROTLI_PARAM_QUALITY]: 11,
            },
          })
          if (brotliBuffer.byteLength < source.byteLength) {
            await fs.writeFile(`${outputPath}.br`, brotliBuffer)
          }
        })

      await Promise.all(tasks)
    },
  }
}

function resolveManualChunk(id) {
  const normalized = id.split(path.sep).join('/')

  if (normalized.includes('/src/layout/AdminLayout/') || normalized.includes('/src/stores/')) {
    return 'app-shell'
  }

  if (!normalized.includes('/node_modules/')) {
    return undefined
  }

  if (normalized.includes('/axios/')) {
    return 'vendor-axios'
  }

  if (normalized.includes('/@wangeditor/')) {
    return 'vendor-editor'
  }

  if (normalized.includes('/echarts/') || normalized.includes('/zrender/')) {
    return 'vendor-echarts'
  }

  if (normalized.includes('/markdown-it/')) {
    return 'vendor-content'
  }

  if (normalized.includes('/element-plus/')) {
    return 'vendor-element-plus'
  }

  if (
    normalized.includes('/vue/')
    || normalized.includes('/vue-router/')
    || normalized.includes('/pinia/')
    || normalized.includes('/@vue/')
  ) {
    return 'vendor-vue'
  }

  return undefined
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    base: normalizeAssetBase(env.VITE_CDN_ASSET_HOST || env.VITE_ASSET_BASE_URL),
    plugins: [
      vue(),
      AutoImport({
        resolvers: [ElementPlusResolver()],
        imports: ['vue', 'vue-router', 'pinia'],
      }),
      Components({
        directives: true,
        resolvers: [ElementPlusResolver({ importStyle: false })],
      }),
      emitCompressedAssets(),
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src'),
        '@shared': path.resolve(__dirname, '../shared'),
        'element-plus': path.resolve(__dirname, '../node_modules/element-plus'),
      },
    },
    server: {
      host: '127.0.0.1',
      port: 5174,
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
          rewrite: (requestPath) => requestPath,
        },
      },
    },
    optimizeDeps: {
      include: ['vue', 'vue-router', 'pinia', 'axios', '@element-plus/icons-vue', 'element-plus/es'],
    },
    build: {
      target: 'es2015',
      sourcemap: false,
      minify: 'esbuild',
      cssMinify: 'esbuild',
      cssCodeSplit: true,
      chunkSizeWarningLimit: 1000,
      rollupOptions: {
        output: {
          manualChunks: resolveManualChunk,
        },
      },
    },
    css: {
      preprocessorOptions: {
        scss: {
          additionalData: `@use "sass:color";\n@use "@/assets/styles/variables.scss" as *;\n@use "@/assets/styles/mixins" as *;`,
        },
      },
    },
  }
})
