/**
 * 管理端 e2e 构建入口。
 *
 * 背景：管理端仓库内没有 .env.e2e（其余两端有），vite build --mode e2e 会退回到 .env 里的线上基址，
 * 于是 dist-e2e 把 api.coyjs.cn 打进产物，e2e 请求全部落到真实线上域名（审查条目 P2-2）。
 * 这里在调用 vite 前把 VITE_API_BASE_URL 固定为同源的 /api：vite 的 loadEnv 会把 process.env 全量合并进
 * import.meta.env，构建期即生效，无需在仓库内新增 .env* 文件。
 *
 * 同时管理端 preview 没有 /api 代理（vite.config.ts 无 preview.proxy），所以 e2e 用例必须自行 route mock 接口，
 * 本脚本只负责让产物里的基址不再指向线上。
 */
import { spawnSync } from 'node:child_process';

import { resolveBin } from '../../backend/scripts/e2e_runner.mjs';

const CWD = process.cwd();
// 不写死 ../node_modules：单端安装依赖时 vite 可能装在本端目录，按 package.json 解析才可靠。
const VITE_BIN = resolveBin(CWD, 'vite');

const result = spawnSync(process.execPath, [VITE_BIN, 'build', '--mode', 'e2e', '--outDir', 'dist-e2e'], {
  cwd: CWD,
  stdio: 'inherit',
  env: {
    ...process.env,
    VITE_API_BASE_URL: '/api',
  },
});

if (result.error) {
  throw result.error;
}

process.exit(result.status ?? 1);
