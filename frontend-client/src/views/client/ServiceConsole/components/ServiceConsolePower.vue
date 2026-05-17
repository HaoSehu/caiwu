<template>
  <!-- 电源状态 -->
  <section class="console-panel">
    <div class="console-panel__header">
      <h3>电源状态</h3>
    </div>
    <div class="detail-grid detail-grid--two">
      <div class="detail-cell">
        <span class="detail-cell__label">当前状态</span>
        <div class="detail-cell__value">
          <el-tag :type="resolveRuntimeTagType(detail.runtime?.power_state)" effect="plain">
            {{ detail.runtime?.power_label || detail.upstream?.status_label || '--' }}
          </el-tag>
        </div>
      </div>
      <div class="detail-cell">
        <span class="detail-cell__label">状态描述</span>
        <div class="detail-cell__value">
          <strong>{{ detail.runtime?.description || '状态正常' }}</strong>
        </div>
      </div>
    </div>
  </section>

  <!-- 任务状态 -->
  <section v-if="taskStatuses.length" class="console-panel">
    <div class="console-panel__header">
      <h3>任务状态</h3>
    </div>
    <div class="status-strip">
      <article v-for="item in taskStatuses" :key="item.type" class="status-strip__item">
        <span>{{ item.label }}</span>
        <strong>{{ item.description }}</strong>
        <el-button text type="primary" @click="emit('fetch-module-status', item.type)">刷新</el-button>
      </article>
    </div>
  </section>

  <!-- 危险操作 -->
  <section class="console-panel console-panel--danger">
    <div class="console-panel__header">
      <h3>危险操作</h3>
    </div>
    <div class="danger-box">
      <p>以下操作可能导致数据丢失，仅在实例无响应时使用。</p>
      <div class="danger-box__actions">
        <el-button type="danger" plain :disabled="!detail.actions?.power || actionLoading" @click="emit('power-action', 'hard_off')">强制关机</el-button>
        <el-button type="danger" plain :disabled="!detail.actions?.power || actionLoading" @click="emit('power-action', 'hard_reboot')">强制重启</el-button>
      </div>
    </div>
  </section>

  <!-- 实例维护 -->
  <section class="console-panel">
    <div class="console-panel__header">
      <h3>实例维护</h3>
    </div>
    <div class="maintenance-actions">
      <el-button :disabled="!detail.actions?.password_reset" @click="emit('open-password-dialog')">重置密码</el-button>
      <el-button :disabled="!detail.actions?.reinstall" @click="emit('open-reinstall-dialog')">重装系统</el-button>
    </div>
  </section>
</template>

<script setup>
import { resolveRuntimeTagType } from '@/views/client/ServiceConsole/composables/useServiceConsole.js'

defineProps({
  detail: { type: Object, required: true },
  taskStatuses: { type: Array, default: () => [] },
  actionLoading: { type: Boolean, default: false },
})

const emit = defineEmits(['power-action', 'fetch-module-status', 'open-password-dialog', 'open-reinstall-dialog'])
</script>
