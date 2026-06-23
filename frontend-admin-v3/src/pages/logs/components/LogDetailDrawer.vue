<template>
  <t-drawer
    :visible="visible"
    :size="drawerSize"
    :header="headerTitle"
    :footer="false"
    @close="emit('close')"
  >
    <template v-if="currentLog">
      <div class="detail-grid">
        <article v-for="item in detailFields" :key="item.label">
          <span>{{ item.label }}</span>
          <strong>{{ item.value }}</strong>
        </article>
      </div>
      <div v-for="item in detailBlocks" :key="item.label" class="detail-block">
        <h3>{{ item.label }}</h3>
        <iframe
          v-if="item.html"
          class="mail-preview-frame"
          :srcdoc="item.value"
          sandbox="allow-same-origin"
          title="邮件正文预览"
        />
        <pre v-else class="json-block">{{ item.value }}</pre>
      </div>
      <div class="detail-drawer-actions">
        <t-button variant="outline" @click="emit('close')">
          <template #icon><chevron-left-icon /></template>
          返回
        </t-button>
      </div>
    </template>
  </t-drawer>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ChevronLeftIcon } from 'tdesign-icons-vue-next';

import { fieldValue } from '@/utils/format';

type LogTab = 'system' | 'admin-logins' | 'api' | 'sms' | 'email' | 'tasks';
type RecordRow = Record<string, unknown>;

