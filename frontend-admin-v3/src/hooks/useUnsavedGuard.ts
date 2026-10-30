import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { onBeforeUnmount, onMounted } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';

export interface UnsavedGuardOptions {
  /** 离开确认标题，默认「放弃未保存的修改？」 */
  title?: string;
  /** 离开确认正文，默认说明修改会丢失 */
  message?: string;
}

/**
 * 未保存离开保护（全项目唯一实现点）。
 *
 * 覆盖两条真实离开路径：
 * 1. 路由跳转 —— `onBeforeRouteLeave` 拦截，确认后才放行；
 * 2. 关闭或刷新标签页 —— `beforeunload` 交给浏览器弹原生提示。
 *
 * 必须在 setup 顶层调用。守卫绑定的是路由记录而不是组件生命周期，
 * 因此被 keep-alive 缓存的页面同样生效。
 */
export function useUnsavedGuard(isDirty: () => boolean, options: UnsavedGuardOptions = {}) {
  const title = options.title || '放弃未保存的修改？';
  const message = options.message || '当前页面存在未保存的修改，离开后这些修改会丢失。';

  function confirmLeave(): Promise<boolean> {
    return new Promise((resolve) => {
      const dialog = DialogPlugin.confirm({
        header: title,
        body: message,
        theme: 'warning',
        confirmBtn: { content: '放弃并离开', theme: 'danger' },
        cancelBtn: '继续编辑',
        onConfirm() {
          dialog.hide();
          resolve(true);
        },
        onCancel() {
          dialog.hide();
          resolve(false);
        },
        onClose() {
          dialog.hide();
          resolve(false);
        },
      });
    });
  }

  onBeforeRouteLeave(() => {
    if (!isDirty()) return true;
    return confirmLeave();
  });

  function handleBeforeUnload(event: BeforeUnloadEvent) {
    if (!isDirty()) return;
    event.preventDefault();
    event.returnValue = '';
  }

  onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
  });

  onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
  });
}
