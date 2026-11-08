/**
 * 演示（demo）模式的 axios adapter。
 *
 * 演练版不连后端：所有请求由这里从一份构建期/发布期生成的静态数据集响应，
 * 键为 "<METHOD> <path>"（如 "GET /v2/admin/orders"），支持单段通配 "*"。
 *
 * 约束：
 * - 任何方法（含 POST/PUT/PATCH/DELETE）命中都返回成功信封 { code: 0, message: 'ok', data }，
 *   写操作只在页面内生效、刷新即复原（数据本身是只读静态文件）。
 * - 未命中返回 fallbackData（默认 {}）的同一成功信封 —— 三端拦截器只在 code === 0 时放行，
 *   返回错误会让页面连环弹错并被踢回登录页。
 * - 提供 dataUrl 时首个请求触发一次同源 fetch；加载失败只在 console.warn 记录，
 *   随后按空数据集继续返回成功信封，页面不白屏。
 */
import type { AxiosAdapter, AxiosResponse, InternalAxiosRequestConfig } from "axios";

export interface DemoDataset {
  meta?: { app?: string; exported_at?: string };
  /** "GET /v2/admin/orders" -> 该接口响应信封中的 data 载荷 */
  endpoints: Record<string, unknown>;
}

export interface CreateDemoAdapterOptions {
  /** 直接注入数据集（测试、本地联调） */
  dataset?: DemoDataset;
  /** 同源静态 JSON 地址，首个请求时懒加载一次并缓存 */
  dataUrl?: string;
  /** 未命中时的 data 载荷，默认 {} */
  fallbackData?: unknown;
  /** 命中回调（测试用） */
  onMatch?: (key: string) => void;
}

const EMPTY_DATASET: DemoDataset = { endpoints: {} };

/** 把一次请求规范化为数据集键："GET /v2/admin/orders"（去查询串/尾斜杠，保留 pathname）。 */
export function buildDemoKey(method: string, url: string): string {
  const normalizedMethod = String(method || "get").trim().toUpperCase();
  let pathname = String(url || "/").trim();

  try {
    // 相对路径也能解析：base 只用于补全，最终只取 pathname
    pathname = new URL(pathname, "http://demo.local").pathname;
  } catch {
    // URL 无法解析时退回手工裁剪
    pathname = pathname.split("?")[0].split("#")[0];
  }

  const segments = pathname
    .replace(/\\/g, "/")
    .split("/")
    .filter((segment) => segment.length > 0);

  return `${normalizedMethod} /${segments.join("/")}`;
}

/**
 * 从 axios 请求配置解析出后端相对路径，剥掉 baseURL 的路径前缀。
 * 生产/演示下 config.url 都是 "/v2/..."；这里兼容传绝对 URL 或
 * url 已拼上 "/demo/api" 前缀的调用方。
 */
function resolveRequestPath(config: {
  url?: string;
  baseURL?: string;
}): string {
  const raw = String(config.url || "/").trim();

  let pathname: string;
  try {
    pathname = new URL(raw, "http://demo.local").pathname;
  } catch {
    pathname = raw.split("?")[0].split("#")[0];
  }

  const base = String(config.baseURL || "").trim();
  if (base) {
    let basePath = "";
    try {
      basePath = new URL(base, "http://demo.local").pathname;
    } catch {
      basePath = "";
    }
    basePath = basePath.replace(/\/+$/, "");
    if (basePath && (pathname === basePath || pathname.startsWith(`${basePath}/`))) {
      pathname = pathname.slice(basePath.length) || "/";
    }
  }

  return pathname;
}

function compileWildcardKey(key: string): RegExp {
  const escaped = key
    .split("*")
    .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
    .join("[^/]+");
  return new RegExp(`^${escaped}$`);
}

/**
 * 在数据集中查找请求。先精确匹配，再按同方法通配键的字典序取首个命中。
 * 返回命中的键与其 data 载荷（data 原样返回，不做拷贝）。
 */
export function matchDemoKey(
  dataset: DemoDataset,
  method: string,
  url: string,
): { key: string; data: unknown } | null {
  const endpoints = dataset?.endpoints ?? EMPTY_DATASET.endpoints;
  const requestKey = buildDemoKey(method, url);

  if (Object.prototype.hasOwnProperty.call(endpoints, requestKey)) {
    return { key: requestKey, data: endpoints[requestKey] };
  }

  const wildcardKeys = Object.keys(endpoints)
    .filter((key) => key.includes("*"))
    .sort();

  for (const key of wildcardKeys) {
    if (compileWildcardKey(key).test(requestKey)) {
      return { key, data: endpoints[key] };
    }
  }

  return null;
}

function toAxiosResponse(
  config: InternalAxiosRequestConfig,
  data: unknown,
): AxiosResponse {
  return {
    data,
    status: 200,
    statusText: "OK",
    headers: {},
    config,
    request: {},
  };
}

const SUCCESS_ENVELOPE = (data: unknown) => ({ code: 0, message: "ok", data });

export function createDemoAdapter(options: CreateDemoAdapterOptions = {}): AxiosAdapter {
  const fallbackData = options.fallbackData ?? {};
  let dataset: DemoDataset | null = options.dataset ?? null;
  let loadPromise: Promise<DemoDataset> | null = null;

  function loadDataset(): Promise<DemoDataset> {
    if (dataset) {
      return Promise.resolve(dataset);
    }

    if (!options.dataUrl) {
      dataset = EMPTY_DATASET;
      return Promise.resolve(dataset);
    }

    loadPromise ||= globalThis
      .fetch(options.dataUrl)
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`demo dataset HTTP ${response.status}`);
        }
        const parsed = (await response.json()) as DemoDataset;
        if (!parsed || typeof parsed !== "object" || !parsed.endpoints) {
          throw new Error("demo dataset 缺少 endpoints 字段");
        }
        dataset = parsed;
        return dataset;
      })
      .catch((error) => {
        // 数据集缺失不能阻断页面：空数据集继续返回成功信封，只在控制台留一线索
        console.warn("[demoAdapter] 数据集加载失败，按空数据集继续：", error);
        dataset = EMPTY_DATASET;
        return dataset;
      });

    return loadPromise;
  }

  return async (config: InternalAxiosRequestConfig) => {
    const current = await loadDataset();
    const method = String(config.method || "get");
    const matched = matchDemoKey(current, method, resolveRequestPath(config));

    if (matched) {
      options.onMatch?.(matched.key);
      return toAxiosResponse(config, SUCCESS_ENVELOPE(matched.data));
    }

    return toAxiosResponse(config, SUCCESS_ENVELOPE(fallbackData));
  };
}
