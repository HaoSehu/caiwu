const ALLOWED_TAGS = new Set([
  "a",
  "b",
  "blockquote",
  "br",
  "code",
  "del",
  "div",
  "em",
  "h1",
  "h2",
  "h3",
  "h4",
  "h5",
  "h6",
  "hr",
  "i",
  "img",
  "li",
  "ol",
  "p",
  "pre",
  "s",
  "span",
  "strong",
  "sub",
  "sup",
  "table",
  "tbody",
  "td",
  "th",
  "thead",
  "tr",
  "u",
  "ul",
]);

const DROP_TAGS = new Set([
  "base",
  "embed",
  "form",
  "iframe",
  "link",
  "meta",
  "object",
  "script",
  "style",
  "svg",
]);

const ALLOWED_ATTRS = new Set([
  "alt",
  "class",
  "colspan",
  "decoding",
  "height",
  "href",
  "id",
  "loading",
  "rel",
  "referrerpolicy",
  "rowspan",
  "src",
  "target",
  "title",
  "width",
]);

const URI_ATTRS = new Set(["href", "src"]);
const URL_PROTOCOLS = new Set(["http:", "https:", "mailto:", "tel:"]);
const IMAGE_PROTOCOLS = new Set(["http:", "https:"]);

export interface SanitizeRenderedHtmlOptions {
  imageAltFallback?: string;
  /**
   * 受控放行 style 属性（逐条清洗脚本/绑定/固定定位向量，url() 仅 http(s)/data:image）。
   * 默认 false（style 属性一律移除）。用于日志邮件正文预览等需要保留排版的半可信内容。
   */
  allowStyleAttr?: boolean;
  /**
   * 受控放行 img src 的 data:image 内嵌图（默认 false）。用于日志邮件预览路径，
   * 与预览壳 CSP 的 img-src data: 对齐。
   */
  allowDataImage?: boolean;
}

// 站内链接判定：锚点、以 / 开头（排除 // 协议相对）、./、../、无协议相对路径。
// 站内链接保留权重（不加 nofollow、不强制新开标签），外链才收紧 rel。
function isInternalHref(value: unknown): boolean {
  const url = String(value || "")
    .trim()
    .replace(/[\u0000-\u001F\u007F\s]+/g, "");
  if (!url) return false;
  if (url.startsWith("#")) return true;
  if (url.startsWith("/") && !url.startsWith("//")) return true;
  if (url.startsWith("./") || url.startsWith("../")) return true;
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(url)) return false;
  return true;
}

function normalizedUrl(value: unknown): string {
  return String(value || "")
    .trim()
    .replace(/[\u0000-\u001F\u007F\s]+/g, "");
}

function isSafeUrl(value: unknown, tagName: string, attrName: string): boolean {
  const url = normalizedUrl(value);

  if (!url) return false;
  if (/^(javascript|vbscript|file|data):/i.test(url)) return false;
  if (
    url.startsWith("#") ||
    url.startsWith("/") ||
    url.startsWith("./") ||
    url.startsWith("../")
  )
    return true;

  try {
    const parsed = new URL(
      url,
      typeof window !== "undefined"
        ? window.location.origin
        : "https://example.invalid",
    );
    const allowedProtocols =
      tagName === "img" || attrName === "src" ? IMAGE_PROTOCOLS : URL_PROTOCOLS;

    return allowedProtocols.has(parsed.protocol);
  } catch {
    return false;
  }
}

// style 属性受控放行（仅 options.allowStyleAttr 时启用）：
// 任一声明含脚本/绑定/固定定位向量，或 url() 指向非 http(s)/data:image，
// 即整条丢弃（style 值内 url() 可含分号，逐声明拆分不可靠），宁可损失排版不留风险。
// CSS 允许用反斜杠转义书写 at-规则与属性名（如 @\69 mport、position:\66 ixed），
// 字面量正则会被这类写法绕过。这里把转义序列解码成等价文本后再做检测，
// 返回值仍是原始样式串，不改变邮件排版。
function decodeCssEscapes(value: unknown): string {
  return String(value || "")
    .replace(/\\([0-9a-fA-F]{1,6})[ \t\r\n\f]?/g, (_match, hex: string) => {
      const code = parseInt(hex, 16);
      return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : "";
    })
    .replace(/\\(.)/g, "$1");
}

