/* eslint-disable simple-import-sort/imports */
import { createApp } from 'vue';

import App from './App.vue';
import router from './router';
import { store } from './store';
import { initClientRuntimeConnectionHints } from './app/runtime/network';
import { initClientSessionActivityTracking, setClientToken } from './app/runtime/session';

// TDesign 组件按需引入（见 vite.config.ts 的 createTDesignResolver）：
// 模板里的 <t-xxx> 解析到 tdesign-vue-next/es/<组件>/index，子入口自带 import './style/css.mjs'；
// 函数式插件同样走 es/{message,dialog}/plugin 子路径。不再全量 app.use(TDesign)，
// 也不经过包入口 barrel（barrel 会拉起全部组件入口，其样式 sideEffects 不可摇除，导致 CSS 全量进包）。
// 因此这里只需补一份基础变量与基础类。
import 'tdesign-vue-next/es/style/index.css';
import '@/style/index.less';
import './permission';

const app = createApp(App);

// 演练版：没有后端，预置假凭证让路由守卫放行；
// /v2/client/auth/info 由静态数据集返回演示用户。
if (import.meta.env.MODE === 'demo') {
  setClientToken('demo-client-token');
}

initClientSessionActivityTracking();
// 接口域与站点跨域（VITE_API_BASE_URL），启动即预热 dns-prefetch / preconnect。
// 极验验证码脚本来自 static.geetest.com，由本端显式声明（shared 不再硬编码业务域名）。
initClientRuntimeConnectionHints({
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL,
  urls: ['https://static.geetest.com'],
});

app.use(store);
app.use(router);

app.mount('#app');
