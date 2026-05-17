<template>
  <section class="console-panel console-panel--logs">
    <div class="console-panel__header console-panel__header--compact">
      <div class="console-logs-head">
        <h3>操作日志</h3>
        <span class="console-logs-meta">
          <span>共 {{ logsState.summary?.total || 0 }} 条</span>
          <span>今日 {{ logsState.summary?.today_total || 0 }} 条</span>
          <span v-if="logsState.summary?.latest_created_at">最近 {{ logsState.summary.latest_created_at }}</span>
        </span>
      </div>
      <el-button size="small" :loading="logsState.loading" @click="emit('load')">刷新</el-button>
    </div>
    <div class="console-panel__body">
      <div class="console-logs-toolbar">
        <el-input
          v-model="logsState.keyword"
          size="small"
          clearable
          placeholder="搜索操作说明、安全组、转发名称"
          class="console-logs-toolbar__keyword"
          @keyup.enter="emit('reload')"
          @clear="emit('reload')"
        />
        <el-select
          v-model="logsState.category"
          size="small"
          clearable
          placeholder="全部类型"
          class="console-logs-toolbar__category"
          @change="emit('reload')"
        >
          <el-option
            v-for="item in logCategoryOptions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
        <el-button size="small" link type="primary" @click="emit('reset')">重置</el-button>
      </div>

      <div class="console-log-table">
        <el-table :data="logsState.list" row-key="id" size="small" v-loading="logsState.loading">
          <el-table-column prop="created_at" label="操作时间" min-width="160" />
          <el-table-column label="操作详情" min-width="480">
            <template #default="{ row }">
              <div class="operation-cell">
                <div class="operation-cell__head">
                  <strong>{{ row.action_label }}</strong>
                  <span class="operation-cell__category">{{ row.category_label }}</span>
                </div>
                <p v-if="row.summary">{{ row.summary }}</p>
                <div v-if="row.detail_items?.length" class="operation-cell__meta">
                  <span
                    v-for="detailItem in row.detail_items.slice(0, 3)"
                    :key="`${row.id}-${detailItem.label}`"
                  >
                    {{ detailItem.label }}：{{ detailItem.value }}
                  </span>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="操作人" min-width="150">
            <template #default="{ row }">
              <div class="actor-cell">
                <strong>{{ row.actor_name || row.actor_label }}</strong>
                <span v-if="row.actor_name && row.actor_label && row.actor_name !== row.actor_label">{{ row.actor_label }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="ip_address" label="IP 地址" min-width="130" />
        </el-table>
      </div>

      <div v-if="logsState.list?.length" class="console-log-mobile">
        <article
          v-for="row in logsState.list"
          :key="`log-${row.id}`"
          class="console-mobile-card console-log-card"
        >
          <div class="console-mobile-card__top">
            <div class="operation-cell__head">
              <strong>{{ row.action_label }}</strong>
              <span class="operation-cell__category">{{ row.category_label }}</span>
            </div>
            <span class="console-mobile-card__time">{{ row.created_at }}</span>
          </div>

          <p v-if="row.summary" class="console-log-card__summary">{{ row.summary }}</p>

          <div v-if="row.detail_items?.length" class="operation-cell__meta">
            <span
              v-for="detailItem in row.detail_items.slice(0, 3)"
              :key="`${row.id}-${detailItem.label}`"
            >
              {{ detailItem.label }}：{{ detailItem.value }}
            </span>
          </div>

          <div class="console-mobile-card__meta console-log-card__meta">
            <span>操作人：{{ row.actor_name || row.actor_label }}</span>
            <span v-if="row.ip_address">IP：{{ row.ip_address }}</span>
          </div>
        </article>
      </div>

      <el-empty
        v-if="!logsState.loading && !logsState.list?.length"
        description="当前暂无实例日志"
        :image-size="72"
      />

      <div v-if="logsState.total > 0" class="pagination-wrap">
        <el-pagination
          v-model:current-page="logsState.page"
          v-model:page-size="logsState.page_size"
          :page-sizes="[10, 20, 50]"
          :total="logsState.total"
          layout="total, sizes, prev, pager, next"
          small
          @current-change="emit('load')"
          @size-change="emit('size-change')"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  logsState: { type: Object, required: true },
  logCategoryOptions: { type: Array, default: () => [] },
})

const emit = defineEmits(['load', 'reload', 'reset', 'size-change'])
</script>
