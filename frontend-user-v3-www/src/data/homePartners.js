/**
 * 首页合作伙伴跑马灯条幅图数据
 * 来源：idcmax.com 合作伙伴条幅
 *
 * 技术债（待处理）：图片全部指向外站 CDN（https://qny.9yidc.cn/...），存在以下风险，
 * 需要后续下载到本仓库 public/img/website/partners/ 并改为本地路径：
 * 1. CDN 单点依赖：对方域名/DNS/证书任一环节变更或下线，首页合作伙伴区会整块裂图，
 *    且与本站发布节奏完全脱钩，无法在本仓库内回归验证。
 * 2. 第三方外链审计风险：图片请求会把访客 IP、UA、Referer 直接暴露给外部域名。
 * 3. 性能与体积不可控：源图为 PNG，无法压缩、不能按 DPR 出多倍图，也不支持格式协商。
 * 落地建议：下载并转 WebP，配合 srcset/sizes 输出 1x/2x 两档，
 *    同时补齐 width/height 避免布局抖动。
 * 注意：本阶段无网络下载授权，src 仍保持外链，请勿凭空捏造本地路径。
 */
export const partnerStrips = Object.freeze([
  // 第1行 — 正向滚动
  { id: 'row1', src: 'https://qny.9yidc.cn/Uploads/20260617/140933_94561c4769f2.png', alt: '合作伙伴标识墙第 1 组（共 6 组，创欧云计算生态伙伴）', direction: 'forward', speed: '18s' },
  // 第2行 — 反向滚动
  { id: 'row2', src: 'https://qny.9yidc.cn/Uploads/20260617/140936_3a199c951efc.png', alt: '合作伙伴标识墙第 2 组（共 6 组，创欧云计算生态伙伴）', direction: 'reverse', speed: '21s' },
  // 第3行 — 正向滚动
  { id: 'row3', src: 'https://qny.9yidc.cn/Uploads/20260617/140939_2bcda2a1cca9.png', alt: '合作伙伴标识墙第 3 组（共 6 组，创欧云计算生态伙伴）', direction: 'forward', speed: '24s' },
  // 第4行 — 反向滚动
  { id: 'row4', src: 'https://qny.9yidc.cn/Uploads/20260617/140943_d0ce87f94b68.png', alt: '合作伙伴标识墙第 4 组（共 6 组，创欧云计算生态伙伴）', direction: 'reverse', speed: '27s' },
  // 第5行 — 正向滚动
  { id: 'row5', src: 'https://qny.9yidc.cn/Uploads/20260617/140946_a549ed163525.png', alt: '合作伙伴标识墙第 5 组（共 6 组，创欧云计算生态伙伴）', direction: 'forward', speed: '30s' },
  // 第6行 — 反向滚动
  { id: 'row6', src: 'https://qny.9yidc.cn/Uploads/20260617/140949_8d2808e4d416.png', alt: '合作伙伴标识墙第 6 组（共 6 组，创欧云计算生态伙伴）', direction: 'reverse', speed: '33s' },
])
