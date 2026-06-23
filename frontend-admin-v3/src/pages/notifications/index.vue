<template>
  <div class="notifications-page">
    <t-card :bordered="false">
      <div class="page-tabs-toolbar">
        <t-tabs :value="activeTab" @change="handleTabChange">
          <t-tab-panel value="interfaces" label="接口配置" />
          <t-tab-panel value="email-templates" label="邮件模板" />
          <t-tab-panel value="api-directory" label="API 接口" />
        </t-tabs>
        <t-button variant="outline" :loading="currentLoading" @click="refreshCurrentTab">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>
    </t-card>

    <template v-if="activeTab === 'interfaces'">
      <section class="channel-grid">
        <t-card :bordered="false" :loading="settingsLoading">
          <template #title>
            <span class="card-title"><mail-icon /> 邮件接口</span>
          </template>
          <t-form ref="emailFormRef" :data="emailForm" label-align="top">
            <t-form-item label="启用邮件" name="enabled">
              <t-switch v-model="emailForm.enabled" />
            </t-form-item>
            <t-form-item label="SMTP 主机" name="host">
              <t-input v-model="emailForm.host" placeholder="例如 smtp.qq.com" />
            </t-form-item>
            <t-form-item label="SMTP 端口" name="port">
              <t-input v-model="emailForm.port" placeholder="例如 465 / 587" />
            </t-form-item>
            <t-form-item label="发件邮箱" name="username">
              <t-input v-model="emailForm.username" placeholder="请输入发件邮箱" />
            </t-form-item>
            <t-form-item label="授权密码" name="password">
              <t-input
                v-model="emailForm.password"
                type="password"
                :placeholder="secretPlaceholder('email_password', '请输入邮箱授权码')"
              />
            </t-form-item>
            <t-form-item label="发件名称" name="from_name">
              <t-input v-model="emailForm.from_name" placeholder="例如 创欧云" />
            </t-form-item>
          </t-form>
          <div class="form-actions">
            <t-button theme="primary" :loading="savingEmail" @click="saveEmailSettings">保存邮件配置</t-button>
          </div>
        </t-card>

        <t-card :bordered="false" :loading="settingsLoading">
          <template #title>
            <span class="card-title"><mobile-icon /> 短信接口</span>
          </template>
          <t-form ref="smsFormRef" :data="smsForm" label-align="top">
            <t-form-item label="启用短信" name="enabled">
              <t-switch v-model="smsForm.enabled" />
            </t-form-item>
            <t-form-item label="Access Key" name="access_key">
              <t-input v-model="smsForm.access_key" :placeholder="secretPlaceholder('sms_access_key', '请输入 Access Key')" />
            </t-form-item>
            <t-form-item label="Secret Key" name="secret_key">
              <t-input
                v-model="smsForm.secret_key"
                type="password"
                :placeholder="secretPlaceholder('sms_secret_key', '请输入 Secret Key')"
              />
            </t-form-item>
            <t-form-item label="签名" name="sign_name">
              <t-input v-model="smsForm.sign_name" placeholder="请输入短信签名" />
            </t-form-item>
            <t-form-item label="验证码模板" name="template_code">
              <t-input v-model="smsForm.template_code" placeholder="请输入模板编号" />
            </t-form-item>
          </t-form>
          <div class="form-actions">
            <t-button theme="primary" :loading="savingSms" @click="saveSmsSettings">保存短信配置</t-button>
          </div>
        </t-card>
      </section>
    </template>

    <template v-else-if="activeTab === 'email-templates'">
      <t-card :bordered="false" :loading="templatesLoading">
        <div class="template-toolbar">
          <div>
            <h2>邮件模板</h2>
            <p>点击查看进入独立详情页编辑主题、正文和变量预览。</p>
          </div>
          <t-radio-group v-model="templateAudience" variant="default-filled">
            <t-radio-button v-for="item in templateAudienceOptions" :key="item.value" :value="item.value">
              {{ item.label }}
            </t-radio-button>
          </t-radio-group>
        </div>

        <div v-if="!isMobile" class="table-scroll">
          <t-table row-key="code" :data="filteredTemplates" :columns="templateColumns" hover table-layout="fixed" @row-click="openTemplate">
            <template #name="{ row }">
              <div class="stack-cell">
                <strong>{{ row.name }}</strong>
                <span>{{ row.description }}</span>
              </div>
            </template>
            <template #code="{ row }">
              <t-space size="small">
                <t-tag variant="light">{{ row.code }}</t-tag>
                <t-tag :theme="row.audience === 'admin' ? 'warning' : 'success'" variant="light">
                  {{ row.audience === 'admin' ? '管理员' : '用户' }}
                </t-tag>
              </t-space>
            </template>
            <template #preview="{ row }">{{ row.preview }}</template>
            <template #variables="{ row }">{{ row.variables.length }}</template>
            <template #bodyType="{ row }">
              <t-tag :theme="row.isHtml ? 'primary' : 'default'" variant="light">{{ row.isHtml ? 'HTML' : '文本' }}</t-tag>
            </template>
            <template #actions="{ row }">
              <t-button theme="primary" variant="text" @click.stop="openTemplate(row)">查看</t-button>
            </template>
          </t-table>
        </div>

        <div v-else class="mobile-list">
          <article v-for="row in filteredTemplates" :key="row.code" class="template-mobile-card">
            <div class="template-mobile-card__head">
              <strong>{{ row.name }}</strong>
              <t-tag variant="light">{{ row.code }}</t-tag>
            </div>
            <p>{{ row.description }}</p>
            <dl>
              <div><dt>主题</dt><dd>{{ fieldValue(row.subject) }}</dd></div>
              <div><dt>变量数</dt><dd>{{ row.variables.length }}</dd></div>
              <div><dt>正文类型</dt><dd>{{ row.isHtml ? 'HTML' : '文本' }}</dd></div>
              <div><dt>面向对象</dt><dd>{{ row.audience === 'admin' ? '管理员' : '用户' }}</dd></div>
            </dl>
            <t-button theme="primary" variant="outline" @click="openTemplate(row)">查看</t-button>
          </article>
        </div>
      </t-card>
    </template>

    <template v-else>
      <section class="api-layout">
        <t-card :bordered="false" class="api-sidebar">
          <template #title>接口分类</template>
          <div class="api-meta-grid">
            <article><span>接口</span><strong>{{ apiMeta.total || apiItems.length }}</strong></article>
            <article><span>模块</span><strong>{{ apiMeta.moduleCount || apiModules.length }}</strong></article>
            <article><span>权限</span><strong>{{ apiMeta.accessCounts?.permission || 0 }}</strong></article>
            <article><span>登录</span><strong>{{ apiMeta.accessCounts?.auth || 0 }}</strong></article>
          </div>
          <div class="api-category-list">
            <button type="button" :class="{ active: selectedApiModule === 'all' }" @click="selectedApiModule = 'all'">
              <span>全部接口</span>
              <strong>{{ apiItems.length }}</strong>
            </button>
            <button
              v-for="item in apiModules"
              :key="item.key"
              type="button"
              :class="{ active: selectedApiModule === item.key }"
              @click="selectedApiModule = item.key"
            >
              <span>{{ item.label }}</span>
              <strong>{{ item.count }}</strong>
            </button>
          </div>
        </t-card>

        <div class="api-main">
          <t-card :bordered="false">
            <div class="api-directory-head">
              <div>
                <h2>API 接口页</h2>
                <p>数据来源：{{ apiMeta.dataSource || '-' }}，生成时间：{{ apiMeta.generatedAt || '-' }}，基础地址：{{ apiMeta.baseURL || '/api' }}</p>
              </div>
              <t-button variant="outline" @click="resetApiFilters">重置筛选</t-button>
            </div>
            <div class="api-filter-grid">
              <t-input v-model="apiFilters.keyword" clearable placeholder="搜索路径、权限码、控制器或源码文件">
                <template #suffix-icon><search-icon /></template>
              </t-input>
              <t-select v-model="apiFilters.access" placeholder="访问级别">
                <t-option label="全部级别" value="all" />
                <t-option label="公开" value="public" />
                <t-option label="仅登录" value="auth" />
                <t-option label="登录 + 权限" value="permission" />
              </t-select>
              <t-select v-model="apiFilters.method" placeholder="请求方法">
                <t-option label="全部方法" value="all" />
                <t-option v-for="item in methodOptions" :key="item" :label="item" :value="item" />
              </t-select>
              <t-select v-model="apiFilters.source" placeholder="前端来源">
                <t-option label="全部来源" value="all" />
                <t-option label="管理端前端" value="frontend-admin-v3" />
                <t-option label="官网/用户入口" value="frontend-user-v3-www" />
                <t-option label="用户控制台" value="frontend-user-v4-console" />
                <t-option label="未发现前端调用" value="untracked" />
              </t-select>
            </div>
          </t-card>

          <t-card :bordered="false">
            <template #title>接口明细</template>
            <template #subtitle>Showing {{ filteredApiItems.length }} / {{ apiItems.length }}</template>
            <div class="table-scroll">
              <t-table row-key="id" :data="filteredApiItems" :columns="apiColumns" hover table-layout="fixed">
                <template #category="{ row }">
                  <div class="stack-cell">
                    <strong>{{ row.scopeLabel }} / {{ row.moduleLabel }}</strong>
                    <span>{{ row.module }}</span>
                  </div>
                </template>
                <template #method="{ row }">
                  <t-tag :theme="methodTheme(row.method)" variant="light">{{ row.method }}</t-tag>
                </template>
                <template #path="{ row }">
                  <div class="stack-cell">
                    <strong>{{ row.callPath }}</strong>
                    <span>{{ row.backendPath }}</span>
                  </div>
                </template>
                <template #access="{ row }">
                  <div class="tag-stack">
                    <t-tag :theme="accessTheme(row.access)" variant="light">{{ row.accessLabel }}</t-tag>
                    <t-tag v-if="row.permission" variant="light">{{ row.permission }}</t-tag>
                    <span v-else>无需额外权限码</span>
                  </div>
                </template>
                <template #source="{ row }">
                  <div class="tag-stack">
                    <t-tag v-for="label in row.sourceAppLabels" :key="label" variant="light">{{ label }}</t-tag>
                    <span v-if="!row.sourceAppLabels.length">未发现前端调用</span>
                  </div>
                </template>
                <template #handler="{ row }">
                  <div class="stack-cell">
                    <strong>{{ row.handler }}</strong>
                    <span>{{ row.sourceFiles.length }} 个源码命中</span>
                  </div>
                </template>
                <template #actions="{ row }">
                  <t-button theme="primary" variant="text" @click="copyApiPath(row.backendPath)">复制</t-button>
                </template>
              </t-table>
            </div>
          </t-card>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiIcon, MailIcon, MobileIcon, RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import type { FormInstanceFunctions, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';

import { adminApi, type SettingItem } from '@/api/admin';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { errorMessage } from '@/utils/userMessage';
import apiCatalogData from '@/data/apiCatalog.generated.json';

import './index.less';

type NotificationTab = 'interfaces' | 'email-templates' | 'api-directory';
type TemplateAudience = 'user' | 'admin';

interface NotificationForm {
  enabled: boolean;
  host: string;
  port: string;
  username: string;
  password: string;
  from_name: string;
}

interface SmsForm {
  enabled: boolean;
  provider: string;
  access_key: string;
  secret_key: string;
  sign_name: string;
  template_code: string;
}

interface TemplateSummary {
  code: string;
  name: string;
  description: string;
  variables: string[];
  defaultSubject: string;
  defaultContent: string;
}

interface TemplateRow extends TemplateSummary {
  subject: string;
  preview: string;
  isHtml: boolean;
  audience: TemplateAudience;
}

interface ApiCatalogItem extends Record<string, unknown> {
  id: string;
  scope: string;
  scopeLabel: string;
  module: string;
  moduleLabel: string;
  method: string;
  methods?: string[];
  callPath: string;
  backendPath: string;
  access: string;
  accessLabel: string;
  permission?: string;
  handler?: string;
  sourceApps: string[];
  sourceAppLabels: string[];
  sourceFiles: string[];
}

interface ApiCatalogMeta {
  total?: number;
  moduleCount?: number;
  dataSource?: string;
  generatedAt?: string;
  baseURL?: string;
  accessCounts?: Record<string, number>;
}

const route = useRoute();
const router = useRouter();
const activeTab = ref<NotificationTab>(normalizeTab(route.query.tab));
const settingsLoading = ref(false);
const templatesLoading = ref(false);
const savingEmail = ref(false);
const savingSms = ref(false);
const emailFormRef = ref<FormInstanceFunctions>();
const smsFormRef = ref<FormInstanceFunctions>();
const settingsMap = ref<Record<string, unknown>>({});
const settingsMetaMap = ref<Record<string, SettingItem>>({});
const templateAudience = ref<TemplateAudience>('user');
const selectedApiModule = ref('all');

const emailForm = reactive<NotificationForm>({
  enabled: false,
  host: '',
  port: '',
  username: '',
  password: '',
  from_name: '',
});
const smsForm = reactive<SmsForm>({
  enabled: false,
  provider: 'aliyun',
  access_key: '',
  secret_key: '',
  sign_name: '',
  template_code: '',
});
const apiFilters = reactive({
  keyword: '',
  access: 'all',
  method: 'all',
  source: 'all',
});

const adminTemplateCodes = new Set(['100010', '100011', '100013', '100014']);
const templateSummaries: TemplateSummary[] = [
  { code: '100001', name: '邮箱验证码', description: '发送邮箱验证码时使用。', variables: ['code', 'expire_minutes'], defaultSubject: '邮箱验证码', defaultContent: '邮箱验证码与时效提醒。' },
  { code: '100002', name: '登录提醒', description: '客户登录成功后发送安全提醒。', variables: ['site_name', 'display_name', 'email', 'login_at', 'ip', 'device'], defaultSubject: '{{site_name}} 登录提醒', defaultContent: '登录设备、IP、时间等安全提醒。' },
  { code: '100003', name: '服务续费提醒', description: '服务到期前自动发送续费提醒。', variables: ['site_name', 'display_name', 'service_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message'], defaultSubject: '【{{site_name}}】服务续费提醒（{{days_left}} 天后到期）', defaultContent: '服务名称、到期时间和续费提示。' },
  { code: '100004', name: '账单付款提醒', description: '账单到期前发送付款提醒。', variables: ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], defaultSubject: '【{{site_name}}】账单付款提醒 #{{invoice_no}}', defaultContent: '账单到期前付款提醒。' },
  { code: '100005', name: '账单逾期催款', description: '账单逾期后自动发送催缴提醒。', variables: ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'], defaultSubject: '【{{site_name}}】账单逾期催款 #{{invoice_no}}', defaultContent: '逾期账单催缴提醒。' },
  { code: '100006', name: '服务到期暂停通知', description: '服务因过期被系统暂停时发送通知。', variables: ['site_name', 'display_name', 'service_name', 'expires_at'], defaultSubject: '【{{site_name}}】服务到期暂停通知', defaultContent: '服务暂停原因与恢复方式。' },
  { code: '100007', name: '服务恢复通知', description: '服务续费成功恢复后发送通知。', variables: ['display_name', 'service_name', 'expires_at'], defaultSubject: '服务恢复通知', defaultContent: '服务恢复成功通知。' },
  { code: '100008', name: '账单通知', description: '管理员主动发送账单提醒或账单确认时使用。', variables: ['site_name', 'display_name', 'notice_title', 'invoice_no', 'order_no', 'product_name', 'amount', 'status_label', 'due_at', 'paid_at', 'payment_method', 'trade_no', 'notice_message'], defaultSubject: '【{{site_name}}】{{notice_title}} #{{invoice_no}}', defaultContent: '通用账单状态通知。' },
  { code: '100009', name: '手动入账通知', description: '管理员手动设为已支付后发送通知。', variables: ['invoice_no', 'order_no', 'paid_amount', 'payment_method', 'paid_at', 'trade_no', 'remark'], defaultSubject: '账单支付确认通知', defaultContent: '手动入账确认通知。' },
  { code: '100010', name: '新工单提醒', description: '客户提交新工单后通知管理员。', variables: ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], defaultSubject: '【{{site_name}}】新工单提醒 #{{ticket_id}}', defaultContent: '新工单提交提醒。' },
  { code: '100011', name: '工单待回复提醒', description: '客户补充工单回复后通知管理员。', variables: ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'], defaultSubject: '【{{site_name}}】工单待回复提醒 #{{ticket_id}}', defaultContent: '工单追加回复提醒。' },
  { code: '100012', name: '工单回复通知', description: '管理员回复工单后通知用户。', variables: ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'tickets_url', 'login_tip'], defaultSubject: '【{{site_name}}】工单回复通知 #{{ticket_id}}', defaultContent: '工单回复通知与跳转入口。' },
  { code: '100013', name: '用户下单提醒', description: '用户创建新订单后通知管理员。', variables: ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at'], defaultSubject: '【{{site_name}}】用户下单提醒 #{{order_no}}', defaultContent: '用户提交新订单后的管理员提醒，包含配置名称。' },
  { code: '100014', name: '用户支付完成提醒', description: '用户订单支付完成后通知管理员。', variables: ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at'], defaultSubject: '【{{site_name}}】用户支付完成 #{{order_no}}', defaultContent: '用户订单支付完成后的管理员提醒，包含配置名称。' },
];

const apiCatalog = apiCatalogData as { meta?: ApiCatalogMeta; items?: ApiCatalogItem[] };
const apiMeta = apiCatalog.meta || {};
const apiItems = (apiCatalog.items || []).map((item) => ({
  ...item,
  sourceApps: item.sourceApps || [],
  sourceAppLabels: item.sourceAppLabels || [],
  sourceFiles: item.sourceFiles || [],
}));

const isMobile = useMediaQuery('(max-width: 768px)');
const currentLoading = computed(() => {
  if (activeTab.value === 'interfaces') return settingsLoading.value || savingEmail.value || savingSms.value;
  if (activeTab.value === 'email-templates') return templatesLoading.value;
  return false;
});
const templateAudienceOptions = computed(() => [
  { label: `用户模板（${templateRows.value.filter((item) => item.audience === 'user').length}）`, value: 'user' },
  { label: `管理员模板（${templateRows.value.filter((item) => item.audience === 'admin').length}）`, value: 'admin' },
]);
const templateRows = computed<TemplateRow[]>(() =>
  templateSummaries.map((template) => {
    const subject = stringValue(settingsMap.value[`email_template_subject_${template.code}`]) || template.defaultSubject;
    const content = stringValue(settingsMap.value[`email_template_content_${template.code}`]) || template.defaultContent;
    const preview = stripHtml(content);
    return {
      ...template,
      subject,
      preview: preview ? (preview.length > 88 ? `${preview.slice(0, 88)}...` : preview) : '-',
      isHtml: /<([a-z][a-z0-9]*)(\s|>)/i.test(content.trim()),
      audience: adminTemplateCodes.has(template.code) ? 'admin' : 'user',
    };
  }),
);
const filteredTemplates = computed(() => templateRows.value.filter((item) => item.audience === templateAudience.value));
const apiModules = computed(() => {
  const moduleMap = new Map<string, { key: string; label: string; count: number }>();
  apiItems.forEach((item) => {
    const key = `${item.scope}:${item.module}`;
    const current = moduleMap.get(key) || { key, label: `${item.scopeLabel} / ${item.moduleLabel}`, count: 0 };
    current.count += 1;
    moduleMap.set(key, current);
  });
  return Array.from(moduleMap.values()).sort((left, right) => right.count - left.count);
});
const methodOptions = computed(() => Array.from(new Set(apiItems.map((item) => item.method))).sort());
const filteredApiItems = computed(() => {
  const keyword = apiFilters.keyword.trim().toLowerCase();
  return apiItems.filter((item) => {
    if (selectedApiModule.value !== 'all' && `${item.scope}:${item.module}` !== selectedApiModule.value) return false;
    if (apiFilters.access !== 'all' && item.access !== apiFilters.access) return false;
    if (apiFilters.method !== 'all' && item.method !== apiFilters.method) return false;
    if (apiFilters.source === 'untracked' && item.sourceApps.length > 0) return false;
    if (apiFilters.source !== 'all' && apiFilters.source !== 'untracked' && !item.sourceApps.includes(apiFilters.source)) return false;
    if (!keyword) return true;
    return [
      item.scopeLabel,
      item.moduleLabel,
      item.method,
      item.callPath,
      item.backendPath,
      item.accessLabel,
      item.permission,
      item.handler,
      ...item.sourceAppLabels,
      ...item.sourceFiles,
    ]
      .join(' ')
      .toLowerCase()
      .includes(keyword);
  });
});

const templateColumns: PrimaryTableCol<TemplateRow>[] = [
  { colKey: 'name', title: '模板信息', minWidth: 260 },
  { colKey: 'code', title: '编号 / 对象', minWidth: 180 },
  { colKey: 'subject', title: '当前主题', minWidth: 220, ellipsis: true },
  { colKey: 'preview', title: '正文摘要', minWidth: 260, ellipsis: true },
  { colKey: 'variables', title: '变量数', width: 90 },
  { colKey: 'bodyType', title: '正文类型', width: 110 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
];
const apiColumns: PrimaryTableCol<ApiCatalogItem>[] = [
  { colKey: 'category', title: '所属分类', minWidth: 220 },
  { colKey: 'method', title: '方法', width: 100 },
  { colKey: 'path', title: '路径', minWidth: 340 },
  { colKey: 'access', title: '访问控制', minWidth: 210 },
  { colKey: 'source', title: '前端来源', minWidth: 170 },
  { colKey: 'handler', title: '控制器方法', minWidth: 220 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
];

function normalizeTab(value: unknown): NotificationTab {
  const tab = Array.isArray(value) ? value[0] : value;
  return tab === 'email-templates' || tab === 'api-directory' ? tab : 'interfaces';
}

function handleTabChange(value: string | number) {
  activeTab.value = normalizeTab(value);
  router.replace({ path: '/admin/notifications', query: activeTab.value === 'interfaces' ? {} : { tab: activeTab.value } });
  refreshCurrentTab();
}

function refreshCurrentTab() {
  if (activeTab.value === 'interfaces') return loadSettings();
  if (activeTab.value === 'email-templates') return loadTemplates();
  return undefined;
}

async function loadSettings() {
  settingsLoading.value = true;
  try {
    const response = await adminApi.settings.list({ group: 'notification' });
    settingsMap.value = normalizeSettings(response);
    settingsMetaMap.value = normalizeSettingItems(response);
    fillNotificationForms();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载通知配置失败'));
  } finally {
    settingsLoading.value = false;
  }
}

async function loadTemplates() {
  templatesLoading.value = true;
  try {
    const response = await adminApi.settings.list({ group: 'notification' });
    settingsMap.value = normalizeSettings(response);
    settingsMetaMap.value = normalizeSettingItems(response);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载邮件模板列表失败'));
  } finally {
    templatesLoading.value = false;
  }
}

function fillNotificationForms() {
  emailForm.enabled = toBool(settingsMap.value.email_enabled);
  emailForm.host = stringValue(settingsMap.value.email_host);
  emailForm.port = stringValue(settingsMap.value.email_port);
  emailForm.username = stringValue(settingsMap.value.email_username);
  emailForm.password = '';
  emailForm.from_name = stringValue(settingsMap.value.email_from_name);
  smsForm.enabled = toBool(settingsMap.value.sms_enabled);
  smsForm.provider = stringValue(settingsMap.value.sms_driver) || stringValue(settingsMap.value.sms_provider) || 'aliyun';
  smsForm.access_key = '';
  smsForm.secret_key = '';
  smsForm.sign_name = stringValue(settingsMap.value.sms_sign_name);
  smsForm.template_code = stringValue(settingsMap.value.sms_template_code);
}

async function saveEmailSettings() {
  if (!validateEmailForm()) return;
  savingEmail.value = true;
  try {
    const settings: Record<string, unknown> = {
      email_enabled: emailForm.enabled ? 1 : 0,
      email_host: emailForm.host.trim(),
      email_port: emailForm.port.trim(),
      email_username: emailForm.username.trim(),
      email_from_name: emailForm.from_name.trim(),
    };
    if (emailForm.password.trim()) {
      settings.email_password = emailForm.password.trim();
    }

    await adminApi.settings.save({
      group: 'notification',
      settings,
    });
    MessagePlugin.success('邮件配置已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存邮件配置失败'));
  } finally {
    savingEmail.value = false;
  }
}

async function saveSmsSettings() {
  if (!validateSmsForm()) return;
  savingSms.value = true;
  try {
    const settings: Record<string, unknown> = {
      sms_enabled: smsForm.enabled ? 1 : 0,
      sms_driver: smsForm.provider,
      sms_provider: smsForm.provider,
      sms_sign_name: smsForm.sign_name.trim(),
      sms_template_code: smsForm.template_code.trim(),
    };
    if (smsForm.access_key.trim()) {
      settings.sms_access_key = smsForm.access_key.trim();
    }
    if (smsForm.secret_key.trim()) {
      settings.sms_secret_key = smsForm.secret_key.trim();
    }

    await adminApi.settings.save({
      group: 'notification',
      settings,
    });
    MessagePlugin.success('短信配置已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存短信配置失败'));
  } finally {
    savingSms.value = false;
  }
}

function validateEmailForm() {
  if (!emailForm.enabled) return true;
  if (!emailForm.host.trim()) return warnRequired('SMTP 主机');
  const port = Number(emailForm.port);
  if (!Number.isInteger(port) || port <= 0 || port > 65535) {
    MessagePlugin.warning('SMTP 端口必须是 1-65535 的整数');
    return false;
  }
  if (!emailForm.username.trim()) return warnRequired('发件邮箱');
  if (!hasSecretInputOrStored('email_password', emailForm.password)) return warnRequired('授权密码');
  if (!emailForm.from_name.trim()) return warnRequired('发件名称');
  return true;
}

function validateSmsForm() {
  if (!smsForm.enabled) return true;
  if (!hasSecretInputOrStored('sms_access_key', smsForm.access_key)) return warnRequired('Access Key');
  if (!hasSecretInputOrStored('sms_secret_key', smsForm.secret_key)) return warnRequired('Secret Key');
  if (!smsForm.sign_name.trim()) return warnRequired('签名');
  if (!smsForm.template_code.trim()) return warnRequired('验证码模板');
  return true;
}

function warnRequired(label: string) {
  MessagePlugin.warning(`启用后必须填写${label}`);
  return false;
}

function openTemplate(row: TemplateRow | TableRowData) {
  const code = String((row as TemplateRow).code || '');
  if (!code) return;
  router.push({ path: `/admin/notifications/email-templates/${code}`, query: { tab: templateAudience.value } });
}

function resetApiFilters() {
  selectedApiModule.value = 'all';
  apiFilters.keyword = '';
  apiFilters.access = 'all';
  apiFilters.method = 'all';
  apiFilters.source = 'all';
}

async function copyApiPath(path: string) {
  try {
    await navigator.clipboard?.writeText(path);
    MessagePlugin.success('接口路径已复制');
  } catch {
    MessagePlugin.error('复制失败，请手动复制');
  }
}

function normalizeSettings(response: SettingItem[] | Record<string, unknown>) {
  const items = extractSettingItems(response);
  if (items.length) return Object.fromEntries(items.map((item) => [item.key, item.value]));

  const record = toRecord(response);
  return record;
}

function normalizeSettingItems(response: SettingItem[] | Record<string, unknown>) {
  return Object.fromEntries(extractSettingItems(response).map((item) => [item.key, item]));
}

function extractSettingItems(response: SettingItem[] | Record<string, unknown>) {
  if (Array.isArray(response)) return response;

  const record = toRecord(response);
  return Array.isArray(record.list) ? (record.list as SettingItem[]) : [];
}

function hasStoredSecret(key: string) {
  return settingsMetaMap.value[key]?.has_value === true;
}

function hasSecretInputOrStored(key: string, value: string) {
  return value.trim() !== '' || hasStoredSecret(key);
}

function secretPlaceholder(key: string, fallback: string) {
  return hasStoredSecret(key) ? '已配置，留空表示不修改' : fallback;
}

function methodTheme(method: string) {
  if (method === 'GET') return 'primary';
  if (method === 'POST') return 'success';
  if (method === 'PUT') return 'warning';
  if (method === 'DELETE') return 'danger';
  return 'default';
}

function accessTheme(access: string) {
  if (access === 'permission') return 'warning';
  if (access === 'auth') return 'success';
  return 'default';
}

function stripHtml(value: string) {
  return value
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function toBool(value: unknown) {
  return value === true || value === 1 || value === '1' || value === 'true';
}

function stringValue(value: unknown) {
  if (value === null || value === undefined) return '';
  return String(value);
}

function fieldValue(value: unknown) {
  const normalized = stringValue(value);
  return normalized || '-';
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}


watch(
  () => route.query.tab,
  (value) => {
    activeTab.value = normalizeTab(value);
    if (activeTab.value === 'interfaces' && !Object.keys(settingsMap.value).length) loadSettings();
    if (activeTab.value === 'email-templates' && !Object.keys(settingsMap.value).length) loadTemplates();
  },
);

onMounted(() => {
  refreshCurrentTab();
});
</script>
