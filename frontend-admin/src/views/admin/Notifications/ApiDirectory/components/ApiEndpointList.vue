<template>
  <el-card shadow="never" class="directory-table-card">
    <template #header>
      <div class="panel-header">
        <div>
          <strong>接口明细</strong>
          <p>左侧点到哪一类，右侧就只显示该类接口；复制按钮默认复制后端真实注册路径。</p>
        </div>
        <span>{{ filteredItems.length }} Routes</span>
      </div>
    </template>

    <el-table :data="filteredItems" stripe row-key="id" class="directory-table">
      <template #empty>
        <div class="table-empty">
          <strong>没有匹配到接口</strong>
          <p>可以切换左侧分类树，或者清空右侧筛选条件后再试。</p>
        </div>
      </template>

      <el-table-column label="所属分类" min-width="220">
        <template #default="{ row }">
          <div class="category-cell">
            <div class="category-tags">
              <el-tag size="small" effect="light">{{ row.scopeLabel }}</el-tag>
              <el-tag size="small" effect="plain" type="success">{{ row.moduleLabel }}</el-tag>
            </div>
            <strong>{{ row.subgroupLabel }}</strong>
            <small>{{ row.module }} / {{ row.subgroupKey }}</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="方法" width="120" align="center">
        <template #default="{ row }">
          <el-tag :type="methodTagType(row.methods)" effect="dark">{{ row.method }}</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="路径" min-width="360">
        <template #default="{ row }">
          <div class="path-cell">
            <strong>{{ row.callPath }}</strong>
            <small>{{ row.backendPath }}</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="访问控制" min-width="220">
        <template #default="{ row }">
          <div class="access-cell">
            <el-tag size="small" effect="light" :type="accessTagType(row.access)">
              {{ row.accessLabel }}
            </el-tag>

            <el-tag
              v-for="guard in row.guards"
              :key="guard"
              size="small"
              effect="plain"
              type="info"
            >
              {{ guard }}
            </el-tag>

            <small v-if="row.permission">{{ row.permission }}</small>
            <small v-else>无需额外权限码</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="前端来源" min-width="180">
        <template #default="{ row }">
          <div class="source-cell" v-if="row.sourceAppLabels.length">
            <el-tag
              v-for="label in row.sourceAppLabels"
              :key="label"
              size="small"
              effect="plain"
            >
              {{ label }}
            </el-tag>
            <small>{{ row.sourceFiles.length }} 个源码命中</small>
          </div>

          <div v-else class="source-cell source-cell-empty">
            <small>未发现前端调用</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="控制器方法" min-width="220">
        <template #default="{ row }">
          <div class="handler-cell">
            <strong>{{ row.handler }}</strong>
            <small>{{ row.controllerLabel }} / {{ row.actionName || 'Closure' }}</small>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="操作" :width="isMobile ? 60 : 108" fixed="right">
        <template #default="{ row }">
          <div v-if="!isMobile">
            <el-button text type="primary" @click="emit('copy', row.backendPath)">
              复制
            </el-button>
          </div>
          <el-dropdown v-else trigger="click" @command="() => emit('copy', row.backendPath)">
            <span class="action-link">···</span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item>复制</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script setup>
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()

defineProps({
  filteredItems: { type: Array, default: () => [] },
  methodTagType: { type: Function, required: true },
  accessTagType: { type: Function, required: true },
})

const emit = defineEmits(['copy'])
</script>

<style scoped lang="scss">
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

.directory-table-card :deep(.el-card__body) {
  padding-top: 16px;
}

.category-cell,
.path-cell,
.access-cell,
.source-cell,
.handler-cell {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.category-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.category-cell strong,
.path-cell strong,
.handler-cell strong {
  color: $text-color-primary;
  font-size: 14px;
  line-height: 1.6;
  word-break: break-all;
}

.category-cell small,
.path-cell small,
.access-cell small,
.source-cell small,
.handler-cell small {
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
  word-break: break-all;
}

.access-cell :deep(.el-tag),
.source-cell :deep(.el-tag) {
  width: fit-content;
}

.source-cell-empty {
  justify-content: center;
  min-height: 52px;
}

.table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 40px 0;
}

.table-empty strong {
  color: $text-color-primary;
  font-size: 15px;
}

.table-empty p {
  color: $text-color-secondary;
  font-size: 13px;
}
</style>
