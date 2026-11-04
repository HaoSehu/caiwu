<template>
  <t-drawer
    :visible="visible"
    :size="drawerSize"
    :header="drawerTitle"
    :footer="false"
    destroy-on-close
    @close="emit('close')"
  >
    <t-loading :loading="loading" size="small" class="drawer-loading">
      <template v-if="current">
        <t-alert v-if="current.channel === 'sms' && !current.providerTemplateId" theme="info" class="drawer-alert">
          短信正文会先渲染业务变量，再连同供应商模板 ID 一起交给短信插件发送；未填写模板 ID 时按短信宝纯文本通道处理。
        </t-alert>

        <t-tabs v-if="availableChannels.length > 1" :value="activeChannel" @change="handleChannelChange">
          <t-tab-panel
            v-for="option in availableChannels"
            :key="option.value"
            :value="option.value"
            :label="`${option.label}${option.isEnabled ? '' : '（禁用）'}`"
          />
        </t-tabs>

        <t-form v-if="currentChannel" ref="formRef" :data="form" :rules="rules" label-align="top">
          <t-form-item label="发送状态" name="isEnabled">
            <t-switch v-model="form.isEnabled" :custom-value="[true, false]" :label="['启用', '停用']" />
          </t-form-item>

          <t-form-item
            v-if="current.channel === 'email'"
            label="邮件主题"
            name="subject"
            :required-mark="!isOverridden('subject')"
          >
            <t-input v-model="form.subject" placeholder="请输入邮件主题" :disabled="isOverridden('subject')" />
            <template v-if="isOverridden('subject')" #help>
              <span class="override-hint">该字段由当前邮件插件接管，管理端不可修改</span>
            </template>
          </t-form-item>

          <div v-if="current.channel === 'email'" class="subject-preview">
            <span>主题预览</span>
            <strong>{{ renderedSubject }}</strong>
          </div>

          <t-form-item
            v-if="current.channel === 'sms'"
            label="供应商模板 ID"
            name="providerTemplateId"
            :required-mark="!isOverridden('provider_template_id')"
          >
            <t-input
              v-model="form.providerTemplateId"
              placeholder="请输入运营商审核通过的模板 ID"
              :disabled="isOverridden('provider_template_id')"
            />
            <template v-if="isOverridden('provider_template_id')" #help>
              <span class="override-hint">该字段由当前短信插件接管，管理端不可修改</span>
            </template>
          </t-form-item>

          <t-form-item :label="current.channel === 'sms' ? '短信正文' : 'HTML 正文'" name="content">
            <t-textarea
              v-model="form.content"
              class="message-code-input"
              :placeholder="current.channel === 'sms' ? '请输入短信正文' : '请输入邮件 HTML 正文'"
              :autosize="{ minRows: 14, maxRows: 24 }"
              :disabled="isOverridden('content')"
            />
            <template v-if="isOverridden('content')" #help>
              <span class="override-hint">
                该正文由当前{{ current.channel === 'sms' ? '短信' : '邮件' }}插件接管，管理端不可修改
              </span>
            </template>
          </t-form-item>
        </t-form>

        <t-card v-if="currentChannel" :bordered="false" class="variable-card">
          <template #title>可用变量</template>
          <div class="token-list">
            <t-tag v-for="variable in currentChannel.variables" :key="variable" variant="light">
              {{ variable }}
            </t-tag>
            <span v-if="!currentChannel.variables.length" class="muted-text">当前模板没有变量</span>
          </div>
        </t-card>

        <div v-if="current.channel === 'email'" class="preview-pane">
          <div class="pane-header">
            <strong>实时预览</strong>
            <span>按真实发送结构渲染</span>
          </div>
          <!-- 脚本执行防线：sandbox 不开放 allow-scripts/allow-same-origin，正文脚本无法运行也触达不到父域 -->
          <iframe class="preview-frame" :srcdoc="previewDocument" sandbox="" title="邮件模板预览" />
        </div>
        <div v-else class="sms-preview">
          <strong>短信预览</strong>
          <p>{{ renderedSmsContent }}</p>
        </div>
      </template>
    </t-loading>

    <template #footer>
      <t-space>
        <t-button variant="outline" @click="openTestSend">测试发送</t-button>
        <t-button variant="outline" :disabled="!current" @click="resetCurrentTemplate">恢复默认</t-button>
        <t-button theme="primary" :loading="saving" :disabled="!canManage || !current" @click="saveCurrentTemplate">
          保存模板
        </t-button>
      </t-space>
    </template>
  </t-drawer>
