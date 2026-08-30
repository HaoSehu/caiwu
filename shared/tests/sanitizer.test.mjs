/**
 * HTML Sanitizer 安全回归测试
 * 运行: node --experimental-strip-types ./tests/sanitizer.test.mjs
 */
import assert from 'node:assert/strict'
import { JSDOM } from 'jsdom'

// 注入 JSDOM 的 DOMParser 和 Node
const dom = new JSDOM('<!DOCTYPE html>')
globalThis.DOMParser = dom.window.DOMParser
globalThis.Node = dom.window.Node

// 动态导入 sanitizer（依赖 DOMParser 全局变量）
const { sanitizeRenderedHtml } = await import('../htmlSanitizer.js')

// 1. script 标签应被移除
{
  const result = sanitizeRenderedHtml('<p>Hello</p><script>alert("xss")</script>')
  assert.equal(result.includes('<script>'), false, 'script 标签应被移除')
  assert.equal(result.includes('alert'), false, 'script 内容应被移除')
}

// 2. iframe 应被移除
{
  const result = sanitizeRenderedHtml('<p>Text</p><iframe src="evil.com"></iframe>')
  assert.equal(result.includes('<iframe'), false, 'iframe 应被移除')
  assert.equal(result.includes('evil.com'), false, 'iframe src 应被移除')
}

// 3. style 标签应被移除
{
  const result = sanitizeRenderedHtml('<style>body { color: red; }</style><p>OK</p>')
  assert.equal(result.includes('<style>'), false, 'style 标签应被移除')
}

// 4. on* 事件属性应被移除
{
  const result = sanitizeRenderedHtml('<p onclick="alert(1)">Click me</p>')
  assert.equal(result.includes('onclick'), false, 'onclick 事件属性应被移除')
}

{
  const result = sanitizeRenderedHtml('<div onmouseover="evil()">Hover</div>')
  assert.equal(result.includes('onmouseover'), false, 'onmouseover 事件属性应被移除')
}

// 5. javascript: 协议应被移除
{
  const result = sanitizeRenderedHtml('<a href="javascript:alert(1)">Link</a>')
  assert.equal(result.includes('javascript:'), false, 'javascript: 协议应被移除')
}

// 6. data: 协议应被移除
{
  const result = sanitizeRenderedHtml('<a href="data:text/html,<script>alert(1)</script>">Link</a>')
  assert.equal(result.includes('data:'), false, 'data: 协议应被移除')
}

// 7. 合法标签应保留
{
  const result = sanitizeRenderedHtml('<p><strong>Bold</strong> text</p>')
  assert.ok(result.includes('<p>'), '正常 p 标签应保留')
  assert.ok(result.includes('<strong>'), '正常 strong 标签应保留')
}

// 8. 合法 a 标签应保留并添加 target/rel
{
  const result = sanitizeRenderedHtml('<a href="https://example.com">Link</a>')
  assert.ok(result.includes('https://example.com'), '合法 href 应保留')
  assert.ok(result.includes('target="_blank"'), '应添加 target="_blank"')
  assert.ok(result.includes('rel="noopener'), '应添加 rel 属性')
}

// 9. 合法 img 标签应保留
{
  const result = sanitizeRenderedHtml('<img src="https://example.com/img.png" alt="test">')
  assert.ok(result.includes('https://example.com/img.png'), '合法 img src 应保留')
  assert.ok(result.includes('loading="lazy"'), 'img 应默认懒加载')
  assert.ok(result.includes('decoding="async"'), 'img 应默认异步解码')
  assert.ok(result.includes('referrerpolicy="no-referrer"'), 'img 应默认隐藏来源页')
}

// 10. 无 alt 的 img 应添加默认 alt
{
  const result = sanitizeRenderedHtml('<img src="https://example.com/img.png">')
  assert.ok(result.includes('alt="image"'), '无 alt 的 img 应添加默认 alt')
}

// 11. 空输入不应报错
{
  assert.equal(sanitizeRenderedHtml(''), '', '空输入应返回空字符串')
  assert.equal(sanitizeRenderedHtml(null), '', 'null 输入应返回空字符串')
}

// 12. 无 DOMParser 时返回原始输入
{
  const backup = globalThis.DOMParser
  globalThis.DOMParser = undefined
  const result = sanitizeRenderedHtml('<p>Raw</p>')
  assert.equal(result, '&lt;p&gt;Raw&lt;/p&gt;', '无 DOMParser 时应转义 HTML')
  globalThis.DOMParser = backup
}

// 13. 嵌套 svg 应被移除
{
  const result = sanitizeRenderedHtml('<svg><circle /></svg>')
  assert.equal(result.includes('<svg'), false, 'svg 应被移除')
}

// 14. form 元素应被移除
{
  const result = sanitizeRenderedHtml('<form action="evil"><input name="pwd"></form><p>OK</p>')
  assert.equal(result.includes('<form'), false, 'form 应被移除')
  assert.equal(result.includes('<input'), false, 'input 应被移除')
}

// 15. embed/object 应被移除
{
  const result = sanitizeRenderedHtml('<embed src="evil.swf"><object data="evil"></object>')
  assert.equal(result.includes('<embed'), false, 'embed 应被移除')
  assert.equal(result.includes('<object'), false, 'object 应被移除')
}

// 16. 合法 markdown 渲染结果应保留
{
  const mdContent = '<h1>Title</h1><p>Some <strong>bold</strong> text with <a href="https://example.com">link</a>.</p><ul><li>Item 1</li><li>Item 2</li></ul>'
  const result = sanitizeRenderedHtml(mdContent)
  assert.ok(result.includes('<h1>'), 'h1 应保留')
  assert.ok(result.includes('<ul>'), 'ul 应保留')
  assert.ok(result.includes('<li>'), 'li 应保留')
}

