<template>
  <div class="email-templates-page">
    <template v-if="templateDefinition">
      <div class="page-header">
        <div class="page-title">
          <el-button :icon="ArrowLeft" text type="primary" @click="goBack">返回列表</el-button>
          <div>
            <div class="title-row">
              <h2>{{ templateDefinition.name }}</h2>
              <el-tag size="small" effect="plain">{{ templateDefinition.code }}</el-tag>
            </div>
            <p v-pre>正文支持 HTML 片段编辑，系统发送时会自动套用站点邮件外壳，并可直接实时预览。支持变量占位 `{{ key }}` 和条件区块 `{{#key}}...{{/key}}`。</p>
          </div>
        </div>
      </div>

      <el-card shadow="never" class="templates-card" v-loading="loading">
        <div class="template-body">
          <div class="template-meta">
            <div class="token-list">
              <span class="token-label">可用变量</span>
              <el-tag
                v-for="variable in templateDefinition.variables"
                :key="`${templateDefinition.code}-${variable}`"
                size="small"
                effect="plain"
              >
                {{ variable }}
              </el-tag>
            </div>
          </div>

          <div class="preview-params">
            <div
              v-for="variable in templateDefinition.variables"
              :key="`${templateDefinition.code}-sample-${variable}`"
              class="preview-param"
            >
              <span>{{ variable }}</span>
              <strong>{{ getPreviewValue(templateDefinition, variable) }}</strong>
            </div>
          </div>

          <el-form :model="templateDefinition" label-width="88px" class="template-form">
            <el-form-item label="邮件主题">
              <div class="subject-block">
                <el-input v-model="templateDefinition.subject" />
                <div class="subject-preview">
                  <span>主题预览</span>
                  <strong>{{ renderPreviewSubject(templateDefinition) }}</strong>
                </div>
              </div>
            </el-form-item>

          <el-form-item label="邮件正文">
              <div class="editor-grid">
                <section class="editor-pane">
                  <div class="pane-header">
                    <strong>HTML 正文片段</strong>
                    <span>支持旧文本模板，未检测到 HTML 时会自动转为样式化段落。</span>
                  </div>
                  <el-input
                    v-model="templateDefinition.content"
                    type="textarea"
                    :rows="18"
                    resize="vertical"
                    placeholder="请输入 HTML 正文片段"
                    class="template-code-input"
                  />
                </section>

                <section class="preview-pane">
                  <div class="pane-header">
                    <strong>实时预览</strong>
                    <span>{{ siteName }} 站点风格</span>
                  </div>
                  <iframe
                    class="preview-frame"
                    :srcdoc="buildPreviewDocument(templateDefinition)"
                    sandbox=""
                    title="邮件模板预览"
                  />
                </section>
              </div>
            </el-form-item>
          </el-form>
        </div>
      </el-card>
    </template>

    <el-empty v-else description="模板不存在">
      <el-button type="primary" @click="goBack">返回邮件模板列表</el-button>
    </el-empty>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Check, RefreshRight } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'
import { useAppStore } from '@/stores/app'
import emailLogoSvgRaw from '../../../../../public/branding/logo1.svg?raw'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const ADMIN_TEMPLATE_CODES = new Set(['100010', '100011', '100013', '100014'])

