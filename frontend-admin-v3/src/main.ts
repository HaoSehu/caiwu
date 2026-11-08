/* eslint-disable simple-import-sort/imports */
import { createApp } from 'vue';

import App from './App.vue';
import { setupGlobalErrorHandling } from './app/runtime/errorHandling';
import { initAdminRuntimeConnectionHints } from './app/runtime/network';
import { initAdminSessionActivityTracking } from './app/runtime/session';
import router from './router';
import { store } from './store';
import i18n from './locales';

// TDesign 组件按需引入（见 vite.config.ts 的 createTDesignResolver）：
// 模板里的 <t-xxx> 解析到 tdesign-vue-next/es/<组件>/index，子入口自带 import './style/css.mjs'；
// 函数式插件同样走 es/{message,dialog}/plugin 子路径。不再全量 app.use(TDesign)，
// 也不经过包入口 barrel（barrel 会拉起全部组件入口，其样式 sideEffects 不可摇除，导致 CSS 全量进包）。
// 因此这里只需补一份基础变量与基础类。
import 'tdesign-vue-next/es/style/index.css';
// theme.css（设计令牌）由 @/style/index.less 首行引入，与用户控制台保持同一级联顺序
import '@/style/index.less';
import './permission';

const app = createApp(App);

initAdminSessionActivityTracking();
// 接口域与站点跨域（VITE_API_BASE_URL），启动即预热 dns-prefetch / preconnect（审查条目 D6）
initAdminRuntimeConnectionHints({ apiBaseUrl: import.meta.env.VITE_API_BASE_URL });
setupGlobalErrorHandling(app);

app.use(store);
app.use(router);
app.use(i18n);

app.mount('#app');
