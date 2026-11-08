<template>
  <div class="notifications-page">
    <t-card :bordered="false" :loading="templatesLoading">
      <div class="template-toolbar">
        <div>
          <h2>消息配置</h2>
          <p>按发送对象维护每类消息在邮件、短信与站内消息上的启用状态与模板内容。</p>
        </div>
      </div>

      <div class="page-tabs-toolbar">
        <t-tabs :value="templateAudience" @change="handleAudienceChange">
          <t-tab-panel v-for="item in templateAudienceOptions" :key="item.value" :value="item.value">
            <template #label>{{ item.label }}</template>
          </t-tab-panel>
        </t-tabs>
        <t-space v-if="canManage">
          <t-button theme="default" variant="outline" :loading="pullingTemplates" @click="pullProviderTemplates">
            <template #icon><refresh-icon /></template>
            从插件拉取模板
          </t-button>
          <t-button
            theme="default"
            variant="outline"
            :disabled="templatesLoading || !filteredTemplates.length"
            @click="openBatchDialog"
          >
            <template #icon><setting-icon /></template>
            批量配置
          </t-button>
        </t-space>
      </div>

      <div v-if="!isMobile" class="table-scroll">
        <t-table
          row-key="code"
          :data="filteredTemplates"
          :columns="templateColumns"
          :loading="templatesLoading"
          hover
          table-layout="fixed"
          aria-label="消息配置列表"
          @row-click="openTemplate"
        >
          <template #empty>
            <t-empty :description="templatesLoading ? '加载中…' : '暂无数据'" />
          </template>
          <template #name="{ row }">
            <div class="stack-cell">
              <strong>{{ row.name }}</strong>
              <span>{{ row.description }}</span>
            </div>
          </template>
          <template #email="{ row }">
            <span v-if="'email' in (row.channels || {})" class="channel-switch" @click.stop>
              <t-switch
                :value="row.channels.email && isSendable(row, 'email')"
                :custom-value="[true, false]"
                :disabled="!canManage || !isSendable(row, 'email')"
                :loading="channelSavingKey === `${row.code}:email`"
                size="small"
                @change="(value: unknown) => toggleChannel(row, 'email', value)"
              />
            </span>
            <span v-else class="muted-text">-</span>
          </template>
          <template #sms="{ row }">
            <t-tooltip v-if="lockReason(row, 'sms')" :content="lockReason(row, 'sms')">
              <span class="channel-switch is-locked">
                <t-switch :value="false" :custom-value="[true, false]" disabled size="small" />
              </span>
            </t-tooltip>
            <span v-else-if="'sms' in (row.channels || {})" class="channel-switch" @click.stop>
              <t-switch
                :value="row.channels.sms && isSendable(row, 'sms')"
                :custom-value="[true, false]"
                :disabled="!canManage || !isSendable(row, 'sms')"
                :loading="channelSavingKey === `${row.code}:sms`"
                size="small"
                @change="(value: unknown) => toggleChannel(row, 'sms', value)"
              />
            </span>
            <span v-else class="muted-text">-</span>
          </template>
          <template #inbox="{ row }">
            <span v-if="'message' in (row.channels || {})" class="channel-switch" @click.stop>
              <t-switch
                :value="row.channels.message && isSendable(row, 'message')"
                :custom-value="[true, false]"
                :disabled="!canManage || !isSendable(row, 'message')"
                :loading="channelSavingKey === `${row.code}:message`"
                size="small"
                @change="(value: unknown) => toggleChannel(row, 'message', value)"
              />
            </span>
            <span v-else class="muted-text">-</span>
          </template>
          <template #actions="{ row }">
            <t-space v-if="canManage" size="small">
              <t-button variant="text" :loading="testSendLoadingKey === row.code" @click.stop="openTestSend(row)">
                测试发送
              </t-button>
              <t-button theme="primary" variant="text" @click.stop="openTemplate(row)">编辑</t-button>
            </t-space>
            <t-button v-else theme="primary" variant="text" @click.stop="openTemplate(row)">查看</t-button>
          </template>
        </t-table>
      </div>

      <div v-else class="mobile-list" data-mobile-view="card">
        <article v-for="row in filteredTemplates" :key="row.code" class="template-mobile-card">
          <div class="template-mobile-card__head">
            <strong>{{ row.name }}</strong>
          </div>
          <p>{{ row.description }}</p>
          <dl>
            <div v-for="channel in templateChannelColumns" :key="`${row.code}-${channel.value}`">
              <dt>{{ channel.label }}</dt>
              <dd>
                <t-switch
                  v-if="channel.key in (row.channels || {})"
                  :value="row.channels[channel.key] && isSendable(row, channel.key)"
                  :custom-value="[true, false]"
                  :disabled="!canManage || !isSendable(row, channel.key)"
                  :loading="channelSavingKey === `${row.code}:${channel.key}`"
                  size="small"
                  @change="(value: unknown) => toggleChannel(row, channel.key, value)"
                />
                <span v-else class="muted-text">-</span>
                <small v-if="lockReason(row, channel.key)" class="channel-lock-hint">
                  {{ lockReason(row, channel.key) }}
                </small>
              </dd>
            </div>
          </dl>
          <div class="template-mobile-card__actions">
            <t-button
              v-if="canManage"
              variant="outline"
              :loading="testSendLoadingKey === row.code"
              @click="openTestSend(row)"
            >
              测试发送
            </t-button>
            <t-button theme="primary" variant="outline" @click="openTemplate(row)">
              {{ canManage ? '编辑' : '查看' }}
            </t-button>
          </div>
        </article>
      </div>
    </t-card>

    <scene-detail-drawer
      :visible="drawerVisible"
      :row="drawerRow"
      :audience="templateAudience"
      :channel="drawerChannel"
      :can-manage="canManage"
      @close="closeDrawer"
      @saved="handleDrawerSaved"
      @channel-change="handleDrawerChannelChange"
      @test-send="openTestSendFromDrawer"
    />

    <t-dialog
      v-model:visible="pullResultVisible"
      header="从插件拉取模板"
      width="min(480px, calc(100vw - 32px))"
      :confirm-btn="{ content: '知道了' }"
      @confirm="pullResultVisible = false"
      @close="pullResultVisible = false"
    >
      <div class="pull-result">
        <p class="pull-result__lead">已从当前短信插件的号码认证参数配置拉取验证码模板。</p>
        <dl>
          <div>
            <dt>插件</dt>
            <dd>{{ pullResult?.plugin_key || '-' }}</dd>
          </div>
          <div>
            <dt>已写回业务模板</dt>
            <dd>{{ pullResult?.applied ?? 0 }} 条</dd>
          </div>
          <div>
            <dt>拉取到的场景文案</dt>
            <dd>{{ Object.keys(pullResult?.purposes || {}).length }} 条</dd>
          </div>
        </dl>
        <div v-if="pullResult?.purposes" class="pull-result__list">
          <div v-for="(content, purpose) in pullResult.purposes" :key="purpose" class="pull-result__item">
            <strong>{{ purposeLabel(String(purpose)) }}</strong>
            <span>{{ content }}</span>
          </div>
        </div>
        <p class="pull-result__hint">
          插件内置的验证码模板已写回对应业务模板正文，并由插件接管（管理端只读）。如需调整请联系插件维护方。
        </p>
      </div>
    </t-dialog>

    <batch-channel-dialog
      :visible="batchVisible"
      :rows="filteredTemplates"
      :audience="templateAudience"
      @close="batchVisible = false"
      @saved="handleBatchSaved"
    />

    <t-dialog
      v-model:visible="testSendVisible"
      :header="testSendDialogTitle"
      width="min(560px, calc(100vw - 32px))"
      :confirm-btn="{ content: '确定', loading: testSending }"
      @confirm="submitTestSend"
    >
      <div class="template-test-dialog">
        <div v-if="testSendTarget" class="template-test-target">
          <strong>{{ testSendTarget.name }}</strong>
          <span>{{ testSendTarget.channel === 'sms' ? '短信模板' : '邮件模板' }} · {{ testSendTarget.code }}</span>
        </div>
        <t-form label-align="right">
          <t-form-item v-if="testSendChannelOptions.length > 1" label="发送渠道">
            <t-radio-group
              :value="testSendChannel"
              variant="default-filled"
              @change="(value: unknown) => switchTestSendChannel(value)"
            >
              <t-radio-button v-for="option in testSendChannelOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </t-radio-button>
            </t-radio-group>
          </t-form-item>
          <t-form-item :label="testSendChannel === 'sms' ? '接收手机号' : '接收邮箱地址'">
            <t-input
              v-model="testSendRecipient"
              :placeholder="testSendPlaceholder"
              clearable
              :type="testSendChannel === 'sms' ? 'tel' : 'email'"
              @input="testSendResult = null"
            />
          </t-form-item>
        </t-form>

        <div
          v-if="testSendResult"
          class="template-test-feedback"
          :class="`template-test-feedback--${testSendResult.status}`"
        >
          <strong>{{ testSendSummaryText }}</strong>
          <span>成功 {{ testSendResult.success_count }} 条，失败 {{ testSendResult.failed_count }} 条</span>
        </div>
        <div v-if="testSendResult?.results?.length" class="template-test-results">
          <div
            v-for="item in testSendResult.results"
            :key="`${item.recipient}:${item.status}`"
            class="template-test-result-row"
          >
            <span>{{ item.recipient }}</span>
            <t-tag :theme="item.status === 'success' ? 'success' : 'danger'" variant="light">
              {{ item.status === 'success' ? '成功' : '失败' }}
            </t-tag>
            <small v-if="item.error">{{ item.error }}</small>
          </div>
        </div>
      </div>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { RefreshIcon, SettingIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol, TableRowData } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { MessageAudience, MessageSceneRow as SceneRow, NotificationTemplateTestSendResponse } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { AdminPermissions } from '@/constants/permissions';
