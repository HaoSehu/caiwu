<template>
  <section class="content-toolbar-card">
    <div class="content-toolbar-head">
      <div class="content-toolbar-actions">
        <el-button @click="emit('refresh')">刷新</el-button>
        <el-button @click="emit('open-category')">分类管理</el-button>
        <el-button type="primary" @click="emit('create')">新增{{ currentArticleLabel }}</el-button>
      </div>
    </div>

    <div class="search-bar content-search-bar">
      <el-input
        v-model="filters.keyword"
        clearable
        class="search-field search-field-wide"
        :placeholder="`搜索${currentArticleLabel}标题、摘要、正文或别名`"
        @keyup.enter="emit('search')"
      />

      <el-select
        v-model="filters.category_id"
        clearable
        filterable
        class="search-field"
        placeholder="全部分类"
        @change="emit('search')"
      >
        <el-option
          v-for="item in categories"
          :key="item.id"
          :label="item.name"
          :value="item.id"
        />
      </el-select>

      <el-select
        v-model="filters.status"
        clearable
        class="search-field"
        placeholder="全部状态"
        @change="emit('search')"
      >
        <el-option
          v-for="item in statusOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </el-select>

      <el-select
        v-model="filters.is_pinned"
        clearable
        class="search-field search-field-last"
        placeholder="置顶状态"
        @change="emit('search')"
      >
        <el-option
          v-for="item in pinOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </el-select>

    </div>

    <div class="content-category-strip">
      <button
        type="button"
        class="category-chip"
        :class="{ active: !filters.category_id }"
        @click="emit('category-filter', null)"
      >
        全部分类
      </button>

      <button
        v-for="item in categories"
        :key="item.id"
        type="button"
        class="category-chip"
        :class="{ active: Number(filters.category_id) === Number(item.id) }"
        @click="emit('category-filter', item.id)"
      >
        <span>{{ item.name }}</span>
        <small>{{ item.articles_count || 0 }}</small>
      </button>

      <button type="button" class="category-chip category-chip--ghost" @click="emit('open-category')">
        管理分类
      </button>
    </div>

    <div v-if="activeFilterTags.length" class="toolbar-foot">
      <div class="active-filters">
        <span class="active-filters-label">当前筛选</span>
        <el-tag
          v-for="tag in activeFilterTags"
          :key="tag.key"
          closable
          size="small"
          @close="emit('clear-filter', tag.key)"
        >
          {{ tag.label }}
        </el-tag>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  currentArticleLabel: { type: String, default: '' },
  categories: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  pinOptions: { type: Array, default: () => [] },
  activeFilterTags: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
})

const emit = defineEmits([
  'refresh',
  'open-category',
  'create',
  'search',
  'reset-filters',
  'category-filter',
  'clear-filter',
])
</script>

<style scoped lang="scss">
.content-toolbar-card {
  padding: 16px;
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.content-toolbar-head,
.toolbar-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.content-tabs,
.content-toolbar-actions,
.content-category-strip {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.content-tab,
.category-chip {
  appearance: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 34px;
  padding: 0 14px;
  border: 1px solid $border-color;
  border-radius: 999px;
  background: $bg-color-card;
  color: $text-color-secondary;
  font: inherit;
  cursor: pointer;
  transition: all 0.18s ease;
}

.content-tab:hover,
.category-chip:hover {
  border-color: $border-color-strong;
  background: $bg-color-hover;
  color: $text-color-primary;
}

.content-tab.active,
.category-chip.active {
  border-color: $color-primary-border;
  background: $color-primary-soft;
  color: $color-primary;
}

.content-search-bar {
  margin-top: 16px;
  align-items: center;
}

.search-field {
  flex: 1;
  min-width: 0;
}

.search-field-wide {
  flex: 2;
}

.content-category-strip {
  margin-top: 14px;
}

.category-chip small {
  color: $text-color-placeholder;
  font-size: 11px;
}

.category-chip.active small {
  color: $color-primary;
}

.category-chip--ghost {
  border-style: dashed;
}

.active-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.active-filters-label {
  color: $text-color-placeholder;
  font-size: 12px;
}

@media (max-width: 900px) {
  .content-toolbar-card {
    padding: 12px;
  }

  .content-toolbar-head {
    gap: 8px;
  }

  .content-toolbar-actions {
    width: 100%;
    justify-content: flex-end;
  }

  .content-search-bar {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    align-items: center;
  }

  .search-field-wide {
    grid-column: 1 / -1;
    width: 100%;
  }

  .search-field-last {
    grid-column: auto;
  }

  .search-field {
    width: 100%;
  }

  .content-category-strip {
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 2px;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .category-chip {
    flex-shrink: 0;
  }
}

@media (max-width: 380px) {
  .content-search-bar {
    grid-template-columns: 1fr 1fr 1fr;
  }

  .search-field-wide {
    grid-column: 1 / -1;
  }

  .content-toolbar-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .content-toolbar-actions {
    justify-content: flex-start;
  }
}
</style>
