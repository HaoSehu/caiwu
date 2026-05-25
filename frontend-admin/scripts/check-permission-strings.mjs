/**
 * 权限字符串巡检脚本。
 * 扫描业务代码中直接使用权限字符串字面量的情况（应统一引用 AdminPermissions 常量）。
 * 运行: node ./scripts/check-permission-strings.mjs
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, extname } from 'node:path'

const PERMISSION_PATTERNS = [
  /permissions\s*\.\s*includes\s*\(\s*['"]([a-z][a-z0-9_.]*?)['"]\s*\)/g,
  /hasPermission\s*\(\s*['"]([a-z][a-z0-9_.]*?)['"]\s*\)/g,
  /hasAnyPermission\s*\(\s*\[[\s\S]*?['"]([a-z][a-z0-9_.]*?)['"][\s\S]*?\]\s*\)/g,
  /hasAllPermissions\s*\(\s*\[[\s\S]*?['"]([a-z][a-z0-9_.]*?)['"][\s\S]*?\]\s*\)/g,
]

const ALLOWED_RAW = new Set(['*'])

const EXCLUDE_PATTERNS = [
  /import\s+.*AdminPermissions.*from/,
  /AdminPermissions\./,
  /PERMISSIONS\./,
]

const SRC_DIR = 'src'
const EXTENSIONS = new Set(['.js', '.ts', '.vue'])

function collectFiles(dir) {
  const result = []
  const entries = readdirSync(dir, { withFileTypes: true })
  for (const entry of entries) {
    const full = join(dir, entry.name)
    if (entry.name === 'node_modules' || entry.name === 'constants') continue
    if (entry.isDirectory()) {
      result.push(...collectFiles(full))
    } else if (EXTENSIONS.has(extname(entry.name))) {
      result.push(full)
    }
  }
  return result
}

let found = 0

for (const file of collectFiles(SRC_DIR)) {
  const content = readFileSync(file, 'utf-8')

  for (const pattern of PERMISSION_PATTERNS) {
    pattern.lastIndex = 0
    let match
    while ((match = pattern.exec(content)) !== null) {
      const rawValue = match[1]
      if (ALLOWED_RAW.has(rawValue)) continue

      const contextStart = Math.max(0, match.index - 20)
      const contextEnd = Math.min(content.length, match.index + match[0].length + 20)
      let context = content.slice(contextStart, contextEnd).replace(/\n/g, '\\n').trim()
      if (context.length > 80) context = '...' + context.slice(-77)

      console.log(`${file}: 原始权限字符串 '${rawValue}'`)
      console.log(`  上下文: ${context}`)
      found++
    }
  }
}

if (found === 0) {
  console.log('权限字符串巡检通过：未发现原始权限字符串字面量')
} else {
  console.log(`\n共发现 ${found} 处原始权限字符串，请替换为 AdminPermissions 常量`)
  process.exit(1)
}
