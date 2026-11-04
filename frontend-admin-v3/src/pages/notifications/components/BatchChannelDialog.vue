<template>
  <t-dialog
    :visible="visible"
    header="批量配置"
    width="min(560px, calc(100vw - 32px))"
    :confirm-btn="{ content: '确定', loading: saving }"
    :cancel-btn="{ content: '取消' }"
    @confirm="submit"
    @close="emit('close')"
  >
    <div class="batch-channel-dialog">
      <t-alert theme="info" class="batch-alert">
        批量配置只修改所选消息在指定渠道的启用状态，不会改动各消息的模板正文与变量。
      </t-alert>

      <t-form label-align="right">
        <t-form-item label="消息渠道" :help="channelHelp">
          <t-select v-model="channel" :disabled="!channelOptions.length" @change="handleChannelChange">
            <t-option
              v-for="item in channelOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
              :disabled="!item.count"
            />
          </t-select>
        </t-form-item>

        <t-form-item label="适用消息" :help="`已选 ${selectedCodes.length} / ${applicableRows.length} 条`">
          <div class="batch-message-picker">
            <div class="batch-select-all">
              <t-checkbox
                :checked="allSelected"
                :indeterminate="someSelected"
                :disabled="!applicableRows.length"
                @change="toggleAll"
              >
                全部适用消息（{{ applicableRows.length }}）
              </t-checkbox>
            </div>
            <div class="batch-message-list">
              <t-checkbox
                v-for="item in applicableRows"
                :key="item.code"
                class="batch-message-item"
                :checked="selectedCodes.includes(item.code)"
                @change="(checked: boolean) => toggleOne(item.code, checked)"
              >
                <span class="batch-message-name">{{ item.name }}</span>
                <t-tag variant="light" size="small">{{ item.code }}</t-tag>
              </t-checkbox>
              <t-empty v-if="!applicableRows.length" description="当前渠道没有可批量修改的消息" />
            </div>
          </div>
        </t-form-item>

        <t-form-item label="启用状态">
          <t-radio-group v-model="state" variant="default-filled">
            <t-radio-button value="keep">保持原状态</t-radio-button>
            <t-radio-button value="enable">启用</t-radio-button>
            <t-radio-button value="disable">禁用</t-radio-button>
          </t-radio-group>
        </t-form-item>
      </t-form>

      <p class="batch-footnote">将修改 {{ selectedCodes.length }} 条消息的{{ channelLabel }}配置。</p>
    </div>
  </t-dialog>
</template>
<script setup lang="ts">
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, ref, watch } from 'vue';

import { adminApi } from '@/api/admin';
import { errorMessage } from '@/utils/userMessage';

type TemplateAudience = 'customer' | 'admin';
type TemplateChannel = 'email' | 'sms';

interface MessageSceneRow {
  code: string;
  name: string;
  description: string;
  revision: number;
  channels: Record<string, boolean>;
  /** 该场景实际挂载了模板、且未被插件锁定的渠道；不在其中的渠道批量开启也不会生效 */
  sendable_channels: string[];
}

const props = defineProps<{
  visible: boolean;
  rows: MessageSceneRow[];
  audience: TemplateAudience;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const channelOptions = computed(() =>
  (
    [
      { value: 'email' as const, label: '邮件' },
      { value: 'sms' as const, label: '短信' },
    ] as const
  ).map((item) => ({
    ...item,
    // 只统计「该渠道真的能发送」的场景：没有模板的渠道批量开启也不会生效
    count: props.rows.filter((row) => (row.sendable_channels || []).includes(item.value)).length,
  })),
);

const channel = ref<TemplateChannel>('email');
const state = ref<'keep' | 'enable' | 'disable'>('keep');
const selectedCodes = ref<string[]>([]);
const saving = ref(false);

const applicableRows = computed(() =>
  props.rows.filter((row) => (row.sendable_channels || []).includes(channel.value)),
);
const allSelected = computed(
  () => applicableRows.value.length > 0 && selectedCodes.value.length === applicableRows.value.length,
);
const someSelected = computed(
  () => selectedCodes.value.length > 0 && selectedCodes.value.length < applicableRows.value.length,
);
const channelLabel = computed(() => channelOptions.value.find((item) => item.value === channel.value)?.label || '');
const channelHelp = computed(() =>
  applicableRows.value.length ? '' : '当前渠道没有可批量修改的消息，请切换到其他渠道。',
);

function handleChannelChange(value: unknown) {
  const next = value === 'sms' ? 'sms' : 'email';
  channel.value = next;
  selectedCodes.value = [];
}

function toggleAll(checked: boolean) {
  selectedCodes.value = checked ? applicableRows.value.map((row) => row.code) : [];
}

function toggleOne(code: string, checked: boolean) {
  selectedCodes.value = checked
    ? Array.from(new Set([...selectedCodes.value, code]))
    : selectedCodes.value.filter((item) => item !== code);
}

async function submit() {
  if (!selectedCodes.value.length) {
    MessagePlugin.warning('请至少选择一条消息');
    return;
  }
  if (state.value === 'keep') {
    MessagePlugin.warning('请选择启用状态');
    return;
  }

  saving.value = true;
  try {
    const selected = props.rows.filter((row) => selectedCodes.value.includes(row.code));
    await adminApi.settings.batchMessageScenes({
      audience: props.audience,
      channel: channel.value,
      enabled: state.value === 'enable',
      items: selected.map((row) => ({ code: row.code, revision: row.revision })),
    });
    MessagePlugin.success(`已配置 ${selected.length} 条消息`);
    emit('saved');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '批量配置失败'));
  } finally {
    saving.value = false;
  }
}

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return;
    const first = channelOptions.value.find((item) => item.count > 0);
    channel.value = first?.value ?? 'email';
    state.value = 'keep';
    selectedCodes.value = [];
  },
  { immediate: true },
);
</script>
