<template>
  <section class="console-top-card" v-loading="detailLoading">
    <div class="console-top-card__main">
      <div class="console-title-line">
        <h1>{{ detail.name || `服务 #${serviceId}` }}</h1>
        <el-button text type="primary" class="console-name-edit" @click="emit('open-name')">修改名称</el-button>
        <el-tag
          :type="statusTagType"
          effect="plain"
          :title="statusTagTitle"
        >
          {{ statusTagLabel }}
        </el-tag>
        <el-tag v-if="detail.product?.type_label" effect="plain">
          {{ detail.product.type_label }}
        </el-tag>
      </div>

      <div class="console-meta-line">
        <span>实例 ID: {{ detail.id || '--' }}</span>
        <span>地址: {{ serviceRegion }}</span>
        <span>{{ headerConnectionLabel }}: {{ headerConnectionText }}</span>
      </div>

      <div class="console-remark-line">
        <span>备注:</span>
        <strong :class="{ 'is-empty': !detail.remark }">{{ detail.remark || '点击添加备注' }}</strong>
        <button
          type="button"
          class="console-remark-edit"
          :aria-label="detail.remark ? '编辑备注' : '添加备注'"
          @click="emit('open-remark')"
        >
          <el-icon><EditPen /></el-icon>
        </button>
      </div>
    </div>

    <div class="console-top-card__actions">
      <el-button
        class="console-action-btn"
        type="primary"
        :disabled="!detail.actions?.power || actionLoading"
        @click="emit('power-action', 'on')"
      >
        开机
      </el-button>
      <el-button
        class="console-action-btn"
        :disabled="!detail.actions?.power || actionLoading"
        @click="emit('power-action', 'off')"
      >
        关机
      </el-button>
      <el-button
        class="console-action-btn"
        :disabled="!detail.actions?.power || actionLoading"
        @click="emit('power-action', 'reboot')"
      >
        重启
      </el-button>
      <el-button
        class="console-action-btn console-action-btn--sync"
        plain
        :loading="statusSyncing"
        :disabled="!canSyncStatus || actionLoading"
        @click="emit('sync-status')"
      >
        <el-icon><RefreshRight /></el-icon>
        状态同步
      </el-button>
      <el-dropdown @command="emit('more-command', $event)">
        <el-button class="console-action-btn console-action-btn--more" :disabled="actionLoading">
          更多
          <el-icon class="el-icon--right"><ArrowDown /></el-icon>
        </el-button>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="password" :disabled="!detail.actions?.password_reset">重置密码</el-dropdown-item>
            <el-dropdown-item command="reinstall" :disabled="!detail.actions?.reinstall">重装系统</el-dropdown-item>
            <el-dropdown-item command="hard_off" :disabled="!detail.actions?.power">强制关机</el-dropdown-item>
            <el-dropdown-item command="hard_reboot" :disabled="!detail.actions?.power">强制重启</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </section>

  <section v-if="showExpireBanner" class="console-expire-banner">
    <div class="console-expire-banner__copy">
      <el-icon><WarningFilled /></el-icon>
      <span>实例将于 <strong>{{ detail.expires_at }}</strong> 到期，建议提前续费避免服务中断。</span>
    </div>
    <el-button link type="primary" @click="emit('open-renew')">立即续费</el-button>
  </section>

  <el-alert
    v-if="!canManageConsole"
    type="info"
    :closable="false"
    show-icon
    title="当前实例未接入完整控制能力，页面将以只读模式展示基础信息。"
  />
</template>

<script setup>
import { computed } from 'vue'
import { ArrowDown, EditPen, RefreshRight, WarningFilled } from '@element-plus/icons-vue'
import { resolveToneTagType } from '@/views/client/ServiceConsole/composables/useServiceConsole.js'
import { resolveServiceStatusLabel } from '@/domains/services/useServiceCenter'

const props = defineProps({
  detail: { type: Object, required: true },
  serviceId: { type: Number, required: true },
  serviceRegion: { type: String, default: '--' },
  headerConnectionLabel: { type: String, default: 'IP' },
  headerConnectionText: { type: String, default: '--' },
  detailLoading: { type: Boolean, default: false },
  actionLoading: { type: Boolean, default: false },
  statusSyncing: { type: Boolean, default: false },
  canSyncStatus: { type: Boolean, default: false },
  canManageConsole: { type: Boolean, default: false },
})

const emit = defineEmits(['open-name', 'open-remark', 'open-renew', 'power-action', 'sync-status', 'more-command'])

// 上游异常时，不覆盖主状态标签，只在 tooltip 中提示
const hasUpstreamError = computed(() => Boolean(props.detail?.upstream?.remote_error))
const statusTagType = computed(() => (
  resolveToneTagType(props.detail?.status_tone)
))
const statusTagLabel = computed(() => (
  resolveServiceStatusLabel(props.detail?.status)
))
const statusTagTitle = computed(() => (
  hasUpstreamError.value ? String(props.detail?.upstream?.remote_error || '') : ''
))

const showExpireBanner = computed(() => {
  if (!props.detail?.expires_at) return false

  // 兼容 iOS/Safari，将 "-" 替换为 "/"
  const expireStr = String(props.detail.expires_at).replace(/-/g, '/')
  const expireTime = new Date(expireStr).getTime()
  if (isNaN(expireTime)) return false

  const currentTime = Date.now()
  const diffDays = (expireTime - currentTime) / (1000 * 60 * 60 * 24)

  // 到期前 7 天以内
  return diffDays <= 7
})
</script>
