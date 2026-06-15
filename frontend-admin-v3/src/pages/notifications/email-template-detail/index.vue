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
          <t-form-item label="邮件主题" name="subject">
            <t-input v-model="templateDefinition.subject" placeholder="请输入邮件主题" />
          </t-form-item>
          <div class="subject-preview">
            <span>主题预览</span>
            <strong>{{ renderPreviewSubject(templateDefinition) }}</strong>
          </div>

          <div class="editor-grid">
            <section class="editor-pane">
              <div class="pane-header">
                <strong>HTML 正文片段</strong>
                <span>未检测到 HTML 时会按段落转为预览。</span>
              </div>
              <t-textarea
                v-model="templateDefinition.content"
                class="template-code-input"
                placeholder="请输入 HTML 正文片段"
                :autosize="{ minRows: 18, maxRows: 26 }"
              />
            </section>

            <section class="preview-pane">
              <div class="pane-header">
                <strong>实时预览</strong>
                <span>{{ siteName }} 站点风格</span>
              </div>
              <iframe class="preview-frame" :srcdoc="buildPreviewDocument(templateDefinition)" sandbox="allow-same-origin" title="邮件模板预览" />
            </section>
          </div>
        </t-form>
      </t-card>
    </template>

    <t-card v-else :bordered="false">
      <t-empty title="模板不存在" description="当前模板编号不在系统邮件模板目录中。">
        <t-button theme="primary" @click="goBack">返回邮件模板列表</t-button>
      </t-empty>
    </t-card>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
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

