// 官网布局的导航数据，与 WebsiteLayout 的模板结构一一对应。
// 独立成文件便于和交互逻辑（composables）分开维护，避免布局组件继续膨胀。

export const navigationItems = [
  { to: '/', label: '首页', match: ['WwwHome'] },
  {
    to: '/products',
    label: '产品',
    match: [
      'WwwProducts',
      'WwwProductsPurchase',
      'WwwProductsPurchaseWithChild',
      'WwwProductDetail',
    ],
    menuId: 'products',
  },
  {
    to: '/notices',
    label: '公告',
    match: ['WwwNotices', 'WwwNoticeDetail'],
    menuId: 'notices',
  },
  {
    to: '/help',
    label: '帮助',
    match: ['WwwHelp', 'WwwHelpDetail'],
    menuId: 'help',
  },
  { to: '/about', label: '支持', match: ['WwwAbout'], menuId: 'about' },
]

export const aboutQuickLinks = [
  { to: '/about', title: '关于我们', desc: '企业简介、发展愿景与服务承诺' },
  {
    to: '/client/tickets',
    title: '工单支持',
    desc: '提交售后工单，获得 1v1 响应',
  },
  { to: '/help', title: '帮助文档', desc: '常见问题与使用指南' },
  { to: '/notices', title: '公告动态', desc: '产品更新、活动与维护通知' },
]
