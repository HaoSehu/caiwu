function normalizeConsoleOrigin() {
  // 演练版三端同站：控制台在 /demo/console/，不跳生产域名
  if (import.meta.env.MODE === "demo") {
    return "/demo/console";
  }

  const configuredOrigin = String(import.meta.env.VITE_CONSOLE_SITE_URL || "")
    .trim()
    .replace(/\/+$/, "");
  if (configuredOrigin) {
    return configuredOrigin;
  }

  const publicSiteOrigin = String(import.meta.env.VITE_PUBLIC_SITE_URL || "")
    .trim()
    .replace(/\/+$/, "");
  if (publicSiteOrigin) {
    try {
      const publicUrl = new URL(publicSiteOrigin);
      if (publicUrl.hostname.startsWith("www.")) {
        publicUrl.hostname = `console.${publicUrl.hostname.slice(4)}`;
        return publicUrl.toString().replace(/\/+$/, "");
      }
    } catch {
      // 忽略非法的公开站点地址，继续走运行时兜底
    }
  }

  if (typeof window === "undefined") {
    return "";
  }

  const { protocol, hostname, port } = window.location;
  if (hostname.startsWith("www.")) {
    const consoleHostname = `console.${hostname.slice(4)}`;
    return `${protocol}//${consoleHostname}${port ? `:${port}` : ""}`;
  }

  if (hostname === "127.0.0.1" || hostname === "localhost") {
    const consolePort = String(
      import.meta.env.VITE_CONSOLE_DEV_PORT || "5173",
    ).trim();
    return `${protocol}//${hostname}${consolePort ? `:${consolePort}` : ""}`;
  }

  if (import.meta.env.DEV) {
    console.warn(
      "[consoleUrl] 无法推断控制台地址，请显式配置 VITE_CONSOLE_SITE_URL。",
    );
  }

  // 最后兜底仍返回空，由调用侧保留 /client/* 路径，避免误跳回官网域名。
  if (publicSiteOrigin) {
    if (import.meta.env.DEV) {
      console.warn(
        "[consoleUrl] 控制台地址推断失败，保留 /client/* 路径，请显式配置 VITE_CONSOLE_SITE_URL。",
      );
    }
  }

  return "";
}

function normalizeConsolePath(path = "/client/dashboard") {
  const normalized = String(path || "/client/dashboard").trim();
  // 只允许站内相对路径：拒绝协议相对（//host）与带 scheme 的绝对 URL，防止开放重定向
  const isSafePath =
    normalized.startsWith("/") &&
    !normalized.startsWith("//") &&
    !/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(normalized);
  return isSafePath ? normalized : "/client/dashboard";
}

function appendQuery(url, query = {}) {
  const entries = Object.entries(query).filter(
    ([, value]) => value !== undefined && value !== null && value !== "",
  );

  if (!entries.length) {
    return url;
  }

  const separator = url.includes("?") ? "&" : "?";
  const search = new URLSearchParams();
  entries.forEach(([key, value]) => {
    search.set(key, String(value));
  });
  return `${url}${separator}${search.toString()}`;
}

export function buildConsoleUrl(path = "/client/dashboard", query = {}) {
  const consolePath = normalizeConsolePath(path);
  const consoleOrigin = normalizeConsoleOrigin();

  if (!consoleOrigin) {
    return appendQuery(consolePath, query);
  }

  try {
    return appendQuery(
      new URL(consolePath, `${consoleOrigin}/`).toString(),
      query,
    );
  } catch {
    return appendQuery(consolePath, query);
  }
}

// 判断「这条路径是不是站内相对的控制台路径」（导航配置里的 link.to 那种）。
// 刻意不经过 normalizeConsolePath：那个函数为了防开放重定向会把绝对 URL 回落成
// "/client/dashboard"，于是对任何绝对 URL 都恒返回 true。调用方既会传站内相对路径，
// 也会传 buildConsoleUrl 的返回值（解析出控制台源站时是绝对 URL），两种都要判准。
export function isConsolePath(path = "") {
  const normalized = String(path || "").trim();
  if (!normalized.startsWith("/") || normalized.startsWith("//")) {
    return false;
  }

  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(normalized)) {
    return false;
  }

  return normalized.startsWith("/client/");
}

export function navigateToConsole(path = "/client/dashboard", query = {}) {
  const targetUrl = buildConsoleUrl(path, query);

  if (typeof window !== "undefined") {
    window.location.assign(targetUrl);
  }

  return targetUrl;
}
