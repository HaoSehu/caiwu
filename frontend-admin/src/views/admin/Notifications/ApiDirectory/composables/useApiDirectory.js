import { computed, ref } from 'vue'
import { ElMessage } from 'element-plus'
import apiCatalogData from '@/data/apiCatalog.generated.json'

export function useApiDirectory() {
  const meta = Object.freeze(apiCatalogData.meta || {})
  const rawApiItems = Object.freeze(apiCatalogData.items || [])

  const treeRef = ref(null)
  const selectedTreeKey = ref('all')
  const keyword = ref('')
  const accessFilter = ref('all')
  const methodFilter = ref('all')
  const sourceFilter = ref('all')

  const treeProps = Object.freeze({
    label: 'label',
    children: 'children',
  })

  const scopeOrder = Object.freeze(['admin', 'client', 'site', 'system'])

  const subgroupSegmentLabels = Object.freeze({
    login: '登录',
    register: '注册',
    info: '信息',
    summary: '汇总',
    overview: '总览',
    services: '服务管理',
    invoices: '发票',
    'balance-logs': '余额流水',
    tickets: '工单',
    'operation-logs': '操作日志',
    'sms-logs': '短信日志',
    'email-logs': '邮件日志',
    'module-status': '模块状态',
    'login-as': '代登录',
    recharge: '余额充值',
    'toggle-status': '状态切换',
    'payment-status': '支付状态',
    'batch-sync': '批量同步',
    reorder: '排序',
    owners: '关联归属',
    'sort-order': '排序值',
    balance: '供应商余额',
    'config-template': '配置模板',
    products: '商品',
    trigger: '手动触发',
    cleanup: '清理',
    rewards: '奖励明细',
    withdrawals: '提现',
    'account-logs': '账户流水',
    articles: '文章',
    categories: '分类',
    'admin-users': '管理员',
    close: '关闭',
    assign: '分配',
    reply: '回复',
    'upload-image': '上传图片',
    init: '初始化',
    qrcode: '二维码',
    status: '状态',
    scan: '扫码',
    callback: '回调',
    'fee-config': '费用配置',
    notices: '公告',
    'help-articles': '帮助文章',
    'grouped-overview': '分组总览',
    config: '配置信息',
    renew: '续费',
    auto: '自动续费',
    power: '电源操作',
    password: '密码',
    reset: '重置',
    reinstall: '重装',
    options: '选项',
    'nat-forwardings': 'NAT 转发',
    monitor: '监控',
    batch: '批量查询',
    'security-groups': '安全组',
    rules: '规则',
    apply: '应用',
    vnc: 'VNC',
    'vnc-tokens': 'VNC 令牌',
    phone: '手机号',
    email: '邮箱',
    profile: '资料',
    'notification-preferences': '通知偏好',
    'alipay-account': '支付宝账户',
    'captcha-config': '验证码配置',
    'captcha-script': '验证码脚本',
    'phone-code': '手机验证码',
    'email-code': '邮箱验证码',
    'reset-password': '重置密码',
    tasks: '任务',
    system: '系统',
    'admin-logins': '管理员登录',
    content: '内容',
    health: '健康检查',
    quote: '询价',
  })

  const catalogItems = Object.freeze(rawApiItems.map((item) => {
    const handlerInfo = parseHandler(item.handler)
    const subgroupInfo = resolveSubgroup(item)

    return {
      ...item,
      controllerLabel: handlerInfo.controllerLabel,
      actionName: handlerInfo.actionName,
      subgroupKey: subgroupInfo.key,
      subgroupLabel: subgroupInfo.label,
    }
  }))

  const totalEndpoints = catalogItems.length
  const subgroupTotal = new Set(catalogItems.map((item) => `${item.scope}:${item.module}:${item.subgroupKey}`)).size

  const accessSummary = computed(() => ({
    public: meta.accessCounts?.public || 0,
    auth: meta.accessCounts?.auth || 0,
    permission: meta.accessCounts?.permission || 0,
  }))

  const untrackedCount = computed(() => (
    catalogItems.filter((item) => item.sourceApps.length === 0).length
  ))

  const accessOptions = computed(() => [
    { value: 'public', label: '公开' },
    { value: 'auth', label: '仅登录' },
    { value: 'permission', label: '登录 + 权限' },
  ])

  const methodOptions = computed(() => (
    Array.from(new Set(catalogItems.flatMap((item) => item.methods || [])))
      .sort((left, right) => left.localeCompare(right))
  ))

  const treeData = computed(() => buildTree(catalogItems))
  const treeNodeMap = computed(() => flattenTreeNodes(treeData.value))

  const defaultExpandedKeys = computed(() => (
    treeData.value.map((node) => node.key)
  ))

  const activeTreeNode = computed(() => (
    treeNodeMap.value.get(selectedTreeKey.value) || {
      key: 'all',
      type: 'all',
      label: '全部接口',
      pathLabels: ['全部接口'],
      count: totalEndpoints,
    }
  ))

  const activeTreePath = computed(() => activeTreeNode.value.pathLabels || ['全部接口'])

  const activeTreeDescription = computed(() => {
    if (activeTreeNode.value.type === 'scope') {
      return `当前聚焦 ${activeTreeNode.value.label}，会展示该分端下的全部模块与接口。`
    }

    if (activeTreeNode.value.type === 'module') {
      return `当前聚焦 ${activeTreeNode.value.label} 模块，适合查看同一业务域下的全部接口。`
    }

    if (activeTreeNode.value.type === 'subgroup') {
      return `当前聚焦 ${activeTreeNode.value.label} 子类目，适合精确定位同一资源动作组的接口。`
    }

    return '当前显示项目内全部已注册 API 接口，你可以从左侧树快速缩小范围。'
  })

  const filteredItems = computed(() => {
    const searchValue = keyword.value.trim().toLowerCase()

    return catalogItems.filter((item) => {
      if (!matchTreeSelection(item, activeTreeNode.value)) {
        return false
      }

      if (accessFilter.value !== 'all' && item.access !== accessFilter.value) {
        return false
      }

      if (methodFilter.value !== 'all' && !(item.methods || []).includes(methodFilter.value)) {
        return false
      }

      if (sourceFilter.value === 'untracked' && item.sourceApps.length > 0) {
        return false
      }

      if (sourceFilter.value !== 'all' && sourceFilter.value !== 'untracked' && !item.sourceApps.includes(sourceFilter.value)) {
        return false
      }

      if (!searchValue) {
        return true
      }

      const haystack = [
        item.scopeLabel,
        item.moduleLabel,
        item.module,
        item.subgroupLabel,
        item.subgroupKey,
        item.method,
        item.callPath,
        item.backendPath,
        item.accessLabel,
        item.permission,
        item.handler,
        item.controllerLabel,
        item.actionName,
        ...(item.guards || []),
        ...(item.sourceAppLabels || []),
        ...(item.sourceFiles || []),
      ]
        .join(' ')
        .toLowerCase()

      return haystack.includes(searchValue)
    })
  })

  function buildTree(items) {
    const scopeMap = new Map()

    for (const item of items) {
      if (!scopeMap.has(item.scope)) {
        scopeMap.set(item.scope, {
          key: `scope:${item.scope}`,
          type: 'scope',
          scope: item.scope,
          label: item.scopeLabel,
          secondary: '',
          count: 0,
          pathLabels: [item.scopeLabel],
          children: [],
          _modules: new Map(),
        })
      }

      const scopeNode = scopeMap.get(item.scope)
      scopeNode.count += 1

      const moduleNodeKey = `${item.scope}:${item.module}`
      if (!scopeNode._modules.has(moduleNodeKey)) {
        scopeNode._modules.set(moduleNodeKey, {
          key: `module:${moduleNodeKey}`,
          type: 'module',
          scope: item.scope,
          module: item.module,
          label: item.moduleLabel,
          secondary: item.module,
          count: 0,
          pathLabels: [item.scopeLabel, item.moduleLabel],
          children: [],
          _subgroups: new Map(),
        })
      }

      const moduleNode = scopeNode._modules.get(moduleNodeKey)
      moduleNode.count += 1

      const subgroupNodeKey = `${moduleNodeKey}:${item.subgroupKey}`
      if (!moduleNode._subgroups.has(subgroupNodeKey)) {
        moduleNode._subgroups.set(subgroupNodeKey, {
          key: `subgroup:${subgroupNodeKey}`,
          type: 'subgroup',
          scope: item.scope,
          module: item.module,
          subgroupKey: item.subgroupKey,
          label: item.subgroupLabel,
          secondary: '',
          count: 0,
          pathLabels: [item.scopeLabel, item.moduleLabel, item.subgroupLabel],
          controllers: new Set(),
        })
      }

      const subgroupNode = moduleNode._subgroups.get(subgroupNodeKey)
      subgroupNode.count += 1
      subgroupNode.controllers.add(item.controllerLabel)
    }

    return scopeOrder
      .filter((scope) => scopeMap.has(scope))
      .map((scope) => {
        const scopeNode = scopeMap.get(scope)
        const moduleNodes = Array.from(scopeNode._modules.values())
          .map((moduleNode) => {
            const subgroupNodes = Array.from(moduleNode._subgroups.values())
              .map((subgroupNode) => ({
                ...subgroupNode,
                secondary: `${subgroupNode.controllers.size} 个控制器`,
              }))
              .sort(sortTreeNode)

            return {
              ...moduleNode,
              secondary: `${subgroupNodes.length} 个子类目`,
              children: subgroupNodes,
            }
          })
          .sort(sortTreeNode)

        return {
          ...scopeNode,
          secondary: `${moduleNodes.length} 个模块`,
          children: moduleNodes,
        }
      })
  }

  function flattenTreeNodes(nodes) {
    const result = new Map()

    for (const node of nodes) {
      result.set(node.key, node)
      if (node.children?.length) {
        const childMap = flattenTreeNodes(node.children)
        childMap.forEach((value, key) => result.set(key, value))
      }
    }

    return result
  }

  function resolveSubgroup(item) {
    const segments = item.normalizedPath.split('/').filter(Boolean)
    const pathWithoutScope = segments.slice(1)
    const remainingSegments = pathWithoutScope[0] === item.module
      ? pathWithoutScope.slice(1)
      : pathWithoutScope

    const meaningfulSegments = remainingSegments
      .filter((segment) => segment && segment !== '{param}')
      .slice(0, 3)

    if (!meaningfulSegments.length) {
      if (remainingSegments.includes('{param}')) {
        return { key: 'detail', label: '基础详情' }
      }

      return { key: 'basic', label: '基础操作' }
    }

    return {
      key: meaningfulSegments.join('/'),
      label: meaningfulSegments
        .map((segment) => subgroupSegmentLabels[segment] || humanizeSegment(segment))
        .join(' / '),
    }
  }

  function parseHandler(handler) {
    if (!handler || handler === 'Closure') {
      return {
        controllerLabel: 'Closure',
        actionName: '',
      }
    }

    const [controllerName, actionName] = String(handler).split('@')

    return {
      controllerLabel: controllerName.replace(/Controller$/, ''),
      actionName: actionName || '',
    }
  }

  function humanizeSegment(segment) {
    return segment
      .split('-')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ')
  }

  function sortTreeNode(left, right) {
    if (left.label === right.label) {
      return left.count - right.count
    }

    return left.label.localeCompare(right.label, 'zh-CN')
  }

  function matchTreeSelection(item, treeNode) {
    if (!treeNode || treeNode.type === 'all') {
      return true
    }

    if (treeNode.type === 'scope') {
      return item.scope === treeNode.scope
    }

    if (treeNode.type === 'module') {
      return item.scope === treeNode.scope && item.module === treeNode.module
    }

    if (treeNode.type === 'subgroup') {
      return item.scope === treeNode.scope
        && item.module === treeNode.module
        && item.subgroupKey === treeNode.subgroupKey
    }

    return true
  }

  function handleTreeNodeClick(node) {
    selectTreeNode(node)
  }

  function selectTreeNode(node) {
    selectedTreeKey.value = node.key
    if (node.key !== 'all') {
      treeRef.value?.setCurrentKey(node.key)
    } else {
      treeRef.value?.setCurrentKey(undefined)
    }
  }

  function resetFilters() {
    keyword.value = ''
    accessFilter.value = 'all'
    methodFilter.value = 'all'
    sourceFilter.value = 'all'
    selectTreeNode({ key: 'all' })
  }

  function methodTagType(methods) {
    if ((methods || []).length > 1) {
      return 'info'
    }

    const method = methods?.[0]
    if (method === 'GET') return 'primary'
    if (method === 'POST') return 'success'
    if (method === 'PUT') return 'warning'
    if (method === 'DELETE') return 'danger'
    return 'info'
  }

  function accessTagType(access) {
    if (access === 'permission') return 'warning'
    if (access === 'auth') return 'success'
    return 'info'
  }

  async function copyText(value, successMessage) {
    try {
      if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(value)
      } else {
        const textarea = document.createElement('textarea')
        textarea.value = value
        textarea.setAttribute('readonly', 'readonly')
        textarea.style.position = 'absolute'
        textarea.style.left = '-9999px'
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
      }

      ElMessage.success(successMessage)
    } catch {
      ElMessage.error('复制失败，请手动复制')
    }
  }

  function copyFilteredEndpoints(items) {
    if (!items.length) {
      ElMessage.warning('当前没有可复制的接口结果')
      return
    }

    const content = items
      .map((item) => `${item.method} ${item.backendPath}`)
      .join('\n')

    copyText(content, '当前筛选结果已复制')
  }

  return {
    // refs
    treeRef,
    selectedTreeKey,
    keyword,
    accessFilter,
    methodFilter,
    sourceFilter,
    // constants
    meta,
    treeProps,
    totalEndpoints,
    subgroupTotal,
    // computed
    accessSummary,
    untrackedCount,
    accessOptions,
    methodOptions,
    treeData,
    defaultExpandedKeys,
    activeTreeNode,
    activeTreePath,
    activeTreeDescription,
    filteredItems,
    // methods
    handleTreeNodeClick,
    selectTreeNode,
    resetFilters,
    methodTagType,
    accessTagType,
    copyText,
    copyFilteredEndpoints,
  }
}
