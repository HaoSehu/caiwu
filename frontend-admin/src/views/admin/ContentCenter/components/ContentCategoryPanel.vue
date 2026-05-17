<template>
  <el-dialog
    v-model="visible"
    title="分类管理"
    width="980px"
    destroy-on-close
    class="content-dialog"
  >
    <div class="dialog-intro">
      <strong>{{ currentArticleLabel }}分类管理</strong>
      <p>分类用于前台聚合文章内容。删除前请先迁移该分类下的文章。</p>
    </div>

    <div class="category-dialog-layout">
      <el-card shadow="never">
        <template #header>
          <div class="dialog-section-header">
            <strong>{{ categoryForm.id ? '编辑分类' : '新增分类' }}</strong>
            <span>当前共 {{ categories.length }} 个分类</span>
          </div>
        </template>

        <el-form
          ref="categoryFormRef"
          :model="categoryForm"
          :rules="categoryRules"
          label-position="top"
        >
          <div class="dialog-grid">
            <el-form-item label="分类名称" prop="name">
              <el-input v-model="categoryForm.name" maxlength="80" placeholder="请输入分类名称" />
            </el-form-item>

            <el-form-item label="别名">
              <el-input v-model="categoryForm.slug" maxlength="120" placeholder="留空自动生成" />
            </el-form-item>

            <el-form-item label="排序值">
              <el-input-number v-model="categoryForm.sort_order" :min="0" :max="999999" controls-position="right" />
            </el-form-item>

            <el-form-item label="状态">
              <el-switch
                v-model="categoryForm.status"
                :active-value="1"
                :inactive-value="0"
                active-text="启用"
                inactive-text="停用"
              />
            </el-form-item>

            <el-form-item class="dialog-span-2" label="分类说明">
              <el-input
                v-model="categoryForm.description"
                type="textarea"
                :rows="3"
                maxlength="255"
                show-word-limit
                placeholder="用于后台备注和前台分类说明"
              />
            </el-form-item>
          </div>
        </el-form>

        <div class="dialog-actions">
          <el-button v-if="categoryForm.id" @click="emit('reset-form')">取消编辑</el-button>
          <el-button type="primary" :loading="categorySaving" @click="emit('submit')">
            {{ categoryForm.id ? '保存分类' : '新增分类' }}
          </el-button>
        </div>
      </el-card>

      <el-card shadow="never">
        <template #header>
          <div class="dialog-section-header">
            <strong>分类列表</strong>
            <span>支持编辑名称、排序和上下架状态</span>
          </div>
        </template>

        <el-table :data="categories" v-loading="categoryLoading" size="small" stripe>
          <el-table-column prop="name" label="分类名称" min-width="140" />
          <el-table-column prop="slug" label="别名" min-width="120" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag size="small" :type="Number(row.status) === 1 ? 'success' : 'info'">
                {{ Number(row.status) === 1 ? '启用' : '停用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="80" />
          <el-table-column label="文章数" width="90">
            <template #default="{ row }">{{ row.articles_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="操作" :width="isMobile ? 60 : 120" fixed="right">
            <template #default="{ row }">
              <div v-if="!isMobile" class="action-inline">
                <el-button size="small" text type="primary" @click="emit('fill-form', row)">编辑</el-button>
                <el-button size="small" text type="danger" @click="emit('delete', row)">删除</el-button>
              </div>
              <el-dropdown v-else trigger="click" @command="(cmd) => handleCategoryPanelAction(cmd, row)">
                <span class="action-link">···</span>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item command="edit">编辑</el-dropdown-item>
                    <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>
  </el-dialog>
</template>

<script setup>
defineProps({
  categories: { type: Array, default: () => [] },
  categoryLoading: { type: Boolean, default: false },
  categorySaving: { type: Boolean, default: false },
  categoryForm: { type: Object, required: true },
  categoryFormRef: { type: Object, default: null },
  categoryRules: { type: Object, default: () => ({}) },
  currentArticleLabel: { type: String, default: '' },
})

import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
const visible = defineModel({ type: Boolean })
const emit = defineEmits(['submit', 'delete', 'fill-form', 'reset-form'])

function handleCategoryPanelAction(command, row) {
  if (command === 'edit') {
    emit('fill-form', row)
  } else if (command === 'delete') {
    emit('delete', row)
  }
}
</script>

<style scoped lang="scss">
.dialog-intro {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-soft;
}

.dialog-intro strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.dialog-intro p {
  margin-top: 6px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.dialog-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.dialog-section-header strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.dialog-section-header span {
  color: $text-color-secondary;
  font-size: 12px;
}

.category-dialog-layout {
  display: grid;
  grid-template-columns: 340px minmax(0, 1fr);
  gap: 16px;
}

.dialog-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
}

.dialog-span-2 {
  grid-column: span 2;
}

.dialog-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 12px;
}

.action-inline {
  display: flex;
  align-items: center;
  gap: 8px;
}

.content-dialog :deep(.el-dialog__body) {
  padding-top: 12px;
}

@media (max-width: 1100px) {
  .category-dialog-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .dialog-grid {
    grid-template-columns: 1fr;
  }

  .dialog-span-2 {
    grid-column: span 1;
  }
}

@media (max-width: 640px) {
  .dialog-actions {
    justify-content: stretch;
  }

  .dialog-actions :deep(.el-button) {
    width: 100%;
  }
}
</style>