import { useListPage } from '@/hooks/useListPage';
import { MOBILE_MEDIA_QUERY, useMediaQuery } from '@/hooks/useMediaQuery';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

import BatchChannelDialog from './components/BatchChannelDialog.vue';
import SceneDetailDrawer from './components/SceneDetailDrawer.vue';

defineOptions({ name: 'AdminNotificationList' });

type TemplateChannel = 'email' | 'sms';

interface ChannelEntry {
  name: string;
  isEnabled: boolean;
}

interface MessageSceneRow {
  code: string;
  name: string;
  description: string;
  email?: ChannelEntry;
  sms?: ChannelEntry;
  inbox?: ChannelEntry;
  wechat?: ChannelEntry;
}

const route = useRoute();
const router = useRouter();
// 模板启停/测试发送/编辑与后端设置写路由同权限码，只读岗只保留浏览
const canManage = computed(() => hasAdminPermission(AdminPermissions.SETTINGS_MANAGE));
// 场景数据源受众为 customer/admin；query.tab 兼容旧 user 值。
const templateAudience = ref<MessageAudience>(normalizeAudience(route.query.tab));
const isMobile = useMediaQuery(MOBILE_MEDIA_QUERY);
const {
  list: sceneSummaries,
  loading: templatesLoading,
  loadList,
} = useListPage<Record<string, never>, SceneRow>({
  defaultFilters: {},
  immediate: false,
  onError: (error) => MessagePlugin.error(errorMessage(error, '加载消息配置列表失败')),
  fetch: async () => {
    const response = await adminApi.settings.messageScenes({ audience: templateAudience.value });
    const list = response.data || [];
    return { list, total: list.length };
  },
});

