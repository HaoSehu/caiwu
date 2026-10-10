/* eslint-disable simple-import-sort/imports */
import { createApp } from 'vue';
import TDesign from 'tdesign-vue-next';

import App from './App.vue';
import { setupGlobalErrorHandling } from './app/runtime/errorHandling';
import { initAdminSessionActivityTracking } from './app/runtime/session';
import router from './router';
import { store } from './store';
import i18n from './locales';

import 'tdesign-vue-next/es/style/index.css';
import '../../theme.css';
import '@/style/index.less';
import './permission';

const app = createApp(App);

initAdminSessionActivityTracking();
setupGlobalErrorHandling(app);

app.use(TDesign);
app.use(store);
app.use(router);
app.use(i18n);

app.mount('#app');
