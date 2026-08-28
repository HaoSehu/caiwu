<template>
  <div class="data-state">
    <LoadingState v-if="loading" text="加载中" compact />
    <t-empty v-else-if="error" :description="errorText || '加载失败，请稍后重试'">
      <template #action>
        <t-button theme="primary" variant="outline" @click="emit('retry')">重新加载</t-button>
      </template>
    </t-empty>
    <t-empty v-else-if="empty" :description="description || '暂无数据'">
      <template v-if="$slots['empty-action']" #action>
        <slot name="empty-action" />
      </template>
    </t-empty>
    <slot v-else />
  </div>
</template>

<script setup lang="ts">
import LoadingState from './LoadingState.vue';

defineProps<{
  loading?: boolean;
  empty?: boolean;
  error?: boolean;
  errorText?: string;
  description?: string;
}>();

const emit = defineEmits<{
  (e: 'retry'): void;
}>();
</script>
