<template>
  <div class="settings-page admin-page">
    <el-tabs :model-value="pageKey" class="settings-hub-tabs" @tab-change="onTabChange">
      <el-tab-pane label="系统设置" name="system" />
      <el-tab-pane label="商品设置" name="product" />
      <el-tab-pane label="流量包设置" name="traffic_package" />
      <el-tab-pane label="支付配置" name="payment" />
      <el-tab-pane label="推荐奖励" name="referral" />
      <el-tab-pane label="自动化策略" name="automation" />
      <el-tab-pane label="基础信息" name="site_basic" />
      <el-tab-pane label="SEO 设置" name="site_seo" />
      <el-tab-pane label="首页 Banner" name="site_hero" />
    </el-tabs>

    <Suspense v-if="isSiteTab" timeout="0">
      <template #default>
        <SiteOpsPage :mode="siteMode" />
      </template>
      <template #fallback>
        <AdminAsyncPane />
      </template>
    </Suspense>
    <template v-else>
    <section class="settings-hero admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">配置中心</span>
        <p>{{ pageConfig.description }}</p>
      </div>
      <div class="page-actions">
        <el-button :icon="RefreshRight" @click="loadSettings">刷新</el-button>
        <el-button type="primary" :icon="Check" :loading="saving" @click="saveSettings">保存设置</el-button>
      </div>
    </section>

    <section v-if="sections.length > 1" class="section-nav">
      <div class="section-nav-label">分组</div>
      <el-segmented
        v-model="activeSection"
        class="section-segmented"
        :options="sectionSegmentOptions"
        :props="{ label: 'title', value: 'anchor' }"
      >
        <template #default="{ item }">
          <div class="section-segment-option">
            <span class="section-segment-option__title">{{ item.title }}</span>
            <span class="section-segment-option__meta">{{ item.fields.length }} 项</span>
          </div>
        </template>
      </el-segmented>
    </section>

    <el-card shadow="never" class="settings-card" v-loading="loading">
      <div v-if="currentSection" :key="currentSection.anchor" class="section-block">
        <el-form ref="formRef" :model="form" :rules="currentSectionRules" label-position="top" class="settings-form">
          <div class="field-grid">
            <div
              v-for="field in currentSection.fields"
              :key="field.key"
              class="field-card"
              :class="[`is-type-${field.type}`, { 'is-wide': field.wide }]"
            >
              <div class="field-info">
                <div class="field-title">
                  <span v-if="isFieldRequired(field)" class="field-required">*</span>
                  {{ field.label }}
                </div>
                <p v-if="field.help" class="field-desc">{{ field.help }}</p>
              </div>
              <div class="field-control">
                <el-form-item :prop="field.key" label-width="0">
                  <el-select
                    v-if="field.type === 'select'"
                    v-model="form[field.key]"
                    v-bind="resolveProps(field)"
                  >
                    <el-option
                      v-for="option in field.options"
                      :key="option.value"
                      :label="option.label"
                      :value="option.value"
                    />
                  </el-select>
                  <component
                    v-else
                    :is="resolveComponent(field)"
                    v-model="form[field.key]"
                    v-bind="resolveProps(field)"
                  />
                </el-form-item>
                <div v-if="field.preview === 'image' && form[field.key]" class="field-preview">
                  <img :src="form[field.key]" :alt="field.label" />
                </div>
              </div>
            </div>
          </div>
        </el-form>
      </div>
    </el-card>
    </template>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Check, RefreshRight } from '@element-plus/icons-vue'
import { ElInput, ElInputNumber, ElMessage, ElSelect, ElSwitch, ElTimePicker } from 'element-plus'
import adminApi from '@/api/admin'
import AdminAsyncPane from '@/components/common/AdminAsyncPane.vue'

const SiteOpsPage = defineAsyncComponent(() => import('./SiteSettingsPage.vue'))

const SETTING_TABS = Object.freeze([
  'system',
  'product',
  'traffic_package',
  'payment',
  'referral',
  'automation',
  'site_basic',
  'site_seo',
  'site_hero',
])

const SITE_TAB_TO_MODE = Object.freeze({
  site_basic: 'basic',
  site_seo: 'seo',
  site_hero: 'home_hero',
})