const drawerVisible = ref(false);
const drawerRow = ref<MessageSceneRow | null>(null);
const drawerChannel = ref<TemplateChannel>('email');
const batchVisible = ref(false);
// 渠道开关的保存中状态，键为 `${sceneCode}:${channel}`，避免整表 loading 闪烁。
const channelSavingKey = ref('');
// 测试发送前拉取场景模板时的加载态，键为场景编码。
const testSendLoadingKey = ref('');
// 「从插件拉取模板」的进行态与最近一次结果
const pullingTemplates = ref(false);
const pullResult = ref<{ plugin_key?: string; applied?: number; purposes?: Record<string, string> } | null>(null);
const pullResultVisible = ref(false);
const testSendVisible = ref(false);
const testSending = ref(false);
const testSendRecipient = ref('');
const testSendTarget = ref<{ channel: TemplateChannel; code: string; name: string } | null>(null);
const testSendResult = ref<NotificationTemplateTestSendResponse | null>(null);
// 测试发送时可切换的渠道与当前选择；候选来自该场景实际挂的模板。
const testSendChannel = ref<TemplateChannel>('email');
const testSendChannelOptions = ref<Array<{ value: TemplateChannel; label: string }>>([]);
// 场景内各渠道可用的模板，切换渠道时据此换模板码。
const testSendTemplates = ref<Array<{ channel: string; code: string; name: string }>>([]);

