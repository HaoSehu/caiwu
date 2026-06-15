<template>
  <div class="settings-page">
    <t-card :bordered="false">
      <div class="page-tabs-toolbar">
        <t-tabs :value="activeTab" @change="handleTabChange">
          <t-tab-panel v-for="item in tabOptions" :key="item.value" :value="item.value" :label="item.label" />
        </t-tabs>
        <t-space>
          <t-button variant="outline" :loading="currentLoading" @click="refreshCurrentTab">
            <template #icon><refresh-icon /></template>
            刷新
          </t-button>
          <t-button theme="primary" :loading="currentSaving" @click="saveCurrentTab">
            <template #icon><check-icon /></template>
            保存设置
          </t-button>
        </t-space>
      </div>
    </t-card>

    <template v-if="activeTab !== 'site_hero'">
      <t-card v-if="sections.length > 1" :bordered="false">
        <div class="section-nav">
          <span>分组</span>
          <t-radio-group v-model="activeSection" variant="default-filled">
            <t-radio-button v-for="section in sections" :key="section.anchor" :value="section.anchor">
              {{ section.title }} · {{ section.fields.length }} 项
            </t-radio-button>
          </t-radio-group>
        </div>
      </t-card>

      <t-card :bordered="false" :loading="settingsLoading">
        <template v-if="currentSection" #title>{{ currentSection.title }}</template>
        <template v-if="currentSection?.description" #subtitle>{{ currentSection.description }}</template>

        <t-form :data="form" label-align="top" class="settings-form">
          <div class="settings-field-grid">
            <article
              v-for="field in currentSection?.fields || []"
              :key="field.key"
              class="field-card"
              :class="{ 'field-card--switch': field.type === 'switch', 'field-card--wide': field.wide }"
            >
              <div class="field-info">
                <strong>
                  <span v-if="isFieldRequired(field)" class="required-mark">*</span>
                  {{ field.label }}
                </strong>
                <p v-if="field.help">{{ field.help }}</p>
              </div>
              <div class="field-control">
                <t-switch v-if="field.type === 'switch'" v-model="form[field.key]" />
                <t-select v-else-if="field.type === 'select'" v-model="form[field.key]" clearable :placeholder="field.placeholder || `请选择${field.label}`">
                  <t-option v-for="option in field.options || []" :key="String(option.value)" :label="option.label" :value="option.value" />
                </t-select>
                <t-input-number
                  v-else-if="field.type === 'number'"
                  v-model="form[field.key]"
                  theme="normal"
                  :min="field.min"
                  :max="field.max"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                />
                <t-textarea
                  v-else-if="field.type === 'textarea'"
                  v-model="form[field.key]"
                  :autosize="{ minRows: field.rows || 3, maxRows: Math.max(field.rows || 3, 6) }"
                  :maxlength="field.maxlength"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                />
                <t-time-picker
                  v-else-if="field.type === 'time'"
                  v-model="form[field.key]"
                  clearable
                  format="HH:mm:ss"
                  :placeholder="field.placeholder || `请选择${field.label}`"
                />
                <t-input
                  v-else-if="field.type === 'image'"
                  v-model="form[field.key]"
                  :maxlength="field.maxlength"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                >
                  <template #suffix-icon>
                    <upload-icon class="upload-trigger" @click="selectImage(field)" />
                  </template>
                </t-input>
                <t-input
                  v-else
                  v-model="form[field.key]"
                  :type="field.type === 'password' ? 'password' : 'text'"
                  :maxlength="field.maxlength"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                />
                <div v-if="field.preview === 'image' && form[field.key]" class="field-preview">
                  <img :src="String(form[field.key])" :alt="field.label" />
                </div>
              </div>
            </article>
          </div>
        </t-form>
      </t-card>

    </template>

    <template v-else>
      <t-card :bordered="false" :loading="heroLoading" class="hero-card">
        <t-alert
          v-if="heroDirty"
          theme="warning"
          message="当前存在未保存修改。保存后官网首页最长约 2 分钟同步。"
        />

        <section class="hero-section">
          <div class="hero-section-head">
            <div>
              <h2>轮播项（{{ heroForm.slides.length }} / {{ maxSlides }}）</h2>
              <p>每个轮播项包含导航名称、主标题、描述和两个按钮。</p>
            </div>
            <t-space>
              <t-button variant="outline" @click="resetSlidesToDefault">恢复默认</t-button>
              <t-button theme="primary" :disabled="heroForm.slides.length >= maxSlides" @click="addSlide">
                <template #icon><add-icon /></template>
                新增轮播
              </t-button>
            </t-space>
          </div>

          <article v-for="(slide, index) in heroForm.slides" :key="`slide-${index}`" class="slide-card">
            <div class="slide-card-head">
              <div>
                <span>{{ index + 1 }}</span>
                <strong>{{ slide.rail_title || '未命名轮播' }}</strong>
              </div>
              <t-space>
                <t-button variant="text" :disabled="index === 0" @click="moveSlide(index, -1)">
                  <template #icon><arrow-up-icon /></template>
                  上移
                </t-button>
                <t-button variant="text" :disabled="index === heroForm.slides.length - 1" @click="moveSlide(index, 1)">
                  <template #icon><arrow-down-icon /></template>
                  下移
                </t-button>
                <t-button theme="danger" variant="text" :disabled="heroForm.slides.length <= 1" @click="removeSlide(index)">
                  <template #icon><delete-icon /></template>
                  删除
                </t-button>
              </t-space>
            </div>
            <div class="slide-form-grid">
              <t-form label-align="top" :data="slide">
                <t-form-item label="导航名称">
                  <t-input v-model="slide.rail_title" maxlength="20" placeholder="例如：官网换新" />
                </t-form-item>
                <t-form-item label="主标题">
                  <t-input v-model="slide.title" maxlength="80" placeholder="例如：官网焕新 · 云上新体验" />
                </t-form-item>
                <t-form-item label="描述文案">
                  <t-textarea v-model="slide.desc" :autosize="{ minRows: 4, maxRows: 6 }" maxlength="300" />
                </t-form-item>
              </t-form>
              <t-form label-align="top" :data="slide">
                <t-form-item label="主按钮文案">
                  <t-input v-model="slide.primary_text" maxlength="20" />
                </t-form-item>
                <t-form-item label="主按钮跳转">
                  <t-input v-model="slide.primary_path" maxlength="255" />
                </t-form-item>
                <t-form-item label="次按钮文案">
                  <t-input v-model="slide.secondary_text" maxlength="20" />
                </t-form-item>
                <t-form-item label="次按钮跳转">
                  <t-input v-model="slide.secondary_path" maxlength="255" />
                </t-form-item>
              </t-form>
            </div>
          </article>
        </section>

        <section class="hero-section">
          <div class="hero-section-head">
            <div>
              <h2>底部特色卡片（{{ heroForm.features.length }} / {{ maxFeatures }}）</h2>
              <p>用于官网首页轮播下方的横向卡片组。</p>
            </div>
            <t-space>
              <t-button variant="outline" @click="resetFeaturesToDefault">恢复默认</t-button>
              <t-button theme="primary" :disabled="heroForm.features.length >= maxFeatures" @click="addFeature">
                <template #icon><add-icon /></template>
                新增卡片
              </t-button>
            </t-space>
          </div>

          <div class="feature-list">
            <article v-for="(feature, index) in heroForm.features" :key="`feature-${index}`" class="feature-card">
              <div class="feature-card-sort">
                <t-button variant="text" :disabled="index === 0" @click="moveFeature(index, -1)">
                  <template #icon><arrow-up-icon /></template>
                </t-button>
                <t-button variant="text" :disabled="index === heroForm.features.length - 1" @click="moveFeature(index, 1)">
                  <template #icon><arrow-down-icon /></template>
                </t-button>
              </div>
              <t-input v-model="feature.kicker" maxlength="20" placeholder="标签 kicker" />
              <t-input v-model="feature.title" maxlength="50" placeholder="标题" />
              <t-textarea v-model="feature.desc" :autosize="{ minRows: 2, maxRows: 3 }" maxlength="120" placeholder="描述" />
              <t-input v-model="feature.path" maxlength="255" placeholder="跳转路径，可选" />
              <t-button theme="danger" variant="text" :disabled="heroForm.features.length <= 1" @click="removeFeature(index)">
                <template #icon><delete-icon /></template>
                删除
              </t-button>
            </article>
          </div>
        </section>
      </t-card>
    </template>

    <input ref="fileInputRef" class="hidden-file-input" type="file" accept="image/*" @change="handleImageFileChange" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  AddIcon,
  ArrowDownIcon,
  ArrowUpIcon,
  CheckIcon,
  DeleteIcon,
  RefreshIcon,
  UploadIcon,
} from 'tdesign-icons-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import type { TableRowData } from 'tdesign-vue-next';

