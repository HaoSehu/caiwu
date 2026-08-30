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

// 站内链接判定：锚点、以 / 开头（排除 // 协议相对）、./、../、无协议相对路径。
// 站内链接保留权重（不加 nofollow、不强制新开标签），外链才收紧 rel。
function isInternalHref(value) {
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

function normalizedUrl(value) {
  return String(value || "")
    .trim()
    .replace(/[\u0000-\u001F\u007F\s]+/g, "");
}

function isSafeUrl(value, tagName, attrName) {
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
function sanitizeStyleValue(value) {
  // 浏览器把 CSS 注释当空白，先剥离再检测，防 expression/*x*/( 等拼接变体绕过
  const raw = String(value || "").replace(/\/\*[\s\S]*?\*\//g, "");
  if (
    /expression\s*\(|behaviou?r\s*:|-moz-binding|javascript\s*:|@import|position\s*:\s*fixed/i.test(
      raw,
    )
  ) {
    return "";
  }

  const urlMatches = raw.matchAll(/url\(\s*(['"]?)([^'")]+)\1\s*\)/gi);
  for (const match of urlMatches) {
    if (!/^(https?:|data:image\/)/i.test(match[2].trim())) {
      return "";
    }
  }

  return raw.trim();
}

function cleanAttribute(element, attr, options) {
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

function sanitizeElement(element, imageAltFallback, options) {
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

function walk(node, imageAltFallback, options) {
  for (const child of Array.from(node.childNodes)) {
    if (child.nodeType === Node.ELEMENT_NODE) {
      sanitizeElement(child, imageAltFallback, options);
      if (child.parentNode) {
        walk(child, imageAltFallback, options);
      }
    } else if (child.nodeType !== Node.TEXT_NODE) {
      child.remove();
    }
  }
}

export function sanitizeRenderedHtml(html, options = {}) {
  const source = String(html || "");

  if (
    !source ||
    typeof DOMParser === "undefined" ||
    typeof Node === "undefined"
  ) {
    return source
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  const imageAltFallback =
    String(options.imageAltFallback || "image").trim() || "image";
  const document = new DOMParser().parseFromString(source, "text/html");

  walk(document.body, imageAltFallback, options);

  return document.body.innerHTML;
}