const props = defineProps<{
  visible: boolean;
  currentLog: RecordRow | null;
  activeTab: LogTab | 'schedules' | 'cleanup';
  drawerSize: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

defineOptions({ name: 'LogDetailDrawer' });

const headerTitle = computed(() => {
  const row = props.currentLog || {};
  if (props.activeTab === 'admin-logins') return `管理员登录 · ${fieldValue(row.admin_username) || '详情'}`;
  if (props.activeTab === 'api') return `API 日志 · ${fieldValue(row.request_id) || '详情'}`;
  if (props.activeTab === 'sms') return `短信日志 · ${fieldValue(row.phone) || '详情'}`;
  if (props.activeTab === 'email') return `邮件日志 · ${fieldValue(row.to_email) || '详情'}`;
  if (props.activeTab === 'tasks') return `任务日志 · ${fieldValue(row.task_key) || '详情'}`;
  return `系统日志 · ${fieldValue(row.id) || '详情'}`;
});

const detailFields = computed(() => {
  const row = props.currentLog || {};
  if (props.activeTab === 'api') {
    return [
      { label: '请求方法', value: fieldValue(row.method) },
      { label: '状态码', value: fieldValue(row.status) },
      { label: '请求路径', value: fieldValue(row.path) },
      { label: '模块', value: fieldValue(row.module) },
      { label: '请求号', value: fieldValue(row.request_id) },
      { label: '记录时间', value: formatDate(row.created_at) },
      { label: '调用端', value: userTypeLabel(row.user_type) },
      { label: 'IP 地址', value: fieldValue(row.ip_address) },
    ];
  }
  if (props.activeTab === 'admin-logins') {
    return [
      { label: '登录账号', value: fieldValue(row.admin_username) },
      { label: '昵称', value: fieldValue(row.admin_nickname || row.actor_name) },
      { label: '角色', value: fieldValue(row.role_name) },
      { label: '登录时间', value: formatDate(row.created_at) },
      { label: 'IP 地址', value: fieldValue(row.ip_address) },
      { label: '数据来源', value: sourceLabel(row.source) },
    ];
  }
  if (props.activeTab === 'sms') {
    return [
      { label: '手机号', value: fieldValue(row.phone) },
      { label: '发送状态', value: statusLabel(row.status) },
      { label: '模板编号', value: fieldValue(row.template_code) },
      { label: '供应商', value: fieldValue(row.provider) },
      { label: '请求号', value: fieldValue(row.request_id) },
      { label: '发送时间', value: formatDate(row.sent_at) },
      { label: '创建时间', value: formatDate(row.created_at) },
      { label: '错误信息', value: fieldValue(row.error_msg) },
    ];
  }
  if (props.activeTab === 'email') {
    return [
      { label: '收件邮箱', value: fieldValue(row.to_email) },
      { label: '发送状态', value: statusLabel(row.status) },
      { label: '主题', value: fieldValue(row.subject) },
      { label: '模板编号', value: fieldValue(row.template_code) },
      { label: '发送时间', value: formatDate(row.sent_at) },
      { label: '创建时间', value: formatDate(row.created_at) },
      { label: '错误信息', value: fieldValue(row.error_msg) },
    ];
  }
  if (props.activeTab === 'tasks') {
    return [
      { label: '任务名称', value: fieldValue(row.task_title || row.task_key) },
      { label: '任务键', value: fieldValue(row.task_key) },
      { label: '日志级别', value: fieldValue(row.level) },
      { label: '记录时间', value: formatDate(row.time) },
    ];
  }
  return [
    { label: '日志级别', value: fieldValue(row.level) },
    { label: '记录时间', value: formatDate(row.time) },
  ];
});

const detailBlocks = computed(() => {
  const row = props.currentLog || {};
  if (props.activeTab === 'api') {
    return [
      { label: '请求参数', value: formatJson(row.params) },
      { label: '完整上下文', value: formatJson(row.detail) },
      { label: 'User-Agent', value: formatJson(row.user_agent) },
    ];
  }
  if (props.activeTab === 'admin-logins') {
    return [{ label: '上下文详情', value: formatJson(row.detail) }];
  }
  if (props.activeTab === 'sms') {
    return [
      { label: '模板参数', value: formatJson(row.params, '暂无模板参数') },
      { label: '短信内容', value: fieldValue(row.content) },
    ];
  }
  if (props.activeTab === 'email') {
    return [
      { label: '邮件正文预览', value: buildContentPreviewDoc(row.content), html: true },
      { label: 'HTML 源码', value: fieldValue(row.content) },
    ];
  }
  return [
    { label: '格式化内容', value: fieldValue(row.message) },
    { label: '原始日志', value: fieldValue(row.raw) },
  ];
});

function formatDate(value: unknown) {
  return fieldValue(value);
}

function statusLabel(status: unknown) {
  if (props.activeTab === 'admin-logins') return sourceLabel(status);
  const statusKey = String(status || '').toLowerCase();
  return String({ success: '发送成功', failed: '发送失败', pending: '待发送' }[statusKey] || fieldValue(status));
}

function userTypeLabel(value: unknown) {
  return (
    {
      admin: '管理员',
      client: '客户',
      guest: '访客',
    }[String(value || '').toLowerCase()] || '系统'
  );
}

function sourceLabel(value: unknown) {
  return (
    {
      operation_log: '操作日志',
      admin_snapshot: '账号快照',
    }[String(value || '').toLowerCase()] || '未知来源'
  );
}

function formatJson(value: unknown, fallback = '-') {
  if (value === null || value === undefined || value === '') return fallback;
  if (typeof value === 'string') {
    const text = value.trim();
    if (!text) return fallback;
    try {
      return JSON.stringify(JSON.parse(text), null, 2);
    } catch {
      return text;
    }
  }
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return fallback;
  }
}

function buildContentPreviewDoc(value: unknown) {
  const normalized = String(value ?? '').trim();
  if (!normalized) {
    return '<!doctype html><html lang="zh-CN"><body style="margin:0;padding:24px;color:#86909c;">暂无正文内容</body></html>';
  }
  if (/<!doctype\s+html|<html\b|<body\b/i.test(normalized)) return normalized;
  if (/<([a-z][a-z0-9]*)(\s|>)/i.test(normalized)) {
    return `<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><style>body{margin:0;padding:24px;background:#f5f7fa;font-family:Arial,sans-serif;color:#1f2329}.mail-preview{max-width:680px;margin:0 auto;padding:24px;border:1px solid #dce7ff;border-radius: var(--td-radius-extraLarge, 12px);background:#fff}</style></head><body><div class="mail-preview">${normalized}</div></body></html>`;
  }
  return `<!doctype html><html lang="zh-CN"><body style="margin:0;padding:24px;font-family:Arial,sans-serif;background:#f8fafc;color:#1f2329;"><pre style="margin:0;white-space:pre-wrap;line-height:1.75;">${escapeHtml(normalized)}</pre></body></html>`;
}

function escapeHtml(value: string) {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>
