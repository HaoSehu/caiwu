<template>
  <div class="email-template-detail-page">
    <div class="template-toolbar">
      <t-button theme="primary" variant="text" @click="goBack">
        <template #icon><chevron-left-icon /></template>
        返回列表
      </t-button>
      <t-space>
        <t-button variant="outline" :loading="loading" @click="loadSettings">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
        <t-button variant="outline" :disabled="!templateDefinition" @click="resetCurrentTemplate">恢复默认</t-button>
        <t-button theme="primary" :loading="saving" :disabled="!templateDefinition" @click="saveCurrentTemplate">保存模板</t-button>
      </t-space>
    </div>

    <template v-if="templateDefinition">
      <t-card :bordered="false" :loading="loading">
        <template #title>可用变量</template>
        <div class="token-list">
          <t-tag v-for="variable in templateDefinition.variables" :key="variable" variant="light">{{ variable }}</t-tag>
        </div>
        <div class="preview-params">
          <article v-for="variable in templateDefinition.variables" :key="`${variable}-sample`">
            <span>{{ variable }}</span>
            <strong>{{ getPreviewValue(variable) }}</strong>
          </article>
        </div>
      </t-card>

      <t-card :bordered="false" :loading="loading">
        <t-form :data="templateDefinition" label-align="top">
          <t-form-item v-if="templateDefinition.channel === 'email'" label="邮件主题" name="subject">
            <t-input v-model="templateDefinition.subject" placeholder="请输入邮件主题" />
          </t-form-item>
          <t-form-item v-if="templateDefinition.channel === 'sms'" label="供应商模板 ID" name="providerTemplateId">
            <t-input v-model="templateDefinition.providerTemplateId" placeholder="请输入运营商审核通过的模板 ID" />
          </t-form-item>
          <div v-if="templateDefinition.channel === 'email'" class="subject-preview">
            <span>主题预览</span>
            <strong>{{ renderPreviewSubject(templateDefinition) }}</strong>
          </div>

          <div class="editor-grid">
            <section class="editor-pane">
              <div class="pane-header">
                <strong>{{ templateDefinition.channel === 'sms' ? '短信正文' : 'HTML 正文片段' }}</strong>
                <span>{{ templateDefinition.channel === 'sms' ? '业务层会先渲染正文，再连同供应商模板 ID 一起交给短信插件发送。' : '未检测到 HTML 时会按段落转为预览。' }}</span>
              </div>
              <t-textarea
                v-model="templateDefinition.content"
                class="template-code-input"
                :placeholder="templateDefinition.channel === 'sms' ? '请输入短信正文' : '请输入 HTML 正文片段'"
                :autosize="{ minRows: 18, maxRows: 26 }"
              />
            </section>

            <section class="preview-pane">
              <div class="pane-header">
                <strong>实时预览</strong>
                <span>{{ templateDefinition.channel === 'sms' ? '短信内容' : `${siteName} 站点风格` }}</span>
              </div>
              <iframe
                v-if="templateDefinition.channel === 'email'"
                class="preview-frame"
                :srcdoc="buildPreviewDocument(templateDefinition)"
                sandbox="allow-same-origin"
                title="邮件模板预览"
              />
              <div v-else class="sms-preview">
                <p>{{ renderPreviewSmsContent(templateDefinition) }}</p>
              </div>
            </section>
          </div>
        </t-form>
      </t-card>
    </template>

    <t-card v-else :bordered="false">
      <t-empty title="模板不存在" description="当前模板编号不在系统通知模板目录中。">
        <t-button theme="primary" @click="goBack">返回模板列表</t-button>
      </t-empty>
    </t-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeftIcon, RefreshIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';

import { adminApi } from '@/api/admin';

import './index.less';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const saving = ref(false);
const siteName = '创欧云';
const templateDefinitions = ref([]);

const templateCode = computed(() => String(route.params.code || '').trim());
const templateChannel = computed(() => (route.path.includes('/sms-templates/') ? 'sms' : 'email'));
const templateDefinition = computed(() => templateDefinitions.value.find((item) => item.code === templateCode.value));
const currentTab = computed(() => {
  if (route.query.tab === 'admin' || route.query.tab === 'user') return route.query.tab;
  return templateDefinition.value?.audience === 'admin' ? 'admin' : 'user';
});