import { adminApi, type HomeHeroFeature, type HomeHeroPayload, type HomeHeroSlide, type SettingItem } from '@/api/admin';

import './index.less';

type SettingsTab =
  | 'system'
  | 'payment'
  | 'referral'
  | 'automation'
  | 'site_basic'
  | 'site_hero';
type FieldType = 'input' | 'password' | 'textarea' | 'switch' | 'select' | 'number' | 'image' | 'time';
type FieldValue = string | number | boolean | null;

interface FieldOption {
  label: string;
  value: string | number | boolean;
}

interface SettingField {
  key: string;
  label: string;
  type?: FieldType;
  default?: FieldValue;
  group?: string;
  help?: string;
  options?: FieldOption[];
  min?: number;
  max?: number;
  rows?: number;
  wide?: boolean;
  required?: boolean;
  requiredWhen?: (model: Record<string, FieldValue>) => boolean;
  pattern?: RegExp;
  patternMessage?: string;
  preview?: 'image';
  maxlength?: number;
  placeholder?: string;
}

interface SettingSection {
  title: string;
  description?: string;
  fields: SettingField[];
  anchor?: string;
}

interface SettingsConfig {
  group: string;
  title: string;
  description: string;
  sections: SettingSection[];
}

const route = useRoute();
const router = useRouter();
const settingsLoading = ref(false);
const settingsSaving = ref(false);
const heroLoading = ref(false);
const heroSaving = ref(false);
const activeSection = ref('');
const form = reactive<Record<string, FieldValue>>({});
const fileInputRef = ref<HTMLInputElement>();
const pendingImageField = ref<SettingField | null>(null);

