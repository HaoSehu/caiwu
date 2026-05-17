<template>
  <div class="client-page invoices-page">
    <section class="summary-grid">
      <article class="summary-card">
        <span>账单总数</span>
        <strong>{{ summary.total || 0 }}</strong>
      </article>
      <article class="summary-card">
        <span>待支付</span>
        <strong>{{ summary.unpaid || 0 }}</strong>
      </article>
      <article class="summary-card">
        <span>已支付</span>
        <strong>{{ summary.paid || 0 }}</strong>
      </article>
      <article class="summary-card">
        <span>待付金额</span>
        <strong>¥ {{ summary.unpaid_amount || '0.00' }}</strong>
      </article>
    </section>

    <section class="panel-card">
      <div class="toolbar-grid">
        <el-input v-model="filters.keyword" placeholder="搜索账单号或商品名" clearable @keyup.enter="handleSearch" />
        <el-select v-model="filters.status" placeholder="全部状态" clearable>
          <el-option label="待支付" :value="0" />
          <el-option label="已支付" :value="1" />
          <el-option label="已取消" :value="2" />
          <el-option label="已过期" :value="3" />
          <el-option label="已退款" :value="5" />
        </el-select>
        <el-select v-model="filters.type" placeholder="全部类型" clearable>
          <el-option label="新购账单" value="new" />
          <el-option label="续费账单" value="renew" />
          <el-option label="升级账单" value="upgrade" />
          <el-option label="流量包" value="traffic" />
        </el-select>
        <div class="toolbar-actions">
          <el-button @click="resetFilters">重置</el-button>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
        </div>
      </div>

      <el-table :data="list" v-loading="loading">
        <el-table-column label="账单号" min-width="170">
          <template #default="{ row }">
            <button type="button" class="table-link-button" @click="openDetail(row.id)">
              {{ row.invoice_no || `#${row.id}` }}
            </button>
          </template>
        </el-table-column>
        <el-table-column label="商品" min-width="220">
          <template #default="{ row }">
            <div class="stack-cell">
              <strong>{{ row.combined_display_name || row.product_display_name || '--' }}</strong>
              <span>{{ row.product_spec_display || row.summary || row.type_label || '--' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="金额" min-width="120">
          <template #default="{ row }">¥ {{ row.amount || '0.00' }}</template>
        </el-table-column>
        <el-table-column label="待付" min-width="120">
          <template #default="{ row }">¥ {{ row.payable_amount || '0.00' }}</template>
        </el-table-column>
        <el-table-column label="状态" min-width="120">
          <template #default="{ row }">
            <el-tag :type="resolveInvoiceTagType(row.status)" effect="light">
              {{ row.status_label || '--' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" min-width="180" prop="created_at" />
      </el-table>

      <el-empty v-if="!loading && !list.length" description="暂无账单记录" />

      <div v-if="total > 0" class="pager-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next"
          @current-change="loadList"
          @size-change="handlePageSizeChange"
        />
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInvoices } from '@/composables/useInvoices'

const router = useRouter()
const {
  loading,
  list,
  total,
  page,
  pageSize,
  summary,
  filters,
  resolveInvoiceTagType,
  loadList,
  loadData,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
} = useInvoices()

function openDetail(id) {
  router.push(`/client/invoices/${id}`)
}

onMounted(() => {
  void loadData()
})
</script>

<style scoped lang="scss">
.invoices-page {
  gap: 20px;
}

.summary-grid,
.toolbar-grid {
  display: grid;
  gap: 16px;
}

.summary-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.summary-card,
.panel-card {
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: #fff;
  box-shadow: $shadow-sm;
}

.summary-card {
  padding: 18px 20px;

  span {
    display: block;
    color: $text-color-secondary;
    font-size: 13px;
  }

  strong {
    display: block;
    margin-top: 10px;
    color: $text-color-primary;
    font-size: 28px;
    font-weight: 700;
  }
}

.panel-card {
  padding: 20px;
}

.toolbar-grid {
  grid-template-columns: 1.3fr 180px 180px auto;
  margin-bottom: 18px;
}

.toolbar-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.stack-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;

  strong {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.table-link-button {
  border: none;
  background: none;
  color: $color-primary;
  cursor: pointer;
  padding: 0;
}

.pager-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 960px) {
  .summary-grid,
  .toolbar-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .summary-grid,
  .toolbar-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-actions,
  .pager-wrap {
    justify-content: flex-start;
  }
}
</style>
