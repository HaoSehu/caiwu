<template>
  <div class="record-detail-page">
    <!-- 顶部操作条 -->
    <div v-if="showToolbar" class="record-detail-toolbar">
      <t-button
        v-if="showBack"
        variant="text"
        theme="default"
        class="record-detail-toolbar__back"
        @click="emit('back')"
      >
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

    <!-- 首次进入的三态统一复用 shared 的 DataState（与用户控制台同一套加载/空态表达）；
         已加载完成后的刷新仍用 t-loading 原位覆盖，避免整页被替换（审查条目 A5）。
         首屏加载优先出骨架（与摘要卡同构），比居中 spinner 更少跳版；
         错误态与真实空态仍交给 DataState 区分文案（审查条目 P2-12） -->
    <div v-if="!ready && loading && !error" class="record-detail-skeleton" aria-busy="true">
      <t-skeleton :row-col="summarySkeletonRowCol" animation="gradient" class="record-detail-skeleton__summary" />
      <div class="record-detail-skeleton__metrics">
        <t-skeleton
          v-for="slot in 3"
          :key="slot"
          :row-col="metricSkeletonRowCol"
          animation="gradient"
          class="record-detail-skeleton__metric"
        />
      </div>
      <t-skeleton :row-col="bodySkeletonRowCol" animation="gradient" class="record-detail-skeleton__body" />
    </div>
    <data-state
      v-else-if="!ready"
      :loading="loading"
      :error="error"
      :error-text="errorText"
      :empty="!loading && !error"
      :description="emptyText"
      @retry="emit('retry')"
    />
    <t-loading v-else :loading="loading" size="small">
      <div class="record-detail-shell">
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
            <div v-for="metric in visibleMetrics" :key="metric.label" class="summary-metric-card">
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
    </t-loading>
  </div>
</template>
<script setup lang="ts">
import DataState from '@caiwu/shared/user-v3/components/DataState.vue';
import { ChevronLeftIcon, FileCopyIcon, RefreshIcon } from 'tdesign-icons-vue-next';
import type { SkeletonRowCol } from 'tdesign-vue-next';
import { computed } from 'vue';

import { copyToClipboard } from '@/utils/format';

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
    /** 首次加载失败：与 loading / empty 三态互斥，交由 DataState 呈现失败与重试 */
    error?: boolean;
    errorText?: string;
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
    error: false,
    errorText: '',
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
  (event: 'retry'): void;
  (event: 'update:activeTab', value: string): void;
}>();

/* 骨架尺寸对齐真实摘要卡：标题行、指标卡、正文块，避免加载完成时版式跳动 */
const summarySkeletonRowCol: SkeletonRowCol = [
  [{ width: '96px', height: '12px' }],
  [{ width: '220px', height: '24px', margin: '12px 0 0' }],
  [{ width: '320px', height: '12px', margin: '12px 0 0' }],
];
const metricSkeletonRowCol: SkeletonRowCol = [
  [{ width: '56px', height: '12px' }],
  [{ width: '88px', height: '22px', margin: '8px 0 0' }],
];
const bodySkeletonRowCol: SkeletonRowCol = [[{ width: '100%', height: '220px' }]];

const visibleMetrics = computed(() => props.metrics.filter((item) => item.show !== false && item.label !== '状态'));
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

function copyText(text: string) {
  void copyToClipboard(text);
}
</script>
<style lang="less" scoped>
.record-detail-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-l);
}

.record-detail-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--td-comp-margin-m);
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
    gap: var(--td-comp-margin-s);
  }
}

.record-detail-shell {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-l);
}

/* 首屏骨架：与 .record-detail-summary 同一套留白与圆角，加载完成不跳版 */
.record-detail-skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-l);
  padding: var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-xl);
  border-radius: var(--td-radius-large);
  background: var(--td-bg-color-container);
  box-shadow: var(--td-shadow-1);
}

.record-detail-skeleton__summary {
  display: flex;
}

.record-detail-skeleton__metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--td-comp-margin-m);
}

.record-detail-skeleton__metric {
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-secondarycontainer);
}

.record-detail-skeleton__body {
  display: flex;
}

@media (max-width: 767.98px) {
  .record-detail-skeleton {
    padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
  }

  .record-detail-skeleton__metrics {
    grid-template-columns: minmax(0, 1fr);
  }
}

/* 顶部概览大卡片 */
.record-detail-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--td-comp-margin-xxl);
  padding: 20px 24px;
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-large);
  background: var(--td-bg-color-container);
  box-shadow: var(--td-shadow-1);

  &__identity {
    display: flex;
    flex-direction: column;
    gap: var(--td-comp-margin-s);
    min-width: 0;
    flex: 1;

    .identity-eyebrow {
      font-size: var(--td-font-size-body-small);
      color: var(--td-text-color-placeholder);
      letter-spacing: 0.5px;
    }

    .identity-headline {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;

      .identity-title {
        font-size: var(--td-font-size-size-6, 20px);
        font-weight: 600;
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
        font-size: var(--td-font-size-size-3, 14px);
        font-weight: 500;
        padding: 2px 10px;
        border-radius: var(--td-radius-small);
      }
    }

    .identity-sub-row {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;

      .identity-description {
        font-size: var(--td-font-size-body-medium);
        color: var(--td-text-color-secondary);
      }
    }
  }

  &__metrics {
    display: flex;
    align-items: stretch;
    gap: var(--td-comp-margin-m);
    flex-shrink: 0;

    .summary-metric-card {
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: var(--td-comp-margin-xs);
      padding: 10px 18px;
      background: var(--td-bg-color-secondarycontainer);
      border-radius: var(--td-radius-medium);
      min-width: 120px;
      text-align: right;

      .metric-label {
        font-size: var(--td-font-size-body-small);
        color: var(--td-text-color-placeholder);
      }

      .metric-value {
        font-size: var(--td-font-size-size-6, 20px);
        font-weight: 600;
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
  gap: var(--td-comp-margin-s);
}

/* 主体卡片 */
.record-detail-body {
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-large);
  background: var(--td-bg-color-container);
  box-shadow: var(--td-shadow-1);
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

@media (max-width: 767.98px) {
  .record-detail-summary {
    padding: 16px;

    &__identity {
      .identity-headline .identity-title {
        font-size: var(--td-font-size-size-6, 20px);
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
          font-size: var(--td-font-size-size-4, 16px);
        }
      }
    }
  }

  .record-detail-body__content {
    padding: 16px;
  }
}
</style>
