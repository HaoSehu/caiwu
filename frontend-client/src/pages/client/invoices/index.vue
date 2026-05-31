<template>
  <div class="client-page invoices-page">
    <div class="toolbar-grid">
        <el-input v-model="filters.keyword" placeholder="搜索账单号或商品名" clearable @keyup.enter="handleSearch" />
        <el-select v-model="filters.status" placeholder="全部状态" clearable @change="handleSearch">
          <el-option label="待支付" :value="0" />
          <el-option label="已支付" :value="1" />
          <el-option label="已取消" :value="2" />
          <el-option label="已过期" :value="3" />
          <el-option label="已退款" :value="5" />
        </el-select>
        <el-select v-model="filters.type" placeholder="全部类型" clearable @change="handleSearch">
          <el-option label="新购账单" value="new" />
          <el-option label="续费账单" value="renew" />
          <el-option label="升级账单" value="upgrade" />
          <el-option label="流量包" value="traffic" />
        </el-select>
      </div>

      <el-table v-if="!isMobileScreen" :data="list" v-loading="loading">
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

      <!-- 手机移动端账单卡片流 -->
      <div v-else class="mobile-invoice-list" v-loading="loading">
        <div
          v-for="row in list"
          :key="row.id"
          class="mobile-invoice-card"
          @click="openDetail(row.id)"
        >
          <div class="card-row card-row--top">
            <span class="invoice-no">{{ row.invoice_no || `#${row.id}` }}</span>
            <el-tag :type="resolveInvoiceTagType(row.status)" size="small" effect="light">
              {{ row.status_label || '--' }}
            </el-tag>
          </div>
          <div class="invoice-product-title">
            {{ row.combined_display_name || row.product_display_name || '--' }}
          </div>
          <div class="invoice-spec">
            {{ row.product_spec_display || row.summary || row.type_label || '--' }}
          </div>
          <div class="card-row card-row--bottom">
            <span class="invoice-amount-row">
              账单：<strong>¥{{ row.amount || '0.00' }}</strong> 
              <span v-if="row.payable_amount && Number(row.payable_amount) > 0" class="payable-sub">
                （待付：<strong class="payable-orange">¥{{ row.payable_amount }}</strong>）
              </span>
            </span>
            <span class="invoice-time">{{ row.created_at }}</span>
          </div>
        </div>
      </div>

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
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInvoices } from '@/composables/useInvoices'
import { useViewport } from '@/composables/useViewport'

const router = useRouter()
const { isMobileScreen } = useViewport()
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

.toolbar-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: 1.3fr 180px 180px auto;
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
  .toolbar-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .toolbar-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-actions,
  .pager-wrap {
    justify-content: flex-start;
  }
}

/* ── 手机移动端账单卡片流 ── */
.mobile-invoice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

.mobile-invoice-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
  cursor: pointer;
  transition: transform 0.15s ease, background-color 0.15s ease;

  &:active {
    transform: scale(0.99);
    background: $bg-color-hover;
  }

  .card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .invoice-no {
    color: $text-color-placeholder;
    font-size: 12px;
    font-weight: 600;
  }

  .invoice-product-title {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
    text-align: left;
  }

  .invoice-spec {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.4;
    text-align: left;
    word-break: break-all;
  }

  .invoice-amount-row {
    color: $text-color-secondary;
    font-size: 12px;
    text-align: left;

    strong {
      color: $text-color-primary;
      font-weight: 600;
    }

    .payable-sub {
      color: $text-color-placeholder;
      font-size: 11px;
    }

    .payable-orange {
      color: $color-accent-orange;
      font-weight: 600;
    }
  }

  .invoice-time {
    color: $text-color-placeholder;
    font-size: 11px;
  }
}
</style>
