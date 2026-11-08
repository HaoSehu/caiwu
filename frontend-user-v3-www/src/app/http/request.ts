import { createDemoAdapter, createHttpClient } from "@caiwu/shared/runtime";

import { getClientToken } from "@/app/runtime/session";

let lastErrorMsg = "";
let lastErrorTimer: ReturnType<typeof setTimeout> | null = null;
// 接口错误提示是"出错才用"的能力，静态 import 会把 message 插件（含 loading、图标等
// 依赖）整包拉进入口 chunk，实测入口 gzip 从 20.9 KB 涨到 48.8 KB，首屏直接超预算。
// 因此保持惰性加载：首次报错时再拉，之后复用同一个 promise。
let messageLoader: Promise<
  typeof import("tdesign-vue-next/es/message/plugin")
> | null = null;

function loadMessage() {
  messageLoader ||= import("tdesign-vue-next/es/message/plugin");
  return messageLoader;
}

function showError(msg: string) {
  if (msg === lastErrorMsg) {
    return;
  }

  lastErrorMsg = msg;
  void loadMessage()
    .then(({ MessagePlugin }) => {
      MessagePlugin.error(msg);
    })
    // chunk 加载失败时必须把缓存的 rejected promise 清掉，否则后续所有接口错误
    // 都会复用同一个失败 promise，整个会话的提示全部静默消失。
    .catch(() => {
      messageLoader = null;
      // 提示实际没弹出来，就不能继续占用去重槽位：清掉刚记下的文案，
      // 让 1 秒窗口内的下一次报错立刻重试加载并展示。
      lastErrorMsg = "";
    });
  if (lastErrorTimer) {
    clearTimeout(lastErrorTimer);
  }
  lastErrorTimer = setTimeout(() => {
    lastErrorMsg = "";
  }, 1000);
}

const apiBaseUrl = String(import.meta.env.VITE_API_BASE_URL || "")
  .trim()
  .replace(/\/+$/, "");

if (!apiBaseUrl) {
  throw new Error("VITE_API_BASE_URL 必须配置");
}

// 官网为公开站点：无登录跳转逻辑，40100/401 仅按错误抛出，由调用方决定处理方式。
const request = createHttpClient({
  baseURL: apiBaseUrl,
  showError,
  resolveToken: () => getClientToken(),
  // 演练版（--mode demo）：请求不发出网络，由静态数据集响应；生产构建为 undefined。
  adapter:
    import.meta.env.MODE === "demo"
      ? createDemoAdapter({ dataUrl: import.meta.env.VITE_DEMO_DATA_URL })
      : undefined,
});

export default request;