const maxSlides = 5;
const maxFeatures = 5;
const heroDefaults = reactive<{ slides: HomeHeroSlide[]; features: HomeHeroFeature[] }>({ slides: [], features: [] });
const heroForm = reactive<{ slides: HomeHeroSlide[]; features: HomeHeroFeature[] }>({ slides: [], features: [] });
const heroSnapshot = ref('');

const automationScheduleModeOptions: FieldOption[] = [
  { label: '每 5 分钟', value: 'every_five_minutes' },
  { label: '每 10 分钟', value: 'every_ten_minutes' },
  { label: '每 15 分钟', value: 'every_fifteen_minutes' },
  { label: '每 30 分钟', value: 'every_thirty_minutes' },
  { label: '每小时', value: 'hourly' },
  { label: '每天', value: 'daily' },
];

const tabOptions: Array<{ label: string; value: SettingsTab }> = [
  { label: '系统设置', value: 'system' },
  { label: '支付配置', value: 'payment' },
  { label: '推荐奖励', value: 'referral' },
  { label: '自动化策略', value: 'automation' },
  { label: '基础信息', value: 'site_basic' },
  { label: '首页 Banner', value: 'site_hero' },
];
const activeTab = ref<SettingsTab>(normalizeTab(route.query.tab));

const configs: Record<Exclude<SettingsTab, 'site_hero'>, SettingsConfig> = {
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
          { key: 'geetest_captcha_key', label: 'Captcha Key', type: 'password', default: '', requiredWhen: (model) => Boolean(model.geetest_enabled), help: '来自 GeeTest 控制台的 captcha_key。' },
        ],
      },
      {
        title: '邮件短信限流',
        description: '控制验证码发送频率，避免接口被恶意刷取。',
        fields: [
          { key: 'email_rate_limit_enabled', group: 'message_limit', label: '启用邮箱限流', type: 'switch', default: false },
          { key: 'email_cooldown_seconds', group: 'message_limit', label: '邮箱冷却时间（秒）', type: 'number', default: 60, min: 0 },
          { key: 'email_target_hourly_limit', group: 'message_limit', label: '邮箱每小时上限', type: 'number', default: 10, min: 0 },
          { key: 'email_ip_hourly_limit', group: 'message_limit', label: 'IP 每小时上限', type: 'number', default: 20, min: 0 },
          { key: 'sms_rate_limit_enabled', group: 'message_limit', label: '启用短信限流', type: 'switch', default: false },
          { key: 'sms_cooldown_seconds', group: 'message_limit', label: '手机号冷却时间（秒）', type: 'number', default: 60, min: 0 },
          { key: 'sms_target_hourly_limit', group: 'message_limit', label: '手机号每小时上限', type: 'number', default: 10, min: 0 },
          { key: 'sms_ip_hourly_limit', group: 'message_limit', label: '短信 IP 每小时上限', type: 'number', default: 20, min: 0 },
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
        fields: [
          { key: 'alipay_enabled', label: '启用支付宝支付', type: 'switch', default: false },
          { key: 'alipay_name', label: '前端名称', type: 'input', default: '支付宝支付', requiredWhen: (model) => Boolean(model.alipay_enabled) },
          { key: 'alipay_app_id', label: 'APPID', type: 'input', default: '', requiredWhen: (model) => Boolean(model.alipay_enabled) },
          { key: 'alipay_private_key', label: '商户私钥', type: 'password', default: '', wide: true, requiredWhen: (model) => Boolean(model.alipay_enabled) },
          { key: 'alipay_public_key', label: '支付宝公钥', type: 'password', default: '', wide: true, requiredWhen: (model) => Boolean(model.alipay_enabled) },
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
        fields: [
          { key: 'enabled', label: '启用推荐返利', type: 'switch', default: true },
          { key: 'reward_rate', label: '默认奖励比例（%）', type: 'number', default: 10, min: 0, max: 100 },
          { key: 'reward_freeze_days', label: '奖励冻结期（天）', type: 'number', default: 4, min: 0, max: 365 },
          { key: 'withdraw_min_amount', label: '最低提现金额（元）', type: 'number', default: 20, min: 0 },
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
        fields: [
          { key: 'expire_suspend_enabled', label: '启用到期自动暂停', type: 'switch', default: true },
          { key: 'expire_suspend_after_days', label: '到期后暂停天数', type: 'number', default: 0, min: 0, max: 365 },
          { key: 'expire_suspend_notify_enabled', label: '暂停前发送通知', type: 'switch', default: true },
          { key: 'expire_unsuspend_notify_enabled', label: '恢复后发送通知', type: 'switch', default: true },
          { key: 'expire_terminate_enabled', label: '启用自动终止', type: 'switch', default: false },
          { key: 'expire_terminate_after_days', label: '暂停后终止天数', type: 'number', default: 7, min: 1, max: 365 },
          { key: 'service_lifecycle_schedule_mode', label: '任务执行周期', type: 'select', default: 'every_five_minutes', options: automationScheduleModeOptions },
          { key: 'service_lifecycle_schedule_time', label: '执行时间', type: 'time', default: '00:05:00', placeholder: 'HH:mm:ss' },
        ],
      },
      {
        title: '账单与提醒',
        fields: [
          { key: 'renew_notice_enabled', label: '启用续费提醒', type: 'switch', default: true },
          { key: 'renew_create_invoice_enabled', label: '自动创建续费账单', type: 'switch', default: true },
          { key: 'invoice_unpaid_reminder_enabled', label: '启用未支付提醒', type: 'switch', default: true },
          { key: 'invoice_unpaid_before_due_days', label: '到期前提醒天数', type: 'number', default: 1, min: 0, max: 30 },
          { key: 'invoice_overdue_reminder_days', label: '逾期提醒天数', type: 'input', default: '1,3,5', wide: true, pattern: /^\d+(,\d+)*$/, patternMessage: '请使用英文逗号分隔天数，例如 1,3,5' },
          { key: 'invoice_overdue_after_days', label: '逾期标记天数', type: 'number', default: 0, min: 0, max: 365 },
          { key: 'billing_maintenance_schedule_mode', label: '任务执行周期', type: 'select', default: 'hourly', options: automationScheduleModeOptions },
          { key: 'billing_maintenance_schedule_time', label: '执行时间', type: 'time', default: '00:00:00', placeholder: 'HH:mm:ss' },
        ],
      },
      {
        title: '工单与待支付清理',
        fields: [
          { key: 'ticket_auto_close_enabled', label: '启用工单自动关闭', type: 'switch', default: true },
          { key: 'ticket_auto_close_after_hours', label: '工单自动关闭时长（小时）', type: 'number', default: 48, min: 1, max: 720 },
          { key: 'pending_order_cleanup_enabled', label: '启用未支付账单清理', type: 'switch', default: true },
          { key: 'pending_order_cleanup_after_hours', label: '未支付账单保留时长（小时）', type: 'number', default: 1, min: 1, max: 720 },
          { key: 'pending_recharge_cleanup_enabled', label: '启用未支付充值单清理', type: 'switch', default: true },
          { key: 'pending_recharge_cleanup_after_days', label: '未支付充值单保留天数', type: 'number', default: 3, min: 0, max: 365 },
          { key: 'ticket_auto_close_schedule_mode', label: '工单任务执行周期', type: 'select', default: 'hourly', options: automationScheduleModeOptions },
          { key: 'ticket_auto_close_schedule_time', label: '工单执行时间', type: 'time', default: '00:00:00', placeholder: 'HH:mm:ss' },
          { key: 'order_cleanup_schedule_mode', label: '账单清理执行周期', type: 'select', default: 'every_five_minutes', options: automationScheduleModeOptions },
          { key: 'order_cleanup_schedule_time', label: '账单清理执行时间', type: 'time', default: '00:00:00', placeholder: 'HH:mm:ss' },
        ],
      },
    ],
  },
  site_basic: {
    group: 'basic',
    title: '基础信息',
    description: '维护站点名称、Logo、Favicon、官方联系方式与备案号。',
    sections: [
      {
        title: '站点信息',
        fields: [
          { key: 'site_name', label: '站点名称', type: 'input', default: '', maxlength: 50, placeholder: '例如：创欧云' },
          { key: 'browser_title', label: '浏览器标题', type: 'input', default: '', maxlength: 80, placeholder: '留空则默认使用站点名称' },
          { key: 'site_logo', label: '站点 Logo', type: 'image', default: '', maxlength: 255, preview: 'image', wide: true, placeholder: '/branding/logo.svg' },
          { key: 'site_favicon', label: '站点 Favicon', type: 'image', default: '', maxlength: 255, preview: 'image', wide: true, placeholder: '/branding/logo1.svg' },
          { key: 'service_phone', label: '官方QQ群', type: 'input', default: '', maxlength: 40 },
          { key: 'support_group_qr', label: '官方群聊二维码', type: 'image', default: '', maxlength: 255, preview: 'image', wide: true },
          { key: 'support_group_link', label: '入群链接', type: 'input', default: '', maxlength: 255 },
          { key: 'terms_url', label: '服务条款链接', type: 'input', default: '', maxlength: 255 },
          { key: 'privacy_url', label: '隐私政策链接', type: 'input', default: '', maxlength: 255 },
        ],
      },
    ],
  },
};

