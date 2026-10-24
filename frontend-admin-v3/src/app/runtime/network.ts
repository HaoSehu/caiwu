import { initRuntimeConnectionHints } from '@caiwu/shared/runtime';

interface ConnectionHintOptions {
  apiBaseUrl?: string;
  urls?: Array<string | undefined | null>;
}

/**
 * 初始化运行时连接预热：把 API 域加入 dns-prefetch / preconnect。
 * 资产域与站点同源时无需预热，跨域的接口域才是首屏请求的真实瓶颈（审查条目 D6）。
 */
export function initAdminRuntimeConnectionHints(options: ConnectionHintOptions = {}) {
  initRuntimeConnectionHints(options);
}
