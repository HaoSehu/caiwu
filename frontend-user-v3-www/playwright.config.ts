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
  webServer: {
    // e2e 构建指向本地后端（.env.e2e → /api 代理到 127.0.0.1:8000），独立产物不覆盖生产 dist
    command:
      "npm run build:e2e && npm run preview -- --outDir dist-e2e --port 4173 --strictPort",
    port: 4173,
    // 强制自建服务并拒绝复用，避免残留 preview 顶替导致测到过期产物
    reuseExistingServer: false,
    timeout: 120_000,
  },
});