const previewBaseParams = computed(() => ({
  site_name: siteName,
  display_name: '张三',
  username: 'zhangsan',
  recipient_name: '运维管理员',
  email: 'demo@example.com',
  client_email: 'client@example.com',
  client_name: '测试用户',
  login_at: '2026-04-01 14:30:00',
  ip: '203.0.113.25',
  device: 'Windows / Chrome',
  code: '482915',
  expire_minutes: '10',
  min: '5',
  service_name: '香港云服务器 CSP-2G',
  days_left: '3',
  expires_at: '2026-04-10 23:59:59',
  billing_cycle_label: '月付',
  urgency_message: '您的服务将在 3 天后到期，请提前完成续费。',
  invoice_no: 'zd202604011430004821',
  order_no: 'dd202604011430004821',
  order_id: 'dd202604011430004821',
  product_name: 'ecs.g9i.2c2g 2 vCPU 2G',
  product_main_ip: '203.0.113.18',
  product_username: 'administrator',
  operating_system: 'Ubuntu 22.04',
  addon_ip: '203.0.113.19',
  activated_at: '2026-04-01 14:36:00',
  hostname: 'hk-node-01',
  amount: '199.00',
  due_date: '2026-04-05',
  due_at: '2026-04-05 23:59:59',
  paid_at: '2026-04-01 10:12:33',
  refunded_at: '2026-04-02 09:10:00',
  payment_method: '支付宝',
  trade_no: '2026040100001001',
  notice_title: '账单提醒',
  notice_message: '请在到期前完成支付，以免影响关联服务。',
  status_label: '待支付',
  paid_amount: '199.00',
  order_total_fee: '199.00',
  remark: '人工核账通过',
  ticket_id: '1024',
  ticket_subject: '实例网络不通',
  ticket_created_at: '2026-04-01 14:40:00',
  ticket_closed_at: '2026-04-04 14:40:00',
  department: '技术支持',
  priority: '高',
  status: '处理中',
  message_preview: '您好，实例无法 SSH 登录，请协助排查。',
  staff_name: '技术支持 A',
  tickets_url: '/client/tickets',
  login_tip: '如您尚未登录，请先登录后查看工单详情。',
  bind_type: '手机',
  bind_account: '138****8000',
  registered_at: '2026-04-01 09:00:00',
  terminated_at: '2026-05-10 03:00:00',
  resumed_at: '2026-04-02 12:00:00',
  approved_at: '2026-04-03 16:00:00',
  bound_at: '2026-04-01 09:30:00',
}));

function normalizeSettings(response) {
  if (Array.isArray(response)) return Object.fromEntries(response.map((item) => [item.key, item.value]));
  if (response && Array.isArray(response.list)) return Object.fromEntries(response.list.map((item) => [item.key, item.value]));
  return response && typeof response === 'object' ? response : {};
}

function applySettings(template, settings) {
  const settingKeys = template.setting_keys || {};
  const subjectKey = settingKeys.subject || `email_template_subject_${template.code}`;
  const contentKey = settingKeys.content || `${template.channel}_template_content_${template.code}`;
  const providerTemplateIdKey = settingKeys.provider_template_id || `sms_template_provider_template_id_${template.code}`;
  const defaultSubject = template.subject || '';
  const defaultContent = template.content || '';
  const defaultProviderTemplateId = template.provider_template_id || '';

  return {
    ...template,
    defaultSubject,
    defaultContent,
    defaultProviderTemplateId,
    subject: settings[subjectKey] || defaultSubject,
    content: settings[contentKey] || defaultContent,
    providerTemplateId: settings[providerTemplateIdKey] || defaultProviderTemplateId,
  };
}

async function loadSettings() {
  loading.value = true;
  try {
    const [templateResponse, response] = await Promise.all([
      adminApi.settings.notificationTemplates({ channel: templateChannel.value }),
      adminApi.settings.list({ group: 'notification' }),
    ]);
    const settings = normalizeSettings(response);
    templateDefinitions.value = (templateResponse.list || []).map((template) => applySettings(template, settings));
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载通知模板失败'));
  } finally {
    loading.value = false;
  }
}

async function saveCurrentTemplate() {
  if (!templateDefinition.value) return;
  saving.value = true;
  try {
    const settingKeys = templateDefinition.value.setting_keys || {};
    const settings = {
      [settingKeys.content || `${templateDefinition.value.channel}_template_content_${templateDefinition.value.code}`]: templateDefinition.value.content,
    };

    if (templateDefinition.value.channel === 'email') {
      settings[settingKeys.subject || `email_template_subject_${templateDefinition.value.code}`] = templateDefinition.value.subject;
    }
    if (templateDefinition.value.channel === 'sms') {
      settings[settingKeys.provider_template_id || `sms_template_provider_template_id_${templateDefinition.value.code}`] =
        templateDefinition.value.providerTemplateId || '';
    }

    await adminApi.settings.save({
      group: 'notification',
      settings,
    });
    MessagePlugin.success('模板已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存模板失败'));
  } finally {
    saving.value = false;
  }
}

function resetCurrentTemplate() {
  if (templateDefinition.value) resetTemplate(templateDefinition.value);
}

function resetTemplate(template) {
  template.subject = template.defaultSubject || '';
  template.content = template.defaultContent;
  template.providerTemplateId = template.defaultProviderTemplateId || '';
}

function goBack() {
  router.push({ path: '/admin/notifications', query: templateChannel.value === 'sms' ? { tab: 'sms-templates' } : { tab: currentTab.value } });
}

