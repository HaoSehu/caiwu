<template>
  <aside class="directory-sidebar">
    <el-card shadow="never" class="sidebar-card">
      <template #header>
        <div class="panel-header">
          <div>
            <strong>接口分类树</strong>
            <p>按分端、模块和子类目逐层收起，优先解决"接口太散、找不到"的问题。</p>
          </div>
          <span>{{ subgroupTotal }} 类</span>
        </div>
      </template>

      <div class="sidebar-summary">
        <button
          type="button"
          class="tree-root-button"
          :class="{ active: selectedTreeKey === 'all' }"
          @click="emit('select-node', { key: 'all' })"
        >
          <span>全部接口</span>
          <strong>{{ totalEndpoints }}</strong>
        </button>

        <div class="tree-summary-grid">
          <article>
            <span>模块</span>
            <strong>{{ meta.moduleCount }}</strong>
          </article>

          <article>
            <span>公开</span>
            <strong>{{ accessSummary.public }}</strong>
          </article>

          <article>
            <span>登录</span>
            <strong>{{ accessSummary.auth }}</strong>
          </article>

          <article>
            <span>权限</span>
            <strong>{{ accessSummary.permission }}</strong>
          </article>
        </div>
      </div>

      <el-scrollbar class="tree-scroll">
        <el-tree
          ref="treeRef"
          :data="treeData"
          node-key="key"
          :props="treeProps"
          :current-node-key="selectedTreeKey"
          :default-expanded-keys="defaultExpandedKeys"
          highlight-current
          expand-on-click-node
          class="api-tree"
          @node-click="emit('node-click', $event)"
        >
          <template #default="{ data }">
            <div class="tree-node" :class="`tree-node-${data.type}`">
              <div class="tree-node-copy">
                <strong>{{ data.label }}</strong>
                <small v-if="data.secondary">{{ data.secondary }}</small>
              </div>
              <em>{{ data.count }}</em>
            </div>
          </template>
        </el-tree>
      </el-scrollbar>
    </el-card>
  </aside>
</template>

<script setup>
defineProps({
  treeRef: { type: Object, default: null },
  treeData: { type: Array, default: () => [] },
  treeProps: { type: Object, required: true },
  selectedTreeKey: { type: String, default: 'all' },
  defaultExpandedKeys: { type: Array, default: () => [] },
  totalEndpoints: { type: Number, default: 0 },
  subgroupTotal: { type: Number, default: 0 },
  meta: { type: Object, default: () => ({}) },
  accessSummary: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['node-click', 'select-node'])
</script>

<style scoped lang="scss">
.directory-sidebar {
  position: sticky;
  top: 0;
}

.sidebar-card :deep(.el-card__body) {
  display: grid;
  gap: 16px;
  padding-top: 16px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.panel-header strong {
  color: $text-color-primary;
  font-size: 16px;
}

.panel-header p {
  margin-top: 6px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.7;
}

.panel-header span {
  color: $text-color-placeholder;
  font-size: 12px;
  white-space: nowrap;
}

.sidebar-summary {
  display: grid;
  gap: 12px;
}

.tree-root-button {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  width: 100%;
  min-height: 52px;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: 16px;
  background: linear-gradient(180deg, #ffffff, $bg-color-soft);
  cursor: pointer;
  text-align: left;
  transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}

.tree-root-button:hover {
  transform: translateY(-1px);
  border-color: $color-primary-border;
}

.tree-root-button.active {
  border-color: $color-primary-border;
  background: linear-gradient(180deg, rgba($color-primary, 0.08), rgba(255, 255, 255, 0.98));
  box-shadow: 0 8px 18px rgba($color-primary, 0.08);
}

.tree-root-button span {
  color: $text-color-secondary;
  font-size: 13px;
  font-weight: 600;
}

.tree-root-button strong {
  color: $text-color-primary;
  font-size: 20px;
  font-weight: 700;
}

.tree-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.tree-summary-grid article {
  padding: 12px 14px;
  border: 1px solid $divider-color;
  border-radius: 14px;
  background: $bg-color-soft;
}

.tree-summary-grid span {
  display: block;
  color: $text-color-placeholder;
  font-size: 12px;
}

.tree-summary-grid strong {
  display: block;
  margin-top: 8px;
  color: $text-color-primary;
  font-size: 18px;
  font-weight: 700;
}

.tree-scroll {
  max-height: calc(100vh - 380px);
  padding-right: 6px;
}

.api-tree {
  background: transparent;
}

.api-tree :deep(.el-tree-node__content) {
  min-height: 44px;
  margin-bottom: 4px;
  border-radius: 10px;
}

.api-tree :deep(.el-tree-node__content:hover) {
  background: $bg-color-hover;
}

.api-tree :deep(.el-tree-node.is-current > .el-tree-node__content) {
  background: $color-primary-soft;
  box-shadow: inset 0 0 0 1px $color-primary-border;
}

.tree-node {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  width: 100%;
  min-width: 0;
}

.tree-node-copy {
  min-width: 0;
}

.tree-node-copy strong,
.tree-node-copy small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tree-node-copy strong {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
}

.tree-node-copy small {
  margin-top: 4px;
  color: $text-color-placeholder;
  font-size: 11px;
}

.tree-node em {
  flex-shrink: 0;
  min-width: 24px;
  padding: 2px 6px;
  border-radius: 999px;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 11px;
  font-style: normal;
  font-weight: 700;
  text-align: center;
}

@media (max-width: 1180px) {
  .directory-sidebar {
    position: static;
  }

  .tree-scroll {
    max-height: 420px;
  }
}

@media (max-width: 640px) {
  .tree-summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
