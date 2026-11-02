import fs from 'node:fs/promises'
import path from 'node:path'
import { pathToFileURL } from 'node:url'

const ROOT = process.cwd()
const TARGETS = [
  'src',
  'package.json',
  'vite.config.js',
]

const TEXT_FILE_EXTENSIONS = new Set([
  '.vue',
  '.js',
  '.mjs',
  // src 下的应用入口与 router/store 都是 .ts，不纳入就会整类跳过本检查。
  '.ts',
  '.tsx',
  '.jsx',
  '.json',
  '.scss',
  '.css',
  '.md',
  '.html',
])

function createIssueCollector() {
  return []
}

function inspectFile(relativePath, content, issues) {
  if (content.includes('\uFFFD')) {
    issues.push({
      file: relativePath,
      problem: '包含 Unicode replacement character（疑似编码损坏）',
    })
  }

  const lines = content.split(/\r?\n/)
  lines.forEach((line, index) => {
    const trimmed = line.trim()

    if (
      trimmed.startsWith('router-link>') ||
      trimmed.startsWith('/router-link>') ||
      trimmed.startsWith('p>') ||
      trimmed.startsWith('/p>')
    ) {
      issues.push({
        file: relativePath,
        problem: `第 ${index + 1} 行疑似缺失起始尖括号`,
      })
    }

    if (/title="[^"]*$/.test(trimmed) || /label="[^"]*$/.test(trimmed) || /placeholder="[^"]*$/.test(trimmed)) {
      issues.push({
        file: relativePath,
        problem: `第 ${index + 1} 行疑似存在未闭合属性引号`,
      })
    }
  })
}

export async function runSourceHealthCheck() {
  const issues = createIssueCollector()

  for (const target of TARGETS) {
    await walk(target, issues)
  }

  return issues
}

async function walk(targetPath, issues) {
  const absolutePath = path.join(ROOT, targetPath)
  const stats = await fs.stat(absolutePath)

  if (stats.isDirectory()) {
    const entries = await fs.readdir(absolutePath, { withFileTypes: true })
    for (const entry of entries) {
      if (entry.name === 'node_modules' || entry.name === 'dist') {
        continue
      }
      await walk(path.join(targetPath, entry.name), issues)
    }
    return
  }

  const extension = path.extname(absolutePath)
  if (!TEXT_FILE_EXTENSIONS.has(extension)) {
    return
  }

  const content = await fs.readFile(absolutePath, 'utf8')
  inspectFile(targetPath, content, issues)
}

function reportIssues(issues) {
  if (issues.length) {
    console.error('Source health check failed:')
    for (const issue of issues) {
      console.error(`- ${issue.file}: ${issue.problem}`)
    }
    return false
  }

  console.log('Source health check passed.')
  return true
}

async function main() {
  const issues = await runSourceHealthCheck()

  if (!reportIssues(issues)) {
    process.exit(1)
  }
}

// 必须用 pathToFileURL 归一化：Windows 下 new URL('E:\\a\\b', 'file:') 得到的是
// file:///E:/a/b 之外的畸形结果，直接比较会让脚本静默空跑（EXIT=0 无输出）。
const isDirectRun = process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href

if (isDirectRun) {
  main().catch((error) => {
    console.error(error?.stack || String(error))
    process.exit(1)
  })
}
