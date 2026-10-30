// 云服务器落地页文案：只被对应的落地页薄壳静态引入，避免 5 页全量文案进入同一个 chunk。
export default {
  slug: 'cloud-server',
  path: '/cloud-server',
  routeName: 'WwwSeoCloudServer',
  keyword: '云服务器',
  title: '云服务器 - 稳定弹性计算与 IDC 云主机 - 创欧云',
  description: '创欧云云服务器面向企业网站、业务系统和开发测试场景，提供稳定弹性计算、灵活配置和 IDC 运维支持。',
  keywords: '云服务器,云主机,弹性云服务器,IDC 云服务器,创欧云',
  changefreq: 'weekly',
  priority: '0.9',
  hero: {
    eyebrow: '弹性计算',
    title: '稳定易用的云服务器',
    summary: '面向网站托管、业务系统、接口服务和开发测试，按实际业务规模选择 CPU、内存、带宽和系统镜像，减少前期硬件投入。',
    points: ['灵活配置', '快速交付', '工单支持'],
    stats: [
      { value: '多规格', label: 'CPU / 内存 / 带宽组合' },
      { value: '多系统', label: 'Linux 与 Windows 镜像' },
      { value: '按需选', label: '适配测试到生产环境' },
    ],
  },
  visual: { src: '/img/website/solutions/website.svg', alt: '云服务器业务部署示意' },
  features: [
    { title: '配置可按业务阶段调整', description: '从轻量网站到业务应用都能选择合适规格，后续扩容时减少重复迁移成本。' },
    { title: '适合标准化部署', description: '常用系统镜像、网络和安全配置集中管理，方便新业务快速上线。' },
    { title: '售后路径清晰', description: '通过账号与工单体系沉淀问题记录，便于排查网络、系统和费用相关问题。' },
  ],
  scenarios: [
    { title: '企业官网与营销站', description: '承载官网、专题页、表单提交和轻量后台，适合稳定展示与日常维护。' },
    { title: '开发测试环境', description: '为项目测试、接口联调和临时演示提供独立环境，减少本地环境差异。' },
    { title: '中小型业务系统', description: '适用于管理后台、API 服务、数据看板等需要独立运行环境的业务。' },
  ],
  faqs: [
    { question: '云服务器适合哪些业务？', answer: '适合企业官网、业务后台、接口服务、开发测试和轻量数据库等常见互联网业务。' },
    { question: '新业务应该怎么选配置？', answer: '可以先从较小规格开始，根据访问量、CPU、内存和带宽使用情况再调整。' },
    { question: '云服务器和独立服务器有什么区别？', answer: '云服务器更适合弹性和快速交付，独立服务器更适合对物理资源独占要求更高的场景。' },
  ],
  relatedLinks: [
    { label: '查看产品与服务', to: '/products' },
    { label: '阅读帮助中心', to: '/help' },
    { label: '了解创欧云', to: '/about' },
  ],
  cta: {
    title: '按业务规模选择云服务器',
    description: '先查看可售产品和配置，再进入控制台完成账号注册与下单。',
    primaryText: '查看产品配置',
    primaryTo: '/products',
    secondaryText: '注册账号',
    secondaryConsolePath: '/client/register',
  },
}
