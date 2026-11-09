/**
 * 演示（demo）模式的 axios adapter。
 *
 * 演练版不连后端：所有请求由这里从一份构建期/发布期生成的静态数据集响应，
 * 键为 "<METHOD> <path>"（如 "GET /v2/admin/orders"），支持单段通配 "*"。
 *
 * 约束：
 * - 读请求（GET/HEAD 等）命中返回成功信封；未命中返回 fallbackData（默认 {}）的
 *   同一成功信封 —— 三端拦截器只在 code === 0 时放行，读接口返回错误会让页面
 *   连环弹错并被踢回登录页。
 * - 写请求（POST/PUT/PATCH/DELETE）只在数据集显式登记该端点时才返回成功载荷；
 *   未登记一律返回失败信封（code 42200，「演示环境不可执行该操作」），避免
 *   「演示里点退款/充值显示成功」这类误导（M-49）。数据集是只读静态文件，
 *   写操作即便返回成功也不会真正落库。
 * - 提供 dataUrl 时首个请求触发一次同源 fetch；加载失败只在 console.warn 记录，
 *   随后按空数据集继续响应，读接口不白屏，写接口按上面规则统一拒绝。
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
  /**
   * 关键接口的兜底载荷：命中失败（数据集未就绪/缺键）时也返回它。
   * 用于权限这类「缺数据会把用户踢到错误页」的接口。
   */
  requiredFallbacks?: Record<string, unknown>;
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
 * 数据集键归一：导出清单历史上允许 `METHOD path?query|{json body}` 写法（导出请求用），
 * 但旧导出脚本把原始键原样写进数据集，`buildDemoKey` 只取 pathname，带后缀的键
 * 永远匹配不上（M-10）。这里对键做同样的归一，旧数据集与未来带后缀的导出都能命中。
 */
export function normalizeDemoDatasetKey(key: string): string {
  const withoutBody = String(key || "").split("|")[0];
  const spaceIndex = withoutBody.indexOf(" ");
  if (spaceIndex <= 0) {
    return buildDemoKey("get", withoutBody);
  }

  return buildDemoKey(withoutBody.slice(0, spaceIndex), withoutBody.slice(spaceIndex + 1));
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
  // 键先归一（去 query/body 后缀），通配符只保留在路径段上
  const escaped = normalizeDemoDatasetKey(key)
    .split("*")
    .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
    .join("[^/]+");
  return new RegExp(`^${escaped}$`);
}

/**
 * 在数据集中查找请求。先精确匹配，再按同方法通配键的字典序取首个命中。
 * 返回命中的键与其 data 载荷（data 原样返回，不做拷贝）。
 * 数据集键按 normalizeDemoDatasetKey 归一后再比较，兼容历史导出带 ?query/|body 后缀的键。
 */
export function matchDemoKey(
  dataset: DemoDataset,
  method: string,
  url: string,
): { key: string; data: unknown } | null {
  const endpoints = dataset?.endpoints ?? EMPTY_DATASET.endpoints;
  const requestKey = buildDemoKey(method, url);

  const exact = findNormalizedKey(endpoints, requestKey);
  if (exact !== null) {
    return { key: exact, data: endpoints[exact] };
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

/** 在原始键里找归一后等于 targetKey 的键（精确匹配优先于通配）。 */
function findNormalizedKey(
  endpoints: Record<string, unknown>,
  targetKey: string,
): string | null {
  if (Object.prototype.hasOwnProperty.call(endpoints, targetKey)) {
    return targetKey;
  }

  for (const key of Object.keys(endpoints)) {
    if (!key.includes("*") && normalizeDemoDatasetKey(key) === targetKey) {
      return key;
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

/** 演示环境写请求的统一拒绝信封：与后端 BusinessException 同结构，三端拦截器按业务错误提示。 */
const DEMO_WRITE_REJECTED_ENVELOPE: {
  code: number;
  message: string;
  data: null;
} = {
  code: 42200,
  message: "演示环境不可执行该操作",
  data: null,
};

const WRITE_METHODS = new Set(["post", "put", "patch", "delete"]);

/**
 * 演示环境允许「无副作用成功」的认证写请求：退出登录只清本地会话，
 * 服务端没有可写入的状态。若按未登记写请求统一拒绝，演示包点退出会弹
 * 「演示环境不可执行该操作」的误导错误（本地仍会退出）。
 */
const DEMO_NOOP_WRITE_KEYS = new Set([
  "POST /v2/client/auth/logout",
  "POST /v2/admin/auth/logout",
]);

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
    const requestPath = resolveRequestPath(config);
    const matched = matchDemoKey(current, method, requestPath);

    if (matched) {
      options.onMatch?.(matched.key);
      // 命中但载荷为空（例如 auth/info 返回 {}）时同样走关键接口兜底，
      // 否则空权限会把管理端整站踢到 403。
      const isEmptyPayload =
        matched.data === null ||
        matched.data === undefined ||
        (typeof matched.data === "object" &&
          !Array.isArray(matched.data) &&
          Object.keys(matched.data as Record<string, unknown>).length === 0);
      if (isEmptyPayload) {
        const fallback = options.requiredFallbacks?.[matched.key];
        if (fallback !== undefined) {
          return toAxiosResponse(config, SUCCESS_ENVELOPE(fallback));
        }
      }
      return toAxiosResponse(config, SUCCESS_ENVELOPE(matched.data));
    }

    const requiredFallback = options.requiredFallbacks?.[
      buildDemoKey(method, requestPath)
    ];
    if (requiredFallback !== undefined) {
      return toAxiosResponse(config, SUCCESS_ENVELOPE(requiredFallback));
    }

    // 未登记的写请求一律拒绝：演示数据集是只读静态文件，返回成功只会让
    // 「退款/充值/审批」在演示里显示成功，误导体验者（M-49）。
    if (WRITE_METHODS.has(method.toLowerCase())) {
      if (DEMO_NOOP_WRITE_KEYS.has(buildDemoKey(method, requestPath))) {
        return toAxiosResponse(config, SUCCESS_ENVELOPE(null));
      }
      return toAxiosResponse(config, DEMO_WRITE_REJECTED_ENVELOPE);
    }

    return toAxiosResponse(config, SUCCESS_ENVELOPE(fallbackData));
  };
}