</template>
<script setup lang="ts">
import { escapeHtml, sanitizeRenderedHtml } from '@caiwu/shared/htmlSanitizer';
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref, watch } from 'vue';

import type { NotificationTemplateItem } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { resolveApiAssetUrl } from '@/utils/apiAssetUrl';
import { errorMessage } from '@/utils/userMessage';

type TemplateChannel = 'email' | 'sms';

interface TemplateDefinition extends NotificationTemplateItem {
  subject: string;
  content: string;
  providerTemplateId: string;
  isEnabled: boolean;
  defaultSubject: string;
  defaultContent: string;
  defaultProviderTemplateId: string;
  defaultIsEnabled: boolean;
}

interface ChannelOption {
  value: TemplateChannel;
  label: string;
  isEnabled: boolean;
  variables: string[];
}

interface SettingEntry {
  key: string;
  value: unknown;
}

interface SettingsMap {
  [key: string]: unknown;
}

interface DrawerRow {
  code: string;
  name: string;
  channels?: Record<string, boolean>;
}

const props = defineProps<{
  visible: boolean;
  row: DrawerRow | null;
  audience: 'customer' | 'admin';
  channel: TemplateChannel;
  canManage: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
  (e: 'channel-change', channel: TemplateChannel): void;
  (e: 'test-send', payload: { channel: TemplateChannel; code: string; name: string }): void;
}>();

const DEFAULT_SITE_NAME = '创欧云';
const DEFAULT_SITE_LOGO = '/branding/logo.svg';
const isMobile = useMediaQuery('(max-width: 767.98px)');
const drawerSize = computed(() => (isMobile.value ? '100%' : '720px'));

const loading = ref(false);
const saving = ref(false);
const formRef = ref<FormInstanceFunctions>();
const templates = ref<TemplateDefinition[]>([]);
const activeChannel = ref<TemplateChannel>('email');
const siteName = ref(DEFAULT_SITE_NAME);
const siteLogo = ref(DEFAULT_SITE_LOGO);

const form = reactive({
  isEnabled: true,
  subject: '',
  content: '',
  providerTemplateId: '',
});

const rules: Record<string, FormRule[]> = {
  subject: [{ required: true, message: '请输入邮件主题', type: 'error' }],
  providerTemplateId: [{ required: true, message: '请输入供应商模板 ID', type: 'error' }],
};

const availableChannels = computed<ChannelOption[]>(() =>
  templates.value.map((template) => ({
    value: template.channel,
    label: template.channel === 'sms' ? '短信' : '邮件',
    isEnabled: template.is_enabled !== false,
    variables: template.variables,
  })),
);

const current = computed(() => templates.value.find((template) => template.channel === activeChannel.value) ?? null);

/** 该字段是否被插件接管（插件优先，管理端只读） */
function isOverridden(field: string) {
  return (current.value?.overridden_fields || []).includes(field);
}
const currentChannel = computed(() => availableChannels.value.find((item) => item.value === activeChannel.value));
const drawerTitle = computed(() => {
  if (!props.row) return '消息详情';
  return `${props.canManage ? '编辑' : '查看'}消息 · ${props.row.name}`;
});

