import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const adminRoot = path.resolve(__dirname, '..')
const repoRoot = path.resolve(adminRoot, '..')
const backendRoot = path.join(repoRoot, 'backend')
const outputPath = path.join(adminRoot, 'src', 'data', 'apiCatalog.generated.json')

const scopeLabels = {
  admin: '管理端',
  client: '客户端',
  site: '站点',
  system: '系统',
}

const sourceAppLabels = {
  'frontend-admin': '管理端前端',
  'frontend-client': '客户端前端',
}

const accessLabels = {
  public: '公开',
  auth: '仅登录',
  permission: '登录 + 权限',
}

const moduleLabels = {
  auth: '认证',
  dashboard: '仪表盘',
  users: '用户',
  orders: '订单',
  products: '商品',
  'product-groups': '商品分组',
  'product-categories': '商品分类',
  'product-types': '商品类型',
  suppliers: '供应商',
  settings: '系统设置',
  schedules: '计划任务',
  logs: '日志',
  referral: '推荐',
  'referral-withdrawals': '推荐提现',
  'referral-account-logs': '推荐账户流水',
  'referral-rewards': '推荐奖励',
  verification: '实名',
  tickets: '工单',
  services: '服务',
  recharge: '充值',
  payment: '支付',
  invoices: '发票',
  'balance-logs': '余额流水',
  content: '内容',
  notices: '公告',
  'help-articles': '帮助文章',
  'member-levels': '会员等级',
  blackhole: '黑洞查询',
  health: '健康检查',
  config: '站点配置',
  site: '站点',
  'vnc-tokens': 'VNC 令牌',
}

const frontendSources = [
  {
    app: 'frontend-admin',
    dir: path.join(repoRoot, 'frontend-admin', 'src'),
  },
  {
    app: 'frontend-client',
    dir: path.join(repoRoot, 'frontend-client', 'src'),
  },
]

const phpCommand = resolvePhpCommand()
const routeListRaw = execFileSync(phpCommand.command, ['artisan', 'route:list', '--json'], {
  cwd: backendRoot,
  encoding: 'utf8',
  shell: phpCommand.shell,
})

const routes = JSON.parse(routeListRaw).filter((item) => String(item.uri || '').startsWith('api/'))
const usageMap = collectFrontendUsage(frontendSources)

const items = routes
  .map((route) => normalizeRoute(route, usageMap))
  .sort(sortCatalogItems)

const payload = {
  meta: {
    generatedAt: formatDateTime(new Date()),
    total: items.length,
    baseURL: '/api',
    dataSource: 'php artisan route:list --json + frontend source scan',
    scopeLabels,
    sourceAppLabels,
    accessLabels,
    scopeCounts: countBy(items, 'scope'),
    accessCounts: countBy(items, 'access'),
    moduleCount: new Set(items.map((item) => `${item.scope}:${item.module}`)).size,
  },
  items,
}

