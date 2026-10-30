// 香港服务器落地页文案：只被对应的落地页薄壳静态引入，避免 5 页全量文案进入同一个 chunk。
export default {
  slug: 'hong-kong-server',
  path: '/hong-kong-server',
  routeName: 'WwwSeoHongKongServer',
  keyword: '香港服务器',
  title: '香港服务器 - 面向出海与跨境访问的云服务器 - 创欧云',
  description: '创欧云香港服务器适合跨境网站、外贸业务和亚太访问场景，提供云服务器配置选择与工单支持。',
  keywords: '香港服务器,香港云服务器,香港云主机,跨境服务器,创欧云',
  changefreq: 'weekly',
  priority: '0.8',
  hero: {
    eyebrow: '跨境访问',
    title: '面向亚太业务的香港服务器',
    summary: '适用于外贸站点、跨境业务、亚太用户访问和业务中转场景，兼顾部署效率、访问体验和日常运维支持。',
    points: ['亚太访问', '外贸建站', '部署灵活'],
    stats: [
      { value: '香港节点', label: '适合亚太访问场景' },
      { value: '多带宽', label: '按站点访问量选择' },
      { value: '工单协同', label: '支持网络与系统问题排查' },
    ],
  },
  visual: { src: '/img/website/solutions/go-global.svg', alt: '香港服务器跨境业务示意' },
  features: [
    { title: '适合跨境访问入口', description: '香港节点常用于面向亚太用户的网站、接口服务和轻量业务入口。' },
    { title: '部署链路更短', description: '网站、证书、应用和数据迁移可以在同一业务流程中逐步完成。' },
    { title: '便于后续扩展', description: '业务增长后可继续选择更高配置或调整带宽，减少一次性投入压力。' },
  ],
  scenarios: [
    { title: '外贸展示站', description: '承载企业介绍、产品目录、询盘表单和多语言页面。' },
    { title: '跨境业务入口', description: '作为面向亚太用户的应用入口、接口服务或轻量后台。' },
    { title: '海外测试环境', description: '用于验证海外线路、业务访问和不同区域用户体验。' },
  ],
  faqs: [
    { question: '香港服务器适合什么业务？', answer: '适合外贸网站、亚太用户访问、跨境业务入口和海外测试环境。' },
    { question: '香港服务器是否适合国内用户访问？', answer: '具体体验受线路、带宽和访问区域影响，建议按业务主要用户所在地选择配置。' },
    { question: '如何选择香港服务器带宽？', answer: '展示型网站通常先按基础带宽试运行，视频、下载或高并发业务需要预留更高带宽。' },
  ],
  relatedLinks: [
    { label: '查看云服务器', to: '/cloud-server' },
    { label: '查看美国服务器', to: '/us-server' },
    { label: '查看产品与服务', to: '/products' },
  ],
  cta: {
    title: '为跨境业务选择香港服务器',
    description: '先确认访问区域、带宽需求和系统环境，再选择合适配置。',
    primaryText: '查看产品配置',
    primaryTo: '/products',
    secondaryText: '提交咨询工单',
    secondaryConsolePath: '/client/tickets',
  },
}
