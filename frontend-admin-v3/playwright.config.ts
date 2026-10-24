import { defineConfig } from '@playwright/test';

// 管理端 e2e：复用构建产物（npm run build:e2e），由 scripts/e2e-run.mjs 托管 vite preview。
// 接口一律由用例内的 page.route() 拦截（见 e2e/fixtures.ts），不依赖本地后端。
export default defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  expect: { timeout: 30_000 },
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://127.0.0.1:4173',
    headless: true,
    viewport: { width: 1280, height: 800 },
    locale: 'zh-CN',
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  // preview 服务由 scripts/e2e-run.mjs 显式托管（Windows 下 Playwright 自带的 webServer
  // 无法可靠回收 preview 进程树，会导致命令跑完用例后不退出），故此处不声明 webServer。
  // 入口统一走 npm run test:e2e。
});