// 17. 表格标签应保留
{
  const result = sanitizeRenderedHtml('<table><thead><tr><th>Col</th></tr></thead><tbody><tr><td>Val</td></tr></tbody></table>')
  assert.ok(result.includes('<table>'), 'table 应保留')
  assert.ok(result.includes('<th>'), 'th 应保留')
  assert.ok(result.includes('<td>'), 'td 应保留')
}

// 18. colSpan/rowSpan 合法值应保留
{
  const result = sanitizeRenderedHtml('<table><tr><td colspan="2" rowspan="3">Cell</td></tr></table>')
  assert.ok(result.includes('colspan="2"'), '合法 colspan 应保留')
  assert.ok(result.includes('rowspan="3"'), '合法 rowspan 应保留')
}

// 19. 相对路径和锚点链接应保留
{
  const result = sanitizeRenderedHtml('<a href="/page">Page</a><a href="#section">Section</a>')
  assert.ok(result.includes('/page'), '相对路径应保留')
  assert.ok(result.includes('#section'), '锚点应保留')
}

// 20. 默认不开启 allowStyleAttr 时 style 属性应被移除
{
  const result = sanitizeRenderedHtml('<p style="color: red">Text</p>')
  assert.equal(result.includes('style='), false, '默认应移除 style 属性')
}

// 21. allowStyleAttr 开启时应保留排版声明
{
  const result = sanitizeRenderedHtml('<p style="color: red; font-size: 14px">Text</p>', { allowStyleAttr: true })
  assert.ok(result.includes('color: red'), '合法排版声明应保留')
  assert.ok(result.includes('font-size: 14px'), '合法字号声明应保留')
}

// 22. allowStyleAttr 下 expression/behavior/javascript 向量应整条丢弃（宁可损失排版不留风险）
{
  const result = sanitizeRenderedHtml(
    '<p style="width: expression(alert(1)); color: blue">Text</p>',
    { allowStyleAttr: true },
  )
  assert.equal(result.includes('expression'), false, 'expression() 所在 style 应整条丢弃')
  assert.equal(result.includes('style='), false, '含高危向量的 style 属性应移除')

  const behaviorResult = sanitizeRenderedHtml(
    '<p style="behavior: url(evil.htc)">Text</p>',
    { allowStyleAttr: true },
  )
  assert.equal(behaviorResult.includes('behavior'), false, 'behavior 应被剥除')

  const jsUrlResult = sanitizeRenderedHtml(
    '<p style="background: url(javascript:alert(2))">Text</p>',
    { allowStyleAttr: true },
  )
  assert.equal(jsUrlResult.includes('javascript:'), false, 'javascript: url 应被剥除')
}

// 23. allowStyleAttr 下 url() 仅放行 http(s) 与 data:image
{
  const kept = sanitizeRenderedHtml(
    '<div style="background-image: url(https://example.com/bg.png)"></div>',
    { allowStyleAttr: true },
  )
  assert.ok(kept.includes('url(https://example.com/bg.png)'), 'http(s) url 应保留')

  const keptDataImage = sanitizeRenderedHtml(
    '<div style="background-image: url(data:image/png;base64,AAAA)"></div>',
    { allowStyleAttr: true },
  )
  assert.ok(keptDataImage.includes('data:image/png'), 'data:image url 应保留')

  const dropped = sanitizeRenderedHtml(
    '<div style="background-image: url(data:text/html;base64,AAAA)"></div>',
    { allowStyleAttr: true },
  )
  assert.equal(dropped.includes('data:text/html'), false, '非 data:image 的 url 应被剥除')
}

// 24. allowStyleAttr 下 position: fixed 应被剥除（防预览区点击劫持）
{
  const result = sanitizeRenderedHtml('<div style="position: fixed; top: 0">Overlay</div>', { allowStyleAttr: true })
  assert.equal(result.includes('position: fixed'), false, 'position: fixed 应被剥除')
}

// 25. allowStyleAttr 下 CSS 注释拼接变体应被剥除
{
  const result = sanitizeRenderedHtml('<div style="position:/*x*/fixed; top: 0">Overlay</div>', { allowStyleAttr: true })
  assert.equal(result.includes('fixed'), false, '注释拼接的 position:fixed 应被剥除')

  const exprResult = sanitizeRenderedHtml('<div style="width: expr/*c*/ession(alert(1))">x</div>', { allowStyleAttr: true })
  assert.equal(exprResult.includes('style='), false, '注释拼接的 expression 应整条丢弃')
}

// 26. 默认拒绝 img data: URI；allowDataImage 开启时放行 data:image
{
  const defaultResult = sanitizeRenderedHtml('<img src="data:image/png;base64,AAAA" alt="p">')
  assert.equal(defaultResult.includes('data:image'), false, '默认应剥除 data:image')

  const allowed = sanitizeRenderedHtml('<img src="data:image/png;base64,AAAA" alt="p">', { allowDataImage: true })
  assert.ok(allowed.includes('data:image/png;base64,AAAA'), 'allowDataImage 时 data:image 应保留')

  const rejected = sanitizeRenderedHtml('<img src="data:text/html;base64,AAAA" alt="p">', { allowDataImage: true })
  assert.equal(rejected.includes('data:text/html'), false, '非 data:image 的 data: URI 仍应被剥除')
}

console.log('sanitizer tests passed')
