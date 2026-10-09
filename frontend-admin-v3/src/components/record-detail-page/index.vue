<template>
  <div class="record-detail-page">
    <!-- 顶部操作条 -->
    <div v-if="showToolbar" class="record-detail-toolbar">
      <t-button v-if="showBack" variant="text" theme="default" class="record-detail-toolbar__back" @click="emit('back')">
        <template #icon><chevron-left-icon /></template>
        {{ backText }}
      </t-button>
      <div v-else />
      <div class="record-detail-toolbar__actions">
        <slot name="toolbar-actions" />
        <t-button v-if="showRefresh" variant="outline" :loading="loading" @click="emit('refresh')">
          <template #icon><refresh-icon /></template>
          刷新
        </t-button>
      </div>
    </div>

    <t-loading :loading="loading" size="small">
      <div v-if="ready" class="record-detail-shell">
        <!-- 顶部核心摘要卡片 -->
        <section class="record-detail-summary">
          <div class="record-detail-summary__identity">
            <span v-if="eyebrow" class="identity-eyebrow">{{ eyebrow }}</span>
            <div class="identity-headline">
              <strong class="identity-title">{{ title || '-' }}</strong>
              <t-tooltip content="复制编号" placement="top">
                <t-button
                  v-if="title"
                  variant="text"
                  shape="square"
                  size="small"
                  class="copy-btn"
                  @click="copyText(title)"
                >
                  <template #icon><file-copy-icon /></template>
                </t-button>
              </t-tooltip>
              <t-tag
                v-if="statusLabel"
                :theme="resolveTagTheme(statusTheme)"
                variant="light"
                class="identity-status-tag"
              >
                {{ statusLabel }}
              </t-tag>
            </div>
            <div class="identity-sub-row">
              <span v-if="description" class="identity-description">{{ description }}</span>
              <!-- 关联快速动作药丸 -->
              <div v-if="$slots.relations" class="record-detail-relations">
                <slot name="relations" />
              </div>
            </div>
          </div>

          <!-- 右侧关键指标卡组 -->
          <div v-if="visibleMetrics.length" class="record-detail-summary__metrics">
            <div
              v-for="metric in visibleMetrics"
              :key="metric.label"
              class="summary-metric-card"
            >
              <span class="metric-label">{{ metric.label }}</span>
              <strong class="metric-value" :class="{ 'is-primary': metric.primary }">
                {{ displayValue(metric.value) }}
              </strong>
            </div>
          </div>
        </section>

        <!-- 内容主体卡片 -->
        <section class="record-detail-body">
          <t-tabs
            v-if="visibleTabs.length > 1"
            :value="activeTab"
            class="record-detail-tabs"
            @change="(value: string | number) => emit('update:activeTab', String(value))"
          >
            <t-tab-panel v-for="tab in visibleTabs" :key="tab.value" :value="tab.value" :label="tab.label" />
          </t-tabs>
          <div class="record-detail-body__content">
            <slot :name="`tab-${currentTab}`" />
          </div>
        </section>
      </div>
      <t-empty v-else-if="!loading" :description="emptyText" />
    </t-loading>
  </div>
</template>
<script setup lang="ts">
import { ChevronLeftIcon, FileCopyIcon, RefreshIcon } from 'tdesign-icons-vue-next';
import { MessagePlugin } from 'tdesign-vue-next';
import { computed } from 'vue';

export interface RecordDetailMetric {
  label: string;
  value?: string | number | null;
  primary?: boolean;
  show?: boolean;
}

export interface RecordDetailTab {
  value: string;
  label: string;
  show?: boolean;
}

const props = withDefaults(
  defineProps<{
    loading?: boolean;
    ready?: boolean;
    showBack?: boolean;
    showRefresh?: boolean;
    showToolbar?: boolean;
    backText?: string;
    eyebrow?: string;
    title?: string;
    description?: string;
    statusLabel?: string;
    statusTheme?: string;
    metrics?: RecordDetailMetric[];
    tabs?: RecordDetailTab[];
    activeTab?: string;
    emptyText?: string;
  }>(),
  {
    loading: false,
    ready: false,
    showBack: true,
    showRefresh: true,
    showToolbar: true,
    backText: '返回',
    eyebrow: '详情',
    title: '',
    description: '',
    statusLabel: '',
    statusTheme: 'default',
    metrics: () => [],
    tabs: () => [{ value: 'basic', label: '基本信息' }],
    activeTab: 'basic',
    emptyText: '暂无详情',
  },
);

