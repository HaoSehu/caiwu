<template>
  <t-drawer
    v-if="drawerAlive"
    :visible="visibleValue"
    placement="bottom"
    :size="size"
    :header="false"
    :footer="false"
    :close-btn="false"
    :close-on-overlay-click="closeOnPressModal"
    attach="body"
    class="ms-drawer"
    @update:visible="handleVisibleChange"
  >
    <!-- 读屏语义挂在内层面板而不是 .t-drawer 外壳：.t-drawer 是全屏 fixed 容器
         （pointer-events: none，含遮罩），把 role=dialog 放外壳会让焦点陷阱与
         "对话框"边界覆盖到遮罩上。 -->
    <div
      ref="msRef"
      class="ms"
      role="dialog"
      aria-modal="true"
      :aria-label="title || '选择'"
      tabindex="-1"
      @keydown="handlePanelKeydown"
    >
      <div class="ms-head">
        <button
          v-if="cancelText"
          type="button"
          class="ms-action"
          @click="handleCancel"
        >
          {{ cancelText }}
        </button>
        <span
          v-else
          class="ms-action ms-action--placeholder"
          aria-hidden="true"
        ></span>
        <strong v-if="title" class="ms-title">{{ title }}</strong>
        <span v-else class="ms-title" aria-hidden="true"></span>
        <button
          v-if="confirmText"
          type="button"
          class="ms-action ms-action--primary"
          @click="$emit('confirm')"
        >
          {{ confirmText }}
        </button>
        <span
          v-else
          class="ms-action ms-action--placeholder"
          aria-hidden="true"
        ></span>
      </div>

      <div class="ms-body">
        <slot />
      </div>
    </div>
  </t-drawer>
</template>

<script>
// 这几个变量必须放在模块作用域，不能放 <script setup>。
// <script setup> 的内容会被编译进 setup() 函数体，每个组件实例各有一份；
// 而滚动锁是「整个文档只有一把锁」的语义：商品页同时挂 7 个 MobileSheet，
// 「A 先关、B 还开着」时若各自持有 lockCount，B 会误判无人持有而提前解锁，
// 随后再关 B 又会把 body 再锁一次，最终没有任何弹层却永久停在 position:fixed
// （用户只能刷新）。savedFocusEl 同理：它记录「打开弹层前的焦点落点」，
// 也必须由最后一次解锁的那个实例还回去。
let lockCount = 0;
let lockedScrollY = 0;
let savedBodyStyles = null;
let savedFocusEl = null;
</script>

<script setup>
import { computed, nextTick, ref, watch, onBeforeUnmount } from "vue";

// t-drawer 没有 opened 事件（node_modules/tdesign-vue-next/es/drawer/props.mjs 只有
// onBeforeOpen / onBeforeClose / onClose / onCloseBtnClick / onConfirm / onCancel /
// onOverlayClick / onEscKeydown / onSizeDragEnd），但三个 Picker（Option / Os /
// Region，MobileRangePicker 不用本组件）依赖 @opened 在抽屉
// 入场完成后测量 DOM（滚轮项高度、居中定位），emits 契约不可改。
// 因此这里按入场过渡时长补发：.t-drawer__content-wrapper 的
// transform 0.28s cubic-bezier(.38,0,.24,1)（es/drawer/style/index.css），取 300ms 略有余量，
// 与迁移前抽屉组件的 opened 触发时机等价。
const OPEN_ANIMATION_MS = 300;
// 关闭后延迟销毁 DOM 的余量，需大于上面的入场/退场时长。
const DESTROY_DELAY_MS = 350;

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  visible: {
    type: Boolean,
    default: undefined,
  },
  size: {
    type: String,
    default: "40%",
  },
  title: {
    type: String,
    default: "",
  },
  cancelText: {
    type: String,
    default: "",
  },
  confirmText: {
    type: String,
    default: "",
  },
  closeOnPressModal: {
    type: Boolean,
    default: true,
  },
  // 说明：曾提供 confirmLoading / confirmDisabled 两个提交态开关，但全仓
  // 零调用方——本项目所有 MobileSheet 的 confirm 都是同步的本地选择确认，
  // 唯一的真实提交入口是商品页的 .buy-btn（自带 disabled 三重守卫）。
  // 按「不保留无调用方的旧代码」清理，需要异步提交态时再按实际场景补。
});

const emit = defineEmits([
  "update:modelValue",
  "update:visible",
  "close",
  "cancel",
  "confirm",
  "opened",
]);

const visibleValue = computed(() => props.visible ?? props.modelValue);

// 抽屉关闭后延迟销毁 DOM，避免 10+ 个隐藏抽屉常驻 DOM 和 aria-modal 污染
const drawerAlive = ref(false);
const msRef = ref(null);
let destroyTimer = null;
let openTimer = null;