const pageConfig = computed<SettingsConfig | null>(() => (activeTab.value === 'site_hero' ? null : configs[activeTab.value]));
const pageDescription = computed(() =>
  activeTab.value === 'site_hero' ? '维护官网首页 Banner 与底部特色卡片。' : pageConfig.value?.description || '',
);
const sections = computed(() =>
  (pageConfig.value?.sections || []).map((section, index) => ({ ...section, anchor: `${activeTab.value}-${index}` })),
);
const currentSection = computed(() => sections.value.find((section) => section.anchor === activeSection.value) || sections.value[0]);
const allFields = computed(() => sections.value.flatMap((section) => section.fields));
const activeGroups = computed(() => Array.from(new Set(allFields.value.map((field) => field.group || pageConfig.value?.group || 'system'))));
const currentLoading = computed(() => (activeTab.value === 'site_hero' ? heroLoading.value : settingsLoading.value));
const currentSaving = computed(() => (activeTab.value === 'site_hero' ? heroSaving.value : settingsSaving.value));
const heroDirty = computed(() => JSON.stringify(heroForm) !== heroSnapshot.value);

function normalizeTab(value: unknown): SettingsTab {
  const tab = Array.isArray(value) ? value[0] : value;
  if (tab === 'site') return 'site_basic';
  return tabOptions.some((item) => item.value === tab) ? (tab as SettingsTab) : 'system';
}

