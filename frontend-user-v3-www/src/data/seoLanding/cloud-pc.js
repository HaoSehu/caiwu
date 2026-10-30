// 云电脑落地页文案：只被对应的落地页薄壳静态引入，避免 5 页全量文案进入同一个 chunk。
export default {
  slug: 'cloud-pc',
  path: '/cloud-pc',
  routeName: 'WwwSeoCloudPc',
  keyword: '云电脑',
  title: '云电脑 - 远程办公与轻量桌面云方案 - 创欧云',
  description: '创欧云云电脑适合远程办公、轻量桌面、软件测试和临时工作环境，提供云端资源选择与账号管理能力。',
  keywords: '云电脑,云桌面,远程办公云电脑,桌面云,创欧云',
  changefreq: 'weekly',
  priority: '0.7',
  hero: {
    eyebrow: '云端桌面',
    title: '灵活可用的云电脑',
    summary: '为远程办公、软件测试、临时桌面和轻量操作环境提供云端资源，减少本地设备差异带来的维护成本。',
    points: ['远程访问', '轻量桌面', '账号管理'],
    stats: [
      { value: '云端运行', label: '减少本地设备依赖' },
      { value: '灵活使用', label: '适合临时与长期场景' },
      { value: '统一入口', label: '控制台管理与续费' },
    ],
  },
  visual: { src: '/img/website/solutions/government.svg', alt: '云电脑远程办公示意' },
  features: [
    { title: '降低本地设备门槛', description: '常用软件、测试环境和轻量桌面可运行在云端资源上。' },
    { title: '适合临时项目', description: '短期测试、外包协作和演示环境可以快速准备。' },
    { title: '方便账号管理', description: '通过统一账户查看服务、续费和售后记录。' },
  ],
  scenarios: [
    { title: '远程办公', description: '为员工或临时协作者提供相对统一的云端操作环境。' },
    { title: '软件测试', description: '隔离测试环境，减少对个人电脑配置和系统版本的依赖。' },
    { title: '演示与培训', description: '在短期活动、培训和客户演示中快速准备可用桌面。' },
  ],
  faqs: [
    { question: '云电脑适合哪些人使用？', answer: '适合远程办公、软件测试、临时项目、培训演示和轻量桌面使用场景。' },
    { question: '云电脑能替代所有本地电脑吗？', answer: '不一定。高性能图形、专业硬件和低延迟外设场景需要单独评估。' },
    { question: '云电脑和云服务器有什么区别？', answer: '云电脑更偏桌面操作体验，云服务器更偏业务系统和服务端应用运行。' },
  ],
  relatedLinks: [
    { label: '查看云服务器', to: '/cloud-server' },
    { label: '查看产品与服务', to: '/products' },
    { label: '查看帮助中心', to: '/help' },
  ],
  cta: {
    title: '为远程办公准备云端桌面',
    description: '先确认软件、系统和使用周期，再选择合适的云电脑资源。',
    primaryText: '查看产品配置',
    primaryTo: '/products',
    secondaryText: '注册账号',
    secondaryConsolePath: '/client/register',
  },
}