// 抽屉打开期间锁住背景滚动。t-drawer 自带的 preventScrollThrough 在本组件
// 的用法下**结构性不可达**，因此必须在站点侧补锁：
// drawer.mjs 里 addStyleElToHead() 在 updateVisibleState() 之前调用，而前者要求
// isVisible.value 已为真；首次打开时它必然还是 false，等随后置真也不会再补调 →
// 注入的那段 <style> 永远进不了 <head>（实测开合两次 head 里始终没有
// style[data-id^="td_drawer_"]）。结果就是打开抽屉后滚轮能把背景滚 600px。
// 注意不要把它误记成 prop 组合问题：showInAttachedElement 与 attach 是两个独立
// prop，前者默认 false，drawer.mjs:268 的 !showInAttachedElement 恒为真，
// 与 attach="body" 无关。
//
// 计数与快照变量定义在文件顶部的独立 <script> 块里（模块作用域、跨实例共享），
// 不能放 <script setup>：那会被编译进 setup() 函数体，每个实例各持一份，双开时会
// 提前解锁甚至把 body 锁死。只在 0→1 时保存快照、1→0 时恢复，中间层开关不动 body 样式。

function lockBodyScroll() {
  lockCount += 1;
  // 已有抽屉在锁：只加计数，不动 body 样式（快照仍是最初见到的旧值）
  if (lockCount > 1) return;
  lockedScrollY = window.scrollY;
  savedFocusEl =
    document.activeElement && document.activeElement !== document.body
      ? document.activeElement
      : null;
  savedBodyStyles = {
    overflow: document.body.style.overflow,
    position: document.body.style.position,
    top: document.body.style.top,
    width: document.body.style.width,
  };
  // fixed 定位是移动端 Safari 唯一可靠的做法：只设 overflow:hidden 在 iOS 上仍可滚动
  document.body.style.overflow = "hidden";
  document.body.style.position = "fixed";
  document.body.style.top = `-${lockedScrollY}px`;
  document.body.style.width = "100%";
}

function unlockBodyScroll() {
  if (lockCount === 0) return;
  lockCount -= 1;
  if (lockCount > 0 || !savedBodyStyles) return;
  document.body.style.overflow = savedBodyStyles.overflow;
  document.body.style.position = savedBodyStyles.position;
  document.body.style.top = savedBodyStyles.top;
  document.body.style.width = savedBodyStyles.width;
  savedBodyStyles = null;
  // fixed 期间 scrollY 被清零，恢复后要回到原位，否则用户会看到页面跳回顶部
  window.scrollTo(0, lockedScrollY);
}