const EMAIL_TEMPLATE_DEFINITIONS = [
  {
    code: '100001',
    name: '邮箱验证码',
    description: '发送邮箱验证码时使用。',
    variables: ['code', 'expire_minutes'],
    defaultSubject: '邮箱验证码',
    defaultContent: `
<p>您好，以下是本次邮箱验证所需的验证码：</p>
<div class="mail-code">{{code}}</div>
<p>请在 <strong>{{expire_minutes}}</strong> 分钟内完成验证。为保障账户安全，请勿将验证码透露给任何人。</p>
<p class="mail-muted">如非本人操作，请直接忽略此邮件。</p>`.trim(),
  },
  {
    code: '100002',
    name: '登录提醒',
    description: '客户登录成功后发送安全提醒。',
    variables: ['site_name', 'display_name', 'email', 'login_at', 'ip', 'device'],
    defaultSubject: '{{site_name}} 登录提醒',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>检测到您的账户刚刚完成一次登录，以下为本次登录摘要。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>登录邮箱</span><strong>{{email}}</strong></div>
  <div class="mail-kv"><span>登录时间</span><strong>{{login_at}}</strong></div>
  <div class="mail-kv"><span>登录 IP</span><strong>{{ip}}</strong></div>
  <div class="mail-kv"><span>登录设备</span><strong>{{device}}</strong></div>
</div>
<p class="mail-muted">如非本人操作，请立即修改密码，并检查账户安全设置。</p>`.trim(),
  },
  {
    code: '100003',
    name: '服务续费提醒',
    description: '服务到期前自动发送续费提醒。',
    variables: ['site_name', 'display_name', 'service_name', 'days_left', 'expires_at', 'billing_cycle_label', 'urgency_message'],
    defaultSubject: '【{{site_name}}】服务续费提醒（{{days_left}} 天后到期）',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>{{urgency_message}}</p>
<div class="mail-panel">
  <div class="mail-kv"><span>服务名称</span><strong>{{service_name}}</strong></div>
  <div class="mail-kv"><span>到期时间</span><strong>{{expires_at}}</strong></div>
  <div class="mail-kv"><span>计费周期</span><strong>{{billing_cycle_label}}</strong></div>
  <div class="mail-kv"><span>剩余天数</span><strong>{{days_left}} 天</strong></div>
</div>
<p>请及时登录控制台完成续费，避免服务被暂停。</p>
<p class="mail-muted">到期后未续费，服务将自动暂停。如有疑问，请联系 {{site_name}} 客服或提交工单。</p>`.trim(),
  },
  {
    code: '100004',
    name: '账单付款提醒',
    description: '账单到期前发送付款提醒。',
    variables: ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'],
    defaultSubject: '【{{site_name}}】账单付款提醒 #{{invoice_no}}',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>以下账单即将到期，请及时处理。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>
  {{#order_no}}<div class="mail-kv"><span>关联订单</span><strong>{{order_no}}</strong></div>{{/order_no}}
  {{#product_name}}<div class="mail-kv"><span>账单内容</span><strong>{{product_name}}</strong></div>{{/product_name}}
  <div class="mail-kv"><span>账单金额</span><strong>¥{{amount}}</strong></div>
  <div class="mail-kv"><span>应付日期</span><strong>{{due_date}}</strong></div>
</div>
<p>{{notice_message}}</p>
<p class="mail-muted">如有疑问，请联系 {{site_name}} 客服或提交工单。</p>`.trim(),
  },
  {
    code: '100005',
    name: '账单逾期催款',
    description: '账单逾期后自动发送催缴提醒。',
    variables: ['site_name', 'display_name', 'invoice_no', 'order_no', 'product_name', 'amount', 'due_date', 'notice_message'],
    defaultSubject: '【{{site_name}}】账单逾期催款 #{{invoice_no}}',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>以下账单已逾期，请尽快完成支付，以免影响相关服务。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>
  {{#order_no}}<div class="mail-kv"><span>关联订单</span><strong>{{order_no}}</strong></div>{{/order_no}}
  {{#product_name}}<div class="mail-kv"><span>账单内容</span><strong>{{product_name}}</strong></div>{{/product_name}}
  <div class="mail-kv"><span>账单金额</span><strong>¥{{amount}}</strong></div>
  <div class="mail-kv"><span>原应付日期</span><strong>{{due_date}}</strong></div>
</div>
<p>{{notice_message}}</p>
<p class="mail-muted">如有疑问，请联系 {{site_name}} 客服或提交工单。</p>`.trim(),
  },
  {
    code: '100006',
    name: '服务到期暂停通知',
    description: '服务因过期被系统暂停时发送通知。',
    variables: ['site_name', 'display_name', 'service_name', 'expires_at'],
    defaultSubject: '【{{site_name}}】服务到期暂停通知',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>您的服务因到期未续费，已被系统自动暂停。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>服务名称</span><strong>{{service_name}}</strong></div>
  <div class="mail-kv"><span>到期时间</span><strong>{{expires_at}}</strong></div>
</div>
<p>请登录控制台完成续费，支付成功后系统将在数分钟内自动恢复服务。</p>
<p class="mail-muted">如有疑问，请联系 {{site_name}} 客服或提交工单。</p>`.trim(),
  },
  {
    code: '100007',
    name: '服务恢复通知',
    description: '服务续费成功恢复后发送通知。',
    variables: ['display_name', 'service_name', 'expires_at'],
    defaultSubject: '服务恢复通知',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>您的服务已因续费成功恢复为正常状态，当前信息如下。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>服务名称</span><strong>{{service_name}}</strong></div>
  <div class="mail-kv"><span>新的到期时间</span><strong>{{expires_at}}</strong></div>
</div>
<p class="mail-muted">感谢您的续费支持。</p>`.trim(),
  },
  {
    code: '100008',
    name: '账单通知',
    description: '管理员主动发送账单提醒或账单确认时使用。',
    variables: ['site_name', 'display_name', 'notice_title', 'invoice_no', 'order_no', 'product_name', 'amount', 'status_label', 'due_at', 'paid_at', 'payment_method', 'trade_no', 'notice_message'],
    defaultSubject: '【{{site_name}}】{{notice_title}} #{{invoice_no}}',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>{{notice_title}}，以下为本次账单通知详情。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>
  {{#order_no}}<div class="mail-kv"><span>关联订单</span><strong>{{order_no}}</strong></div>{{/order_no}}
  {{#product_name}}<div class="mail-kv"><span>账单内容</span><strong>{{product_name}}</strong></div>{{/product_name}}
  <div class="mail-kv"><span>账单金额</span><strong>¥{{amount}}</strong></div>
  <div class="mail-kv"><span>账单状态</span><strong>{{status_label}}</strong></div>
  {{#due_at}}<div class="mail-kv"><span>到期时间</span><strong>{{due_at}}</strong></div>{{/due_at}}
  {{#paid_at}}<div class="mail-kv"><span>支付时间</span><strong>{{paid_at}}</strong></div>{{/paid_at}}
  {{#payment_method}}<div class="mail-kv"><span>支付方式</span><strong>{{payment_method}}</strong></div>{{/payment_method}}
  {{#trade_no}}<div class="mail-kv"><span>支付流水号</span><strong>{{trade_no}}</strong></div>{{/trade_no}}
</div>
<p>{{notice_message}}</p>`.trim(),
  },
  {
    code: '100009',
    name: '手动入账通知',
    description: '管理员手动设为已支付后发送通知。',
    variables: ['invoice_no', 'order_no', 'paid_amount', 'payment_method', 'paid_at', 'trade_no', 'remark'],
    defaultSubject: '账单支付确认通知',
    defaultContent: `
<p>您好：</p>
<p>您的账单已由管理员手动入账，具体信息如下。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>
  <div class="mail-kv"><span>订单号</span><strong>{{order_no}}</strong></div>
  <div class="mail-kv"><span>支付金额</span><strong>¥{{paid_amount}}</strong></div>
  <div class="mail-kv"><span>支付方式</span><strong>{{payment_method}}</strong></div>
  <div class="mail-kv"><span>支付时间</span><strong>{{paid_at}}</strong></div>
  {{#trade_no}}<div class="mail-kv"><span>支付流水号</span><strong>{{trade_no}}</strong></div>{{/trade_no}}
  {{#remark}}<div class="mail-kv"><span>备注</span><strong>{{remark}}</strong></div>{{/remark}}
</div>
<p class="mail-muted">如对本次处理有疑问，请及时联系管理员。</p>`.trim(),
  },
  {
    code: '100010',
    name: '新工单提醒',
    description: '客户提交新工单后通知管理员。',
    variables: ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'],
    defaultSubject: '【{{site_name}}】新工单提醒 #{{ticket_id}}',
    defaultContent: `
<p>您好，{{recipient_name}}：</p>
<p>有客户提交了新的工单，请尽快处理。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>工单编号</span><strong>#{{ticket_id}}</strong></div>
  <div class="mail-kv"><span>工单标题</span><strong>{{ticket_subject}}</strong></div>
  <div class="mail-kv"><span>工单分类</span><strong>{{department}}</strong></div>
  <div class="mail-kv"><span>优先级</span><strong>{{priority}}</strong></div>
  <div class="mail-kv"><span>当前状态</span><strong>{{status}}</strong></div>
  <div class="mail-kv"><span>提交用户</span><strong>{{client_name}}</strong></div>
  <div class="mail-kv"><span>用户邮箱</span><strong>{{client_email}}</strong></div>
</div>
<p>工单内容摘要：{{message_preview}}</p>
<p class="mail-muted">请尽快登录后台工单页面查看并回复。</p>`.trim(),
  },
  {
    code: '100011',
    name: '工单待回复提醒',
    description: '客户补充工单回复后通知管理员。',
    variables: ['site_name', 'recipient_name', 'ticket_id', 'ticket_subject', 'department', 'priority', 'status', 'client_name', 'client_email', 'message_preview'],
    defaultSubject: '【{{site_name}}】工单待回复提醒 #{{ticket_id}}',
    defaultContent: `
<p>您好，{{recipient_name}}：</p>
<p>客户刚刚补充了工单回复，请及时跟进。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>工单编号</span><strong>#{{ticket_id}}</strong></div>
  <div class="mail-kv"><span>工单标题</span><strong>{{ticket_subject}}</strong></div>
  <div class="mail-kv"><span>工单分类</span><strong>{{department}}</strong></div>
  <div class="mail-kv"><span>优先级</span><strong>{{priority}}</strong></div>
  <div class="mail-kv"><span>当前状态</span><strong>{{status}}</strong></div>
  <div class="mail-kv"><span>提交用户</span><strong>{{client_name}}</strong></div>
  <div class="mail-kv"><span>用户邮箱</span><strong>{{client_email}}</strong></div>
</div>
<p>最新回复摘要：{{message_preview}}</p>
<p class="mail-muted">请尽快登录后台工单页面查看并回复。</p>`.trim(),
  },
  {
    code: '100012',
    name: '工单回复通知',
    description: '管理员回复工单后通知用户。',
    variables: ['site_name', 'display_name', 'ticket_id', 'ticket_subject', 'status', 'staff_name', 'message_preview', 'tickets_url', 'login_tip'],
    defaultSubject: '【{{site_name}}】工单回复通知 #{{ticket_id}}',
    defaultContent: `
<p>您好，{{display_name}}：</p>
<p>您的工单已有管理员回复，请及时查看。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>工单编号</span><strong>#{{ticket_id}}</strong></div>
  <div class="mail-kv"><span>工单标题</span><strong>{{ticket_subject}}</strong></div>
  <div class="mail-kv"><span>当前状态</span><strong>{{status}}</strong></div>
  <div class="mail-kv"><span>回复人员</span><strong>{{staff_name}}</strong></div>
</div>
<p>回复摘要：{{message_preview}}</p>
{{#tickets_url}}<a class="mail-button" href="{{tickets_url}}" target="_blank" rel="noopener">查看工单详情</a>{{/tickets_url}}
{{#login_tip}}<p class="mail-muted">{{login_tip}}</p>{{/login_tip}}`.trim(),
  },
  {
    code: '100013',
    name: '用户下单提醒',
    description: '用户创建新订单后通知管理员。',
    variables: ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'order_type_label', 'product_name', 'billing_cycle_label', 'order_amount', 'order_status_label', 'created_at'],
    defaultSubject: '【{{site_name}}】用户下单提醒 #{{order_no}}',
    defaultContent: `
<p>您好，{{recipient_name}}：</p>
<p>有用户刚刚提交了新的订单，请及时关注。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>用户名称</span><strong>{{user_name}}</strong></div>
  <div class="mail-kv"><span>用户邮箱</span><strong>{{user_email}}</strong></div>
  <div class="mail-kv"><span>订单编号</span><strong>{{order_no}}</strong></div>
  {{#invoice_no}}<div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>{{/invoice_no}}
  <div class="mail-kv"><span>订单类型</span><strong>{{order_type_label}}</strong></div>
  <div class="mail-kv"><span>配置名称</span><strong>{{product_name}}</strong></div>
  <div class="mail-kv"><span>计费周期</span><strong>{{billing_cycle_label}}</strong></div>
  <div class="mail-kv"><span>订单金额</span><strong>¥{{order_amount}}</strong></div>
  <div class="mail-kv"><span>订单状态</span><strong>{{order_status_label}}</strong></div>
  <div class="mail-kv"><span>下单时间</span><strong>{{created_at}}</strong></div>
</div>
<p class="mail-muted">请尽快登录后台订单页面查看详情。</p>`.trim(),
  },
  {
    code: '100014',
    name: '用户支付完成提醒',
    description: '用户订单支付完成后通知管理员。',
    variables: ['site_name', 'recipient_name', 'user_name', 'user_email', 'order_no', 'invoice_no', 'product_name', 'billing_cycle_label', 'paid_amount', 'payment_method', 'trade_no', 'paid_at'],
    defaultSubject: '【{{site_name}}】用户支付完成 #{{order_no}}',
    defaultContent: `
<p>您好，{{recipient_name}}：</p>
<p>有用户订单已完成支付，请及时跟进后续处理。</p>
<div class="mail-panel">
  <div class="mail-kv"><span>用户名称</span><strong>{{user_name}}</strong></div>
  <div class="mail-kv"><span>用户邮箱</span><strong>{{user_email}}</strong></div>
  <div class="mail-kv"><span>订单编号</span><strong>{{order_no}}</strong></div>
  {{#invoice_no}}<div class="mail-kv"><span>账单编号</span><strong>{{invoice_no}}</strong></div>{{/invoice_no}}
  <div class="mail-kv"><span>配置名称</span><strong>{{product_name}}</strong></div>
  <div class="mail-kv"><span>计费周期</span><strong>{{billing_cycle_label}}</strong></div>
  <div class="mail-kv"><span>支付金额</span><strong>¥{{paid_amount}}</strong></div>
  <div class="mail-kv"><span>支付方式</span><strong>{{payment_method}}</strong></div>
  {{#trade_no}}<div class="mail-kv"><span>支付流水号</span><strong>{{trade_no}}</strong></div>{{/trade_no}}
  <div class="mail-kv"><span>支付时间</span><strong>{{paid_at}}</strong></div>
</div>
<p class="mail-muted">请登录后台订单页面查看详情。</p>`.trim(),
  },
]

const loading = ref(false)
const savingTemplates = ref(false)

const emailTemplates = reactive(
  EMAIL_TEMPLATE_DEFINITIONS.map((item) => ({
    ...item,
    subject: item.defaultSubject,
    content: item.defaultContent,
  }))
)

const templateCode = computed(() => String(route.params.code || '').trim())
const templateDefinition = computed(() => findTemplate(templateCode.value))
const currentTab = computed(() => {
  if (route.query.tab === 'admin' || route.query.tab === 'user') {
    return route.query.tab
  }

  return ADMIN_TEMPLATE_CODES.has(templateCode.value) ? 'admin' : 'user'
})
const siteName = computed(() => appStore.siteName || '创欧云')
const previewBaseParams = computed(() => ({
  site_name: siteName.value,
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
  urgency_message: '您的服务将在 3 天后到期，请提前完成续费，避免业务中断。',
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
  notice_message: '请在到期前完成支付，以免影响关联服务的继续使用。',
  status_label: '待支付',
  paid_amount: '199.00',
  remark: '人工核账通过',
  ticket_id: '1024',
  ticket_subject: '实例网络不通',
  department: '技术支持',
  priority: '高',
  status: '处理中',
  message_preview: '您好，实例无法 SSH 登录，请协助排查网络与防火墙设置。',
  staff_name: '技术支持 A',
  tickets_url: buildDefaultTicketUrl(),
  login_tip: '如您尚未登录，请先登录后查看工单详情。',
}))

function buildDefaultTicketUrl() {
  const clientBase = String(import.meta.env.VITE_CLIENT_APP_URL || '').trim().replace(/\/+$/, '')
  if (clientBase) return `${clientBase}/client/tickets`
  if (typeof window === 'undefined') return '/client/tickets'
  return new URL('/client/tickets', window.location.origin).href
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function hasTemplateValue(value) {
  return ![null, undefined, '', false].includes(value)
}

function looksLikeHtml(template) {
  const normalized = String(template || '').trim()
  return /^(<!doctype\s+html|<html\b|<body\b)/i.test(normalized) || /<([a-z][a-z0-9]*)(\s|>)/i.test(normalized)
}

function renderTemplateWithResolver(template, params, resolver, htmlMode = false) {
  const source = String(template || '')
  let rendered = source.replace(/\{\{#([a-zA-Z0-9_]+)\}\}([\s\S]*?)\{\{\/\1\}\}/g, (_, key, content) => (
    hasTemplateValue(params[key]) ? content : ''
  ))

  rendered = rendered.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, key) => resolver(key))

  if (!htmlMode) {
    rendered = rendered.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n')
  }

  return rendered.trim()
}

function renderTemplateText(template, params) {
  return renderTemplateWithResolver(template, params, (key) => String(params[key] ?? ''))
}

function convertPlainTextToHtml(content) {
  const normalized = String(content || '').trim()
  if (!normalized) {
    return '<p class="mail-empty">暂无邮件内容</p>'
  }

  return normalized
    .split(/\n{2,}/)
    .map((block) => block
      .split('\n')
      .map((line) => escapeHtml(line))
      .join('<br>'))
    .filter(Boolean)
    .map((block) => `<p>${block}</p>`)
    .join('\n')
}

function renderTemplateContent(template, params) {
  if (looksLikeHtml(template)) {
    return renderTemplateWithResolver(template, params, (key) => escapeHtml(params[key] ?? ''), true)
  }

  return convertPlainTextToHtml(renderTemplateText(template, params))
}

function getPreviewParams(template) {
  const params = { ...previewBaseParams.value }
  template.variables.forEach((key) => {
    if (!(key in params)) {
      params[key] = `示例${key}`
    }
  })
  return params
}

function getPreviewValue(template, key) {
  return String(getPreviewParams(template)[key] ?? '-')
}

function renderPreviewSubject(template) {
  return renderTemplateText(template.subject, getPreviewParams(template)) || '未设置主题'
}

function buildInlineMailLogoSvg() {
  const svg = String(emailLogoSvgRaw || '').trim()
  if (!svg) {
    return ''
  }

  return svg
    .replace(/<\?xml[\s\S]*?\?>\s*/i, '')
    .replace(/<svg\b([^>]*)>/i, '<svg class="mail-logo" aria-hidden="true"$1>')
}

function buildPreviewDocument(template) {
  const params = getPreviewParams(template)
  const subject = renderTemplateText(template.subject, params) || '未设置主题'
  const content = renderTemplateContent(template.content, params)
  const logoMarkup = buildInlineMailLogoSvg()

  const previewDocument = `<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${escapeHtml(subject)}</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f3f4f6;
      font-family: "PingFang SC", "Microsoft YaHei", Arial, sans-serif;
      color: #1f2329;
    }
    .mail-shell {
      width: 100%;
      padding: 32px 12px;
      box-sizing: border-box;
      background: #f3f4f6;
    }
    .mail-card {
      width: 100%;
      max-width: 680px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #cfd6e4;
      overflow: hidden;
    }
    .mail-header {
      display: flex;
      align-items: center;
      padding: 24px 28px 20px;
      border-top: 4px solid #1f4b99;
      border-bottom: 1px solid #d9e0ec;
      background: #f8fafc;
    }
    .mail-branding {
      display: flex;
      align-items: center;
      gap: 16px;
      min-width: 0;
    }
    .mail-logo {
      display: block;
      flex: 0 0 auto;
      width: auto;
      height: 32px;
      max-width: 180px;
    }
    .mail-brand strong {
      display: block;
      font-size: 18px;
      line-height: 1.3;
      letter-spacing: 0.02em;
      color: #162033;
    }
    .mail-brand span {
      display: block;
      margin-top: 6px;
      font-size: 12px;
      color: #5b6575;
    }
    .mail-body {
      padding: 28px;
    }
    .mail-title {
      margin: 0;
      font-size: 28px;
      line-height: 1.4;
      color: #162033;
    }
    .mail-summary {
      margin: 12px 0 0;
      font-size: 14px;
      line-height: 1.8;
      color: #4b5565;
    }
    .mail-divider {
      height: 1px;
      margin: 24px 0;
      background: #d9e0ec;
    }
    .mail-content {
      font-size: 14px;
      line-height: 1.85;
      color: #1f2329;
    }
    .mail-content p {
      margin: 0 0 14px;
    }
    .mail-content p:last-child {
      margin-bottom: 0;
    }
    .mail-content strong {
      color: #162033;
    }
    .mail-content a {
      color: #1f4b99;
      text-decoration: underline;
    }
    .mail-content .mail-panel {
      margin: 18px 0;
      padding: 16px 18px;
      border: 1px solid #d9e0ec;
      background: #f8fafc;
    }
    .mail-content .mail-code {
      display: inline-block;
      margin: 8px 0 16px;
      padding: 14px 18px;
      border: 1px solid #1f4b99;
      background: #eef4ff;
      color: #1f4b99;
      font-size: 28px;
      line-height: 1;
      font-weight: 700;
      letter-spacing: 0.18em;
    }
    .mail-content .mail-kv {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      padding: 10px 0;
      border-bottom: 1px solid #d9e0ec;
    }
    .mail-content .mail-kv:first-child {
      padding-top: 0;
    }
    .mail-content .mail-kv:last-child {
      padding-bottom: 0;
      border-bottom: none;
    }
    .mail-content .mail-kv span {
      color: #4e5969;
      white-space: nowrap;
    }
    .mail-content .mail-kv strong {
      text-align: right;
      word-break: break-word;
    }
    .mail-content .mail-button {
      display: inline-block;
      margin-top: 12px;
      padding: 12px 18px;
      border: 1px solid #1f4b99;
      background: #1f4b99;
      color: #ffffff;
      font-weight: 600;
      text-decoration: none;
    }
    .mail-content .mail-muted,
    .mail-footer {
      color: #6b7280;
      font-size: 12px;
      line-height: 1.8;
    }
    .mail-footer {
      padding: 0 28px 28px;
    }
    .mail-empty {
      color: #86909c;
    }
    @media screen and (max-width: 640px) {
      .mail-shell {
        padding: 18px 10px;
      }
      .mail-header,
      .mail-body,
      .mail-footer {
        padding-left: 18px;
        padding-right: 18px;
      }
      .mail-title {
        font-size: 22px;
      }
      .mail-branding {
        gap: 12px;
      }
      .mail-logo {
        height: 28px;
        max-width: 140px;
      }
      .mail-content .mail-kv {
        display: block;
      }
      .mail-content .mail-kv strong {
        display: block;
        margin-top: 6px;
        text-align: left;
      }
    }
  </style>
</head>
<body>
  <div class="mail-shell">
    <div class="mail-card">
      <div class="mail-header">
        <div class="mail-branding">
          ${logoMarkup}
          <div class="mail-brand">
            <strong>${escapeHtml(siteName.value)}</strong>
            <span>自动通知邮件</span>
          </div>
        </div>
      </div>
      <div class="mail-body">
        <h1 class="mail-title">${escapeHtml(subject)}</h1>
        <p class="mail-summary">您当前看到的是模板预览效果，发送时会按同样的站点外壳渲染。</p>
        <div class="mail-divider"></div>
        <div class="mail-content">${content}</div>
      </div>
      <div class="mail-footer">
        此邮件由 ${escapeHtml(siteName.value)} 系统自动发送，请勿直接回复。如有疑问，请登录站点控制台或联系站内支持。
      </div>
    </div>
  </div>
</body>
</html>`

  return previewDocument
}

function findTemplate(code) {
  return emailTemplates.find((item) => item.code === code)
}

function templateSettingKey(type, code) {
  return `email_template_${type}_${code}`
}

function resetTemplate(template) {
  template.subject = template.defaultSubject
  template.content = template.defaultContent
}

const loadSettings = async () => {
  loading.value = true
  try {
    emailTemplates.forEach((template) => resetTemplate(template))
    const { data } = await adminApi.settings.list({ group: 'notification' })
    data.forEach((item) => {
      if (String(item.key).startsWith('email_template_subject_')) {
        const code = String(item.key).replace('email_template_subject_', '')
        const template = findTemplate(code)
        if (template) {
          template.subject = item.value || template.defaultSubject
        }
      }

      if (String(item.key).startsWith('email_template_content_')) {
        const code = String(item.key).replace('email_template_content_', '')
        const template = findTemplate(code)
        if (template) {
          template.content = item.value || template.defaultContent
        }
      }
    })
  } catch {
    ElMessage.error('加载邮件模板失败')
  } finally {
    loading.value = false
  }
}

const saveCurrentTemplate = async () => {
  if (!templateDefinition.value) {
    return
  }

  savingTemplates.value = true
  try {
    await adminApi.settings.save({
      group: 'notification',
      settings: {
        [templateSettingKey('subject', templateDefinition.value.code)]: templateDefinition.value.subject,
        [templateSettingKey('content', templateDefinition.value.code)]: templateDefinition.value.content,
      },
    })

    ElMessage.success('模板已保存')
  } catch {
    ElMessage.error('保存模板失败')
  } finally {
    savingTemplates.value = false
  }
}

const resetCurrentTemplate = () => {
  if (templateDefinition.value) {
    resetTemplate(templateDefinition.value)
  }
}

const goBack = () => {
  router.push({
    path: '/admin/notifications/email-templates',
    query: { tab: currentTab.value },
  })
}

onMounted(loadSettings)
</script>

<style lang="scss" scoped>
.email-templates-page {
  display: flex;
  flex-direction: column;
  gap: 20px;

  .page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;

    h2 {
      margin: 0;
      font-size: 22px;
      color: #1f2937;
    }

    p {
      margin: 8px 0 0;
      color: #6b7280;
      font-size: 13px;
      line-height: 1.7;
    }
  }

  .page-title {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
  }

  .page-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .title-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .templates-card {
    min-height: 100%;
  }

  .templates-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .header-tip {
    margin: 8px 0 0;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.7;
  }

  .card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    color: #303133;
  }

  .card-icon {
    font-size: 18px;
    color: #2f6bff;
  }

  .template-collapse {
    :deep(.el-collapse-item__header) {
      height: auto;
      min-height: 52px;
      padding: 12px 0;
      align-items: center;
    }
  }

  .template-title {
    display: flex;
    flex-direction: column;
    gap: 6px;

    span {
      color: #6b7280;
      font-size: 12px;
    }
  }

  .template-title-main {
    display: flex;
    align-items: center;
    gap: 10px;

    strong {
      color: #111827;
      font-size: 14px;
    }
  }

  .template-body {
    padding: 8px 4px 12px;
  }

  .template-meta {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
  }

  .token-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .token-label {
    color: #6b7280;
    font-size: 12px;
  }

  .preview-params {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 18px;
  }

  .preview-param {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fafbfd;

    span {
      color: #6b7280;
      font-size: 12px;
      white-space: nowrap;
    }

    strong {
      color: #111827;
      font-size: 12px;
      text-align: right;
      word-break: break-word;
    }
  }

  .template-form {
    :deep(.el-form-item) {
      align-items: stretch;
    }

    :deep(.el-form-item__content) {
      align-items: stretch;
    }

    :deep(.el-form-item:last-child) {
      margin-bottom: 0;
    }
  }

  .subject-block {
    width: 100%;
  }

  .subject-preview {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #f8fbff;
    border: 1px solid #dbe7ff;

    span {
      color: #6b7280;
      font-size: 12px;
    }

    strong {
      color: #111827;
      font-size: 14px;
      line-height: 1.6;
      word-break: break-word;
    }
  }

  .editor-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 16px;
    width: 100%;
  }

  .editor-pane,
  .preview-pane {
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
  }

  .pane-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #eef1f6;
    background: #fafbfd;

    strong {
      color: #111827;
      font-size: 13px;
    }

    span {
      color: #86909c;
      font-size: 12px;
      text-align: right;
    }
  }

  .template-code-input {
    flex: 1;

    :deep(.el-textarea__inner) {
      min-height: 460px;
      border: none;
      border-radius: 0;
      box-shadow: none;
      font-family: "Cascadia Code", "SFMono-Regular", Consolas, "Liberation Mono", monospace;
      font-size: 13px;
      line-height: 1.75;
      color: #1f2329;
    }
  }

  .preview-frame {
    flex: 1;
    width: 100%;
    min-height: 460px;
    border: none;
    background: #f5f7fa;
  }
}

@media (max-width: 1200px) {
  .email-templates-page {
    .editor-grid {
      grid-template-columns: 1fr;
    }
  }
}

@media (max-width: 960px) {
  .email-templates-page {
    .page-header,
    .template-meta {
      flex-direction: column;
      align-items: stretch;
    }

    .preview-params {
      grid-template-columns: 1fr;
    }

    .pane-header {
      flex-direction: column;
      align-items: flex-start;
    }
  }
}
</style>