function sanitizeStyleValue(value: unknown): string {
  // 浏览器把 CSS 注释当空白，先剥离再检测，防 expression/*x*/( 等拼接变体绕过
  const raw = String(value || "").replace(/\/\*[\s\S]*?\*\//g, "");
  // 检测基于解码后的等价文本，返回仍用原始串
  const probe = decodeCssEscapes(raw);
  if (
    /expression\s*\(|behaviou?r\s*:|-moz-binding|javascript\s*:|@import|position\s*:\s*fixed/i.test(
      probe,
    )
  ) {
    return "";
  }

  const urlMatches = probe.matchAll(/url\(\s*(['"]?)([^'")]+)\1\s*\)/gi);
  for (const match of urlMatches) {
    if (!/^(https?:|data:image\/)/i.test(match[2].trim())) {
      return "";
    }
  }

  return raw.trim();
}

function cleanAttribute(
  element: Element,
  attr: Attr,
  options: SanitizeRenderedHtmlOptions,
): void {
  const tagName = element.tagName.toLowerCase();
  const attrName = attr.name.toLowerCase();

  if (attrName === "style" && options.allowStyleAttr) {
    const cleanedStyle = sanitizeStyleValue(attr.value);
    if (cleanedStyle) {
      element.setAttribute("style", cleanedStyle);
    } else {
      element.removeAttribute(attr.name);
    }
    return;
  }

  // 仅 options.allowDataImage 时放行 img 的 data:image 内嵌图（日志邮件预览路径，与 CSP img-src data: 对齐）
  if (
    tagName === "img" &&
    attrName === "src" &&
    options.allowDataImage &&
    /^data:image\//i.test(String(attr.value).trim())
  ) {
    return;
  }

  if (attrName.startsWith("on") || !ALLOWED_ATTRS.has(attrName)) {
    element.removeAttribute(attr.name);
    return;
  }

  if (URI_ATTRS.has(attrName) && !isSafeUrl(attr.value, tagName, attrName)) {
    element.removeAttribute(attr.name);
    return;
  }

  if (
    (attrName === "width" ||
      attrName === "height" ||
      attrName === "colspan" ||
      attrName === "rowspan") &&
    !/^\d{1,4}$/.test(String(attr.value))
  ) {
    element.removeAttribute(attr.name);
    return;
  }

  if (
    attrName === "loading" &&
    !["lazy", "eager"].includes(String(attr.value).toLowerCase())
  ) {
    element.removeAttribute(attr.name);
    return;
  }

  if (
    attrName === "decoding" &&
    !["async", "sync", "auto"].includes(String(attr.value).toLowerCase())
  ) {
    element.removeAttribute(attr.name);
    return;
  }

  if (
    attrName === "referrerpolicy" &&
    ![
      "no-referrer",
      "same-origin",
      "strict-origin",
      "strict-origin-when-cross-origin",
    ].includes(String(attr.value).toLowerCase())
  ) {
    element.removeAttribute(attr.name);
  }
}

function sanitizeElement(
  element: Element,
  imageAltFallback: string,
  options: SanitizeRenderedHtmlOptions,
): void {
  const tagName = element.tagName.toLowerCase();

  if (DROP_TAGS.has(tagName)) {
    element.remove();
    return;
  }

  if (!ALLOWED_TAGS.has(tagName)) {
    walk(element, imageAltFallback, options);
    element.replaceWith(...Array.from(element.childNodes));
    return;
  }

  for (const attr of Array.from(element.attributes)) {
    cleanAttribute(element, attr, options);
  }

  if (tagName === "a") {
    if (isInternalHref(element.getAttribute("href"))) {
      // 站内链接：不新开标签、保留链接权重（不加 nofollow）
      element.removeAttribute("target");
      element.removeAttribute("rel");
    } else {
      element.setAttribute("target", "_blank");
      element.setAttribute("rel", "noopener noreferrer nofollow");
    }
  }

  if (tagName === "img" && !element.getAttribute("alt")) {
    element.setAttribute("alt", imageAltFallback);
  }

  if (tagName === "img") {
    element.setAttribute("loading", "lazy");
    element.setAttribute("decoding", "async");
    element.setAttribute("referrerpolicy", "no-referrer");
  }
}

function walk(
  node: Node,
  imageAltFallback: string,
  options: SanitizeRenderedHtmlOptions,
): void {
  for (const child of Array.from(node.childNodes)) {
    if (child.nodeType === Node.ELEMENT_NODE) {
      sanitizeElement(child as Element, imageAltFallback, options);
      if (child.parentNode) {
        walk(child, imageAltFallback, options);
      }
    } else if (child.nodeType !== Node.TEXT_NODE) {
      child.remove();
    }
  }
}

/**
 * HTML 文本转义：用于把不可信文本拼进 HTML 字符串（导出、预览壳等）。
 * 与 sanitizeRenderedHtml 的无 DOM 兜底路径保持同一套替换规则。
 */
export function escapeHtml(value: unknown): string {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function sanitizeRenderedHtml(
  html?: unknown,
  options: SanitizeRenderedHtmlOptions = {},
): string {
  const source = String(html || "");

  if (
    !source ||
    typeof DOMParser === "undefined" ||
    typeof Node === "undefined"
  ) {
    return escapeHtml(source);
  }

  const imageAltFallback =
    String(options.imageAltFallback || "image").trim() || "image";
  const document = new DOMParser().parseFromString(source, "text/html");

  walk(document.body, imageAltFallback, options);

  return document.body.innerHTML;
}