const route = useRoute()
const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const saving = ref(false)
const form = reactive({})
const activeSection = ref('')

const automationScheduleModeOptions = Object.freeze([
  { label: '每 5 分钟', value: 'every_five_minutes' },
  { label: '每 10 分钟', value: 'every_ten_minutes' },
  { label: '每 15 分钟', value: 'every_fifteen_minutes' },
  { label: '每 30 分钟', value: 'every_thirty_minutes' },
  { label: '每小时', value: 'hourly' },
  { label: '每天', value: 'daily' },
])

const pageConfigs = {
  system: {
    group: 'system',
    title: '系统设置',
    description: '集中配置 GeeTest 行为验证与邮件短信限流策略。',
    sections: [
      {
        title: 'GeeTest 行为验证',
        description: '用于登录、注册和验证码发送前的人机验证。',
        fields: [
          { key: 'geetest_enabled', label: '启用 GeeTest', type: 'switch', default: false, help: '开启后高风险操作将要求完成行为验证。' },
          { key: 'geetest_captcha_id', label: 'Captcha ID', type: 'input', default: '', requiredWhen: (model) => Boolean(model.geetest_enabled), help: '来自 GeeTest 控制台的 captcha_id。' },
          { key: 'geetest_captcha_key', label: 'Captcha Key', type: 'input', default: '', requiredWhen: (model) => Boolean(model.geetest_enabled), help: '来自 GeeTest 控制台的 captcha_key。' },
        ],
      },
      {
        title: '邮件短信限流',
        description: '控制验证码发送频率，避免接口被恶意刷取。',
        fields: [
          { key: 'email_rate_limit_enabled', group: 'message_limit', label: '启用邮箱限流', type: 'switch', default: false, help: '开启后按规则限制邮箱验证码发送。' },
          { key: 'email_cooldown_seconds', group: 'message_limit', label: '邮箱冷却时间（秒）', type: 'number', default: 60, min: 0, help: '同一邮箱两次发送之间的最小间隔。' },
          { key: 'email_target_hourly_limit', group: 'message_limit', label: '邮箱每小时上限', type: 'number', default: 10, min: 0, help: '0 表示不限制该项。' },
          { key: 'email_ip_hourly_limit', group: 'message_limit', label: 'IP 每小时上限', type: 'number', default: 20, min: 0, help: '防止同一来源批量请求邮箱验证码。' },
          { key: 'sms_rate_limit_enabled', group: 'message_limit', label: '启用短信限流', type: 'switch', default: false, help: '开启后按规则限制短信验证码发送。' },
          { key: 'sms_cooldown_seconds', group: 'message_limit', label: '手机号冷却时间（秒）', type: 'number', default: 60, min: 0, help: '同一手机号两次发送之间的最小间隔。' },
          { key: 'sms_target_hourly_limit', group: 'message_limit', label: '手机号每小时上限', type: 'number', default: 10, min: 0, help: '0 表示不限制该项。' },
          { key: 'sms_ip_hourly_limit', group: 'message_limit', label: '短信 IP 每小时上限', type: 'number', default: 20, min: 0, help: '防止同一来源批量请求短信验证码。' },
        ],
      },
    ],
  },
  product: {
    group: 'product',
    title: '商品设置',
    description: '统一维护商品展示、库存提醒和默认业务策略。',
    sections: [
      {
        title: '商品展示策略',
        description: '控制商品上架、排序和前台可见规则。',
        fields: [
          { key: 'product_auto_publish', label: '新商品默认上架', type: 'switch', default: true, help: '创建商品后默认可售。' },
          { key: 'show_out_of_stock', label: '显示缺货商品', type: 'switch', default: false, help: '关闭后前台隐藏库存不足的商品。' },
          {
            key: 'product_sort_mode',
            label: '默认排序方式',
            type: 'select',
            default: 'sort_order',
            options: [
              { label: '手动排序', value: 'sort_order' },
              { label: '创建时间', value: 'created_at' },
              { label: '名称排序', value: 'name' },
            ],
            help: '影响前台默认商品列表顺序。',
          },
        ],
      },
      {
        title: '库存与账单',
        description: '约束库存提醒、默认计费周期和未支付账单处理策略。',
        fields: [
          { key: 'enable_stock_warning', label: '启用库存预警', type: 'switch', default: true, help: '库存低于阈值时提醒管理员。' },
          { key: 'stock_warning_value', label: '库存预警阈值', type: 'number', default: 5, min: 0, help: '库存低于该值时触发预警。' },
          {
            key: 'default_billing_cycle',
            label: '默认计费周期',
            type: 'select',
            default: 'monthly',
            options: [
              { label: '月付', value: 'monthly' },
              { label: '季付', value: 'quarterly' },
              { label: '半年付', value: 'semi' },
              { label: '年付', value: 'yearly' },
            ],
            help: '商品创建时默认使用的计费周期。',
          },
          { key: 'order_auto_cancel_minutes', label: '账单自动取消（分钟）', type: 'number', default: 30, min: 1, help: '未支付账单自动取消时间。' },
        ],
      },
    ],
  },
  traffic_package: {
    group: 'traffic_package',
    title: '流量包设置',
    description: '集中管理用户端服务控制台的流量包购买入口、展示阈值和上游配置项匹配规则。',
    sections: [
      {
        title: '功能开关',
        description: '控制购买入口何时出现，以及前台按钮文案。',
        fields: [
          { key: 'traffic_package_enabled', label: '启用流量包购买', type: 'switch', default: true, help: '关闭后用户端不再展示流量包购买入口，也无法创建流量包账单。' },
          { key: 'traffic_package_button_text', label: '按钮文案', type: 'input', default: '购买流量包', help: '显示在服务控制台流量进度条右侧的按钮名称。' },
          { key: 'traffic_package_display_threshold_percent', label: '显示阈值（%）', type: 'number', default: 0, min: 0, max: 100, help: '仅当流量使用率达到该百分比后才展示购买按钮；填 0 表示只要存在流量上限就始终展示。' },
        ],
      },
      {
        title: '上游匹配规则',
        description: '用于从上游可升降级配置项里识别哪一项是流量包。',
        fields: [
          { key: 'traffic_package_option_field', label: '配置项字段名', type: 'input', default: 'flow_limit', help: '优先按该字段名匹配上游配置项，默认使用 `flow_limit`。' },
          { key: 'traffic_package_option_keyword', label: '配置项关键字', type: 'input', default: '流量', help: '字段名未命中时，按配置项名称里包含的关键字兜底匹配。' },
          { key: 'traffic_package_allow_choice_mode', label: '允许单选档位模式', type: 'switch', default: true, help: '适用于上游返回 1T / 2T / 3T 这种固定档位的流量包。' },
          { key: 'traffic_package_allow_quantity_mode', label: '允许数量区间模式', type: 'switch', default: true, help: '适用于上游返回最小值 / 最大值区间，由用户直接填写目标总流量的模式。' },
        ],
      },
    ],
  },
  payment: {
    group: 'payment',
    title: '支付配置',
    description: '配置支付开关、前台支付名称以及支付宝当面付参数。',
    sections: [
      {
        title: '支付宝支付',
        description: '控制前台支付展示，并配置支付宝开放平台应用参数。',
        fields: [
          { key: 'alipay_enabled', label: '启用支付宝支付', type: 'switch', default: false, help: '开启后客户端账单页将显示支付宝支付选项。' },
          { key: 'alipay_name', label: '前端名称', type: 'input', default: '支付宝支付', requiredWhen: (model) => Boolean(model.alipay_enabled), help: '客户端支付方式上显示的名称。' },
          { key: 'alipay_app_id', label: 'APPID', type: 'input', default: '', requiredWhen: (model) => Boolean(model.alipay_enabled), help: '支付宝开放平台的应用 APPID。' },
          { key: 'alipay_private_key', label: '商户私钥', type: 'input', default: '', wide: true, requiredWhen: (model) => Boolean(model.alipay_enabled), help: 'RSA2 应用私钥，PKCS1 格式，一行字符串。' },
          { key: 'alipay_public_key', label: '支付宝公钥', type: 'input', default: '', wide: true, requiredWhen: (model) => Boolean(model.alipay_enabled), help: '用于验证回调签名的支付宝公钥。' },
        ],
      },
    ],
  },
  referral: {
    group: 'referral',
    title: '推荐奖励配置',
    description: '集中控制推荐返利开关、默认比例、冻结期和最低提现门槛。',
    sections: [
      {
        title: '奖励规则',
        description: '会员等级未命中时将回退到这里的默认奖励策略。',
        fields: [
          { key: 'enabled', label: '启用推荐返利', type: 'switch', default: true, help: '关闭后推荐绑定与返利逻辑停止执行。' },
          { key: 'reward_rate', label: '默认奖励比例（%）', type: 'number', default: 10, min: 0, max: 100, help: '未命中会员等级时的默认返利比例。' },
          { key: 'reward_freeze_days', label: '奖励冻结期（天）', type: 'number', default: 4, min: 0, max: 365, help: '奖励进入可提现余额前需要冻结的天数。' },
          { key: 'withdraw_min_amount', label: '最低提现金额（元）', type: 'number', default: 20, min: 0, help: '用户可发起提现申请的最低门槛。' },
        ],
      },
    ],
  },
  automation: {
    group: 'automation',
    title: '自动化策略配置',
    description: '配置服务生命周期、账单提醒、工单关闭和未支付账单清理策略。',
    sections: [
      {
        title: '服务生命周期',
        description: '管理到期后的自动暂停、通知和自动终止策略。',
        fields: [
          { key: 'expire_suspend_enabled', label: '启用到期自动暂停', type: 'switch', default: true, help: '服务到期后按设定天数自动暂停。' },
          { key: 'expire_suspend_after_days', label: '到期后暂停天数', type: 'number', default: 0, min: 0, max: 365, help: '0 表示到期当天即暂停。' },
          { key: 'expire_suspend_notify_enabled', label: '暂停前发送通知', type: 'switch', default: true, help: '服务被暂停时向用户发送提醒。' },
          { key: 'expire_unsuspend_notify_enabled', label: '恢复后发送通知', type: 'switch', default: true, help: '服务恢复时向用户发送提醒。' },
          { key: 'expire_terminate_enabled', label: '启用自动终止', type: 'switch', default: false, help: '暂停后达到阈值可自动终止服务。' },
          { key: 'expire_terminate_after_days', label: '暂停后终止天数', type: 'number', default: 7, min: 1, max: 365, help: '服务暂停后至少保留 1 天，再到期自动终止。' },
          { key: 'service_lifecycle_schedule_mode', label: '任务执行周期', type: 'select', default: 'every_five_minutes', options: automationScheduleModeOptions, help: '控制服务生命周期维护任务的执行周期。' },
          { key: 'service_lifecycle_schedule_time', label: '执行时间', type: 'time', default: '00:05:00', help: '按“每小时 / 每天”执行时生效。' },
        ],
      },
      {
        title: '账单与提醒',
        description: '控制续费提醒、未支付提醒和逾期处理策略。',
        fields: [
          { key: 'renew_notice_enabled', label: '启用续费提醒', type: 'switch', default: true, help: '固定在到期前 7 / 3 / 1 天提醒。' },
          { key: 'renew_create_invoice_enabled', label: '自动创建续费账单', type: 'switch', default: true, help: '到达提醒窗口时自动生成续费账单。' },
          { key: 'invoice_unpaid_reminder_enabled', label: '启用未支付提醒', type: 'switch', default: true, help: '账单到期前向用户发送未支付提醒。' },
          { key: 'invoice_unpaid_before_due_days', label: '到期前提醒天数', type: 'number', default: 1, min: 0, max: 30, help: '距离账单到期前多少天开始提醒。' },
          { key: 'invoice_overdue_reminder_days', label: '逾期提醒天数', type: 'input', default: '1,3,5', wide: true, pattern: /^\d+(,\d+)*$/, patternMessage: '请使用英文逗号分隔天数，例如 1,3,5', help: '多个提醒节点用英文逗号分隔，例如 1,3,5。' },
          { key: 'invoice_overdue_after_days', label: '逾期标记天数', type: 'number', default: 0, min: 0, max: 365, help: '账单逾期多少天后标记为逾期。' },
          { key: 'billing_maintenance_schedule_mode', label: '任务执行周期', type: 'select', default: 'hourly', options: automationScheduleModeOptions, help: '控制账单自动化维护任务的执行周期。' },
          { key: 'billing_maintenance_schedule_time', label: '执行时间', type: 'time', default: '00:00:00', help: '按“每小时 / 每天”执行时生效。' },
        ],
      },
      {
        title: '工单与待支付清理',
        description: '减少长期悬而未决的工单和未支付账单占用。',
        fields: [
          { key: 'ticket_auto_close_enabled', label: '启用工单自动关闭', type: 'switch', default: true, help: '员工回复后超出阈值自动关闭工单。' },
          { key: 'ticket_auto_close_after_hours', label: '工单自动关闭时长（小时）', type: 'number', default: 48, min: 1, max: 720, help: '超出该时长未获客户回复则自动关闭。' },
          { key: 'pending_order_cleanup_enabled', label: '启用未支付账单清理', type: 'switch', default: true, help: '自动取消长期未支付的账单。' },
          { key: 'pending_order_cleanup_after_hours', label: '未支付账单保留时长（小时）', type: 'number', default: 1, min: 1, max: 720, help: '超出该时长后自动关闭账单。' },
          { key: 'pending_recharge_cleanup_enabled', label: '启用未支付充值单清理', type: 'switch', default: true, help: '自动清理长期未支付的充值申请。' },
          { key: 'pending_recharge_cleanup_after_days', label: '未支付充值单保留天数', type: 'number', default: 3, min: 0, max: 365, help: '达到该天数后自动失效。' },
          { key: 'ticket_auto_close_schedule_mode', label: '工单任务执行周期', type: 'select', default: 'hourly', options: automationScheduleModeOptions, help: '控制工单自动关闭任务的执行周期。' },
          { key: 'ticket_auto_close_schedule_time', label: '工单执行时间', type: 'time', default: '00:00:00', help: '按“每小时 / 每天”执行时生效。' },
          { key: 'order_cleanup_schedule_mode', label: '账单清理执行周期', type: 'select', default: 'every_five_minutes', options: automationScheduleModeOptions, help: '控制账单与充值清理任务的执行周期。' },
          { key: 'order_cleanup_schedule_time', label: '账单清理执行时间', type: 'time', default: '00:00:00', help: '按“每小时 / 每天”执行时生效。' },
        ],
      },
    ],
  },
}
const pageKey = computed(() => {
  const q = route.query.tab
  // 兼容旧 ?tab=site：默认跳 site_basic
  if (q === 'site') return 'site_basic'
  if (q && SETTING_TABS.includes(q)) return q
  return route.meta.settingPage || 'system'
})
const isSiteTab = computed(() => Boolean(SITE_TAB_TO_MODE[pageKey.value]))
const siteMode = computed(() => SITE_TAB_TO_MODE[pageKey.value] || 'basic')
const pageConfig = computed(() => pageConfigs[pageKey.value] || pageConfigs.system)
const sections = computed(() =>
  pageConfig.value.sections.map((section, index) => ({
    ...section,
    anchor: `${pageKey.value}-section-${index}`,
  }))
)
const sectionSegmentOptions = computed(() => sections.value.map(section => ({
  label: section.title,
  value: section.anchor,
  ...section
})))
const currentSection = computed(() =>
  sections.value.find((section) => section.anchor === activeSection.value) || sections.value[0] || null
)