// 渠道列只保留有模板载体的三类；公众号在 caiwu 未接入，不再展示。
const templateChannelColumns = [
  { value: 'email' as const, key: 'email', label: '邮件', width: 150 },
  { value: 'sms' as const, key: 'sms', label: '短信', width: 150 },
  { value: 'inbox' as const, key: 'message', label: '站内消息', width: 150 },
];

const templateAudienceOptions = [
  { label: '发送给客户', value: 'customer' as const },
  { label: '发送给管理员', value: 'admin' as const },
];

const templateColumns = computed<PrimaryTableCol<SceneRow>[]>(() => [
  { colKey: 'name', title: '消息类型', minWidth: 240 },
  ...templateChannelColumns.map((channel): PrimaryTableCol<SceneRow> => ({
    colKey: channel.value,
    title: channel.label,
    width: channel.width,
  })),
  { colKey: 'actions', title: '操作', fixed: 'right', width: 160 },
]);

/**
 * 场景数据源：后端 message-scenes 直接给出「一行一个场景」的渠道状态，
 * 不再需要前端按模板名聚合。
 */
const sceneRows = computed<SceneRow[]>(() => sceneSummaries.value);

const filteredTemplates = computed(() => sceneRows.value);
// 标题与占位符跟随对话框内所选渠道，而不是进来时固定的那条
const testSendDialogTitle = computed(() => (testSendChannel.value === 'sms' ? '测试发送短信' : '测试发送邮件'));
const testSendPlaceholder = computed(() =>
  testSendChannel.value === 'sms' ? '请输入接收手机号，例如：13900001234' : '请输入接收邮箱，例如：tester@example.com',
);
const testSendSummaryText = computed(() => {
  if (!testSendResult.value) return '';
  if (testSendResult.value.status === 'success') return '测试发送成功';
  if (testSendResult.value.status === 'partial_failed') return '测试发送部分失败';
  return '测试发送失败';
});

function normalizeAudience(value: unknown): MessageAudience {
  return value === 'admin' ? 'admin' : 'customer';
}

function normalizeTemplateAudience(value: unknown): MessageAudience | null {
  const tab = Array.isArray(value) ? value[0] : value;
  if (tab === 'admin') return 'admin';
  if (tab === 'customer' || tab === 'user') return 'customer';
  return null;
}

function loadTemplates() {
  return loadList();
}

function handleAudienceChange(value: unknown) {
  const audience = normalizeTemplateAudience(value);
  if (!audience) return;
  templateAudience.value = audience;
  drawerVisible.value = false;
  batchVisible.value = false;
  router.replace({ path: route.path, query: { ...route.query, tab: audience } });
  void loadTemplates();
}

function openTemplate(row: SceneRow | TableRowData) {
  const target = row as SceneRow;
  if (!target.code) return;
  drawerRow.value = target;
  drawerChannel.value = target.channels?.sms && !target.channels?.email ? 'sms' : 'email';
  drawerVisible.value = true;
}

function closeDrawer() {
  drawerVisible.value = false;
  drawerRow.value = null;
}

function handleDrawerChannelChange(channel: TemplateChannel) {
  drawerChannel.value = channel;
}