watch(
  visibleValue,
  (v, oldValue) => {
    clearTimeout(destroyTimer);
    if (v) {
      drawerAlive.value = true;
      lockBodyScroll();
      // 只在「关 → 开」这一跳补发 opened。watch 的源是 visibleValue 这个计算值，
      // 父组件因其它 prop 重渲染不会触发本回调；再看一眼 oldValue，
      // 把「挂载时本该就是打开态」与任何重复赋同值的边界一并挡掉，不重复补发。
      if (oldValue) return;
      clearTimeout(openTimer);
      openTimer = setTimeout(() => {
        if (!visibleValue.value) return;
        // 打开后把焦点移进弹层：t-drawer 不做焦点管理，不主动移入会让读屏与键盘用户
        // 停留在背后的页面上，Tab 会一路穿到弹层外的按钮。
        // 必须等这一帧之后：t-drawer 内部用 setTimeout 把 isVisible 置真才渲染面板，
        // 紧接着的 nextTick 里 msRef 仍是 null。
        void nextTick(() => msRef.value?.focus({ preventScroll: true }));
        emit("opened");
      }, OPEN_ANIMATION_MS);
    } else {
      clearTimeout(openTimer);
      unlockBodyScroll();
      // 等关闭动画完成后再销毁
      destroyTimer = setTimeout(() => {
        drawerAlive.value = false;
        // 焦点必须在销毁之后还：t-drawer 关闭时会先自己 focus 一次即将被移除的外壳
        // （es/drawer/drawer.mjs:164），同步还原会被它顶掉，焦点最终掉到 <body>。
        // 若此时已有别的弹层接管（lockCount > 0），交给那个弹层的焦点移入，不抢。
        // preventScroll：还焦点不能顺带把视口滚到该元素，否则刚还原的滚动位次又被打乱
        // 只在「确实由本实例还了焦点」时清空。清空必须放进 if 里：savedFocusEl 是模块级共享的，
        // 若像以前那样无条件置 null，别的实例那批延迟销毁定时器（每个挂载中的 MobileSheet
        // 在 immediate watch 里都会排一个）就会把刚存下的落点抹掉，导致关闭后焦点回不去。
        if (lockCount === 0 && savedFocusEl && document.contains(savedFocusEl)) {
          savedFocusEl.focus({ preventScroll: true });
          savedFocusEl = null;
        }
      }, DESTROY_DELAY_MS);
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  clearTimeout(destroyTimer);
  clearTimeout(openTimer);
  // 抽屉被父组件直接卸载时收尾，否则 body 会永远停在 fixed 状态
  unlockBodyScroll();
  // 卸载路径同样要处理保存的焦点落点：路由跳转/视口切换导致实例销毁时，
  // 关闭流程里那批延迟销毁定时器不会再跑，savedFocusEl 会留下一个陈旧引用。
  // 落点仍在文档里（例如只是切换断点）就还回去；已随页面移除则清空，避免
  // 下一次打开抽屉时 lockBodyScroll 覆盖它之前被旧引用干扰。
  // 只有真正释放了最后一把锁（lockCount === 0）的实例才动焦点，多弹层叠加时
  // 交给仍开着的那个弹层接管。
  if (lockCount === 0 && savedFocusEl) {
    if (document.contains(savedFocusEl)) {
      savedFocusEl.focus({ preventScroll: true });
    }
    savedFocusEl = null;
  }
});

// t-drawer 不做焦点陷阱。用 Tab / Shift+Tab 在弹层内首尾元素之间循环，
// 避免焦点逃到被遮罩挡住的背景页面上（WCAG 2.4.3 焦点顺序）。
//
// 选择器对齐 HTML 规范里「默认可聚焦」的完整集合，不能只列表单控件：
// - audio[controls] / video[controls]：带 controls 才有原生播放条可 Tab；
// - details > summary：第一个 summary 默认可聚焦（折叠区开关）；
// - iframe：默认落进 tab 序列；
// - [contenteditable]：富文本区可聚焦，这里按任何真值收（含 "true"/"plaintext-only"/""）；
// - [tabindex]:not([tabindex="-1"])：显式 tabindex 的非负值。
// 当前弹层内实测 11 个可聚焦元素（9 个 button + 2 个 tabindex="0" 的 .mrp-col），
// 少列这几类不会漏，但商品介绍弹层会渲染富文本，将来含链接/媒体就会漏。
const FOCUSABLE_SELECTOR = [
  "a[href]",
  "audio[controls]",
  "video[controls]",
  "details > summary",
  "iframe",
  "button:not([disabled])",
  "input:not([disabled])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  "[contenteditable]",
  '[tabindex]:not([tabindex="-1"])',
].join(", ");

function handlePanelKeydown(event) {
  if (event.key !== "Tab" || !msRef.value) return;
  const focusables = [...msRef.value.querySelectorAll(FOCUSABLE_SELECTOR)];
  if (!focusables.length) return;
  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  const active = document.activeElement;
  if (event.shiftKey) {
    if (active === first || active === msRef.value) {
      event.preventDefault();
      last.focus();
    }
    return;
  }
  if (active === last) {
    event.preventDefault();
    first.focus();
  }
}

function updateVisible(value) {
  emit("update:modelValue", value);
  emit("update:visible", value);
  if (!value) {
    emit("close");
  }
}

function handleVisibleChange(value) {
  updateVisible(value);
}

function handleCancel() {
  emit("cancel");
  updateVisible(false);
}
</script>

<style scoped lang="scss">
.ms {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: $bg-color-card;
}

.ms-head {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) 64px;
  align-items: center;
  gap: 12px;
  min-height: 46px;
  padding: 0 14px;
  border-bottom: 1px solid $divider-color;
  flex-shrink: 0;
}

.ms-title {
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ms-action {
  border: none;
  background: none;
  padding: 0;
  font-size: 14px;
  line-height: 46px;
  color: $text-color-primary;
  cursor: pointer;
  text-align: left;

  // 移动端无 hover，提供按压态反馈
  &:active {
    opacity: 0.55;
  }

  &--primary {
    color: $color-primary;
    font-weight: 600;
    text-align: right;
  }

  &--placeholder {
    pointer-events: none;
    visibility: hidden;
  }
}

.ms-body {
  flex: 1;
  overflow-y: auto;
}
</style>

<!-- t-drawer 会 teleport 到 body，scoped 作用域属性不会落到其内部节点；
     此处保留非 scoped，但全部规则以 .ms-drawer 命名空间收口，不污染全局（审查条目 C5）。

     不加 #app 前缀是有依据的：本组件给 t-drawer 传了 attach="body"，抽屉被 teleport
     到 <body> 下，与 index.html:54 的 <div id="app"> 是兄弟节点而非后代。因此入口 theme.css
     里那批 `#app .t-drawer__*` 移动端覆盖（含 body padding:16px、content-wrapper 强制
     100% 宽）对本组件根本不匹配，不存在"ID 压 class"的竞争；写上 #app 反而会让这里的
     规则永不命中，抽屉退回 TDesign 默认内边距与尺寸。

     圆角落在 .t-drawer__content-wrapper（抽屉可见的那层外壳，.t-drawer 本身是全屏
     fixed 容器）；body 归零内边距，把间距交给 .ms-head / .ms-body 自己管。 -->
<style lang="scss">
.ms-drawer .t-drawer__content-wrapper {
  border-radius: 18px 18px 0 0;
  overflow: hidden;
}

.ms-drawer .t-drawer__body {
  padding: 0;
  overflow: hidden;
}
</style>
