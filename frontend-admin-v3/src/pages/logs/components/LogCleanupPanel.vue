<template>
  <t-card :bordered="false" :loading="loading" :data-mobile-view="isMobile ? 'form' : undefined">
    <t-alert theme="warning" message="日志清理为不可逆操作，请确认保留天数、清理类型和确认文本。" />
    <t-alert
      v-if="!canManage"
      theme="warning"
      message="当前账号缺少 log.manage 权限，只能查看清理概览，不能执行清理。"
    />

    <section class="cleanup-section">
      <div class="section-title">
        <h2>数据库日志概览</h2>
        <p>当前可清理的数据库日志记录总览。</p>
      </div>
      <div class="cleanup-card-grid">
        <article v-for="item in databaseCards" :key="item.key">
          <span>{{ item.label }}</span>
          <strong>{{ item.value }}</strong>
        </article>
      </div>
    </section>

    <section class="cleanup-section">
      <div class="section-title">
        <h2>文件日志概览</h2>
        <p>storage/logs 下的日志文件只读展示；文件生命周期由日志轮转自动管理，不提供清理入口。</p>
      </div>
      <div class="cleanup-card-grid">
        <article v-for="item in fileCards" :key="item.key">
          <span>{{ item.label }}</span>
          <strong>{{ item.value }}</strong>
        </article>
      </div>
      <div v-if="fileEntries.length" class="file-meta-grid">
        <article v-for="item in fileEntries" :key="item.name">
          <span>{{ item.name }}</span>
          <strong>{{ item.size }} · {{ item.updated_at }}</strong>
        </article>
      </div>
    </section>

    <section class="cleanup-section">
      <div class="section-title">
        <h2>执行清理</h2>
        <p>请输入确认文本后执行清理。</p>
      </div>
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top" class="cleanup-form">
        <t-form-item label="清理类型" name="type">
          <t-select v-model="form.type" placeholder="请选择清理类型">
            <t-option
              v-for="item in cleanupTypes"
              :key="String(item.value)"
              :label="String(item.label || item.value)"
              :value="String(item.value)"
            />
          </t-select>
        </t-form-item>
        <t-form-item label="保留天数" name="keep_days">
          <t-input-number v-model="form.keep_days" :min="1" :max="3650" theme="normal" />
        </t-form-item>
        <t-form-item label="确认文本" name="confirm_text" required-mark>
          <t-input v-model="form.confirm_text" clearable placeholder="请输入 立即清理" />
        </t-form-item>
        <div class="cleanup-actions">
          <t-button theme="danger" :loading="submitting" :disabled="submitDisabled" @click="handleCleanup"
            >立即清理</t-button
          >
          <t-button
            theme="primary"
            variant="outline"
            :loading="previewing"
            :disabled="previewDisabled"
            @click="handlePreviewCleanup"
            >预览影响范围</t-button
          >
          <t-button variant="outline" @click="form.confirm_text = ''">清空确认</t-button>
        </div>
      </t-form>
    </section>

    <section v-if="lastCleanupResult" class="cleanup-section">
      <div class="section-title">
        <h2>最近一次清理 / 预览结果</h2>
        <p>显示本次操作范围、截止时间和影响条数；预览不会删除任何数据。</p>
      </div>
      <div class="file-meta-grid">
        <article>
          <span>操作模式</span>
          <strong>{{ lastCleanupResult.dry_run ? '预览（未删除）' : '已执行清理' }}</strong>
        </article>
        <article>
          <span>清理类型</span>
          <strong>{{ fieldValue(lastCleanupResult.type) }}</strong>
        </article>
        <article>
          <span>保留天数</span>
          <strong>{{ fieldValue(lastCleanupResult.keep_days) }} 天</strong>
        </article>
        <article>
          <span>截止时间</span>
          <strong>{{ formatDateTime(lastCleanupResult.cutoff_at) }}</strong>
        </article>
        <article>
          <span>影响条数</span>
          <strong>{{ affectedCountText }}</strong>
        </article>
      </div>
      <pre class="json-block">{{ formatJson(lastCleanupResult.affected) }}</pre>
    </section>
  </t-card>
</template>
<script setup lang="ts">
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref, watch } from 'vue';

import { adminApi } from '@/api/admin';
import { MOBILE_MEDIA_QUERY, useMediaQuery } from '@/hooks/useMediaQuery';
import { fieldValue, formatDateTime, formatJson, numberText, toRecord } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({ name: 'LogCleanupPanel' });

const props = defineProps<{
  /** 是否具备 log.manage 权限；无权限时只能看概览 */
  canManage: boolean;
  /** 父级每次需要重新拉取概览时自增，避免父子重复发请求 */
  reloadToken: number;
}>();

type RecordRow = Record<string, unknown>;

const loading = ref(false);
const submitting = ref(false);
// 手机端形态契约：表单类标记只在手机断点渲染，桌面端保持无属性
const isMobile = useMediaQuery(MOBILE_MEDIA_QUERY);
const previewing = ref(false);
const formRef = ref<FormInstanceFunctions>();
const form = reactive({
  type: '',
  keep_days: 30,
  confirm_text: '',
});
const overview = ref<Record<string, unknown>>({ database: {}, file: {}, supported_cleanup_types: [] });
const lastCleanupResult = ref<RecordRow | null>(null);

