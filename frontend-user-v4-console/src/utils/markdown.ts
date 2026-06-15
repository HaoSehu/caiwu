import MarkdownIt from 'markdown-it';

const markdown = new MarkdownIt({
  html: true,
  linkify: true,
  breaks: true,
});

const defaultValidateLink = markdown.validateLink;

markdown.validateLink = (url: string) => {
  const value = String(url || '').trim();
  const normalized = value.replace(/[\u0000-\u001F\u007F\s]+/g, '');
  if (/^(javascript|vbscript|file|data):/i.test(normalized)) {
    return false;
  }
  return defaultValidateLink(value);
};

markdown.core.ruler.after('block', 'demote_headings', (state: any) => {
  for (const token of state.tokens) {
    if (token.type !== 'heading_open' && token.type !== 'heading_close') {
      continue;
    }
    const currentLevel = Number.parseInt(String(token.tag || 'h2').replace(/\D/g, ''), 10) || 2;
    const shifted = Math.min(6, Math.max(2, currentLevel + 1));
    token.tag = `h${shifted}`;
  }
});

const defaultImageRule = markdown.renderer.rules.image;
markdown.renderer.rules.image = (tokens: any[], idx: number, renderOptions: any, env: any, self: any) => {
  const token = tokens[idx];
  const altIndex = token.attrIndex('alt');
  const altFromAttr = altIndex >= 0 ? token.attrs?.[altIndex]?.[1] || '' : '';
  const altFromChildren = token.children?.length
    ? token.children.map((child: any) => child.content || '').join('').trim()
    : '';
  const fallback = String(env?.imageAltFallback || 'image').trim() || 'image';
  const resolvedAlt = (altFromAttr || altFromChildren || fallback).trim() || 'image';

  if (altIndex < 0) {
    token.attrPush(['alt', resolvedAlt]);
  } else if (token.attrs) {
    token.attrs[altIndex][1] = resolvedAlt;
  }

  return defaultImageRule ? defaultImageRule(tokens, idx, renderOptions, env, self) : self.renderToken(tokens, idx, renderOptions);
};

const defaultLinkOpenRule = markdown.renderer.rules.link_open;
markdown.renderer.rules.link_open = (tokens: any[], idx: number, renderOptions: any, env: any, self: any) => {
  const token = tokens[idx];
  const targetIndex = token.attrIndex('target');
  if (targetIndex < 0) {
    token.attrPush(['target', '_blank']);
  } else if (token.attrs) {
    token.attrs[targetIndex][1] = '_blank';
  }

  const relIndex = token.attrIndex('rel');
  if (relIndex < 0) {
    token.attrPush(['rel', 'noopener noreferrer nofollow']);
  } else if (token.attrs) {
    token.attrs[relIndex][1] = 'noopener noreferrer nofollow';
  }

  return defaultLinkOpenRule
    ? defaultLinkOpenRule(tokens, idx, renderOptions, env, self)
    : self.renderToken(tokens, idx, renderOptions);
};

function sanitizeRenderedHtml(html: string, imageAltFallback: string) {
  if (!html || typeof DOMParser === 'undefined' || typeof Node === 'undefined') {
    return html;
  }

  const allowedTags = new Set([
    'a',
    'b',
    'blockquote',
    'br',
    'code',
    'del',
    'div',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'i',
    'img',
    'li',
    'ol',
    'p',
    'pre',
    's',
    'span',
    'strong',
    'sub',
    'sup',
    'table',
    'tbody',
    'td',
    'th',
    'thead',
    'tr',
    'u',
    'ul',
  ]);
  const dropTags = new Set(['base', 'embed', 'form', 'iframe', 'link', 'meta', 'object', 'script', 'style', 'svg']);
  const allowedAttrs = new Set(['alt', 'class', 'colspan', 'height', 'href', 'id', 'rel', 'rowspan', 'src', 'target', 'title', 'width']);
  const uriAttrs = new Set(['href', 'src']);

  function isSafeUrl(value: string, tagName: string, attrName: string) {
    const url = String(value || '').trim().replace(/[\u0000-\u001F\u007F\s]+/g, '');
    if (!url || /^(javascript|vbscript|file|data):/i.test(url)) return false;
    if (url.startsWith('#') || url.startsWith('/') || url.startsWith('./') || url.startsWith('../')) return true;
    try {
      const parsed = new URL(url, window.location.origin);
      const protocols = tagName === 'img' || attrName === 'src' ? new Set(['http:', 'https:']) : new Set(['http:', 'https:', 'mailto:', 'tel:']);
      return protocols.has(parsed.protocol);
    } catch {
      return false;
    }
  }

  function walk(node: Node) {
    for (const child of Array.from(node.childNodes)) {
      if (child.nodeType !== Node.ELEMENT_NODE) {
        if (child.nodeType !== Node.TEXT_NODE) child.remove();
        continue;
      }

      const element = child as Element;
      const tagName = element.tagName.toLowerCase();
      if (dropTags.has(tagName)) {
        element.remove();
        continue;
      }
      if (!allowedTags.has(tagName)) {
        walk(element);
        element.replaceWith(...Array.from(element.childNodes));
        continue;
      }

      for (const attr of Array.from(element.attributes)) {
        const attrName = attr.name.toLowerCase();
        if (attrName.startsWith('on') || !allowedAttrs.has(attrName)) {
          element.removeAttribute(attr.name);
          continue;
        }
        if (uriAttrs.has(attrName) && !isSafeUrl(attr.value, tagName, attrName)) {
          element.removeAttribute(attr.name);
          continue;
        }
        if ((attrName === 'width' || attrName === 'height' || attrName === 'colspan' || attrName === 'rowspan') && !/^\d{1,4}$/.test(attr.value)) {
          element.removeAttribute(attr.name);
        }
      }

      if (tagName === 'a') {
        element.setAttribute('target', '_blank');
        element.setAttribute('rel', 'noopener noreferrer nofollow');
      }
      if (tagName === 'img' && !element.getAttribute('alt')) {
        element.setAttribute('alt', imageAltFallback);
      }
      walk(element);
    }
  }

  const document = new DOMParser().parseFromString(html, 'text/html');
  walk(document.body);
  return document.body.innerHTML;
}

export function renderMarkdown(content = '', options: { imageAltFallback?: string } = {}) {
  const source = String(content || '').trim();
  if (!source) return '';
  const imageAltFallback = String(options.imageAltFallback || 'image').trim() || 'image';
  return sanitizeRenderedHtml(markdown.render(source, { imageAltFallback }), imageAltFallback);
}
