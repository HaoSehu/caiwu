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
  // /about 页的实际内容是「关于我们 / 服务能力 / 联系方式」三块。
  // 原先标签写作「支持」，与左侧「帮助」菜单语义重叠，访客分不清该进哪个，
  // 故按页面真实内容命名。
  { to: '/about', label: '关于我们', match: ['WwwAbout'], menuId: 'about' },
]

export const aboutQuickLinks = [
  // desc 必须能对上 /about 页实际存在的区块，否则点进去会「找不到说的内容」。
  { to: '/about', title: '关于我们', desc: '公司简介、服务能力与联系方式' },
  {
    to: '/client/tickets',
    title: '工单支持',
    desc: '提交售后工单，获得 1v1 响应',
  },
  { to: '/help', title: '帮助文档', desc: '常见问题与使用指南' },
  { to: '/notices', title: '公告动态', desc: '产品更新、活动与维护通知' },
]