const previewBaseParams = computed<Record<string, string>>(() => ({
  site_name: siteName.value,
  site_logo: siteLogo.value,
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

function normalizeSettings(response: unknown): SettingsMap {
  if (Array.isArray(response)) {
    return Object.fromEntries((response as SettingEntry[]).map((item) => [item.key, item.value]));
  }
  const list = (response as { list?: unknown })?.list;
  if (Array.isArray(list)) {
    return Object.fromEntries((list as SettingEntry[]).map((item) => [item.key, item.value]));
  }
  return response && typeof response === 'object' ? (response as SettingsMap) : {};
}

function applySettings(template: NotificationTemplateItem, settings: SettingsMap): TemplateDefinition {
  const settingKeys = template.setting_keys || {};
  const subjectKey = settingKeys.subject || `email_template_subject_${template.code}`;
  const contentKey = settingKeys.content || `${template.channel}_template_content_${template.code}`;
  const providerTemplateIdKey =
    settingKeys.provider_template_id || `sms_template_provider_template_id_${template.code}`;
  const enabledKey = settingKeys.enabled || `${template.channel}_template_enabled_${template.code}`;
  const defaultSubject = template.subject || '';
  const defaultContent = template.content || '';
  const defaultProviderTemplateId = template.provider_template_id || '';
  const defaultIsEnabled = toBooleanValue(template.is_enabled ?? true);

  // 插件接管的字段以模板表为准：设置表里可能残留插件接管前保存的旧值，
  // 若继续优先读设置项，刚拉取的插件模板会被旧值覆盖（表现为抽屉里显示旧文案）。
  const overridden = template.overridden_fields || [];
  const pick = (field: string, settingKey: string, fallback: string) =>
    overridden.includes(field) ? fallback : settingValue(settings, settingKey, fallback);

  return {
    ...template,
    defaultSubject,
    defaultContent,
    defaultProviderTemplateId,
    defaultIsEnabled,
    subject: pick('subject', subjectKey, defaultSubject),
    content: pick('content', contentKey, defaultContent),
    providerTemplateId: pick('provider_template_id', providerTemplateIdKey, defaultProviderTemplateId),
    isEnabled: toBooleanValue(settingValue(settings, enabledKey, defaultIsEnabled)),
  };
}

async function loadTemplate() {
  if (!props.row) return;
  loading.value = true;
  try {
    const [detail, response, basicResponse] = await Promise.all([
      adminApi.settings.messageSceneDetail(props.row.code, { audience: props.audience }),
      adminApi.settings.list({ group: 'notification' }),
      adminApi.settings.list({ group: 'basic' }),
    ]);
    const settings = normalizeSettings(response);
    const basicSettings = normalizeSettings(basicResponse);
    siteName.value = String(basicSettings.site_name || DEFAULT_SITE_NAME);
    siteLogo.value = resolvePreviewLogo(String(basicSettings.site_logo || DEFAULT_SITE_LOGO));

    // 场景关联的模板引用只有 code/channel/name；正文与主题仍来自设置项与模板目录。
    const definitions = await adminApi.settings.notificationTemplates();
    const references = new Map(detail.templates.map((item) => [`${item.channel}:${item.code}`, item]));
    const matched = (definitions.list || []).filter((template) =>
      references.has(`${template.channel}:${template.code}`),
    );

    templates.value = matched.map((template) => applySettings(template, settings));
    activeChannel.value = chooseChannel(props.channel);
    syncForm();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载消息模板失败'));
    templates.value = [];
  } finally {
    loading.value = false;
  }
}

function chooseChannel(preferred: TemplateChannel): TemplateChannel {
  return templates.value.some((template) => template.channel === preferred)
    ? preferred
    : (templates.value[0]?.channel ?? 'email');
}

function syncForm() {
  const template = current.value;
  form.isEnabled = template?.isEnabled ?? true;
  form.subject = template?.subject ?? '';
  form.content = template?.content ?? '';
  form.providerTemplateId = template?.providerTemplateId ?? '';
  formRef.value?.clearValidate?.();
}

function handleChannelChange(value: unknown) {
  const channel = value === 'sms' ? 'sms' : 'email';
  if (channel === activeChannel.value) return;
  activeChannel.value = channel;
  syncForm();
  emit('channel-change', channel);
}

async function saveCurrentTemplate() {
  const template = current.value;
  if (!template || !props.canManage) return;

  const validateResult = await formRef.value?.validate?.();
  if (validateResult !== true) return;

  saving.value = true;
  try {
    const settingKeys = template.setting_keys || {};
    const settings: Record<string, unknown> = {
      [settingKeys.content || `${template.channel}_template_content_${template.code}`]: form.content,
      [settingKeys.enabled || `${template.channel}_template_enabled_${template.code}`]: form.isEnabled ? 1 : 0,
    };
    if (template.channel === 'email') {
      settings[settingKeys.subject || `email_template_subject_${template.code}`] = form.subject;
    }
    if (template.channel === 'sms') {
      settings[settingKeys.provider_template_id || `sms_template_provider_template_id_${template.code}`] =
        form.providerTemplateId || '';
    }

    await adminApi.settings.save({ group: 'notification', settings });
    MessagePlugin.success('模板已保存');
    emit('saved');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存模板失败'));
  } finally {
    saving.value = false;
  }
}

function resetCurrentTemplate() {
  const template = current.value;
  if (!template) return;
  template.isEnabled = template.defaultIsEnabled;
  form.isEnabled = template.defaultIsEnabled;
  form.subject = template.defaultSubject || '';
  form.content = template.defaultContent;
  form.providerTemplateId = template.defaultProviderTemplateId || '';
  formRef.value?.clearValidate?.();
}

function openTestSend() {
  const template = current.value;
  if (!template) return;
  emit('test-send', { channel: template.channel, code: template.code, name: template.name });
}

const previewParams = computed<Record<string, string>>(() => {
  const params: Record<string, string> = { ...previewBaseParams.value };
  const template = current.value;
  template?.variables.forEach((key) => {
    if (!(key in params)) params[key] = `示例${key}`;
  });
  return params;
});

const renderedSubject = computed(() => renderTemplateText(form.subject, previewParams.value) || '未设置主题');
const renderedSmsContent = computed(() => renderTemplateText(form.content, previewParams.value) || '未设置短信正文');

/**
 * 预览壳统一走 shared/htmlSanitizer 节点级清洗并固定 CSP：
 * 模板正文由管理员手写、变量值来自业务数据，二者都可能带脚本或外链资源，
 * 不能直接拼进 srcdoc（sandbox 是第二道防线，不是唯一防线）。
 */
const previewDocument = computed(() => {
  const params = previewParams.value;
  const subject = renderedSubject.value;
  const content = renderTemplateContent(form.content, params);
  const bodyHtml = looksLikeFullHtmlDocument(content)
    ? sanitizePreviewBody(content)
    : sanitizeRenderedHtml(content, {
        imageAltFallback: '图片',
        allowStyleAttr: true,
        allowDataImage: true,
      });
  const csp =
    '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data: https: http:;">';
  return `<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8">${csp}<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>${escapeHtml(subject)}</title></head><body>${bodyHtml}</body></html>`;
});

/** 完整 HTML 文档只保留 body 内容，head 内的脚本/外链样式一律丢弃 */
function sanitizePreviewBody(doc: string) {
  const parsed = new DOMParser().parseFromString(doc, 'text/html');
  const inner = parsed.body ? parsed.body.innerHTML : '';
  return sanitizeRenderedHtml(inner, {
    imageAltFallback: '图片',
    allowStyleAttr: true,
    allowDataImage: true,
  });
}

function renderTemplateContent(template: string, params: Record<string, string>) {
  if (looksLikeHtml(template))
    return renderTemplateWithResolver(template, params, (key) => escapeHtml(params[key] ?? ''), true);
  return String(renderTemplateText(template, params) || '')
    .split(/\n{2,}/)
    .map((block) => `<p>${escapeHtml(block).replace(/\n/g, '<br>')}</p>`)
    .join('');
}

function renderTemplateText(template: string, params: Record<string, string>) {
  return renderTemplateWithResolver(template, params, (key) => String(params[key] ?? ''));
}

function renderTemplateWithResolver(
  template: string,
  params: Record<string, string>,
  resolver: (key: string) => string,
  htmlMode = false,
) {
  let rendered = String(template || '').replace(/\{\{#(\w+)\}\}([\s\S]*?)\{\{\/\1\}\}/g, (_match, key, content) => {
    return hasValue(params[key]) ? content : '';
  });
  rendered = rendered.replace(/\{\{\s*(\w+)\s*\}\}/g, (_match, key) => resolver(key));
  return htmlMode
    ? rendered.trim()
    : rendered
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function looksLikeHtml(template: string) {
  return /<[a-z][a-z0-9]*(?:\s|>)/i.test(String(template || '').trim());
}

function looksLikeFullHtmlDocument(template: string) {
  return /^(?:<!doctype\s+html|<html\b)/i.test(String(template || '').trim());
}

function hasValue(value: unknown) {
  return ![null, undefined, '', false].includes(value as null | undefined | string | boolean);
}

function settingValue<T>(settings: SettingsMap, key: string, fallback: T): T {
  // 用 hasOwnProperty.call 而非 Object.hasOwn：后者要 Chrome 93 / Safari 15.4，超出仓库声明的浏览器基线
  return Object.prototype.hasOwnProperty.call(settings, key) ? ((settings[key] ?? '') as T) : fallback;
}

function toBooleanValue(value: unknown) {
  if (value === true || value === 1) return true;
  return ['1', 'true', 'on', 'yes'].includes(String(value).trim().toLowerCase());
}

function resolvePreviewLogo(value: unknown) {
  const logo = String(value || '').trim() || DEFAULT_SITE_LOGO;
  const resolved = resolveApiAssetUrl(logo, import.meta.env.VITE_API_BASE_URL);
  if (/^(?:https?:)?\/\//i.test(resolved) || resolved.startsWith('data:') || resolved.startsWith('/')) return resolved;
  return `/${resolved}`;
}

// 依赖 visible 与 row.code 两处：父组件先置 row 再置 visible，单看 visible 时
// 可能在 row 尚未写入的同一拍触发加载，导致抽屉空白。
watch(
  () => [props.visible, props.row?.code] as const,
  ([visible]) => {
    if (!visible) return;
    templates.value = [];
    void loadTemplate();
  },
  { immediate: true },
);
</script>
