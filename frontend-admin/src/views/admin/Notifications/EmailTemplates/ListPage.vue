<template>
  <div class="email-template-list-page">
    <div class="page-header">
      <div>
        <h2>邮件模板</h2>
        <p>按模板列表管理邮件通知。点击“查看”进入独立详情页进行编辑和预览。</p>
      </div>
      <el-button :icon="RefreshRight" @click="loadTemplates">刷新</el-button>
    </div>

    <el-card shadow="never" class="list-card" v-loading="loading">
      <el-tabs v-model="activeTab" class="template-tabs">
        <el-tab-pane :label="`用户模板（${userTemplateCount}）`" name="user" />
        <el-tab-pane :label="`管理员模板（${adminTemplateCount}）`" name="admin" />
      </el-tabs>

      <el-table :data="filteredRows" border class="template-table" @row-click="openTemplate">
        <el-table-column label="模板信息" min-width="260">
          <template #default="{ row }">
            <div class="template-main">
              <div class="title-row">
                <strong>{{ row.name }}</strong>
                <el-tag size="small" effect="plain">{{ row.code }}</el-tag>
                <el-tag size="small" :type="row.audience === 'admin' ? 'warning' : 'success'" effect="light">
                  {{ row.audience === 'admin' ? '管理员' : '用户' }}
                </el-tag>
              </div>
              <p>{{ row.description }}</p>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="当前主题" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.subject || '-' }}
          </template>
        </el-table-column>

        <el-table-column label="正文摘要" min-width="260" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.preview }}
          </template>
        </el-table-column>

        <el-table-column label="变量数" width="100" align="center">
          <template #default="{ row }">
            {{ row.variables.length }}
          </template>
        </el-table-column>

        <el-table-column label="正文类型" width="120" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="row.isHtml ? 'primary' : 'info'" effect="light">
              {{ row.isHtml ? 'HTML' : '文本' }}
            </el-tag>
          </template>
        </el-table-column>

      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RefreshRight } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import adminApi from '@/api/admin'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const settingsMap = ref({})
const activeTab = ref('user')
const ADMIN_TEMPLATE_CODES = new Set(['100010', '100011', '100013', '100014'])

const TEMPLATE_SUMMARIES = [
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
]

const templateRows = computed(() => TEMPLATE_SUMMARIES.map((template) => {
  const subject = settingsMap.value[`email_template_subject_${template.code}`] || template.defaultSubject
  const content = settingsMap.value[`email_template_content_${template.code}`] || template.defaultContent
  const preview = String(content || '')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  return {
    ...template,
    subject,
    preview: preview ? (preview.length > 88 ? `${preview.slice(0, 88)}...` : preview) : '-',
    isHtml: /<([a-z][a-z0-9]*)(\s|>)/i.test(String(content || '').trim()),
    audience: ADMIN_TEMPLATE_CODES.has(template.code) ? 'admin' : 'user',
  }
}))

const filteredRows = computed(() => templateRows.value.filter((row) => row.audience === activeTab.value))
const userTemplateCount = computed(() => templateRows.value.filter((row) => row.audience === 'user').length)
const adminTemplateCount = computed(() => templateRows.value.filter((row) => row.audience === 'admin').length)

async function loadTemplates() {
  loading.value = true

  try {
    const { data } = await adminApi.settings.list({ group: 'notification' })
    settingsMap.value = Object.fromEntries((data || []).map((item) => [item.key, item.value]))
  } catch {
    ElMessage.error('加载邮件模板列表失败')
  } finally {
    loading.value = false
  }
}

function openTemplate(row) {
  router.push({
    path: `/admin/notifications/email-templates/${row.code}`,
    query: { tab: activeTab.value },
  })
}

watch(() => route.query.tab, (value) => {
  activeTab.value = value === 'admin' ? 'admin' : 'user'
}, { immediate: true })

watch(activeTab, (value) => {
  if (route.query.tab === value) {
    return
  }

  router.replace({
    path: route.path,
    query: { ...route.query, tab: value },
  })
})

onMounted(loadTemplates)
</script>

<style lang="scss" scoped>
.email-template-list-page {
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

  .template-main {
    display: flex;
    flex-direction: column;
    gap: 6px;

    p {
      margin: 0;
      color: #6b7280;
      font-size: 12px;
      line-height: 1.6;
    }
  }

  .title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;

    strong {
      color: #111827;
      font-size: 14px;
    }
  }

  .template-tabs {
    margin-bottom: 12px;
  }
}

@media (max-width: 960px) {
  .email-template-list-page {
    .page-header {
      flex-direction: column;
      align-items: stretch;
    }
  }
}
</style>
