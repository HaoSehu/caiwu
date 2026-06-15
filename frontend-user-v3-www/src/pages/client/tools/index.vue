<template>
  <div class="client-page tools-page">
    <section class="panel-card">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="黑洞查询" name="query">
          <div class="tool-form-grid">
            <el-input v-model="queryForm.ip" placeholder="请输入需要查询的 IP" />
            <el-button type="primary" :loading="querying" @click="handleQuery">开始查询</el-button>
          </div>
          <pre class="tool-output">{{ queryResult }}</pre>
        </el-tab-pane>

        <el-tab-pane label="宁波白名单" name="ningbo">
          <div class="tool-form-grid tool-form-grid--two">
            <el-input v-model="ningboForm.ip" placeholder="IP" />
            <el-input v-model="ningboForm.domain" placeholder="域名" />
          </div>
          <div class="tool-actions">
            <el-button type="primary" :loading="submitting" @click="handleNingboWhitelist">提交白名单</el-button>
          </div>
        </el-tab-pane>

        <el-tab-pane label="十堰四层规则" name="layer4">
          <div class="tool-form-grid tool-form-grid--two">
            <el-input v-model="layer4Form.ip" placeholder="IP" />
            <el-select v-model="layer4Form.mode">
              <el-option label="模式 1" :value="1" />
              <el-option label="模式 2" :value="2" />
            </el-select>
          </div>
          <div class="tool-actions">
            <el-button type="primary" :loading="submitting" @click="handleAddLayer4">新增规则</el-button>
          </div>
        </el-tab-pane>

        <el-tab-pane label="十堰七层开关" name="layer7">
          <div class="tool-form-grid tool-form-grid--three">
            <el-input v-model="layer7Form.ip" placeholder="IP" />
            <el-input-number v-model="layer7Form.rule_id" :min="1" />
            <el-switch v-model="layer7Form.enabled" active-text="启用" inactive-text="关闭" />
          </div>
          <div class="tool-actions">
            <el-button type="primary" :loading="submitting" @click="handleToggleLayer7">提交开关</el-button>
          </div>
        </el-tab-pane>
      </el-tabs>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useTools } from '@/composables/useTools'

const activeTab = ref('query')

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
} = useTools()
</script>

<style scoped lang="scss">
.panel-card {
  padding: 20px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}

.tool-form-grid {
  display: grid;
  grid-template-columns: 1fr 160px;
  gap: 16px;
  margin-bottom: 16px;
}

.tool-form-grid--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.tool-form-grid--three {
  grid-template-columns: 1.2fr 180px 180px;
}

.tool-actions {
  display: flex;
  justify-content: flex-end;
}

.tool-output {
  min-height: 220px;
  margin: 0;
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $sm-border-radius;
  background: #0f172a;
  color: #e2e8f0;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 767px) {
  .tool-form-grid,
  .tool-form-grid--two,
  .tool-form-grid--three {
    grid-template-columns: 1fr;
  }

  .tool-actions {
    justify-content: flex-start;
  }
}
</style>