const adminTemplateCodes = new Set(['100010', '100011', '100013', '100014']);
const templateDefinitions = reactive(
  [
    { code: '100001', name: '邮箱验证码', description: '发送邮箱验证码时使用。', variables: ['code', 'expire_minutes'], defaultSubject: '邮箱验证码', defaultContent: '<p>您好，以下是本次邮箱验证所需的验证码：</p><div class="mail-code">{{code}}</div><p>请在 <strong>{{expire_minutes}}</strong> 分钟内完成验证。</p>' },
    { code: '100002', name: '登录提醒', description: '客户登录成功后发送安全提醒。', variables: ['site_name', 'display_name', 'email', 'login_at', 'ip', 'device'], defaultSubject: '{{site_name}} 登录提醒', defaultContent: '<p>您好，{{display_name}}：</p><p>检测到您的账户刚刚完成一次登录。</p><div class="mail-panel"><div class="mail-kv"><span>登录邮箱</span><strong>{{email}}</strong></div><div class="mail-kv"><span>登录时间</span><strong>{{login_at}}</strong></div><div class="mail-kv"><span>登录 IP</span><strong>{{ip}}</strong></div><div class="mail-kv"><span>登录设备</span><strong>{{device}}</strong></div></div>' },
    { code: '100003', name: '服务续费提醒', description: '服务到期前自动发送续费提醒。', variables: ['site_name', 'display_name', 'service_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message'], defaultSubject: '【{{site_name}}】服务续费提醒（{{days_left}} 天后到期）', defaultContent: '<p>您好，{{display_name}}：</p><p>{{urgency_message}}</p><div class="mail-panel"><div class="mail-kv"><span>服务名称</span><strong>{{service_name}}</strong></div><div class="mail-kv"><span>到期时间</span><strong>{{expires_at}}</strong></div><div class="mail-kv"><span>计费周期</span><strong>{{billing_cycle_label}}</strong></div></div>' },
    { code: '100004', name: '账单付款提醒', description: '账单到期前发送付款提醒。', variables: ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], defaultSubject: '【{{site_name}}】账单付款提醒 #{{invoice_no}}', defaultContent: '<p>您好，{{display_name}}：</p><p>以下账单即将到期，请及时处理。</p><div class="mail-panel"><div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>{{#order_no}}<div class="mail-kv"><span>关联订单</span><strong>{{order_no}}</strong></div>{{/order_no}}<div class="mail-kv"><span>账单金额</span><strong>¥{{amount}}</strong></div><div class="mail-kv"><span>应付日期</span><strong>{{due_date}}</strong></div></div><p>{{notice_message}}</p>' },
    { code: '100005', name: '账单逾期催款', description: '账单逾期后自动发送催缴提醒。', variables: ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], defaultSubject: '【{{site_name}}】账单逾期催款 #{{invoice_no}}', defaultContent: '<p>您好，{{display_name}}：</p><p>以下账单已逾期，请尽快完成支付。</p><div class="mail-panel"><div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div><div class="mail-kv"><span>账单金额</span><strong>¥{{amount}}</strong></div><div class="mail-kv"><span>原应付日期</span><strong>{{due_date}}</strong></div></div><p>{{notice_message}}</p>' },
    { code: '100006', name: '服务到期暂停通知', description: '服务因过期被系统暂停时发送通知。', variables: ['site_name', 'display_name', 'service_name', 'expires_at'], defaultSubject: '【{{site_name}}】服务到期暂停通知', defaultContent: '<p>您好，{{display_name}}：</p><p>您的服务因到期未续费，已被系统自动暂停。</p><div class="mail-panel"><div class="mail-kv"><span>服务名称</span><strong>{{service_name}}</strong></div><div class="mail-kv"><span>到期时间</span><strong>{{expires_at}}</strong></div></div>' },
    { code: '100007', name: '服务恢复通知', description: '服务续费成功恢复后发送通知。', variables: ['display_name', 'service_name', 'expires_at'], defaultSubject: '服务恢复通知', defaultContent: '<p>您好，{{display_name}}：</p><p>您的服务已恢复为正常状态。</p><div class="mail-panel"><div class="mail-kv"><span>服务名称</span><strong>{{service_name}}</strong></div><div class="mail-kv"><span>新的到期时间</span><strong>{{expires_at}}</strong></div></div>' },
    { code: '100008', name: '账单通知', description: '管理员主动发送账单提醒或账单确认时使用。', variables: ['site_name', 'display_name', 'notice_title', 'invoice_no', 'order_no', 'product_name', 'amount', 'status_label', 'due_at', 'paid_at', 'payment_method', 'trade_no', 'notice_message'], defaultSubject: '【{{site_name}}】{{notice_title}} #{{invoice_no}}', defaultContent: '<p>您好，{{display_name}}：</p><p>{{notice_title}}，以下为账单通知详情。</p><div class="mail-panel"><div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div><div class="mail-kv"><span>账单金额</span><strong>¥{{amount}}</strong></div><div class="mail-kv"><span>账单状态</span><strong>{{status_label}}</strong></div>{{#paid_at}}<div class="mail-kv"><span>支付时间</span><strong>{{paid_at}}</strong></div>{{/paid_at}}</div><p>{{notice_message}}</p>' },
    { code: '100009', name: '手动入账通知', description: '管理员手动设为已支付后发送通知。', variables: ['invoice_no', 'order_no', 'paid_amount', 'payment_method', 'paid_at', 'trade_no', 'remark'], defaultSubject: '账单支付确认通知', defaultContent: '<p>您好：</p><p>您的账单已由管理员手动入账。</p><div class="mail-panel"><div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div><div class="mail-kv"><span>支付金额</span><strong>¥{{paid_amount}}</strong></div><div class="mail-kv"><span>支付方式</span><strong>{{payment_method}}</strong></div>{{#remark}}<div class="mail-kv"><span>备注</span><strong>{{remark}}</strong></div>{{/remark}}</div>' },
    { code: '100010', name: '新工单提醒', description: '客户提交新工单后通知管理员。', variables: ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], defaultSubject: '【{{site_name}}】新工单提醒 #{{ticket_id}}', defaultContent: '<p>您好，{{recipient_name}}：</p><p>有客户提交了新的工单，请尽快处理。</p><div class="mail-panel"><div class="mail-kv"><span>工单编号</span><strong>#{{ticket_id}}</strong></div><div class="mail-kv"><span>工单标题</span><strong>{{ticket_subject}}</strong></div><div class="mail-kv"><span>提交用户</span><strong>{{client_name}}</strong></div></div><p>工单内容摘要：{{message_preview}}</p>' },
    { code: '100011', name: '工单待回复提醒', description: '客户补充工单回复后通知管理员。', variables: ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], defaultSubject: '【{{site_name}}】工单待回复提醒 #{{ticket_id}}', defaultContent: '<p>您好，{{recipient_name}}：</p><p>客户刚刚补充了工单回复，请及时跟进。</p><div class="mail-panel"><div class="mail-kv"><span>工单编号</span><strong>#{{ticket_id}}</strong></div><div class="mail-kv"><span>工单标题</span><strong>{{ticket_subject}}</strong></div></div><p>最新回复摘要：{{message_preview}}</p>' },
    { code: '100012', name: '工单回复通知', description: '管理员回复工单后通知用户。', variables: ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'tickets_url', 'login_tip'], defaultSubject: '【{{site_name}}】工单回复通知 #{{ticket_id}}', defaultContent: '<p>您好，{{display_name}}：</p><p>您的工单已有管理员回复，请及时查看。</p><div class="mail-panel"><div class="mail-kv"><span>工单编号</span><strong>#{{ticket_id}}</strong></div><div class="mail-kv"><span>回复人员</span><strong>{{staff_name}}</strong></div></div><p>回复摘要：{{message_preview}}</p>{{#tickets_url}}<a class="mail-button" href="{{tickets_url}}">查看工单详情</a>{{/tickets_url}}' },
    { code: '100013', name: '用户下单提醒', description: '用户创建新订单后通知管理员。', variables: ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at'], defaultSubject: '【{{site_name}}】用户下单提醒 #{{order_no}}', defaultContent: '<p>您好，{{recipient_name}}：</p><p>有用户刚刚提交了新的订单。</p><div class="mail-panel"><div class="mail-kv"><span>用户名称</span><strong>{{user_name}}</strong></div><div class="mail-kv"><span>订单编号</span><strong>{{order_no}}</strong></div><div class="mail-kv"><span>配置名称</span><strong>{{product_name}}</strong></div><div class="mail-kv"><span>订单金额</span><strong>¥{{order_amount}}</strong></div></div>' },
    { code: '100014', name: '用户支付完成提醒', description: '用户订单支付完成后通知管理员。', variables: ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at'], defaultSubject: '【{{site_name}}】用户支付完成 #{{order_no}}', defaultContent: '<p>您好，{{recipient_name}}：</p><p>有用户订单已完成支付。</p><div class="mail-panel"><div class="mail-kv"><span>用户名称</span><strong>{{user_name}}</strong></div><div class="mail-kv"><span>订单编号</span><strong>{{order_no}}</strong></div><div class="mail-kv"><span>支付金额</span><strong>¥{{paid_amount}}</strong></div><div class="mail-kv"><span>支付方式</span><strong>{{payment_method}}</strong></div></div>' },
  ].map((item) => ({
    ...item,
    audience: adminTemplateCodes.has(item.code) ? 'admin' : 'user',
    subject: item.defaultSubject,
    content: item.defaultContent,
  })),
);