mkdirSync(path.dirname(outputPath), { recursive: true })
writeFileSync(outputPath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8')

console.log(`API catalog generated: ${items.length} routes -> ${path.relative(repoRoot, outputPath)}`)

function collectFrontendUsage(sources) {
  const usage = new Map()

  for (const source of sources) {
    const files = walkFiles(source.dir)

    for (const filePath of files) {
      if (!/\.(js|vue)$/.test(filePath)) {
        continue
      }

      const content = readFileSync(filePath, 'utf8')
      const matches = content.matchAll(/(?:request|axios)\.(get|post|put|delete|patch)\(\s*([`'"])([^`'"]+?)\2/g)

      for (const match of matches) {
        const method = String(match[1] || '').toUpperCase()
        const literalPath = String(match[3] || '').trim()

        if (!literalPath.startsWith('/')) {
          continue
        }

        const normalizedPath = normalizeClientPath(literalPath)
        const key = `${method} ${normalizedPath}`
        const record = {
          app: source.app,
          appLabel: sourceAppLabels[source.app] || source.app,
          file: toPosixPath(path.relative(repoRoot, filePath)),
        }

        if (!usage.has(key)) {
          usage.set(key, [])
        }

        usage.get(key).push(record)
      }
    }
  }

  return usage
}

function normalizeRoute(route, usageMap) {
  const uri = String(route.uri || '')
  const backendPath = `/${uri}`
  const callPath = backendPath.replace(/^\/api/, '') || '/'
  const normalizedCallPath = normalizeClientPath(callPath)
  const methodTokens = String(route.method || '')
    .split('|')
    .filter((token) => token && token !== 'HEAD')

  const middleware = Array.isArray(route.middleware) ? route.middleware : []
  const permissionMiddleware = middleware.find((item) => item.includes('CheckPermission:'))
  const throttleMiddleware = middleware.find((item) => item.includes('ThrottleRequests:'))
  const requiresAuth = middleware.some((item) => item.includes('Authenticate:sanctum'))
  const hasSignedCallback = middleware.some((item) => item.includes('VerifyCallbackSignature'))
  const hasOperationLog = middleware.some((item) => item.includes('LogOperation'))
  const permission = permissionMiddleware ? permissionMiddleware.split(':').slice(1).join(':') : ''
  const throttle = throttleMiddleware ? throttleMiddleware.split(':').slice(1).join(':') : ''
  const access = permission ? 'permission' : (requiresAuth ? 'auth' : 'public')

  const usageEntries = new Map()
  for (const method of methodTokens) {
    const key = `${method} ${normalizedCallPath}`
    for (const entry of usageMap.get(key) || []) {
      usageEntries.set(`${entry.app}:${entry.file}`, entry)
    }
  }

  const usageList = Array.from(usageEntries.values()).sort((a, b) => a.file.localeCompare(b.file))
  const sourceApps = Array.from(new Set(usageList.map((item) => item.app)))

  const { scope, module } = resolveScopeAndModule(uri)
  const guards = []

  if (throttle) {
    guards.push(`限流 ${throttle}`)
  }
  if (hasSignedCallback) {
    guards.push('签名校验')
  }
  if (hasOperationLog) {
    guards.push('记录操作日志')
  }

  return {
    id: `${methodTokens.join('|')}:${backendPath}`,
    scope,
    scopeLabel: scopeLabels[scope] || scope,
    module,
    moduleLabel: moduleLabels[module] || humanizeModule(module),
    method: formatMethodLabel(methodTokens),
    methods: methodTokens,
    callPath,
    backendPath,
    normalizedPath: normalizedCallPath,
    access,
    accessLabel: accessLabels[access],
    permission,
    throttle,
    guards,
    handler: formatHandler(route.action),
    sourceApps,
    sourceAppLabels: sourceApps.map((app) => sourceAppLabels[app] || app),
    sourceFiles: usageList.map((item) => item.file),
  }
}

function resolveScopeAndModule(uri) {
  const segments = uri.split('/').filter(Boolean)
  const scope = ['admin', 'client', 'site'].includes(segments[1]) ? segments[1] : 'system'

  if (scope === 'system') {
    return {
      scope,
      module: segments[1] || 'system',
    }
  }

  let module = segments[2] || 'root'
  if (!module || module.startsWith('{')) {
    module = scope === 'site' ? 'site' : 'auth'
  }

  if (scope !== 'site' && (module === 'login' || module === 'register')) {
    module = 'auth'
  }

  return { scope, module }
}

function normalizeClientPath(input) {
  return input
    .replace(/^\/api/, '')
    .replace(/\$\{[^}]+\}/g, '{param}')
    .replace(/\{[^}]+\}/g, '{param}')
    .replace(/\/+/g, '/')
    .replace(/\/$/, '') || '/'
}

function formatMethodLabel(methods) {
  if (!methods.length) {
    return 'GET'
  }
  return methods.join(' / ')
}

function formatHandler(action) {
  if (!action || action === 'Closure') {
    return 'Closure'
  }
  return String(action).split('\\').at(-1) || String(action)
}

function humanizeModule(module) {
  return module
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function walkFiles(dir) {
  const result = []

  for (const entry of readdirSync(dir)) {
    const fullPath = path.join(dir, entry)
    const stats = statSync(fullPath)

    if (stats.isDirectory()) {
      result.push(...walkFiles(fullPath))
      continue
    }

    result.push(fullPath)
  }

  return result
}

function countBy(items, key) {
  return items.reduce((accumulator, item) => {
    const currentKey = item[key]
    accumulator[currentKey] = (accumulator[currentKey] || 0) + 1
    return accumulator
  }, {})
}

function sortCatalogItems(left, right) {
  const scopeOrder = ['admin', 'client', 'site', 'system']
  const scopeDiff = scopeOrder.indexOf(left.scope) - scopeOrder.indexOf(right.scope)
  if (scopeDiff !== 0) {
    return scopeDiff
  }

  const moduleDiff = left.module.localeCompare(right.module)
  if (moduleDiff !== 0) {
    return moduleDiff
  }

  return left.backendPath.localeCompare(right.backendPath)
}

function formatDateTime(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')

  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

function toPosixPath(value) {
  return value.split(path.sep).join('/')
}

function resolvePhpCommand() {
  const envCommand = String(process.env.PHP_BIN || '').trim()
  if (envCommand) {
    return {
      command: envCommand,
      shell: process.platform === 'win32' && /\.bat$/i.test(envCommand),
    }
  }

  if (process.platform === 'win32') {
    const windowsCandidates = [
      'C:\\ProgramData\\chocolatey\\bin\\php.bat',
      'php',
    ]

    for (const candidate of windowsCandidates) {
      if (candidate === 'php' || existsSync(candidate)) {
        return {
          command: candidate,
          shell: /\.bat$/i.test(candidate),
        }
      }
    }
  }

  return {
    command: 'php',
    shell: false,
  }
}
