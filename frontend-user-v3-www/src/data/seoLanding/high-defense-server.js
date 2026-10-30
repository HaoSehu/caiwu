// 高防服务器落地页文案：只被对应的落地页薄壳静态引入，避免 5 页全量文案进入同一个 chunk。
export default {
  slug: 'high-defense-server',
  path: '/high-defense-server',
  routeName: 'WwwSeoHighDefenseServer',
  keyword: '高防服务器',
  title: '高防服务器 - 面向攻击防护场景的云服务器 - 创欧云',
  description: '创欧云高防服务器适合游戏、业务接口和高风险网站等防护需求场景，提供配置选择与运维支持。',
  keywords: '高防服务器,高防云服务器,防护服务器,游戏服务器防护,创欧云',
  changefreq: 'weekly',
  priority: '0.8',
  hero: {
    eyebrow: '安全防护',
    title: '面向防护需求的高防服务器',
    summary: '适用于容易受到异常流量影响的网站、游戏和接口服务，帮助业务在风险场景下保持更清晰的资源与工单管理路径。',
    points: ['防护场景', '业务连续性', '问题追踪'],
    stats: [
      { value: '高风险业务', label: '适合游戏与接口服务' },
      { value: '可追踪', label: '通过工单沉淀问题记录' },
      { value: '可组合', label: '按服务器规格与带宽选择' },
    ],
  },
  visual: { src: '/img/website/solutions/security.svg', alt: '高防服务器防护场景示意' },
  features: [
    { title: '适配攻击风险更高的业务', description: '游戏、活动站、接口服务等更容易遇到异常流量，需要提前规划防护方案。' },
    { title: '减少排查分散', description: '资源、账单和工单在账户内集中管理，便于复盘问题。' },
    { title: '按业务承压选择配置', description: '根据业务类型、流量规模和系统负载选择服务器规格。' },
  ],
  scenarios: [
    { title: '游戏业务入口', description: '面向游戏登录、接口和官网等对可用性敏感的业务。' },
    { title: '活动与营销页面', description: '适合短期访问波动明显、需要提前准备防护预案的页面。' },
    { title: '业务接口服务', description: '承载 API、回调服务和对外接口，减少异常流量对主业务影响。' },
  ],
  faqs: [
    { question: '高防服务器和普通云服务器有什么区别？', answer: '高防服务器更偏向异常流量防护场景，普通云服务器更适合常规计算和托管需求。' },
    { question: '所有网站都需要高防服务器吗？', answer: '不一定。普通企业站通常先用常规云服务器，游戏、活动和高风险业务再评估高防需求。' },
    { question: '选择高防服务器前要准备什么？', answer: '需要明确业务类型、访问峰值、攻击风险、系统环境和可接受的恢复时间。' },
  ],
  relatedLinks: [
    { label: '查看云服务器', to: '/cloud-server' },
    { label: '查看帮助中心', to: '/help' },
    { label: '查看产品与服务', to: '/products' },
  ],
  cta: {
    title: '为高风险业务准备服务器方案',
    description: '先梳理业务峰值、攻击风险和系统配置，再选择合适资源。',
    primaryText: '查看产品配置',
    primaryTo: '/products',
    secondaryText: '提交咨询工单',
    secondaryConsolePath: '/client/tickets',
  },
}
