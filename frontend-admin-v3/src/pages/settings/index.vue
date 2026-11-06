<template>
  <div class="settings-page" :data-mobile-view="isMobile ? 'form' : undefined">
    <t-card :bordered="false">
      <div class="page-tabs-toolbar">
        <t-tabs :value="activeTab" @change="handleTabChange">
          <t-tab-panel v-for="tab in tabOptions" :key="tab.value" :value="tab.value">
            <template #label>
              <span :class="{ 'tab-group-first': tab.groupStart }">{{ tab.label }}</span>
            </template>
          </t-tab-panel>
        </t-tabs>
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

        <t-form :data="form" label-align="top" class="settings-form" :disabled="!canManageCurrentTab">
          <div class="settings-field-grid">
            <article
              v-for="field in currentSection?.fields || []"
              :key="field.key"
              class="field-card"
              :class="{ 'field-card--switch': field.type === 'switch', 'field-card--wide': field.wide }"
            >
              <div class="field-info">
                <strong>
                  <span v-if="isFieldRequired(field)" class="required-mark">
                    <span aria-hidden="true">*</span>
                    <span class="sr-only">（必填）</span>
                  </span>
                  {{ field.label }}
                </strong>
                <p v-if="field.help">{{ field.help }}</p>
              </div>
              <div class="field-control">
                <t-switch v-if="field.type === 'switch'" v-model="form[field.key]" :aria-label="field.label" />
                <t-select
                  v-else-if="field.type === 'select'"
                  v-model="form[field.key]"
                  clearable
                  :aria-label="field.label"
                  :placeholder="field.placeholder || `请选择${field.label}`"
                >
                  <t-option
                    v-for="option in field.options || []"
                    :key="String(option.value)"
                    :label="option.label"
                    :value="option.value"
                  />
                </t-select>
                <t-input-number
                  v-else-if="field.type === 'number'"
                  v-model="form[field.key]"
                  theme="normal"
                  :min="field.min"
                  :max="field.max"
                  :aria-label="field.label"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                />
                <t-textarea
                  v-else-if="field.type === 'textarea'"
                  v-model="form[field.key]"
                  :autosize="{ minRows: field.rows || 3, maxRows: Math.max(field.rows || 3, 6) }"
                  :maxlength="field.maxlength"
                  :aria-label="field.label"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                />
                <t-time-picker
                  v-else-if="field.type === 'time'"
                  v-model="form[field.key]"
                  clearable
                  format="HH:mm:ss"
                  :aria-label="field.label"
                  :placeholder="field.placeholder || `请选择${field.label}`"
                />
                <div
                  v-else-if="field.type === 'image'"
                  class="cover-image-selector"
                  role="button"
                  tabindex="0"
                  :aria-label="`选择${field.label}`"
                  @click="selectImage(field)"
                  @keydown.enter.prevent="selectImage(field)"
                  @keydown.space.prevent="selectImage(field)"
                >
                  <image-icon />
                  <span v-if="form[field.key]" class="cover-image-selector__name">{{
                    String(form[field.key]).split('/').pop()
                  }}</span>
                  <span v-else class="cover-image-selector__placeholder">点击选择{{ field.label }}</span>
                  <chevron-right-icon />
                </div>
                <secret-input
                  v-else-if="isSecretSettingField(field)"
                  v-model="form[field.key]"
                  :aria-label="field.label"
                  :has-value="hasSettingSecretValue(field)"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                  :reset-key="settingSecretResetKey(field)"
                  :can-reveal="canRevealSettingsSecret"
                  :reveal="() => revealSettingSecret(field)"
                  @edited-change="(value: boolean) => (settingsSecretEdited[settingSecretEditKey(field)] = value)"
                  @reveal-error="(error: unknown) => MessagePlugin.error(errorMessage(error, '读取敏感配置失败'))"
                />
                <t-input
                  v-else
                  v-model="form[field.key]"
                  :type="field.type === 'password' ? 'password' : 'text'"
                  :maxlength="field.maxlength"
                  :aria-label="field.label"
                  :placeholder="field.placeholder || `请输入${field.label}`"
                />
              </div>
            </article>
          </div>
        </t-form>
      </t-card>

      <div v-if="allFields.length > 0" class="settings-bottom-actions">
        <t-button theme="primary" :loading="settingsSaving" :disabled="!canManageCurrentTab" @click="saveSettings">
          <template #icon><check-icon /></template>
          保存设置
        </t-button>
      </div>
    </template>

    <!-- 首页装修已抽成独立组件：通用设置与站点装修各自一套模板，本页不再用 v-if/v-else 切成两半。 -->
    <site-hero-config
      v-else
      :can-manage-site="canManageSite"
      :reload-token="heroReloadToken"
      @dirty-change="heroDirty = $event"
    />

    <input ref="fileInputRef" class="hidden-file-input" type="file" accept="image/*" @change="handleImageFileChange" />
    <input
      ref="mediaDrawerUploadRef"
      class="hidden-file-input"
      type="file"
      accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
      @change="handleMediaDrawerUpload"
    />

    <t-drawer
      :visible="mediaDrawerVisible"
      header="选择媒体"
      size="min(520px, calc(100vw - 32px))"
      placement="right"
      :footer="null"
      @close="closeMediaDrawer"
    >
      <div class="cover-drawer-toolbar">
        <t-select v-model="mediaDrawerType" placeholder="全部类型" @change="loadMediaDrawerList">
          <t-option v-for="item in mediaDrawerTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
        </t-select>
        <t-button variant="outline" @click="openMediaDrawerUpload">
          <template #icon><upload-icon /></template>
          上传新文件
        </t-button>
      </div>
      <div class="cover-drawer-grid">
        <div
          v-for="item in mediaDrawerList"
          :key="item.url"
          class="cover-drawer-card"
          :class="{ 'is-selected': pendingImageField && String(form[pendingImageField.key]) === item.url }"
          role="button"
          tabindex="0"
          :aria-pressed="pendingImageField ? String(form[pendingImageField.key]) === item.url : false"
          @click="selectMediaFromDrawer(item)"
          @keydown.enter.prevent="selectMediaFromDrawer(item)"
          @keydown.space.prevent="selectMediaFromDrawer(item)"
        >
          <video
            v-if="item.isVideo"
            class="cover-drawer-card__img"
            :src="item.url"
            muted
            preload="metadata"
            playsinline
          ></video>
          <img v-else class="cover-drawer-card__img" :src="item.url" :alt="item.filename" loading="lazy" />
          <div class="cover-drawer-card__label">
            <check-circle-filled-icon
              v-if="pendingImageField && String(form[pendingImageField.key]) === item.url"
              class="cover-drawer-card__check"
            />
            <span>{{ item.filename }}</span>
          </div>
        </div>
        <div v-if="!mediaDrawerList.length && !mediaDrawerLoading" class="cover-drawer-empty">
          暂无已上传媒体，请先上传
        </div>
      </div>
    </t-drawer>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { CheckCircleFilledIcon, CheckIcon, ChevronRightIcon, ImageIcon, UploadIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { MediaFileRecord, SettingItem } from '@/api/admin';
import { adminApi } from '@/api/admin';
import SecretInput from '@/components/secret-input/index.vue';
import { AdminPermissions } from '@/constants/permissions';
import { MOBILE_MEDIA_QUERY, useMediaQuery } from '@/hooks/useMediaQuery';
import { useUnsavedGuard } from '@/hooks/useUnsavedGuard';
import { toRecord } from '@/utils/format';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

import SiteHeroConfig from './components/SiteHeroConfig.vue';

defineOptions({ name: 'AdminSettings' });

type SettingsTab = 'referral' | 'automation' | 'log_archive' | 'account_policy' | 'site_basic' | 'site_hero';
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
// 与 index.less 的 `max-width: 767.98px` 同源，用于手机端形态标记。
const isMobile = useMediaQuery(MOBILE_MEDIA_QUERY);
const settingsLoading = ref(false);
const settingsSaving = ref(false);
const activeSection = ref('');
const form = reactive<Record<string, FieldValue>>({});
const settingsGroupCache = reactive<Record<string, Record<string, unknown>>>({});
const settingsMetaCache = reactive<Record<string, Record<string, SettingItem>>>({});
const settingsSecretEdited = reactive<Record<string, boolean>>({});
const fileInputRef = ref<HTMLInputElement>();
const pendingImageField = ref<SettingField | null>(null);
const mediaDrawerVisible = ref(false);
const mediaDrawerLoading = ref(false);
const mediaDrawerList = ref<Array<{ url: string; filename: string; isVideo: boolean }>>([]);
const mediaDrawerType = ref('');
const mediaDrawerTypeOptions = [
  { label: '全部类型', value: '' },
  { label: '图片', value: 'image' },
  { label: '视频', value: 'video' },
];
const mediaDrawerUploadRef = ref<HTMLInputElement>();

const automationScheduleModeOptions: FieldOption[] = [
  { label: '每 15 分钟', value: 'every_fifteen_minutes' },
  { label: '每 30 分钟', value: 'every_thirty_minutes' },
  { label: '每小时', value: 'hourly' },
  { label: '每天', value: 'daily' },
];

interface SettingsTabOption {
  label: string;
  value: SettingsTab;
  /** 是否为分组首项：模板据此加 .tab-group-first 画竖分隔线（样式见 index.less） */
  groupStart: boolean;
}

const tabGroups: Array<{ group: string; label: string; tabs: Array<{ label: string; value: SettingsTab }> }> = [
  {
    group: 'config',
    label: '基础配置',
    tabs: [
      { label: '推荐奖励', value: 'referral' },
      { label: '账户策略', value: 'account_policy' },
      { label: '自动化策略', value: 'automation' },
      { label: '日志归档', value: 'log_archive' },
    ],
  },
  {
    group: 'site',
    label: '站点内容',
    tabs: [
      { label: '基础信息', value: 'site_basic' },
      { label: '首页 Banner', value: 'site_hero' },
    ],
  },
];
// 5 条路由共用本组件（content.ts 的 site-info/site-hero、system.ts 的 automation/log-archive/referral-settings），
// 原先页内没有任何 tab 入口（tabGroups 在模板里零引用），只能靠左侧菜单切换。
// 这里把 tabGroups 摊平成一行 tab，每个分组的首项标记 groupStart 用于画竖分隔线。
const tabPaths: Record<SettingsTab, string> = {
  referral: '/admin/referral-settings',
  account_policy: '/admin/account-policy',
  automation: '/admin/automation',
  log_archive: '/admin/log-archive',
  site_basic: '/admin/site-info',
  site_hero: '/admin/site-hero',
};
// 各 tab 对应路由的 meta.permission，与 router/modules 保持一致：
// 无权限的 tab 不该出现（点了只会被 permission.ts 踢到 403）。
const tabPermissions: Record<SettingsTab, string> = {
  referral: AdminPermissions.SETTINGS_VIEW,
  account_policy: AdminPermissions.SETTINGS_VIEW,
  automation: AdminPermissions.SCHEDULE_VIEW,
  log_archive: AdminPermissions.SETTINGS_VIEW,
  site_basic: AdminPermissions.SITE_VIEW,
  site_hero: AdminPermissions.SITE_VIEW,
};
// allTabOptions 是「路由表里实际存在的 5 个 tab」，只用于校验 URL 上的 tab 值是否合法；
// tabOptions 才是渲染用的一行 tab，按权限过滤——无权限的 tab 不渲染（点了只会被
// permission.ts 的 meta.permission 校验踢到 403，比直接不显示更差）。
const allTabOptions: SettingsTabOption[] = tabGroups.flatMap((group) =>
  group.tabs.map((tab, index) => ({ ...tab, groupStart: index === 0 })),
);
// hasAdminPermission 内部读 useUserStore()，在 computed 里调用即为响应式：
// 权限在登录后一次性填充时这里会自动重算。
const tabOptions = computed<SettingsTabOption[]>(() =>
  allTabOptions.filter((tab) => hasAdminPermission(tabPermissions[tab.value])),
);

function handleTabChange(value: unknown) {
  const tab = normalizeTab(value);
  if (tab === activeTab.value) return;
  // 切 tab 走 replace：页内换视图不该新增浏览器历史。
  // 五个 tab 各有独立路由，落地后由上面 watch(route.meta.settingsTab) 同步 activeTab 并重新加载。
  void router.replace({ path: tabPaths[tab] });
}

const configs: Record<Exclude<SettingsTab, 'site_hero'>, SettingsConfig> = {
  referral: {
    group: 'referral',
    title: '推荐奖励配置',
    description: '集中控制推荐返利开关、默认比例、冻结期和最低提现门槛。',
    sections: [
      {
        title: '奖励规则',
        fields: [
          { key: 'enabled', label: '启用推荐返利', type: 'switch', default: true },
          { key: 'reward_rate', label: '新购默认奖励比例（%）', type: 'number', default: 10, min: 0, max: 100 },
          { key: 'renewal_reward_rate', label: '续费默认奖励比例（%）', type: 'number', default: 0, min: 0, max: 100 },
          { key: 'reward_freeze_days', label: '奖励冻结期（天）', type: 'number', default: 4, min: 0, max: 365 },
          { key: 'withdraw_min_amount', label: '最低提现金额 (¥)', type: 'number', default: 20, min: 0 },
        ],
      },
    ],
  },
  account_policy: {
    group: 'account_policy',
    title: '账户策略配置',
    description: '统一控制实名、手机号与邮箱的强制要求；开启后只拦截下单、支付、充值、提现，不影响只读页面。',
    sections: [
      {
        title: '强制要求',
        fields: [
          {
            key: 'require_verification',
            label: '是否强制开启实名',
            type: 'switch',
            default: false,
            help: '开启后未实名用户无法下单、支付、充值与提现，并在控制台看到不可关闭的引导弹窗。',
          },
          {
            key: 'require_phone',
            label: '是否强制绑定手机号',
            type: 'switch',
            default: false,
            help: '按手机号非空判定绑定状态，不要求短信验证标记。',
          },
          {
            key: 'require_email',
            label: '是否强制绑定邮箱',
            type: 'switch',
            default: false,
            help: '按邮箱非空判定绑定状态，不要求邮件验证标记。',
          },
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
          {
            key: 'service_lifecycle_schedule_mode',
            label: '任务执行周期',
            type: 'select',
            default: 'every_fifteen_minutes',
            options: automationScheduleModeOptions,
          },
          {
            key: 'service_lifecycle_schedule_time',
            label: '执行时间',
            type: 'time',
            default: '00:00:00',
            placeholder: '分钟仅支持 00/15/30/45',
            pattern: /^\d{2}:(00|15|30|45):00$/,
            patternMessage: '分钟仅支持 00、15、30 或 45，秒必须为 00',
          },
        ],
      },
      {
        title: '账单与提醒',
        fields: [
          { key: 'renew_notice_enabled', label: '启用续费提醒', type: 'switch', default: true },
          { key: 'renew_create_invoice_enabled', label: '自动创建续费账单', type: 'switch', default: true },
          { key: 'invoice_unpaid_reminder_enabled', label: '启用未支付提醒', type: 'switch', default: true },
          {
            key: 'invoice_unpaid_before_due_days',
            label: '到期前提醒天数',
            type: 'number',
            default: 1,
            min: 0,
            max: 30,
          },
          {
            key: 'invoice_unpaid_second_reminder_days',
            label: '第2次提醒天数（0=当天，需小于首次提醒天数）',
            type: 'number',
            default: 0,
            min: 0,
            max: 30,
          },
          {
            key: 'invoice_overdue_reminder_days',
            label: '逾期提醒天数',
            type: 'input',
            default: '1,3,5',
            wide: true,
            pattern: /^\d+(,\d+)*$/,
            patternMessage: '请使用英文逗号分隔天数，例如 1,3,5',
          },
          {
            key: 'billing_maintenance_schedule_mode',
            label: '任务执行周期',
            type: 'select',
            default: 'hourly',
            options: automationScheduleModeOptions,
          },
          {
            key: 'billing_maintenance_schedule_time',
            label: '执行时间',
            type: 'time',
            default: '00:00:00',
            placeholder: '分钟仅支持 00/15/30/45',
            pattern: /^\d{2}:(00|15|30|45):00$/,
            patternMessage: '分钟仅支持 00、15、30 或 45，秒必须为 00',
          },
        ],
      },
      {
        title: '工单与待支付清理',
        fields: [
          { key: 'ticket_auto_close_enabled', label: '启用工单自动关闭', type: 'switch', default: true },
          {
            key: 'ticket_auto_close_after_hours',
            label: '工单自动关闭时长（小时）',
            type: 'number',
            default: 48,
            min: 1,
            max: 720,
          },
          { key: 'pending_order_cleanup_enabled', label: '启用未支付账单清理', type: 'switch', default: true },
          { key: 'pending_recharge_cleanup_enabled', label: '启用未支付充值单清理', type: 'switch', default: true },
          {
            key: 'ticket_auto_close_schedule_mode',
            label: '工单任务执行周期',
            type: 'select',
            default: 'hourly',
            options: automationScheduleModeOptions,
          },
          {
            key: 'ticket_auto_close_schedule_time',
            label: '工单执行时间',
            type: 'time',
            default: '00:00:00',
            placeholder: '分钟仅支持 00/15/30/45',
            pattern: /^\d{2}:(00|15|30|45):00$/,
            patternMessage: '分钟仅支持 00、15、30 或 45，秒必须为 00',
          },
          {
            key: 'order_cleanup_schedule_mode',
            label: '账单清理执行周期',
            type: 'select',
            default: 'every_fifteen_minutes',
            options: automationScheduleModeOptions,
          },
          {
            key: 'order_cleanup_schedule_time',
            label: '账单清理执行时间',
            type: 'time',
            default: '00:00:00',
            placeholder: '分钟仅支持 00/15/30/45',
            pattern: /^\d{2}:(00|15|30|45):00$/,
            patternMessage: '分钟仅支持 00、15、30 或 45，秒必须为 00',
          },
        ],
      },
    ],
  },
  log_archive: {
    group: 'log_archive',
    title: '日志归档设置',
    description: '配置 pt-archiver 的运行参数与日志保留策略。',
    sections: [
      {
        title: '运行参数',
        fields: [
          {
            key: 'pt_archiver_binary',
            label: 'pt-archiver 二进制路径',
            type: 'input',
            default: '/usr/bin/pt-archiver',
            required: true,
            maxlength: 255,
          },
          {
            key: 'pt_archiver_defaults_file',
            label: '默认配置文件',
            type: 'input',
            default: '/etc/caiwu/pt-archiver.cnf',
            required: true,
            maxlength: 255,
          },
          { key: 'concurrency', label: '最大并发数', type: 'number', default: 2, min: 1, max: 8 },
          { key: 'batch_size', label: '每批处理行数', type: 'number', default: 1000, min: 100, max: 10000 },
          { key: 'sleep_seconds', label: '批次间隔（秒）', type: 'number', default: 1, min: 0, max: 60 },
        ],
      },
      {
        title: '保留策略',
        fields: [
          { key: 'retention_days', label: '日志保留天数', type: 'number', default: 90, min: 1, max: 3650 },
          { key: 'file_retention_days', label: '归档文件保留天数', type: 'number', default: 180, min: 1, max: 3650 },
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
          {
            key: 'site_name',
            label: '站点名称',
            type: 'input',
            default: '',
            maxlength: 50,
            placeholder: '例如：创欧云',
          },
          {
            key: 'browser_title',
            label: '浏览器标题',
            type: 'input',
            default: '',
            maxlength: 80,
            placeholder: '留空则默认使用站点名称',
          },
          {
            key: 'site_logo',
            label: '站点 Logo',
            type: 'image',
            default: '',
            maxlength: 255,
            placeholder: '/branding/logo.svg',
          },
          {
            key: 'site_favicon',
            label: '站点 Favicon',
            type: 'image',
            default: '',
            maxlength: 255,
            placeholder: '/branding/logo1.svg',
          },
          {
            key: 'client_console_icon',
            label: '用户控制台图标',
            type: 'image',
            default: '',
            maxlength: 255,
            placeholder: '/branding/logo1.svg',
            help: '用于用户控制台导航菜单与登录页 Logo，留空则使用站点 Favicon。',
          },
          { key: 'service_phone', label: '官方QQ群', type: 'input', default: '', maxlength: 40 },
          { key: 'support_group_qr', label: '官方群聊二维码', type: 'image', default: '', maxlength: 255 },
          { key: 'support_group_link', label: '入群链接', type: 'input', default: '', maxlength: 255 },
          { key: 'terms_url', label: '服务条款链接', type: 'input', default: '', maxlength: 255 },
          { key: 'privacy_url', label: '隐私政策链接', type: 'input', default: '', maxlength: 255 },
        ],
      },
    ],
  },
};

const pageConfig = computed<SettingsConfig | null>(() =>
  activeTab.value === 'site_hero' ? null : configs[activeTab.value],
);
const sections = computed(() =>
  (pageConfig.value?.sections || []).map((section, index) => ({ ...section, anchor: `${activeTab.value}-${index}` })),
);
const currentSection = computed(
  () => sections.value.find((section) => section.anchor === activeSection.value) || sections.value[0],
);
const allFields = computed(() => sections.value.flatMap((section) => section.fields));
const activeGroups = computed(() =>
  Array.from(new Set(allFields.value.map((field) => field.group || pageConfig.value?.group || 'system'))),
);
const heroDirty = ref(false);
const heroReloadToken = ref(0);
// 普通设置页同样需要脏判定：字段回填与保存成功后重新取快照
const settingsSnapshot = ref('');
const settingsDirty = computed(() => JSON.stringify(form) !== settingsSnapshot.value);
// 加载期间表单处于中间态，此时不算未保存修改，避免误拦离开。
// 首页装修的脏状态由 SiteHeroConfig 通过 dirty-change 回传，其「加载中不算脏」的守卫在子组件内部。
const pageDirty = computed(() => !settingsLoading.value && (settingsDirty.value || heroDirty.value));
useUnsavedGuard(() => pageDirty.value, {
  message: '设置页存在未保存的修改，离开后这些修改会丢失。',
});

function normalizeTab(value: unknown): SettingsTab {
  const tab = Array.isArray(value) ? value[0] : value;
  if (tab === 'site') return 'site_basic';
  // 用 allTabOptions 而非 tabOptions 校验：URL 上的 tab 合法与否与当前账号权限无关，
  // 无权限的路由由 permission.ts 的 meta.permission 兜底。
  return allTabOptions.some((item) => item.value === tab) ? (tab as SettingsTab) : 'automation';
}

function resolveInitialTab(): SettingsTab {
  const metaTab = route.meta.settingsTab;
  if (typeof metaTab === 'string' && allTabOptions.some((item) => item.value === metaTab)) {
    return metaTab as SettingsTab;
  }
  return normalizeTab(route.query.tab);
}

const activeTab = ref<SettingsTab>(resolveInitialTab());
const canManageSettings = computed(() => hasAdminPermission(AdminPermissions.SETTINGS_MANAGE));
const canManageSite = computed(() => hasAdminPermission(AdminPermissions.SITE_MANAGE));
const canRevealSettingsSecret = computed(() => hasAdminPermission(AdminPermissions.SETTINGS_SECRET_REVEAL));
const canManageCurrentTab = computed(() =>
  activeTab.value === 'site_hero' ? canManageSite.value : canManageSettings.value,
);

function refreshCurrentTab() {
  // 切到首页装修时通知子组件重新拉取；通用设置仍由父级自己加载。
  if (activeTab.value === 'site_hero') {
    heroReloadToken.value += 1;
    return;
  }
  return loadSettings();
}

// 路由切换时同步 activeTab
watch(
  () => route.meta.settingsTab,
  (newTab) => {
    if (typeof newTab === 'string' && allTabOptions.some((item) => item.value === newTab)) {
      activeTab.value = newTab as SettingsTab;
      refreshCurrentTab();
    }
  },
);

function resetFormDefaults() {
  Object.keys(form).forEach((key) => delete form[key]);
  Object.keys(settingsSecretEdited).forEach((key) => delete settingsSecretEdited[key]);
  allFields.value.forEach((field) => {
    form[field.key] = field.default ?? (field.type === 'switch' ? false : '');
  });
}

// 竞态防护：切 tab 会连续触发加载，只认最后一次请求的结果（docs/FRONTEND.md:27）
let settingsRequestSeq = 0;

async function loadSettings() {
  if (!pageConfig.value) return;
  const currentSeq = ++settingsRequestSeq;
  settingsLoading.value = true;
  resetFormDefaults();
  try {
    const maps = Object.fromEntries(
      await Promise.all(
        activeGroups.value.map(async (group): Promise<[string, Record<string, unknown>]> => [
          group,
          await loadSettingsGroup(group),
        ]),
      ),
    );
    if (currentSeq !== settingsRequestSeq) return;
    allFields.value.forEach((field) => {
      const group = field.group || pageConfig.value?.group || 'system';
      form[field.key] = parseFieldValue(field, maps[group]?.[field.key]);
    });
  } catch (error) {
    if (currentSeq !== settingsRequestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载设置失败'));
  } finally {
    if (currentSeq === settingsRequestSeq) {
      settingsLoading.value = false;
      // 加载完成后重置快照，之后的用户编辑才算未保存修改
      settingsSnapshot.value = JSON.stringify(form);
    }
  }
}

async function saveSettings() {
  if (!canManageSettings.value) {
    MessagePlugin.warning('当前账号无系统配置管理权限');
    return;
  }

  if (!validateSettings()) return;
  settingsSaving.value = true;
  // 按 group 串行提交：并发时某个 group 失败会被 Promise.all 吞成一句笼统报错，
  // 已经生效的 group 与失败的 group 无法区分，用户也无从知道该重试哪一块。
  const entries = Object.entries(buildSettingsPayload());
  const savedGroups: string[] = [];
  // 失败分组直接记在循环变量上：早前用 entries[savedGroups.length] 反查下标，
  // 一旦 buildSettingsPayload 的字段顺序与 entries 顺序错位，就会清错缓存、
  // 重拉错分组，真正失败的那组继续显示未落库的值。
  let currentGroup = '';
  try {
    for (const [group, settings] of entries) {
      currentGroup = group;
      await adminApi.settings.save({ group, settings });
      settingsGroupCache[group] = { ...(settingsGroupCache[group] || {}), ...settings };
      savedGroups.push(group);
    }
    settingsSnapshot.value = JSON.stringify(form);
    MessagePlugin.success(`${pageConfig.value?.title || '设置'}已保存`);
  } catch (error) {
    // 失败时清掉对应 group 的本地缓存并重新拉取，避免界面继续展示未落库的值
    const failedGroup = currentGroup;
    if (failedGroup) {
      delete settingsGroupCache[failedGroup];
      delete settingsMetaCache[failedGroup];
      try {
        await loadSettingsGroup(failedGroup);
      } catch {
        // 重新拉取失败不覆盖原始保存错误
      }
    }
    const suffix = savedGroups.length ? `；已保存分组：${savedGroups.join('、')}` : '';
    MessagePlugin.error(`${errorMessage(error, '保存设置失败')}${suffix}`);
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
    if (
      isSecretSettingField(field) &&
      hasSettingSecretValue(field) &&
      !settingsSecretEdited[settingSecretEditKey(field)]
    ) {
      value = '';
    }
    if (!payload[group]) payload[group] = {};
    payload[group][field.key] = value;
    return payload;
  }, {});
}

async function loadSettingsGroup(group: string) {
  if (settingsGroupCache[group]) return settingsGroupCache[group];
  const response = await adminApi.settings.list({ group });
  settingsGroupCache[group] = normalizeSettings(response);
  settingsMetaCache[group] = normalizeSettingItems(response);
  return settingsGroupCache[group];
}

function normalizeSettings(response: SettingItem[] | Record<string, unknown>) {
  if (Array.isArray(response)) return Object.fromEntries(response.map((item) => [item.key, item.value]));
  const record = toRecord(response);
  if (Array.isArray(record.list))
    return Object.fromEntries((record.list as SettingItem[]).map((item) => [item.key, item.value]));
  return record;
}

function normalizeSettingItems(response: SettingItem[] | Record<string, unknown>) {
  if (Array.isArray(response)) return Object.fromEntries(response.map((item) => [item.key, item]));
  const record = toRecord(response);
  if (Array.isArray(record.list))
    return Object.fromEntries((record.list as SettingItem[]).map((item) => [item.key, item]));
  return {};
}

function settingFieldGroup(field: SettingField) {
  return field.group || pageConfig.value?.group || 'system';
}

function settingSecretEditKey(field: SettingField) {
  return `${settingFieldGroup(field)}:${field.key}`;
}

function settingSecretResetKey(field: SettingField) {
  return `${activeTab.value}:${settingSecretEditKey(field)}`;
}

function settingMeta(field: SettingField) {
  return settingsMetaCache[settingFieldGroup(field)]?.[field.key];
}

function isSecretSettingField(field: SettingField) {
  return field.type === 'password' && settingMeta(field)?.is_secret === true;
}

function hasSettingSecretValue(field: SettingField) {
  return settingMeta(field)?.has_value === true;
}

async function revealSettingSecret(field: SettingField) {
  if (!canRevealSettingsSecret.value) return '';

  const response = await adminApi.settings.revealSecret(settingFieldGroup(field), field.key);
  return response.value;
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
  openMediaDrawer();
}

function openMediaDrawer() {
  mediaDrawerVisible.value = true;
  mediaDrawerType.value = '';
  loadMediaDrawerList();
}

function closeMediaDrawer() {
  mediaDrawerVisible.value = false;
}

// 竞态防护：切换媒体类型时只保留最后一次请求的结果（docs/FRONTEND.md:27）
let mediaDrawerRequestSeq = 0;

async function loadMediaDrawerList() {
  const currentSeq = ++mediaDrawerRequestSeq;
  mediaDrawerLoading.value = true;
  try {
    const res = await adminApi.media.list({
      type: mediaDrawerType.value || undefined,
      page_size: 100,
    });
    if (currentSeq !== mediaDrawerRequestSeq) return;
    mediaDrawerList.value = (res.list || [])
      .map(function (item) {
        return {
          url: String(item.url || ''),
          filename:
            String(item.filename || '')
              .split('/')
              .pop() || '',
          isVideo: isMediaDrawerVideo(item),
        };
      })
      .filter(function (item) {
        return item.url;
      });
  } catch (error) {
    if (currentSeq !== mediaDrawerRequestSeq) return;
    mediaDrawerList.value = [];
    MessagePlugin.error(errorMessage(error, '加载媒体库失败，请稍后重试'));
  } finally {
    if (currentSeq === mediaDrawerRequestSeq) mediaDrawerLoading.value = false;
  }
}

function isMediaDrawerVideo(row: MediaFileRecord): boolean {
  return String(row.type || '').toLowerCase() === 'video' || String(row.mime_type || '').startsWith('video/');
}

function selectMediaFromDrawer(item: { url: string; filename: string; isVideo: boolean }) {
  const field = pendingImageField.value;
  if (!field) return;
  form[field.key] = item.url;
  MessagePlugin.success('已选择');
  closeMediaDrawer();
}

function openMediaDrawerUpload() {
  if (mediaDrawerUploadRef.value) {
    mediaDrawerUploadRef.value.value = '';
    mediaDrawerUploadRef.value.click();
  }
}

async function handleMediaDrawerUpload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;
  const data = new FormData();
  data.append('file', file);
  data.append('group', 'site-settings');
  try {
    const response = await adminApi.media.upload(data);
    const url = String(response.url || '');
    if (url) {
      mediaDrawerList.value.unshift({
        url,
        filename:
          String(response.filename || '')
            .split('/')
            .pop() || file.name,
        isVideo: isMediaDrawerVideo(response),
      });
    }
    // 同时设为当前选中的图片
    const field = pendingImageField.value;
    if (field) form[field.key] = url;
    MessagePlugin.success('上传成功');
  } catch (error) {
    const record = error as Record<string, unknown>;
    MessagePlugin.error(String(record.message || '上传失败'));
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
