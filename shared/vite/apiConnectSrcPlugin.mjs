/**
 * 把开发/构建期真实的 API 源站补进 index.html 的 CSP 指令。
 *
 * 背景：三端 index.html 的 CSP 是静态写法，资源类指令统一用 `https:` 兜底
 * （`connect-src 'self' https:`、`img-src 'self' data: blob: https:` 等），只覆盖同源与 https 源站。
 * 开发态 `VITE_API_BASE_URL` 指向 `http://127.0.0.1:8000/api`（http + 跨源），API 请求与它返回的
 * 图片/视频都会被 CSP 直接拦截，表现为登录/列表报 "Network Error"、logo 与首屏视频空白。
 * 生产态 API 是 https，本就被 `https:` 覆盖，因此本插件只在源站协议为 http 时补一条精确 origin，
 * 避免把开发地址写进化产物、也不放宽到 `http:` 通配。
 *
 * @param {Record<string, string>} env loadEnv() 的结果
 * @param {{ placeholder?: string, directives?: string[] }} [options]
 * @returns {import('vite').Plugin}
 */
const DEFAULT_DIRECTIVES = ["connect-src", "img-src", "media-src"];

export function createApiConnectSrcPlugin(env, options = {}) {
  const placeholder = options.placeholder ?? "%VITE_API_BASE_URL%";
  const directives = options.directives ?? DEFAULT_DIRECTIVES;

  return {
    name: "shared-api-connect-src",
    transformIndexHtml: {
      order: "post",
      handler(html) {
        const raw = String(env.VITE_API_BASE_URL || "").trim();
        if (!raw || raw === placeholder) {
          return html;
        }

        let origin;
        try {
          origin = new URL(raw, "http://localhost").origin;
        } catch {
          return html;
        }

        // 生产 https 源站已被 CSP 里的 `https:` 覆盖，无需注入。
        if (!origin.startsWith("http://")) {
          return html;
        }

        const marker = 'http-equiv="Content-Security-Policy"';
        const metaIndex = html.indexOf(marker);
        if (metaIndex === -1) {
          return html;
        }

        const contentStart = html.indexOf('content="', metaIndex);
        if (contentStart === -1) {
          return html;
        }
        const valueStart = contentStart + 'content="'.length;
        const valueEnd = html.indexOf('"', valueStart);
        if (valueEnd === -1) {
          return html;
        }

        const policy = html.slice(valueStart, valueEnd);
        if (policy.includes(origin)) {
          return html;
        }

        let patched = policy;
        for (const directive of directives) {
          if (!patched.includes(directive)) {
            continue;
          }
          patched = patched.replace(
            new RegExp(`(^|;\\s*)${directive}([^;]*)`),
            (match, lead, targets) => `${lead}${directive}${targets} ${origin}`,
          );
        }

        return html.slice(0, valueStart) + patched + html.slice(valueEnd);
      },
    },
  };
}