const allFields = computed(() =>
  sections.value.flatMap((section) => section.fields)
)

const activeGroups = computed(() =>
  [...new Set(allFields.value.map((field) => field.group || pageConfig.value.group))]
)

const isFieldRequired = (field) => {
  return typeof field.requiredWhen === 'function' ? field.requiredWhen(form) : Boolean(field.required)
}

const currentSectionRules = computed(() => {
  const rules = {}

  for (const field of currentSection.value?.fields || []) {
    const fieldRules = []
    const trigger = field.type === 'select' || field.type === 'switch' || field.type === 'time' ? 'change' : 'blur'
    const isRequired = typeof field.requiredWhen === 'function' ? field.requiredWhen(form) : Boolean(field.required)

    if (isRequired) {
      fieldRules.push({
        validator: (_rule, value, callback) => {
          const normalized = field.type === 'switch' ? value : String(value ?? '').trim()
          if (normalized === '' || normalized === undefined || normalized === null) {
            callback(new Error(`请填写${field.label}`))
            return
          }

          callback()
        },
        trigger,
      })
    }

    if (field.type === 'number') {
      fieldRules.push({
        validator: (_rule, value, callback) => {
          if (value === '' || value === undefined || value === null) {
            callback()
            return
          }

          const parsed = Number(value)
          if (Number.isNaN(parsed)) {
            callback(new Error(`${field.label}必须是数字`))
            return
          }

          if (field.min !== undefined && parsed < field.min) {
            callback(new Error(`${field.label}不能小于 ${field.min}`))
            return
          }

          if (field.max !== undefined && parsed > field.max) {
            callback(new Error(`${field.label}不能大于 ${field.max}`))
            return
          }

          callback()
        },
        trigger: 'change',
      })
    }

    if (field.pattern) {
      fieldRules.push({
        validator: (_rule, value, callback) => {
          const normalized = String(value ?? '').trim()
          if (!normalized) {
            callback()
            return
          }

          if (!field.pattern.test(normalized)) {
            callback(new Error(field.patternMessage || `${field.label}格式不正确`))
            return
          }

          callback()
        },
        trigger,
      })
    }

    if (fieldRules.length > 0) {
      rules[field.key] = fieldRules
    }
  }

  return rules
})


