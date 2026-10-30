import MarkdownIt from 'markdown-it'
import type { RenderRule } from 'markdown-it/lib/renderer.mjs'
import type StateCore from 'markdown-it/lib/rules_core/state_core.mjs'
import { sanitizeRenderedHtml } from '../htmlSanitizer'

export interface MarkdownRendererOptions {
  demoteHeadings?: boolean
  imageAltFallback?: string
}

export interface MarkdownHeading {
  id: string
  label: string
  level: number
}

export interface RenderMarkdownOptions {
  imageAltFallback?: string
  /**
   * 给正文标题写 id 用的前缀（如 `notice-heading-12-`）。留空则不给标题加 id。
   * 目录不再回读 DOM：渲染阶段顺带产出标题清单，见 onHeadings。
   */
  headingIdPrefix?: string
  /** 渲染完成后的标题清单（id / 纯文本 label / 级别），顺序与正文一致 */
  onHeadings?: (headings: MarkdownHeading[]) => void
}

interface RenderEnv extends RenderMarkdownOptions {
  imageAltFallback: string
  headingIdPrefix: string
  headings: MarkdownHeading[]
  headingSeq: number
}

interface InlineToken {
  children?: Array<{ type: string; content: string }> | null
  content: string
}

/** 从 inline token 的 children 取纯文本，避免把 **加粗** / [链接](url) 原样塞进目录 */
function inlinePlainText(token: InlineToken): string {
  const children = token.children
  if (!children || !children.length) {
    return String(token.content || '').trim()
  }

  let text = ''
  for (const child of children) {
    if (child.type === 'text' || child.type === 'code_inline') {
      text += child.content
    } else if (child.type === 'softbreak' || child.type === 'hardbreak') {
      text += ' '
    }
  }

  return text.trim()
}

export function createMarkdownRenderer(options: MarkdownRendererOptions = {}) {
  const markdown = new MarkdownIt({
    html: true,
    linkify: true,
    breaks: true,
  })

  const defaultValidateLink = markdown.validateLink

  markdown.validateLink = (url: string) => {
    const value = String(url || '').trim()
    const normalized = value.replace(/[\u0000-\u001F\u007F\s]+/g, '')

    if (/^(javascript|vbscript|file|data):/i.test(normalized)) {
      return false
    }

    return defaultValidateLink(value)
  }

  if (options.demoteHeadings) {
    markdown.core.ruler.after('block', 'demote_headings', (state: StateCore) => {
      for (const token of state.tokens) {
        if (token.type !== 'heading_open' && token.type !== 'heading_close') {
          continue
        }

        const currentLevel = Number.parseInt(String(token.tag || 'h2').replace(/\D/g, ''), 10) || 2
        const shifted = Math.min(6, Math.max(2, currentLevel + 1))
        token.tag = `h${shifted}`
      }
    })
  }

  // PR-14：标题 id 与目录清单在渲染阶段一次算清。原先由页面在 v-html 注入后
  // querySelectorAll('h1, h2, h3, h4') 回读 DOM、再逐个写 heading.id，长文首帧
  // 要多做一遍全量遍历与属性写入；这里随 token 渲染顺带产出，页面不再碰 DOM。
  const headingOpenRule: RenderRule = (tokens, idx, renderOptions, env, self) => {
    const token = tokens[idx]
    const state = env as RenderEnv | undefined
    const prefix = String(state?.headingIdPrefix || '')

    if (!state || !prefix) {
      return self.renderToken(tokens, idx, renderOptions)
    }

    const label = inlinePlainText((tokens[idx + 1] as InlineToken | undefined) ?? { children: null, content: '' })
    if (!label) {
      return self.renderToken(tokens, idx, renderOptions)
    }

    state.headingSeq += 1
    const id = `${prefix}${state.headingSeq}`
    token.attrSet('id', id)
    state.headings.push({
      id,
      label,
      level: Number.parseInt(String(token.tag || 'h2').replace(/\D/g, ''), 10) || 2,
    })

    return self.renderToken(tokens, idx, renderOptions)
  }
  markdown.renderer.rules.heading_open = headingOpenRule

  const defaultImageRule = markdown.renderer.rules.image
  const imageRule: RenderRule = (tokens, idx, renderOptions, env, self) => {
    const token = tokens[idx]
    const altIndex = token.attrIndex('alt')
    const altFromAttr = altIndex >= 0 ? (token.attrs?.[altIndex]?.[1] || '') : ''
    const altFromChildren = token.children && token.children.length
      ? token.children.map((child) => child.content || '').join('').trim()
      : ''
    const fallback = String((env as RenderMarkdownOptions | undefined)?.imageAltFallback || options.imageAltFallback || 'image').trim() || 'image'
    const resolvedAlt = (altFromAttr || altFromChildren || fallback).trim() || 'image'

    if (altIndex < 0) {
      token.attrPush(['alt', resolvedAlt])
    } else if (token.attrs) {
      token.attrs[altIndex][1] = resolvedAlt
    }

    if (defaultImageRule) {
      return defaultImageRule(tokens, idx, renderOptions, env, self)
    }

    return self.renderToken(tokens, idx, renderOptions)
  }
  markdown.renderer.rules.image = imageRule

  // 链接的 target/rel 由 sanitizeRenderedHtml 统一分流：站内链接（#、/、./、../、相对路径）
  // 去掉 target/rel 保留站内权重，外链才补 target="_blank" + rel="noopener noreferrer nofollow"。
  // 这里不再无条件改写：早期实现把所有链接（含站内锚点与站内页面）都强制新开标签并加 nofollow，
  // 导致正文里的站内跳转既打断阅读又丢失内链权重，且与 sanitizer 的分流逻辑互相覆盖。

  return function renderMarkdown(content = '', renderOptions: RenderMarkdownOptions = {}) {
    const source = String(content || '').trim()

    if (!source) {
      renderOptions.onHeadings?.([])
      return ''
    }

    const env: RenderEnv = {
      imageAltFallback: String(renderOptions.imageAltFallback || options.imageAltFallback || 'image').trim() || 'image',
      headingIdPrefix: String(renderOptions.headingIdPrefix || '').trim(),
      headings: [],
      headingSeq: 0,
    }

    const html = markdown.render(source, env)
    renderOptions.onHeadings?.(env.headings)

    return sanitizeRenderedHtml(html, env)
  }
}