function handleDrawerSaved() {
  closeDrawer();
  void loadTemplates();
}

/**
 * 单场景单渠道启停：直接走场景保存接口，只改目标渠道的 enabled。
 * 先乐观更新行状态，失败再回滚并提示。
 */
/** 该场景在该渠道是否真的能发送（有模板且未被插件锁定）；不能发送时开关禁用且显示为关态 */
function isSendable(row: SceneRow, channel: string) {
  return (row.sendable_channels || []).includes(channel);
}

/** 渠道被插件能力锁定的原因；未锁定时为空字符串 */
function lockReason(row: SceneRow, channel: string) {
  return (row.locked_channels || {})[channel] || '';
}

async function toggleChannel(row: SceneRow, channel: string, value: unknown) {
  const enabled = Boolean(value);
  const key = `${row.code}:${channel}`;
  const previous = Boolean(row.channels?.[channel]);

  if (row.channels) row.channels[channel] = enabled;
  channelSavingKey.value = key;

  try {
    const detail = await adminApi.settings.messageSceneDetail(row.code, { audience: templateAudience.value });
    const groups = detail.groups.map((group) => {
      if (!group.configurations[channel]) return group;
      return {
        ...group,
        configurations: { ...group.configurations, [channel]: { ...group.configurations[channel], enabled } },
      };
    });

    await adminApi.settings.saveMessageScene(row.code, {
      audience: templateAudience.value,
      revisions: detail.revisions,
      groups,
    });
    MessagePlugin.success(enabled ? `${channelLabelOf(channel)}已启用` : `${channelLabelOf(channel)}已禁用`);
  } catch (error) {
    if (row.channels) row.channels[channel] = previous;
    MessagePlugin.error(errorMessage(error, '保存渠道状态失败'));
  } finally {
    if (channelSavingKey.value === key) channelSavingKey.value = '';
  }
}

function channelLabelOf(channel: string) {
  return templateChannelColumns.find((item) => item.key === channel)?.label ?? channel;
}

function handleBatchSaved() {
  batchVisible.value = false;
  void loadTemplates();
}

/**
 * 从当前短信插件的号码认证参数配置拉取内置验证码模板。
 *
 * 拉取后直接写回对应业务模板的正文（插件优先接管），并刷新列表。
 */
async function pullProviderTemplates() {
  if (pullingTemplates.value) return;

  pullingTemplates.value = true;
  try {
    const result = await adminApi.settings.pullSmsVerifyCodeTemplates();
    pullResult.value = result;
    pullResultVisible.value = true;
    MessagePlugin.success('已从插件拉取验证码模板');
    void loadTemplates();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '从插件拉取模板失败'));
  } finally {
    pullingTemplates.value = false;
  }
}

/** 号码认证场景 purpose 的中文标签，与插件返回的 purpose 对应 */
function purposeLabel(purpose: string) {
  const labels: Record<string, string> = {
    generic: '登录 / 注册验证码',
    reset: '重置密码',
    change_phone: '修改绑定手机号',
    bind_phone: '绑定新手机号',
    verify_bound_phone: '验证绑定手机号',
  };

  return labels[purpose] || purpose;
}

function openBatchDialog() {
  batchVisible.value = true;
}

/**
 * 测试发送需要真实的模板码：场景行只带渠道启停，模板引用从场景详情取。
 * 同时把该场景可用的渠道列成候选，供对话框内切换。
 */
