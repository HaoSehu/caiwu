<template>
  <!-- A-01 复核结论：原生 button 已把 Enter/Space 合成为一次 click，不要另绑
       @keydown.enter/@keydown.space，否则会与原生合成叠加成按一次切两次。
       真正的问题是 click 会冒泡到 t-input 的根 div：node_modules 的
       es/input/hooks/useInput.mjs 里 onRootClick 无条件 inputRef.focus()，
       于是点一次眼睛图标焦点就被抢回输入框，键盘用户按完 Enter 后按 Space
       时按键会打进 input（e2e/auth.spec.ts 的 Space 用例因此失败）。
       用 .stop 截断冒泡，既保留原生键盘语义，又不被 t-input 抢焦点。 -->
  <button
    type="button"
    class="password-toggle"
    :aria-label="modelValue ? '隐藏密码' : '显示密码'"
    :aria-pressed="modelValue"
    @click.stop="$emit('update:model-value', !modelValue)"
  >
    <browse-icon v-if="modelValue" aria-hidden="true" />
    <browse-off-icon v-else aria-hidden="true" />
  </button>
</template>
<script setup lang="ts">
import { BrowseIcon, BrowseOffIcon } from 'tdesign-icons-vue-next';

defineProps<{ modelValue: boolean }>();
defineEmits<{ 'update:model-value': [value: boolean] }>();
</script>
<style scoped>
.password-toggle {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;

  /* 命中区做到 44px 触控基线：40（--td-comp-size-xl）+ 4（margin-xs），
     与 AuthShell 的 .auth-guide__cta 同一算法。
     原先写 --td-comp-size-m（32px）再用 ::after 上下外扩 6px 凑 44px，
     但 .t-input 自身是 overflow: hidden，外扩部分被整块裁掉 ——
     名义 44px、实测仍 32px。横向也不能靠外扩：同行右侧是 clearable 的清除按钮。
     实测改后按钮盒 44×44，输入框高 48（shared-auth.less 把 .t-input 拉到 comp-size-xxl）。 */
  min-width: calc(var(--td-comp-size-xl) + var(--td-comp-margin-xs));
  min-height: calc(var(--td-comp-size-xl) + var(--td-comp-margin-xs));
  justify-content: center;
  border-radius: var(--td-radius-small);
  color: var(--td-text-color-secondary);
  display: inline-flex;
  align-items: center;
}

.password-toggle:focus-visible {
  /* 描边宽度 2px 直接写：TDesign 没有 border-width 令牌，--td-radius-small 是圆角 */
  outline: 0.125rem solid var(--td-brand-color);
  outline-offset: 0.0625rem;
}
</style>
