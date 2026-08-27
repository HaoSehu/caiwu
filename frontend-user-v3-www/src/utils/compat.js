/**
 * 老内核兼容层 - 为不支持现代 Web API 的浏览器提供桩实现
 */

/**
 * 创建 AbortController 实例或兼容桩
 * @returns {AbortController | { signal: undefined, abort: () => void }}
 */
export function createAbortController() {
  if (typeof AbortController === "function") {
    return new AbortController();
  }
  // 老内核降级：返回桩对象,下游已有 ?. 与条件传参天然兼容
  return {
    signal: undefined,
    abort() {},
  };
}