const templateCode = computed(() => String(route.params.code || '').trim());
const templateDefinition = computed(() => templateDefinitions.find((item) => item.code === templateCode.value));
const currentTab = computed(() => {
  if (route.query.tab === 'admin' || route.query.tab === 'user') return route.query.tab;
  return adminTemplateCodes.has(templateCode.value) ? 'admin' : 'user';
});

const previewBaseParams = computed(() => ({
  site_name: siteName,
  display_name: '张三',
  recipient_name: '运维管理员',
  email: 'demo@example.com',
  client_email: 'client@example.com',
  client_name: '测试用户',
  login_at: '2026-04-01 14:30:00',
  ip: '203.0.113.25',
  device: 'Windows / Chrome',
  code: '482915',
  expire_minutes: '10',
  service_name: '香港云服务器 CSP-2G',
  days_left: '3',
  expires_at: '2026-04-10 23:59:59',
  billing_cycle_label: '月付',
  urgency_message: '您的服务将在 3 天后到期，请提前完成续费。',
  invoice_no: 'zd202604011430004821',
  order_no: 'dd202604011430004821',
  product_name: 'ecs.g9i.2c2g 2 vCPU 2G',
  amount: '199.00',
  due_date: '2026-04-05',
  due_at: '2026-04-05 23:59:59',
  paid_at: '2026-04-01 10:12:33',
  payment_method: '支付宝',
  trade_no: '2026040100001001',
  notice_title: '账单提醒',
  notice_message: '请在到期前完成支付，以免影响关联服务。',
  status_label: '待支付',
  paid_amount: '199.00',
  remark: '人工核账通过',
  ticket_id: '1024',
  ticket_subject: '实例网络不通',
  department: '技术支持',
  priority: '高',
  status: '处理中',
  message_preview: '您好，实例无法 SSH 登录，请协助排查。',
  staff_name: '技术支持 A',
  tickets_url: '/client/tickets',
  login_tip: '如您尚未登录，请先登录后查看工单详情。',
}));

