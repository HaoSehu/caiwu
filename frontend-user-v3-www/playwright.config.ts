import { defineConfig } from "@playwright/test";

// 冒烟测试：复用构建产物（npm run build 后执行），vite preview 静态服务
export default defineConfig({
  testDir: "./e2e",
  timeout: 60_000,
  expect: { timeout: 30_000 },
  retries: 0,
  reporter: [["list"]],
  use: {
    baseURL: "http://127.0.0.1:4173",
    headless: true,
    viewport: { width: 1280, height: 800 },
    locale: "zh-CN",
  },
  projects: [{ name: "chromium", use: { browserName: "chromium" } }],
  // preview 服务由 scripts/e2e-run.mjs 显式托管：Playwright 自带的 webServer 在 Windows 下
  // 无法可靠回收 preview 进程树（用例已跑完但命令不退出，一直等 4173 释放），
  // 与控制台 frontend-user-v4-console 的处理保持一致。入口统一走 npm run test:e2e。
});
