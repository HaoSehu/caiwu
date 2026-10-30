// 美国服务器落地页文案：只被对应的落地页薄壳静态引入，避免 5 页全量文案进入同一个 chunk。
export default {
  slug: 'us-server',
  path: '/us-server',
  routeName: 'WwwSeoUsServer',
  keyword: '美国服务器',
  title: '美国服务器 - 海外业务部署与网站托管 - 创欧云',
  description: '创欧云美国服务器面向海外网站、跨境业务和开发测试场景，提供云服务器配置选择、系统部署和售后支持。',
  keywords: '美国服务器,美国云服务器,海外服务器,海外云主机,创欧云',
  changefreq: 'weekly',
  priority: '0.8',
  hero: {
    eyebrow: '海外节点',
    title: '适合海外部署的美国服务器',
    summary: '面向海外展示站、应用服务、跨境业务和测试环境，帮助团队用较低门槛部署海外访问入口。',
    points: ['海外部署', '网站托管', '业务测试'],
    stats: [
      { value: '海外访问', label: '适合目标用户在海外的业务' },
      { value: '灵活配置', label: '按系统与带宽需求选择' },
      { value: '统一管理', label: '订单、账单和工单集中处理' },
    ],
  },
  visual: { src: '/img/website/solutions/go-global.svg', alt: '美国服务器海外部署示意' },
  features: [
    { title: '面向海外用户访问', description: '适合服务海外用户的网站、接口服务和轻量应用。' },
    { title: '方便业务试运行', description: '先部署测试环境，再根据访问数据调整配置和带宽。' },
    { title: '统一账单管理', description: '海外节点资源可与其他产品一起在账户内查看和续费。' },
  ],
  scenarios: [
    { title: '海外官网', description: '承载英文站、多语言站和面向海外市场的企业展示内容。' },
    { title: '跨境应用服务', description: '部署 API、中转服务、轻量业务后台和运营工具。' },
    { title: '海外链路验证', description: '用于测试不同区域访问、系统兼容和业务延迟。' },
  ],
  faqs: [
    { question: '美国服务器适合国内业务吗？', answer: '如果主要用户在海外，美国服务器更合适；如果主要用户在国内，应优先评估国内或香港节点。' },
    { question: '美国服务器可以做测试环境吗？', answer: '可以，适合验证海外访问、系统部署和跨境业务流程。' },
    { question: '海外服务器选择时最先看什么？', answer: '优先看用户所在地、带宽需求、系统环境和后续运维方式。' },
  ],
  relatedLinks: [
    { label: '查看香港服务器', to: '/hong-kong-server' },
    { label: '查看云服务器', to: '/cloud-server' },
    { label: '查看帮助中心', to: '/help' },
  ],
  cta: {
    title: '部署海外业务入口',
    description: '根据目标用户区域选择节点和带宽，先小规模上线再持续优化。',
    primaryText: '查看产品配置',
    primaryTo: '/products',
    secondaryText: '注册账号',
    secondaryConsolePath: '/client/register',
  },
}
