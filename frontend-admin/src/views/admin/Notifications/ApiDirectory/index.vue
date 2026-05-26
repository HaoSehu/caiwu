<template>
  <div class="api-directory-page admin-page">
    <section class="api-page-topbar">
      <div class="api-page-copy">
        <h2>API 接口页</h2>
        <p>现在按"分端 -> 模块 -> 子类目"组织接口目录，左侧分类树负责快速定位，右侧明细表负责检索、筛选和复制。</p>
      </div>

      <div class="api-page-toolbar">
        <div class="toolbar-meta">
          <article class="toolbar-meta-item">
            <span>数据来源</span>
            <strong>{{ meta.dataSource }}</strong>
          </article>

          <article class="toolbar-meta-item">
            <span>生成时间</span>
            <strong>{{ meta.generatedAt }}</strong>
          </article>

          <article class="toolbar-meta-item">
            <span>基础地址</span>
            <strong>{{ meta.baseURL }}</strong>
          </article>
        </div>
      </div>
    </section>

    <section class="directory-layout">
      <ApiCategoryPanel
        :tree-ref="treeRef"
        :tree-data="treeData"
        :tree-props="treeProps"
        :selected-tree-key="selectedTreeKey"
        :default-expanded-keys="defaultExpandedKeys"
        :total-endpoints="totalEndpoints"
        :subgroup-total="subgroupTotal"
        :meta="meta"
        :access-summary="accessSummary"
        @node-click="handleTreeNodeClick"
        @select-node="selectTreeNode"
      />

      <ApiEndpointDetail
        :active-tree-node="activeTreeNode"
        :active-tree-description="activeTreeDescription"
        :active-tree-path="activeTreePath"
        :total-endpoints="totalEndpoints"
        :filtered-count="filteredItems.length"
        :keyword="keyword"
        :access-filter="accessFilter"
        :method-filter="methodFilter"
        :source-filter="sourceFilter"
        :access-options="accessOptions"
        :method-options="methodOptions"
        @update:keyword="keyword = $event"
        @update:accessFilter="accessFilter = $event"
        @update:methodFilter="methodFilter = $event"
        @update:sourceFilter="sourceFilter = $event"
        @reset-filters="resetFilters"
      >
        <ApiEndpointList
          :filtered-items="filteredItems"
          :method-tag-type="methodTagType"
          :access-tag-type="accessTagType"
          @copy="(path) => copyText(path, '接口路径已复制')"
        />
      </ApiEndpointDetail>
    </section>
  </div>
</template>

<script setup>
import { useApiDirectory } from './composables/useApiDirectory'
import ApiCategoryPanel from './components/ApiCategoryPanel.vue'
import ApiEndpointDetail from './components/ApiEndpointDetail.vue'
import ApiEndpointList from './components/ApiEndpointList.vue'

const {
  treeRef,
  selectedTreeKey,
  keyword,
  accessFilter,
  methodFilter,
  sourceFilter,
  meta,
  treeProps,
  totalEndpoints,
  subgroupTotal,
  accessSummary,
  accessOptions,
  methodOptions,
  treeData,
  defaultExpandedKeys,
  activeTreeNode,
  activeTreePath,
  activeTreeDescription,
  filteredItems,
  handleTreeNodeClick,
  selectTreeNode,
  resetFilters,
  methodTagType,
  accessTagType,
  copyText,
  copyFilteredEndpoints,
} = useApiDirectory()
</script>

<style lang="scss" scoped>
.api-directory-page {
  position: relative;
}

.api-page-topbar {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: flex-start;
  padding-bottom: 16px;
  border-bottom: 1px solid $divider-color;
}

.api-page-copy {
  max-width: 760px;
}

.api-page-copy h2 {
  color: $text-color-primary;
  font-size: $font-size-h1;
  font-weight: 600;
  line-height: 1.2;
}

.api-page-copy p {
  margin-top: 8px;
  color: $text-color-secondary;
  font-size: 14px;
  line-height: 1.7;
}

.api-page-toolbar {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12px;
}

.toolbar-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 12px;
}

.toolbar-meta-item {
  min-width: 140px;
  padding-left: 12px;
  border-left: 1px solid $divider-color;
  text-align: right;
}

.toolbar-meta-item span {
  display: block;
  color: $text-color-placeholder;
  font-size: 12px;
}

.toolbar-meta-item strong {
  display: block;
  margin-top: 6px;
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.5;
}

.page-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.directory-layout {
  display: grid;
  grid-template-columns: 300px minmax(0, 1fr);
  gap: 18px;
  align-items: start;
}

@media (max-width: 1360px) {
  .directory-layout {
    grid-template-columns: 280px minmax(0, 1fr);
  }
}

@media (max-width: 1180px) {
  .directory-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 980px) {
  .api-page-topbar,
  .api-page-toolbar,
  .toolbar-meta,
  .toolbar-meta-item,
  .page-actions {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }

  .toolbar-meta-item {
    min-width: 0;
    padding-left: 0;
    border-left: none;
    text-align: left;
  }
}
</style>
