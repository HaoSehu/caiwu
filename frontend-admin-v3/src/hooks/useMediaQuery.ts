import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * 媒体查询订阅。
 *
 * 每个调用方持有自己的 ref 与 MediaQueryList：管理端 40 个页面虽然共用同一个
 * 查询串，但各页面生命周期独立，共享单例 ref 会在页面切换、组件卸载时相互干扰
 * （曾出现「标记存在但不可见」的误判）。这里只保证查询串来自常量，不追求共享实例。
 */
export function useMediaQuery(query: string) {
  const canMatch = typeof window !== 'undefined' && typeof window.matchMedia === 'function';
  // 同步取一次初值：否则首帧恒为 false，移动端会先渲染桌面分支再切换。
  const matches = ref(canMatch ? window.matchMedia(query).matches : false);
  let mediaQuery: MediaQueryList | null = null;

  const update = () => {
    matches.value = Boolean(mediaQuery?.matches);
  };

  onMounted(() => {
    if (!canMatch) return;
    mediaQuery = window.matchMedia(query);
    update();
    mediaQuery.addEventListener('change', update);
  });

  onBeforeUnmount(() => {
    mediaQuery?.removeEventListener('change', update);
    mediaQuery = null;
  });

  return matches;
}

/** 手机形态查询串，与 docs/DESIGN.md §7 的 `< 768px` 口径一致。 */
export const MOBILE_MEDIA_QUERY = '(max-width: 767.98px)';
