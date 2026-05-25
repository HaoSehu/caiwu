<template>
  <div class="request-error-block">
    <div class="error-icon">
      <el-icon :size="iconSize">
        <WarningFilled />
      </el-icon>
    </div>
    <p class="error-message">{{ message }}</p>
    <p v-if="detail" class="error-detail">{{ detail }}</p>
    <div v-if="showRetry" class="error-actions">
      <el-button type="primary" :loading="retrying" @click="handleRetry">
        {{ retryLabel }}
      </el-button>
      <el-button v-if="showSecondary" @click="handleSecondary">
        {{ secondaryLabel }}
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { WarningFilled } from '@element-plus/icons-vue'

const props = defineProps({
  /** 主错误消息 */
  message: { type: String, default: '数据加载失败' },
  /** 辅助详情 */
  detail: { type: String, default: '' },
  /** 是否展示重试按钮 */
  showRetry: { type: Boolean, default: true },
  /** 重试按钮文案 */
  retryLabel: { type: String, default: '重试' },
  /** 是否展示次要按钮 */
  showSecondary: { type: Boolean, default: false },
  /** 次要按钮文案 */
  secondaryLabel: { type: String, default: '返回' },
  /** 图标尺寸 */
  iconSize: { type: [String, Number], default: 48 },
})

const emit = defineEmits(['retry', 'secondary'])

const retrying = ref(false)

async function handleRetry() {
  retrying.value = true
  try {
    await emit('retry')
  } finally {
    retrying.value = false
  }
}

function handleSecondary() {
  emit('secondary')
}
</script>

<style lang="scss" scoped>
.request-error-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  min-height: 200px;
}

.error-icon {
  color: $color-warning;
  margin-bottom: 16px;
  opacity: 0.8;
}

.error-message {
  margin: 0 0 8px;
  font-size: 15px;
  font-weight: 500;
  color: $text-color-primary;
}

.error-detail {
  margin: 0 0 20px;
  font-size: 13px;
  color: $text-color-placeholder;
  max-width: 400px;
  line-height: 1.6;
}

.error-actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}
</style>
