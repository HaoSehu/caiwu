<template>
  <div class="directory-main">
    <el-card shadow="never" class="selection-card">
      <div class="selection-main">
        <div class="selection-copy">
          <span>当前分类</span>
          <strong>{{ activeTreeNode.label }}</strong>
          <p>{{ activeTreeDescription }}</p>
        </div>

        <div class="selection-stats">
          <article>
            <span>分类接口</span>
            <strong>{{ activeTreeNode.count || totalEndpoints }}</strong>
          </article>

          <article>
            <span>当前结果</span>
            <strong>{{ filteredCount }}</strong>
          </article>
        </div>
      </div>

      <div class="selection-tags">
        <el-tag v-for="tag in activeTreePath" :key="tag" size="small" effect="plain">
          {{ tag }}
        </el-tag>
      </div>
    </el-card>

    <el-card shadow="never" class="filter-card">
      <template #header>
        <div class="panel-header">
          <div>
            <strong>筛选与检索</strong>
            <p>左侧树负责结构化导航，右侧再叠加访问级别、方法、来源和关键字过滤。</p>
          </div>
          <span>Showing {{ filteredCount }} / {{ totalEndpoints }}</span>
        </div>
      </template>

      <div class="filter-grid">
        <el-input
          :model-value="keyword"
          clearable
          placeholder="搜索路径、子类目、权限码、控制器或源码文件"
          @update:model-value="emit('update:keyword', $event)"
        />

        <el-select :model-value="accessFilter" placeholder="访问级别" @update:model-value="emit('update:accessFilter', $event)">
          <el-option label="全部级别" value="all" />
          <el-option
            v-for="option in accessOptions"
            :key="option.value"
            :label="option.label"
            :value="option.value"
          />
        </el-select>

        <el-select :model-value="methodFilter" placeholder="请求方法" @update:model-value="emit('update:methodFilter', $event)">
          <el-option label="全部方法" value="all" />
          <el-option
            v-for="option in methodOptions"
            :key="option"
            :label="option"
            :value="option"
          />
        </el-select>

        <el-select :model-value="sourceFilter" placeholder="前端来源" @update:model-value="emit('update:sourceFilter', $event)">
          <el-option label="全部来源" value="all" />
          <el-option label="管理端前端" value="frontend-admin" />
          <el-option label="客户端前端" value="frontend-client" />
          <el-option label="未发现前端调用" value="untracked" />
        </el-select>
      </div>

      <div class="filter-footer">
        <p>当前分类路径：{{ activeTreePath.join(' / ') }}</p>
        <el-button text @click="emit('reset-filters')">重置筛选</el-button>
      </div>
    </el-card>

    <slot />
  </div>
</template>

<script setup>
defineProps({
  activeTreeNode: { type: Object, required: true },
  activeTreeDescription: { type: String, default: '' },
  activeTreePath: { type: Array, default: () => [] },
  totalEndpoints: { type: Number, default: 0 },
  filteredCount: { type: Number, default: 0 },
  keyword: { type: String, default: '' },
  accessFilter: { type: String, default: 'all' },
  methodFilter: { type: String, default: 'all' },
  sourceFilter: { type: String, default: 'all' },
  accessOptions: { type: Array, default: () => [] },
  methodOptions: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:keyword',
  'update:accessFilter',
  'update:methodFilter',
  'update:sourceFilter',
  'reset-filters',
])
</script>

<style scoped lang="scss">
.directory-main {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}

.selection-card :deep(.el-card__body) {
  display: grid;
  gap: 16px;
}

.selection-main {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: flex-start;
}

.selection-copy span {
  display: block;
  color: $text-color-secondary;
  font-size: 12px;
}

.selection-copy strong {
  display: block;
  margin-top: 10px;
  color: $text-color-primary;
  font-size: 24px;
  font-weight: 700;
}

.selection-copy p {
  margin-top: 10px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.7;
}

.selection-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(120px, 1fr));
  gap: 12px;
}

.selection-stats article {
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: 16px;
  background: $bg-color-soft;
}

.selection-stats span {
  display: block;
  color: $text-color-placeholder;
  font-size: 12px;
}

.selection-stats strong {
  display: block;
  margin-top: 8px;
  color: $text-color-primary;
  font-size: 22px;
  font-weight: 700;
}

.selection-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-card :deep(.el-card__body) {
  display: grid;
  gap: 16px;
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

.filter-grid {
  display: grid;
  grid-template-columns: minmax(260px, 1.8fr) repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.filter-footer {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
}

.filter-footer p {
  color: $text-color-secondary;
  font-size: 13px;
}

@media (max-width: 1360px) {
  .filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 980px) {
  .selection-main,
  .filter-grid,
  .filter-footer,
  .panel-header {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }

  .selection-stats {
    width: 100%;
  }
}

@media (max-width: 640px) {
  .selection-stats,
  .filter-grid {
    grid-template-columns: 1fr;
  }
}
</style>
