<template>
  <section class="console-panel console-panel--vnc">
    <div class="console-panel__header">
      <h3>VNC 控制台</h3>
      <div class="console-toolbar">
        <el-button
          v-if="vncUrl"
          size="small"
          :loading="actionLoading"
          @click="emit('refresh-vnc')"
        >
          <el-icon><RefreshRight /></el-icon>
          刷新连接
        </el-button>
        <el-button
          v-if="vncUrl"
          size="small"
          :loading="vncWindowLoading"
          @click="emit('open-new-window')"
        >
          新窗口打开
        </el-button>
      </div>
    </div>
    <div class="vnc-iframe-wrap">
      <template v-if="vncUrl">
        <iframe
          :key="vncUrl"
          :src="vncUrl"
          class="vnc-iframe"
          allowfullscreen
        />
      </template>
      <div v-else class="vnc-placeholder">
        <el-icon class="vnc-placeholder__icon"><Monitor /></el-icon>
        <p>点击下方按钮获取并打开 VNC 控制台</p>
        <el-button
          type="primary"
          :disabled="!canManageConsole"
          :loading="actionLoading"
          @click="emit('connect-vnc')"
        >
          连接 VNC
        </el-button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Monitor, RefreshRight } from '@element-plus/icons-vue'

defineProps({
  vncUrl: { type: String, default: '' },
  actionLoading: { type: Boolean, default: false },
  vncWindowLoading: { type: Boolean, default: false },
  canManageConsole: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh-vnc', 'open-new-window', 'connect-vnc'])
</script>