const resetForm = () => {
  Object.keys(form).forEach((key) => {
    delete form[key]
  })

  allFields.value.forEach((field) => {
    form[field.key] = field.default
  })
}

const parseValue = (field, value) => {
  if (value === undefined || value === null || value === '') {
    return field.default
  }

  if (field.type === 'switch') {
    return value === true || value === '1' || value === 1 || value === 'true'
  }

  if (field.type === 'number') {
    const parsed = Number(value)
    return Number.isNaN(parsed) ? field.default : parsed
  }

  if (field.type === 'time') {
    return String(value)
  }

  return value
}

const buildPayload = () => {
  return allFields.value.reduce((payload, field) => {
    let value = form[field.key]
    const group = field.group || pageConfig.value.group

    if (field.type === 'switch') {
      value = value ? 1 : 0
    }

    if (!payload[group]) {
      payload[group] = {}
    }

    payload[group][field.key] = value
    return payload
  }, {})
}

const loadSettings = async () => {
  loading.value = true
  try {
    resetForm()
    const responses = await Promise.all(
      activeGroups.value.map((group) => adminApi.settings.list({ group }))
    )
    const settingMaps = Object.fromEntries(
      activeGroups.value.map((group, index) => [
        group,
        Object.fromEntries(((responses[index]?.data) || []).map((item) => [item.key, item.value])),
      ])
    )

    allFields.value.forEach((field) => {
      const group = field.group || pageConfig.value.group
      form[field.key] = parseValue(field, settingMaps[group]?.[field.key])
    })
    formRef.value?.clearValidate?.()
  } catch (error) {
    ElMessage.error(error.message || '加载设置失败')
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  const valid = await formRef.value?.validate?.().catch(() => false)
  if (!valid) {
    return
  }

  saving.value = true
  try {
    const payload = buildPayload()
    await Promise.all(
      Object.entries(payload).map(([group, settings]) =>
        adminApi.settings.save({ group, settings })
      )
    )
    ElMessage.success(`${pageConfig.value.title}已保存`)
  } catch (error) {
    ElMessage.error(error.message || '保存设置失败')
  } finally {
    saving.value = false
  }
}

const resolveComponent = (field) => {
  if (field.type === 'switch') return ElSwitch
  if (field.type === 'select') return ElSelect
  if (field.type === 'number') return ElInputNumber
  if (field.type === 'time') return ElTimePicker
  return ElInput
}

const resolveProps = (field) => {
  if (field.type === 'textarea') {
    return {
      type: 'textarea',
      rows: field.rows || 4,
      placeholder: field.placeholder || `请输入${field.label}`,
      ...(field.props || {}),
    }
  }

  if (field.type === 'select') {
    return {
      placeholder: field.placeholder || `请选择${field.label}`,
      clearable: true,
      ...(field.props || {}),
    }
  }

  if (field.type === 'number') {
    return {
      min: field.min ?? 0,
      max: field.max,
      controlsPosition: 'right',
      placeholder: field.placeholder || `请输入${field.label}`,
      ...(field.props || {}),
    }
  }

  if (field.type === 'time') {
    return {
      clearable: false,
      format: 'HH:mm',
      valueFormat: 'HH:mm:ss',
      placeholder: field.placeholder || `请选择${field.label}`,
      ...(field.props || {}),
    }
  }

  if (field.type === 'switch') {
    return {
      ...(field.props || {}),
    }
  }

  if (field.type === 'password') {
    return {
      placeholder: field.placeholder || `请输入${field.label}`,
      showPassword: true,
      ...(field.props || {}),
    }
  }

  return {
    placeholder: field.placeholder || `请输入${field.label}`,
    ...(field.props || {}),
  }
}

watch(pageKey, () => {
  if (isSiteTab.value) return
  loadSettings()
}, { immediate: true })

watch(sections, (nextSections) => {
  activeSection.value = nextSections[0]?.anchor || ''
}, { immediate: true })

watch(currentSection, () => {
  formRef.value?.clearValidate?.()
})

function onTabChange(tab) {
  router.replace({ query: { ...route.query, tab } })
}
</script>

<style scoped lang="scss">
.settings-page {
  gap: 8px;
}

.settings-hub-tabs {
  :deep(.el-tabs__content) {
    display: none;
  }
}

.settings-hero {
  align-items: center;
}

.page-actions {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.section-nav {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 12px 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.section-nav-label {
  flex-shrink: 0;
  min-width: 48px;
  color: $text-color-secondary;
  font-size: 12px;
  font-weight: 600;
  line-height: 32px;
}

.section-segmented {
  flex: 1;

  :deep(.el-segmented) {
    width: 100%;
    padding: 4px;
    border: 1px solid $divider-color;
    border-radius: $sm-border-radius;
    background: $bg-color-soft;
  }

  :deep(.el-segmented__item) {
    min-height: 40px;
    justify-content: flex-start;
    position: relative;
    z-index: 1;
  }

  :deep(.el-segmented__item-selected) {
    box-shadow: inset 0 0 0 1px $color-primary-border;
    background: $bg-color-card;
    z-index: 0;
  }
}

.section-segment-option {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.section-segment-option__title {
  font-size: 13px;
  font-weight: 600;
}

.section-segment-option__meta {
  font-size: 12px;
  opacity: 0.72;
}

.settings-card {
  border-radius: $base-border-radius;
}

.section-block {
  min-height: 220px;
}

.field-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.field-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 20px;
  border: 1px solid $divider-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
  gap: 16px;

  &.is-type-switch {
    flex-direction: row;
    align-items: center;

    .field-info {
      flex: 1;
      padding-right: 24px;
    }
  }

  &.is-wide {
    grid-column: 1 / -1;
  }
}

.field-info {
  display: flex;
  flex-direction: column;
}

.field-title {
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
  line-height: 1.4;

  .field-required {
    color: $color-danger;
    margin-right: 4px;
  }
}

.field-desc {
  margin: 6px 0 0;
  font-size: 12px;
  line-height: 1.6;
  color: $text-color-placeholder;
}

.field-control {
  width: 100%;
}

.field-card.is-type-switch .field-control {
  width: auto;
}

.field-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 72px;
  margin-top: 12px;
  padding: 12px;
  border: 1px solid $border-color;
  border-radius: $sm-border-radius;
  background: $bg-color-card;

  img {
    max-width: 180px;
    max-height: 52px;
    object-fit: contain;
  }
}

.settings-form {
  :deep(.el-form-item) {
    margin-bottom: 0;
  }

  :deep(.el-select),
  :deep(.el-input-number),
  :deep(.el-date-editor) {
    width: 100%;
  }

  :deep(.el-input-number .el-input__wrapper) {
    width: 100%;
  }

  :deep(.el-time-picker) {
    width: 100%;
  }
}

@media (max-width: 1100px) {
  .field-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .settings-hero,
  .section-nav {
    flex-direction: column;
    align-items: stretch;
  }

  .section-segmented {
    width: 100%;
  }

  .page-actions {
    justify-content: stretch;
  }

  .page-actions :deep(.el-button) {
    flex: 1;
  }
}
</style>


