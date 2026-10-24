/**
 * 用户控制台 e2e 运行器入口。
 *
 * 真正的实现在 backend/scripts/e2e_runner.mjs（三端共用）：Windows 下 Playwright 自带的
 * webServer 无法可靠回收 vite preview 进程树，会表现为「用例跑完了但命令不退出」，所以由脚本
 * 显式托管「起 preview → 等 4173 就绪 → 跑 playwright → 按进程树强杀 → 以用例退出码退出」。
 *
 * 三端固定使用 4173 端口，因此 e2e 必须串行执行，并发会互抢端口直接失败。
 *
 * 用法：npm run test:e2e [-- <playwright 参数>]
 */
import { runE2E } from '../../backend/scripts/e2e_runner.mjs';

await runE2E({ appName: '用户控制台', cwd: process.cwd() });