function getPreviewParams(template) {
  const params = { ...previewBaseParams.value };
  template.variables.forEach((key) => {
    if (!(key in params)) params[key] = `示例${key}`;
  });
  return params;
}

function getPreviewValue(key) {
  if (!templateDefinition.value) return '-';
  return String(getPreviewParams(templateDefinition.value)[key] ?? '-');
}

function renderPreviewSubject(template) {
  return renderTemplateText(template.subject, getPreviewParams(template)) || '未设置主题';
}

function renderPreviewSmsContent(template) {
  return renderTemplateText(template.content, getPreviewParams(template)) || '未设置短信正文';
}

function buildPreviewDocument(template) {
  const params = getPreviewParams(template);
  const subject = renderPreviewSubject(template);
  const content = renderTemplateContent(template.content, params);
  return `<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>${escapeHtml(subject)}</title><style>
body{margin:0;background:#f3f4f6;font-family:"PingFang SC","Microsoft YaHei",Arial,sans-serif;color:#1f2329}.mail-shell{padding:32px 12px}.mail-card{max-width:680px;margin:0 auto;background:#fff;border:1px solid #cfd6e4}.mail-header{padding:24px 28px 20px;border-top:4px solid #1f4b99;border-bottom:1px solid #d9e0ec;background:#f8fafc}.mail-brand strong{display:block;font-size: var(--td-font-size-size-5, 18px);color:#162033}.mail-brand span{display:block;margin-top:6px;font-size: var(--td-font-size-size-1, 12px);color:#5b6575}.mail-body{padding:28px}.mail-title{margin:0;font-size:28px;line-height:1.4;color:#162033}.mail-summary{margin:12px 0 0;font-size: var(--td-font-size-size-3, 14px);line-height:1.8;color:#4b5565}.mail-divider{height:1px;margin:24px 0;background:#d9e0ec}.mail-content{font-size: var(--td-font-size-size-3, 14px);line-height:1.85}.mail-content p{margin:0 0 14px}.mail-panel{margin:18px 0;padding:16px 18px;border:1px solid #d9e0ec;background:#f8fafc}.mail-code{display:inline-block;margin:8px 0 16px;padding:14px 18px;border:1px solid #1f4b99;background:#eef4ff;color:#1f4b99;font-size:28px;font-weight:700;letter-spacing:.18em}.mail-kv{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid #d9e0ec}.mail-kv:last-child{border-bottom:0}.mail-kv span{color:#4e5969}.mail-kv strong{text-align:right}.mail-button{display:inline-block;margin-top:12px;padding:12px 18px;background:#1f4b99;color:#fff;text-decoration:none}.mail-footer{padding:0 28px 28px;color:#6b7280;font-size: var(--td-font-size-size-1, 12px);line-height:1.8}@media(max-width:640px){.mail-body,.mail-footer,.mail-header{padding-left:18px;padding-right:18px}.mail-title{font-size: var(--td-font-size-size-7, 22px)}.mail-kv{display:block}.mail-kv strong{display:block;margin-top:6px;text-align:left}}
</style></head><body><div class="mail-shell"><div class="mail-card"><div class="mail-header"><div class="mail-brand"><strong>${escapeHtml(siteName)}</strong><span>自动通知邮件</span></div></div><div class="mail-body"><h1 class="mail-title">${escapeHtml(subject)}</h1><p class="mail-summary">您当前看到的是模板预览效果，发送时会按同样的站点外壳渲染。</p><div class="mail-divider"></div><div class="mail-content">${content}</div></div><div class="mail-footer">此邮件由 ${escapeHtml(siteName)} 系统自动发送，请勿直接回复。</div></div></div></body></html>`;
}

function renderTemplateContent(template, params) {
  if (looksLikeHtml(template)) return renderTemplateWithResolver(template, params, (key) => escapeHtml(params[key] ?? ''), true);
  return String(renderTemplateText(template, params) || '')
    .split(/\n{2,}/)
    .map((block) => `<p>${escapeHtml(block).replace(/\n/g, '<br>')}</p>`)
    .join('');
}

function renderTemplateText(template, params) {
  return renderTemplateWithResolver(template, params, (key) => String(params[key] ?? ''));
}

function renderTemplateWithResolver(template, params, resolver, htmlMode = false) {
  let rendered = String(template || '').replace(/\{\{#([a-zA-Z0-9_]+)\}\}([\s\S]*?)\{\{\/\1\}\}/g, (_match, key, content) =>
    hasValue(params[key]) ? content : '',
  );
  rendered = rendered.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_match, key) => resolver(key));
  return htmlMode ? rendered.trim() : rendered.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
}

function looksLikeHtml(template) {
  return /<([a-z][a-z0-9]*)(\s|>)/i.test(String(template || '').trim());
}

function hasValue(value) {
  return ![null, undefined, '', false].includes(value);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function errorMessage(error, fallback) {
  return String(error?.response?.data?.message || error?.message || fallback);
}

onMounted(loadSettings);
</script>