function handleTabChange(value: string | number) {
  activeTab.value = normalizeTab(value);
  router.replace({ path: '/admin/settings', query: activeTab.value === 'system' ? {} : { tab: activeTab.value } });
  refreshCurrentTab();
}

function refreshCurrentTab() {
  if (activeTab.value === 'site_hero') return loadHero();
  return loadSettings();
}

function saveCurrentTab() {
  if (activeTab.value === 'site_hero') return saveHero();
  return saveSettings();
}

function resetFormDefaults() {
  Object.keys(form).forEach((key) => delete form[key]);
  allFields.value.forEach((field) => {
    form[field.key] = field.default ?? (field.type === 'switch' ? false : '');
  });
}

async function loadSettings() {
  if (!pageConfig.value) return;
  settingsLoading.value = true;
  resetFormDefaults();
  try {
    const responses = await Promise.all(activeGroups.value.map((group) => adminApi.settings.list({ group })));
    const maps = Object.fromEntries(activeGroups.value.map((group, index) => [group, normalizeSettings(responses[index])]));
    allFields.value.forEach((field) => {
      const group = field.group || pageConfig.value?.group || 'system';
      form[field.key] = parseFieldValue(field, maps[group]?.[field.key]);
    });
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载设置失败'));
  } finally {
    settingsLoading.value = false;
  }
}

