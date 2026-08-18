import { createHttpClient } from "@caiwu/shared/runtime";
import { getClientToken } from "@/app/runtime/session";

let lastErrorMsg = "";
let lastErrorTimer: ReturnType<typeof setTimeout> | null = null;
let messageLoader: Promise<
  typeof import("element-plus/es/components/message/index.mjs")
> | null = null;

function loadMessage() {
  messageLoader ||= import("element-plus/es/components/message/index.mjs");
  return messageLoader;
}

function showError(msg: string) {
  if (msg === lastErrorMsg) {
    return;
  }

  lastErrorMsg = msg;
  void loadMessage().then((messageModule) => {
    messageModule.default.error(msg);
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
});

export default request;