// 必填与确认文本由校验规则驱动（docs/DESIGN.md §4.4），失焦即给字段级红字，不用等提交 flash warning
const rules: Record<string, FormRule[]> = {
  type: [{ required: true, message: '请选择清理类型', type: 'error' }],
  keep_days: [{ required: true, message: '请输入保留天数', type: 'error' }],
  confirm_text: [
    {
      required: true,
      message: '确认文本必须为 立即清理',
      type: 'error',
      validator: (value: unknown) => String(value ?? '').trim() === '立即清理',
    },
  ],
};

const submitDisabled = computed(
  () =>
    submitting.value ||
    previewing.value ||
    !props.canManage ||
    !form.type ||
    !Number.isFinite(Number(form.keep_days)) ||
    Number(form.keep_days) < 1 ||
    form.confirm_text.trim() !== '立即清理',
);
const previewDisabled = computed(
  () =>
    previewing.value ||
    submitting.value ||
    !props.canManage ||
    !form.type ||
    !Number.isFinite(Number(form.keep_days)) ||
    Number(form.keep_days) < 1,
);
const cleanupTypes = computed(() => asArray(overview.value.supported_cleanup_types));
const databaseCards = computed(() => {
  const database = toRecord(overview.value.database);
  return [
    { key: 'sms', label: '短信日志', value: numberText(database.sms) },
    { key: 'email', label: '邮件日志', value: numberText(database.email) },
    { key: 'api', label: 'API 日志', value: numberText(database.api) },
    { key: 'admin_login', label: '管理员登录日志', value: numberText(database.admin_login) },
    { key: 'activity', label: '业务审计日志', value: numberText(database.activity) },
    { key: 'schedule_run', label: '调度执行日志', value: numberText(database.schedule_run) },
    { key: 'gateway', label: '支付网关日志', value: numberText(database.gateway) },
  ];
});
const fileCards = computed(() => {
  const file = toRecord(overview.value.file);
  return [
    { key: 'directory', label: '日志目录', value: fieldValue(file.directory) },
    { key: 'file_count', label: '日志文件数', value: numberText(file.file_count) },
    { key: 'total_size_bytes', label: '总大小', value: formatBytes(file.total_size_bytes) },
    { key: 'latest_updated_at', label: '最后写入时间', value: formatDateTime(file.latest_updated_at) },
  ];
});
const fileEntries = computed(() =>
  asArray(toRecord(overview.value.file).files)
    .slice(0, 10)
    .map((item) => {
      const record = toRecord(item);
      return {
        name: fieldValue(record.name),
        size: formatBytes(record.size_bytes),
        updated_at: fieldValue(record.updated_at),
      };
    }),
);
const affectedCountText = computed(() => {
  const affected = Object.values(toRecord(lastCleanupResult.value?.affected));
  return affected.reduce((total: number, value) => total + Number(value || 0), 0);
});

async function loadOverview() {
  loading.value = true;
  try {
    overview.value = await adminApi.logs.cleanupOverview();
    if (!form.type) form.type = String(cleanupTypes.value[0]?.value || 'sms');
  } catch (error) {
    overview.value = { database: {}, file: {}, supported_cleanup_types: [] };
    MessagePlugin.error(errorMessage(error, '加载日志清理概览失败'));
  } finally {
    loading.value = false;
  }
}

async function handlePreviewCleanup() {
  if (!props.canManage) {
    MessagePlugin.warning('当前账号缺少 log.manage 权限');
    return;
  }
  if (!form.type) {
    MessagePlugin.warning('请选择清理类型');
    return;
  }
  if (!Number.isFinite(Number(form.keep_days)) || Number(form.keep_days) < 1) {
    MessagePlugin.warning('保留天数必须大于 0');
    return;
  }
  previewing.value = true;
  try {
    lastCleanupResult.value = await adminApi.logs.cleanup({
      type: form.type,
      keep_days: Number(form.keep_days),
      dry_run: true,
    });
    MessagePlugin.success('预览完成，未删除数据');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '日志清理预览失败'));
  } finally {
    previewing.value = false;
  }
}

async function handleCleanup() {
  if (!props.canManage) {
    MessagePlugin.warning('当前账号缺少 log.manage 权限');
    return;
  }
  if (!form.type) {
    MessagePlugin.warning('请选择清理类型');
    return;
  }
  if (!Number.isFinite(Number(form.keep_days)) || Number(form.keep_days) < 1) {
    MessagePlugin.warning('保留天数必须大于 0');
    return;
  }
  if (form.confirm_text.trim() !== '立即清理') {
    MessagePlugin.warning('确认文本必须为 立即清理');
    return;
  }
  const validateResult = await formRef.value?.validate?.();
  if (validateResult !== true) return;
  submitting.value = true;
  try {
    lastCleanupResult.value = await adminApi.logs.cleanup({
      type: form.type,
      keep_days: Number(form.keep_days),
      confirm_text: form.confirm_text.trim(),
    });
    form.confirm_text = '';
    await loadOverview();
    MessagePlugin.success('日志清理完成');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '日志清理失败'));
  } finally {
    submitting.value = false;
  }
}

function asArray(value: unknown): RecordRow[] {
  return Array.isArray(value) ? (value as RecordRow[]) : [];
}

function formatBytes(value: unknown) {
  const size = Number(value || 0);
  if (!Number.isFinite(size) || size <= 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let current = size;
  let unitIndex = 0;
  while (current >= 1024 && unitIndex < units.length - 1) {
    current /= 1024;
    unitIndex += 1;
  }
  return `${current.toFixed(current >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

watch(
  () => props.reloadToken,
  () => {
    if (loading.value) return;
    void loadOverview();
  },
);

onMounted(() => {
  void loadOverview();
});
</script>