async function saveSettings() {
  if (!validateSettings()) return;
  settingsSaving.value = true;
  try {
    const payload = buildSettingsPayload();
    await Promise.all(Object.entries(payload).map(([group, settings]) => adminApi.settings.save({ group, settings })));
    MessagePlugin.success(`${pageConfig.value?.title || '设置'}已保存`);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存设置失败'));
  } finally {
    settingsSaving.value = false;
  }
}

function validateSettings() {
  for (const field of allFields.value) {
    const value = form[field.key];
    if (isFieldRequired(field) && !String(value ?? '').trim() && field.type !== 'switch') {
      MessagePlugin.warning(`请填写${field.label}`);
      return false;
    }
    if (field.type === 'number') {
      const num = Number(value);
      if (!Number.isFinite(num)) {
        MessagePlugin.warning(`${field.label}必须是数字`);
        return false;
      }
      if (field.min !== undefined && num < field.min) {
        MessagePlugin.warning(`${field.label}不能小于 ${field.min}`);
        return false;
      }
      if (field.max !== undefined && num > field.max) {
        MessagePlugin.warning(`${field.label}不能大于 ${field.max}`);
        return false;
      }
    }
    if (field.pattern && !field.pattern.test(String(value ?? '').trim())) {
      MessagePlugin.warning(field.patternMessage || `${field.label}格式不正确`);
      return false;
    }
  }
  return true;
}