const emit = defineEmits<{
  (event: 'back'): void;
  (event: 'refresh'): void;
  (event: 'update:activeTab', value: string): void;
}>();

const visibleMetrics = computed(() =>
  props.metrics.filter((item) => item.show !== false && item.label !== '状态'),
);
const visibleTabs = computed(() => props.tabs.filter((item) => item.show !== false));
const currentTab = computed(() => props.activeTab || visibleTabs.value[0]?.value || 'basic');

function displayValue(value: string | number | null | undefined) {
  if (value === null || value === undefined || value === '') return '-';
  return String(value);
}

function resolveTagTheme(theme?: string): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  if (!theme || theme === 'default' || theme === 'info') return 'default';
  if (theme === 'blue') return 'primary';
  return theme as 'primary' | 'success' | 'warning' | 'danger';
}

async function copyText(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    MessagePlugin.success('已复制到剪贴板');
  } catch {
    MessagePlugin.error('复制失败，请手动选择复制');
  }
}
</script>
<style lang="less" scoped>
.record-detail-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.record-detail-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 36px;

  &__back {
    font-weight: 500;
    color: var(--td-text-color-secondary);
    padding: 0 4px;

    &:hover {
      color: var(--td-brand-color);
    }
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

.record-detail-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* 顶部概览大卡片 */
.record-detail-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 20px 24px;
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-large, 8px);
  background: var(--td-bg-color-container);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);

  &__identity {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
    flex: 1;

    .identity-eyebrow {
      font-size: var(--td-font-size-body-small, 12px);
      color: var(--td-text-color-placeholder);
      letter-spacing: 0.5px;
    }

    .identity-headline {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;

      .identity-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--td-text-color-primary);
        font-family: var(--td-font-family);
        line-height: 1.25;
      }

      .copy-btn {
        color: var(--td-text-color-placeholder);
        &:hover {
          color: var(--td-brand-color);
        }
      }

      .identity-status-tag {
        font-size: 13px;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 4px;
      }
    }

    .identity-sub-row {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;

      .identity-description {
        font-size: var(--td-font-size-body-medium, 13px);
        color: var(--td-text-color-secondary);
      }
    }
  }

  &__metrics {
    display: flex;
    align-items: stretch;
    gap: 12px;
    flex-shrink: 0;

    .summary-metric-card {
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 4px;
      padding: 10px 18px;
      background: var(--td-bg-color-secondarycontainer);
      border-radius: var(--td-radius-medium, 6px);
      min-width: 120px;
      text-align: right;

      .metric-label {
        font-size: var(--td-font-size-body-small, 12px);
        color: var(--td-text-color-placeholder);
      }

      .metric-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--td-text-color-primary);
        font-variant-numeric: tabular-nums;
        line-height: 1.3;

        &.is-primary {
          color: var(--td-brand-color);
        }
      }
    }
  }
}

.record-detail-relations {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* 主体卡片 */
.record-detail-body {
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-large, 8px);
  background: var(--td-bg-color-container);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  overflow: hidden;

  .record-detail-tabs {
    border-bottom: 1px solid var(--td-component-border);
    padding: 0 16px;
    background: var(--td-bg-color-container);
  }

  &__content {
    padding: 20px 24px;
  }
}

@media (max-width: 1024px) {
  .record-detail-summary {
    flex-direction: column;
    align-items: stretch;

    &__metrics {
      justify-content: flex-start;
      overflow-x: auto;
      padding-bottom: 4px;
    }
  }
}

@media (max-width: 768px) {
  .record-detail-summary {
    padding: 16px;

    &__identity {
      .identity-headline .identity-title {
        font-size: 18px;
      }
    }

    &__metrics {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      width: 100%;

      .summary-metric-card {
        text-align: left;
        min-width: 0;
        padding: 8px 12px;

        .metric-value {
          font-size: 16px;
        }
      }
    }
  }

  .record-detail-body__content {
    padding: 16px;
  }
}
</style>