async function openTestSend(row: SceneRow | TableRowData) {
  const target = row as SceneRow;
  if (testSendLoadingKey.value) return;

  testSendLoadingKey.value = target.code;
  try {
    const detail = await adminApi.settings.messageSceneDetail(target.code, { audience: templateAudience.value });
    const templates = detail.templates;

    if (!templates.length) {
      MessagePlugin.warning('该消息暂无可用模板');
      return;
    }

    // 只有邮件/短信可测试；站内消息不进传输层。
    const channels = (['email', 'sms'] as const).filter((channel) =>
      templates.some((item) => item.channel === channel),
    );

    testSendTemplates.value = templates.map((item) => ({
      channel: item.channel,
      code: item.code,
      name: item.name,
    }));
    testSendChannelOptions.value = channels.map((channel) => ({
      value: channel,
      label: channel === 'sms' ? '短信' : '邮件',
    }));

    // 默认选该行已启用的渠道；都没启用时优先邮件。
    const defaultChannel: TemplateChannel =
      channels.find((channel) => target.channels?.[channel] === true) ?? channels[0] ?? 'email';

    testSendChannel.value = defaultChannel;
    applyTestSendTemplate(defaultChannel);
    testSendRecipient.value = '';
    testSendResult.value = null;
    testSendVisible.value = true;
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '读取消息模板失败'));
  } finally {
    if (testSendLoadingKey.value === target.code) testSendLoadingKey.value = '';
  }
}

/** 按所选渠道把目标模板换成该渠道的第一条 */
function applyTestSendTemplate(channel: TemplateChannel) {
  const template = testSendTemplates.value.find((item) => item.channel === channel) ?? testSendTemplates.value[0];
  if (!template) return;

  testSendTarget.value = {
    channel: template.channel === 'sms' ? 'sms' : 'email',
    code: template.code,
    name: template.name,
  };
}

/** 对话框内切换渠道：换模板、清空上一条结果与收件人 */
function switchTestSendChannel(value: unknown) {
  const channel: TemplateChannel = value === 'sms' ? 'sms' : 'email';
  if (channel === testSendChannel.value) return;

  testSendChannel.value = channel;
  applyTestSendTemplate(channel);
  testSendRecipient.value = '';
  testSendResult.value = null;
}

function openTestSendFromDrawer(payload: { channel: TemplateChannel; code: string; name: string }) {
  // 抽屉已定位到具体模板：单渠道，不再提供切换
  testSendChannel.value = payload.channel;
  testSendChannelOptions.value = [{ value: payload.channel, label: payload.channel === 'sms' ? '短信' : '邮件' }];
  testSendTemplates.value = [{ channel: payload.channel, code: payload.code, name: payload.name }];
  testSendTarget.value = payload;
  testSendRecipient.value = '';
  testSendResult.value = null;
  testSendVisible.value = true;
}

async function submitTestSend() {
  const target = testSendTarget.value;
  if (!target) return;

  const recipient = normalizeRecipient(testSendRecipient.value, target.channel);
  if (!recipient) {
    MessagePlugin.warning(target.channel === 'sms' ? '请输入接收手机号' : '请输入接收邮箱地址');
    return;
  }

  testSending.value = true;
  try {
    const result = await adminApi.settings.testNotificationTemplateSend({
      channel: target.channel,
      code: target.code,
      recipient,
    });
    testSendResult.value = result;

    if (result.failed_count > 0) {
      MessagePlugin.warning(result.status === 'partial_failed' ? '测试发送部分失败' : '测试发送失败');
    } else {
      MessagePlugin.success('测试发送成功');
    }
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '测试发送失败'));
  } finally {
    testSending.value = false;
  }
}

function normalizeRecipient(value: string, channel: TemplateChannel) {
  const recipient = value.trim();
  if (channel === 'sms') return recipient.replace(/[\s-]+/g, '');
  return recipient;
}

watch(
  () => route.query.tab,
  (value) => {
    if (!isOwnRoute()) return;
    const audience = normalizeTemplateAudience(value);
    if (audience && audience !== templateAudience.value) {
      templateAudience.value = audience;
      drawerVisible.value = false;
    }
  },
);

// keep-alive 复用组件，重新进入本页时收起浮层并刷新模板列表。
watch(
  () => route.meta?.keepAliveName,
  () => {
    if (!isOwnRoute()) return;
    drawerVisible.value = false;
    batchVisible.value = false;
    void loadTemplates();
  },
);

/** 当前路由是否落在本页（keepAliveName 由路由 meta 声明） */
function isOwnRoute(): boolean {
  return route.meta?.keepAliveName === 'AdminNotificationList';
}

onMounted(() => {
  void loadTemplates();
});
</script>
