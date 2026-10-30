// 预压缩（.gz / .br）产物的公共实现。
//
// 必须由两个时机共同调用，缺一不可：
// 1) vite 插件的 closeBundle —— 压缩 vite 这次产出的 assets 与根 index.html；
// 2) 预渲染脚本（prerender-www.mjs）写完所有路由 HTML 之后再调一次 ——
//    因为 build 脚本顺序是 `vite build` → generate-sitemap → prerender，
//    预渲染会重写根 index.html 并新增 7 条静态页 + 5 条 SEO 落地页 + 404.html，
//    这些文件在 closeBundle 时还不存在（或内容还是旧的），只压一次会永久落后一版。
import { readFile, readdir, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import {
  brotliCompressSync,
  constants as zlibConstants,
  gzipSync,
} from "node:zlib";

// 与 nginx `gzip_static on; brotli_static on;` 配合使用的扩展名白名单。
const compressionExtensions = new Set([
  ".css",
  ".html",
  ".js",
  ".json",
  ".map",
  ".mjs",
  ".svg",
  ".txt",
  ".xml",
]);

// 小于 1 KB 的文件压缩收益抵不过额外的请求协商开销。
const compressionThreshold = 1024;

async function collectFiles(rootDirectory) {
  const entries = await readdir(rootDirectory, { withFileTypes: true });
  const files = await Promise.all(
    entries.map(async (entry) => {
      const nextPath = path.join(rootDirectory, entry.name);

      if (entry.isDirectory()) {
        return collectFiles(nextPath);
      }

      return [nextPath];
    }),
  );

  return files.flat();
}

/**
 * 为目录下所有产出文件生成 gzip / brotli 预压缩副本（幂等，可重复调用）。
 * @param {string} targetDirectory 需要处理的目标目录
 * @returns {Promise<{ processed: number, gzipCount: number, brotliCount: number }>}
 */
export async function precompressAssets(targetDirectory) {
  const directoryStats = await stat(targetDirectory).catch(() => null);

  if (!directoryStats?.isDirectory()) {
    return { processed: 0, gzipCount: 0, brotliCount: 0 };
  }

  const files = await collectFiles(targetDirectory);
  const counters = { processed: 0, gzipCount: 0, brotliCount: 0 };

  // 顺序处理即可：gzipSync / brotliCompressSync 都是同步 CPU 运算，
  // 并发调度并不能提升压缩吞吐（CPU 并发度恒为 1），只会让计数逻辑
  // 看起来像有竞态。真正的耗时项是 brotli quality 11，属于一次性构建成本。
  for (const filePath of files) {
    if (!compressionExtensions.has(path.extname(filePath).toLowerCase())) {
      continue;
    }

    const buffer = await readFile(filePath);

    if (buffer.byteLength < compressionThreshold) {
      continue;
    }

    counters.processed += 1;

    const gzipBuffer = gzipSync(buffer, { level: 9 });
    if (gzipBuffer.byteLength < buffer.byteLength) {
      await writeFile(`${filePath}.gz`, gzipBuffer);
      counters.gzipCount += 1;
    }

    const brotliBuffer = brotliCompressSync(buffer, {
      params: {
        [zlibConstants.BROTLI_PARAM_QUALITY]: 11,
      },
    });
    if (brotliBuffer.byteLength < buffer.byteLength) {
      await writeFile(`${filePath}.br`, brotliBuffer);
      counters.brotliCount += 1;
    }
  }

  return counters;
}