function normalizeSettings(response) {
  if (Array.isArray(response)) return Object.fromEntries(response.map((item) => [item.key, item.value]));
  if (response && Array.isArray(response.list)) return Object.fromEntries(response.list.map((item) => [item.key, item.value]));
  return response && typeof response === 'object' ? response : {};
}

async function loadSettings() {
  loading.value = true;
  try {
    templateDefinitions.forEach(resetTemplate);
    const response = await adminApi.settings.list({ group: 'notification' });
    const settings = normalizeSettings(response);
    templateDefinitions.forEach((template) => {
      template.subject = settings[`email_template_subject_${template.code}`] || template.defaultSubject;
      template.content = settings[`email_template_content_${template.code}`] || template.defaultContent;
    });
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载邮件模板失败'));
  } finally {
    loading.value = false;
  }
}

async function saveCurrentTemplate() {
  if (!templateDefinition.value) return;
  saving.value = true;
  try {
    await adminApi.settings.save({
      group: 'notification',
      settings: {
        [`email_template_subject_${templateDefinition.value.code}`]: templateDefinition.value.subject,
        [`email_template_content_${templateDefinition.value.code}`]: templateDefinition.value.content,
      },
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
  template.subject = template.defaultSubject;
  template.content = template.defaultContent;
}

function goBack() {
  router.push({ path: '/admin/notifications', query: { tab: 'email-templates' } });
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

function buildPreviewDocument(template) {
  const params = getPreviewParams(template);
  const subject = renderPreviewSubject(template);
  const content = renderTemplateContent(template.content, params);
  return `<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>${escapeHtml(subject)}</title><style>
body{margin:0;background:#f3f4f6;font-family:"PingFang SC","Microsoft YaHei",Arial,sans-serif;color:#1f2329}.mail-shell{padding:32px 12px}.mail-card{max-width:680px;margin:0 auto;background:#fff;border:1px solid #cfd6e4}.mail-header{padding:24px 28px 20px;border-top:4px solid #1f4b99;border-bottom:1px solid #d9e0ec;background:#f8fafc}.mail-brand strong{display:block;font-size:18px;color:#162033}.mail-brand span{display:block;margin-top:6px;font-size:12px;color:#5b6575}.mail-body{padding:28px}.mail-title{margin:0;font-size:28px;line-height:1.4;color:#162033}.mail-summary{margin:12px 0 0;font-size:14px;line-height:1.8;color:#4b5565}.mail-divider{height:1px;margin:24px 0;background:#d9e0ec}.mail-content{font-size:14px;line-height:1.85}.mail-content p{margin:0 0 14px}.mail-panel{margin:18px 0;padding:16px 18px;border:1px solid #d9e0ec;background:#f8fafc}.mail-code{display:inline-block;margin:8px 0 16px;padding:14px 18px;border:1px solid #1f4b99;background:#eef4ff;color:#1f4b99;font-size:28px;font-weight:700;letter-spacing:.18em}.mail-kv{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid #d9e0ec}.mail-kv:last-child{border-bottom:0}.mail-kv span{color:#4e5969}.mail-kv strong{text-align:right}.mail-button{display:inline-block;margin-top:12px;padding:12px 18px;background:#1f4b99;color:#fff;text-decoration:none}.mail-footer{padding:0 28px 28px;color:#6b7280;font-size:12px;line-height:1.8}@media(max-width:640px){.mail-body,.mail-footer,.mail-header{padding-left:18px;padding-right:18px}.mail-title{font-size:22px}.mail-kv{display:block}.mail-kv strong{display:block;margin-top:6px;text-align:left}}
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
