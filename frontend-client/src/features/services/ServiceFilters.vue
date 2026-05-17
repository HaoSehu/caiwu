<template>
  <div class="service-filter-card">
    <div class="service-filter-bar">
      <el-input
        v-model="filters.keyword"
        clearable
        class="service-filter-bar__search"
        :placeholder="searchPlaceholder"
        @keyup.enter="$emit('search')"
        @clear="$emit('search')"
      >
        <template #suffix>
          <button type="button" class="service-search-trigger" aria-label="搜索服务" @click="$emit('search')">
            <el-icon><Search /></el-icon>
          </button>
        </template>
      </el-input>

      <el-select
        v-model="filters.catalog_type"
        clearable
        class="service-filter-bar__select"
        popper-class="service-filter-select-popper"
        :placeholder="catalogPlaceholder"
        @change="$emit('search')"
      >
        <el-option label="全部分类" value="" />
        <el-option
          v-for="item in catalogTypeOptions"
          :key="item.value"
          :label="`${item.label} (${item.count})`"
          :value="item.value"
        />
      </el-select>

      <el-select
        v-model="filters.status"
        clearable
        class="service-filter-bar__select"
        popper-class="service-filter-select-popper"
        :placeholder="statusPlaceholder"
        @change="$emit('search')"
      >
        <el-option v-for="item in displayStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
      </el-select>

      <el-button class="service-filter-bar__toggle" @click="$emit('change-view-mode', nextViewMode)">
        <el-icon><component :is="toggleIcon" /></el-icon>
        切换
      </el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Grid, Search, Tickets } from '@element-plus/icons-vue'
import { useViewport } from '@/composables/useViewport'

interface SelectOption {
  label: string
  value: string | number
  count?: number
}

interface FiltersModel {
  keyword: string
  catalog_type: string
  status: string
}

const props = defineProps<{
  filters: FiltersModel
  statusOptions?: SelectOption[]
  catalogTypeOptions?: SelectOption[]
  viewMode?: string
  viewModeOptions?: SelectOption[]
}>()

defineEmits(['search', 'reset', 'pick-category', 'change-view-mode'])

const { isMobileScreen } = useViewport()
const nextViewMode = computed(() => (props.viewMode === 'grid' ? 'list' : 'grid'))
const toggleIcon = computed(() => (props.viewMode === 'grid' ? Tickets : Grid))
const searchPlaceholder = computed(() => (isMobileScreen.value ? '搜索' : '搜索服务名称、域名、账单号'))
const catalogPlaceholder = computed(() => (isMobileScreen.value ? '分类' : '服务分类'))
const statusPlaceholder = computed(() => (isMobileScreen.value ? '状态' : '状态分类'))
const displayStatusOptions = computed(() => props.statusOptions.map((item: any) => ({
  ...item,
  label: isMobileScreen.value && item.value === 'active_pending' ? '状态' : item.label,
})))
</script>

<style lang="scss" scoped>
.service-filter-card {
  border: 1px solid rgba(225, 231, 241, 0.9);
  border-radius: 18px;
  background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
  box-shadow: 0 12px 28px rgba(20, 47, 88, 0.05);
}

.service-filter-bar {
  display: grid;
  grid-template-columns: minmax(240px, 1.5fr) repeat(2, minmax(160px, 0.72fr)) auto;
  gap: 14px;
  padding: 18px;
  align-items: center;
}

.service-search-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 8px;
  background: rgba(76, 132, 255, 0.08);
  color: #3978ff;
  cursor: pointer;
}

.service-filter-bar__toggle {
  min-width: 96px;
}

.service-filter-bar {
  :deep(.el-input__wrapper),
  :deep(.el-select__wrapper) {
    min-height: 42px;
    border-radius: 12px;
    box-shadow: none;
    border: 1px solid #dfe6f1;
    background: #fff;
  }
}

@media (max-width: 1080px) {
  .service-filter-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .service-filter-card {
    border-radius: 22px;
    background: linear-gradient(180deg, #f9fbff 0%, #f6f8fd 100%);
    box-shadow: 0 14px 28px rgba(20, 47, 88, 0.06);
  }

  .service-filter-bar {
    grid-template-columns: minmax(0, 1.45fr) minmax(86px, 0.78fr) minmax(86px, 0.78fr) 68px;
    gap: 10px;
    padding: 14px 12px;
    align-items: stretch;
  }

  .service-filter-bar__search,
  .service-filter-bar__select,
  .service-filter-bar__toggle {
    width: 100%;
  }

  .service-filter-bar {
    :deep(.el-input__wrapper),
    :deep(.el-select__wrapper) {
      min-height: 44px;
      padding-left: 12px;
      padding-right: 10px;
      border-radius: 14px;
      border-color: #d8e2f0;
      background: #fff;
    }

    :deep(.el-input__inner),
    :deep(.el-select__placeholder) {
      font-size: 14px;
      color: #7d8aa0;
    }
  }

  .service-search-trigger {
    width: 30px;
    height: 30px;
    border-radius: 10px;
    background: rgba(76, 132, 255, 0.1);
  }

  .service-filter-bar__toggle {
    min-width: 0;
    min-height: 44px;
    border-radius: 14px;
  }
}

@media (max-width: 480px) {
  .service-filter-bar {
    grid-template-columns: minmax(0, 1.3fr) 80px 80px 60px;
    gap: 8px;
    padding: 12px 10px;
  }

  .service-filter-bar {
    :deep(.el-input__wrapper),
    :deep(.el-select__wrapper) {
      min-height: 40px;
      padding-left: 10px;
      padding-right: 8px;
    }

    :deep(.el-input__inner),
    :deep(.el-select__placeholder) {
      font-size: 13px;
    }
  }

  .service-search-trigger {
    width: 28px;
    height: 28px;
  }

  .service-filter-bar__toggle {
    min-height: 40px;
    font-size: 13px;
    padding: 0 8px;
  }
}
</style>
