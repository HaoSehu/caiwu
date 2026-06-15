<template>
  <section class="tools-page">
    <header class="client-page-heading">
      <h1>管理工具</h1>
    </header>

    <t-card class="tools-card" :bordered="false">
      <template #title>管理工具</template>
      <t-tabs v-model="activeTab">
        <t-tab-panel value="query" label="黑洞查询">
          <div class="tool-form-grid">
            <t-input v-model="queryForm.ip" placeholder="请输入需要查询的 IP" />
            <t-button theme="primary" :loading="querying" @click="handleQuery">开始查询</t-button>
          </div>
          <pre class="tool-output">{{ queryResult }}</pre>
        </t-tab-panel>
        <t-tab-panel value="ningbo" label="宁波白名单">
          <div class="tool-form-grid two">
            <t-input v-model="ningboForm.ip" placeholder="IP" />
            <t-input v-model="ningboForm.domain" placeholder="域名" />
          </div>
          <div class="tool-actions">
            <t-button theme="primary" :loading="submitting" @click="handleNingboWhitelist">提交白名单</t-button>
          </div>
        </t-tab-panel>
        <t-tab-panel value="layer4" label="十堰四层规则">
          <div class="tool-form-grid two">
            <t-input v-model="layer4Form.ip" placeholder="IP" />
            <t-select v-model="layer4Form.mode">
              <t-option label="模式 1" :value="1" />
              <t-option label="模式 2" :value="2" />
            </t-select>
          </div>
          <div class="tool-actions">
            <t-button theme="primary" :loading="submitting" @click="handleAddLayer4">新增规则</t-button>
          </div>
        </t-tab-panel>
        <t-tab-panel value="layer7" label="十堰七层开关">
          <div class="tool-form-grid three">
            <t-input v-model="layer7Form.ip" placeholder="IP" />
            <t-input-number v-model="layer7Form.rule_id" :min="1" />
            <t-switch v-model="layer7Form.enabled" />
          </div>
          <div class="tool-actions">
            <t-button theme="primary" :loading="submitting" @click="handleToggleLayer7">提交开关</t-button>
          </div>
        </t-tab-panel>
      </t-tabs>
    </t-card>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';

import { useTools } from '@/domains/tools/useTools';

const activeTab = ref('query');
const {
  querying,
  submitting,
  queryResult,
  queryForm,
  ningboForm,
  layer4Form,
  layer7Form,
  handleQuery,
  handleNingboWhitelist,
  handleAddLayer4,
  handleToggleLayer7,
} = useTools();
</script>

<style scoped lang="less">
.tools-page {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l);
}

.client-page-heading {
  h1 {
    margin: 0;
    color: var(--td-text-color-primary);
    font: var(--td-font-title-large);
  }
}

.tools-card {
  background: var(--td-bg-color-container);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
  box-shadow: var(--td-shadow-1);
}

.tool-form-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(8rem, 10rem);
  gap: var(--td-comp-margin-m);
  margin-bottom: var(--td-comp-margin-m);

  &.two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  &.three {
    grid-template-columns: minmax(0, 1.2fr) minmax(9rem, 12rem) minmax(7rem, 9rem);
  }
}

.tool-actions {
  display: flex;
  justify-content: flex-end;
}

.tool-output {
  min-height: 14rem;
  margin: 0;
  padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-m);
  overflow: auto;
  color: var(--td-text-color-anti);
  white-space: pre-wrap;
  word-break: break-word;
  background: var(--td-gray-color-13);
  border: thin solid var(--td-border-color);
  border-radius: var(--td-radius-medium);
}

@media (max-width: 48rem) {
  .tools-page {
    padding: var(--td-comp-paddingTB-m) var(--td-comp-paddingLR-s);
  }

  .tool-form-grid,
  .tool-form-grid.two,
  .tool-form-grid.three {
    grid-template-columns: 1fr;
  }

  .tool-actions {
    justify-content: flex-start;
  }
}
</style>
