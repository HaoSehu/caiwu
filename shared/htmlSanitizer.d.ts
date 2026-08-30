export interface SanitizeRenderedHtmlOptions {
  imageAltFallback?: string
  /**
   * 受控放行 style 属性（逐条清洗脚本/绑定/固定定位向量，url() 仅 http(s)/data:image）。
   * 默认 false（style 属性一律移除）。用于日志邮件正文预览等需要保留排版的半可信内容。
   */
  allowStyleAttr?: boolean
  /**
   * 受控放行 img src 的 data:image 内嵌图（默认 false）。用于日志邮件预览路径，
   * 与预览壳 CSP 的 img-src data: 对齐。
   */
  allowDataImage?: boolean
}

export function sanitizeRenderedHtml(html?: unknown, options?: SanitizeRenderedHtmlOptions): string