function buildSettingsPayload() {
  return allFields.value.reduce<Record<string, Record<string, FieldValue>>>((payload, field) => {
    const group = field.group || pageConfig.value?.group || 'system';
    let value = form[field.key];
    if (field.type === 'switch') value = value ? 1 : 0;
    if (!payload[group]) payload[group] = {};
    payload[group][field.key] = value;
    return payload;
  }, {});
}

function normalizeSettings(response: SettingItem[] | Record<string, unknown>) {
  if (Array.isArray(response)) return Object.fromEntries(response.map((item) => [item.key, item.value]));
  const record = toRecord(response);
  if (Array.isArray(record.list)) return Object.fromEntries((record.list as SettingItem[]).map((item) => [item.key, item.value]));
  return record;
}

function parseFieldValue(field: SettingField, raw: unknown): FieldValue {
  if (raw === undefined || raw === null || raw === '') return field.default ?? (field.type === 'switch' ? false : '');
  if (field.type === 'switch') return raw === true || raw === 1 || raw === '1' || raw === 'true';
  if (field.type === 'number') {
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : Number(field.default || 0);
  }
  return String(raw);
}

function isFieldRequired(field: SettingField) {
  return typeof field.requiredWhen === 'function' ? field.requiredWhen(form) : Boolean(field.required);
}

function selectImage(field: SettingField) {
  pendingImageField.value = field;
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
    fileInputRef.value.click();
  }
}

async function handleImageFileChange(event: Event) {
  const field = pendingImageField.value;
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!field || !file) return;
  const data = new FormData();
  data.append('file', file);
  data.append('group', 'site-settings');
  try {
    const response = await adminApi.media.upload(data);
    form[field.key] = String(response.url || '');
    MessagePlugin.success('图片上传成功');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '图片上传失败'));
  }
}

async function loadHero() {
  heroLoading.value = true;
  try {
    const data = await adminApi.siteHero.get();
    applyHeroPayload(data);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载首页 Banner 失败'));
  } finally {
    heroLoading.value = false;
  }
}

function applyHeroPayload(payload: HomeHeroPayload = {}) {
  heroDefaults.slides = cloneList(payload.defaults?.slides);
  heroDefaults.features = cloneList(payload.defaults?.features);
  heroForm.slides = cloneList(payload.slides).length ? cloneList(payload.slides) : cloneList(heroDefaults.slides);
  heroForm.features = cloneList(payload.features).length ? cloneList(payload.features) : cloneList(heroDefaults.features);
  heroSnapshot.value = JSON.stringify(heroForm);
}

async function saveHero() {
  if (!validateHero()) return;
  heroSaving.value = true;
  try {
    const payload = {
      slides: heroForm.slides.map((item) => ({ ...item })),
      features: heroForm.features.map((item) => ({ ...item })),
    };
    const response = await adminApi.siteHero.save(payload);
    applyHeroPayload({ ...response, slides: response.slides || payload.slides, features: response.features || payload.features });
    MessagePlugin.success('首页 Banner 已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存首页 Banner 失败'));
  } finally {
    heroSaving.value = false;
  }
}

