/**
 * 把开发/构建期真实的 API 源站补进 index.html 的 CSP 指令。
 *
 * 背景：三端 index.html 的 CSP 是静态写法，资源类指令统一用 `https:` 兜底
 * （`connect-src 'self' https:`、`img-src 'self' data: blob: https:` 等），只覆盖同源与 https 源站。
 * 开发态 `VITE_API_BASE_URL` 指向 `http://127.0.0.1:8000/api`（http + 跨源），API 请求与它返回的
 * 图片/视频都会被 CSP 直接拦截，表现为登录/列表报 "Network Error"、logo 与首屏视频空白。
 * 生产态 API 是 https，本就被 `https:` 覆盖，因此本插件只在源站协议为 http 时补一条精确 origin，
 * 避免把开发地址写进化产物、也不放宽到 `http:` 通配。
 * 相对基址（如 `/api`）经代理后与页面同源，`'self'` 已覆盖，默认跳过；
 * 但后端返回的媒体地址是**绝对 URL**（如 `http://127.0.0.1:8000/media/logo.svg`），
 * 这类资源不由代理转发、也不在 `'self'` 覆盖范围内，需用 `VITE_CSP_API_ORIGIN` 精确声明。
 *
 * script-src 与资源类指令不同：它没有 `https:` 兜底（放开 `https:` 等于允许任意第三方脚本），
 * 而控制台会从 API 源站加载后端代理的极验 gt4.js（/api/v2/client/auth/captcha-script）。
 * 四端是独立域名、浏览器直连 API，该脚本天然跨源，必须把 API 源站精确写进 script-src；
 * 因此需要调用方显式传 `scriptDirective: "script-src"`，http/https 源站都要补。
 *
 * @param {Record<string, string>} env loadEnv() 的结果
 * @param {{ placeholder?: string, directives?: string[], scriptDirective?: string }} [options]
 * @returns {import('vite').Plugin}
 */
const DEFAULT_DIRECTIVES = ["connect-src", "img-src", "media-src"];

export function createApiConnectSrcPlugin(env, options = {}) {
  const placeholder = options.placeholder ?? "%VITE_API_BASE_URL%";
  const directives = options.directives ?? DEFAULT_DIRECTIVES;
  const scriptDirective = String(options.scriptDirective || "").trim();

  return {
    name: "shared-api-connect-src",
    transformIndexHtml: {
      order: "post",
      handler(html) {
        const raw = String(env.VITE_API_BASE_URL || "").trim();
        const extra = String(env.VITE_CSP_API_ORIGIN || "").trim();
        if ((!raw || raw === placeholder) && !extra) {
          return html;
        }

        // 相对基址（如 e2e 的 `/api`）由 preview/dev 代理转发到后端，请求与页面同源，
        // CSP 的 'self' 已经覆盖，不需要按基址注入 origin。继续往下走会让
        // new URL("/api", "http://localhost") 推断出 http://localhost 并写进指令，
        // 等于把 img/media/connect 放行到一个与后端无关的主机（e2e 首页曾因此长期报 CSP 违规）。
        const isRelativeBase = /^[a-z][a-z0-9+.-]*:/i.test(raw) === false && !raw.startsWith("//");

        let origin = "";
        if (isRelativeBase) {
          // 相对基址下唯一可能被拦的是后端直接返回的绝对媒体 URL，只能由调用方显式给出源站。
          // 未配置 VITE_CSP_API_ORIGIN 时保持原策略不动。
          if (!extra) {
            return html;
          }
          try {
            origin = new URL(extra).origin;
          } catch {
            return html;
          }
        } else {
          try {
            origin = new URL(raw, "http://localhost").origin;
          } catch {
            return html;
          }
        }

        // 生产 https 源站已被 CSP 里的 `https:` 覆盖，资源类指令无需注入；
        // script-src 没有该兜底，即使源站是 https 也要精确补一条。
        const originIsHttp = origin.startsWith("http://");
        if (!originIsHttp && !scriptDirective) {
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

        let patched = policy;
        if (originIsHttp) {
          for (const directive of directives) {
            if (!patched.includes(directive)) {
              continue;
            }
            patched = patched.replace(
              new RegExp(`(^|;\\s*)${directive}([^;]*)`),
              (match, lead, targets) => `${lead}${directive}${targets} ${origin}`,
            );
          }
        }

        if (scriptDirective && patched.includes(scriptDirective)) {
          const scriptPattern = new RegExp(`(^|;\\s*)${scriptDirective}([^;]*)`);
          patched = patched.replace(scriptPattern, (match, lead, targets) =>
            targets.split(/\s+/).includes(origin) ? match : `${lead}${scriptDirective}${targets} ${origin}`,
          );
        }

        if (patched === policy) {
          return html;
        }

        return html.slice(0, valueStart) + patched + html.slice(valueEnd);
      },
    },
  };
}
