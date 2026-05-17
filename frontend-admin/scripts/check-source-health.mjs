import fs from 'node:fs/promises'
import path from 'node:path'

const ROOT = process.cwd()
const TARGETS = ['src', 'package.json', 'vite.config.js']
const TEXT_FILE_EXTENSIONS = new Set(['.vue', '.js', '.ts', '.mjs', '.json', '.scss', '.css', '.md', '.html'])

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

    if (/title="[^"]*$/.test(trimmed) || /label="[^"]*$/.test(trimmed) || /placeholder="[^"]*$/.test(trimmed)) {
      issues.push({
        file: relativePath,
        problem: `第 ${index + 1} 行疑似存在未闭合属性引号`,
      })
    }
  })
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
  if (!TEXT_FILE_EXTENSIONS.has(extension) && path.basename(absolutePath) !== 'package.json') {
    return
  }

  const content = await fs.readFile(absolutePath, 'utf8')
  inspectFile(targetPath, content, issues)
}

async function main() {
  const issues = []

  for (const target of TARGETS) {
    await walk(target, issues)
  }

  if (issues.length) {
    console.error('Source health check failed:')
    for (const issue of issues) {
      console.error(`- ${issue.file}: ${issue.problem}`)
    }
    process.exit(1)
  }

  console.log('Source health check passed.')
}

main().catch((error) => {
  console.error(error?.stack || String(error))
  process.exit(1)
})