function validateHero() {
  for (const [index, slide] of heroForm.slides.entries()) {
    for (const [key, label] of [
      ['rail_title', '导航名称'],
      ['title', '主标题'],
      ['desc', '描述'],
      ['primary_text', '主按钮文案'],
      ['primary_path', '主按钮跳转'],
      ['secondary_text', '次按钮文案'],
      ['secondary_path', '次按钮跳转'],
    ] as Array<[keyof HomeHeroSlide, string]>) {
      if (!String(slide[key] ?? '').trim()) {
        MessagePlugin.warning(`第 ${index + 1} 个轮播项的${label}不能为空`);
        return false;
      }
    }
  }
  for (const [index, feature] of heroForm.features.entries()) {
    for (const [key, label] of [
      ['kicker', '标签'],
      ['title', '标题'],
      ['desc', '描述'],
    ] as Array<[keyof HomeHeroFeature, string]>) {
      if (!String(feature[key] ?? '').trim()) {
        MessagePlugin.warning(`第 ${index + 1} 张卡片的${label}不能为空`);
        return false;
      }
    }
  }
  return true;
}

function buildBlankSlide(): HomeHeroSlide {
  return {
    rail_title: '新轮播项',
    title: '',
    desc: '',
    primary_text: '立即体验',
    primary_path: '/products',
    secondary_text: '查看详情',
    secondary_path: '/about',
  };
}

function buildBlankFeature(): HomeHeroFeature {
  return { kicker: '新卡片', title: '', desc: '', path: '' };
}

function addSlide() {
  if (heroForm.slides.length < maxSlides) heroForm.slides.push(buildBlankSlide());
}

function removeSlide(index: number) {
  if (heroForm.slides.length > 1) heroForm.slides.splice(index, 1);
}

function moveSlide(index: number, offset: number) {
  moveItem(heroForm.slides, index, offset);
}

function addFeature() {
  if (heroForm.features.length < maxFeatures) heroForm.features.push(buildBlankFeature());
}

function removeFeature(index: number) {
  if (heroForm.features.length > 1) heroForm.features.splice(index, 1);
}

function moveFeature(index: number, offset: number) {
  moveItem(heroForm.features, index, offset);
}

function resetSlidesToDefault() {
  const dialog = DialogPlugin.confirm({
    header: '恢复默认轮播',
    body: '恢复默认会覆盖当前轮播项，下次保存才会写入数据库。',
    confirmBtn: '恢复默认',
    cancelBtn: '取消',
    onConfirm: () => {
      heroForm.slides = cloneList(heroDefaults.slides);
      dialog.destroy();
    },
  });
}

function resetFeaturesToDefault() {
  const dialog = DialogPlugin.confirm({
    header: '恢复默认卡片',
    body: '恢复默认会覆盖当前特色卡片，下次保存才会写入数据库。',
    confirmBtn: '恢复默认',
    cancelBtn: '取消',
    onConfirm: () => {
      heroForm.features = cloneList(heroDefaults.features);
      dialog.destroy();
    },
  });
}

function moveItem<T>(list: T[], index: number, offset: number) {
  const target = index + offset;
  if (target < 0 || target >= list.length) return;
  const [item] = list.splice(index, 1);
  list.splice(target, 0, item);
}

function cloneList<T extends Record<string, unknown>>(list?: T[]) {
  return Array.isArray(list) ? list.map((item) => ({ ...item })) : [];
}

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function errorMessage(error: unknown, fallback: string) {
  const record = toRecord(error);
  const response = toRecord(record.response);
  const data = toRecord(response.data);
  return String(data.message || record.message || fallback);
}

watch(
  () => route.query.tab,
  (value) => {
    const next = normalizeTab(value);
    if (next === activeTab.value) return;
    activeTab.value = next;
    refreshCurrentTab();
  },
);

watch(
  sections,
  (next) => {
    activeSection.value = next[0]?.anchor || '';
  },
  { immediate: true },
);

onMounted(() => {
  refreshCurrentTab();
});
</script>
