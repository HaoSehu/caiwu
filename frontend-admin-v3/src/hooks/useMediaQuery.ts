import { onBeforeUnmount, onMounted, ref } from 'vue';

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
