<template>
  <div class="client-page record-page orders-page">
    <section class="client-page-head">
      <div class="client-page-heading">
        <h2>订单记录</h2>
        <p>查看购买服务产生的订单、开通状态和关联账单。</p>
      </div>
    </section>

    <div class="toolbar-grid">
      <el-input v-model="filters.keyword" placeholder="搜索订单号、账单号或服务名" clearable @keyup.enter="handleSearch" />
      <el-select v-model="filters.status" placeholder="全部状态" clearable @change="handleSearch">
        <el-option label="待付款" :value="0" />
        <el-option label="已付款" :value="1" />
        <el-option label="开通中" :value="2" />
        <el-option label="已完成" :value="3" />
        <el-option label="已取消" :value="4" />
        <el-option label="已退款" :value="5" />
      </el-select>
      <el-select v-model="filters.type" placeholder="全部类型" clearable @change="handleSearch">
        <el-option label="新购" value="new" />
        <el-option label="续费" value="renew" />
      </el-select>
    </div>

    <el-table v-if="!isMobileScreen" :data="list" v-loading="loading">
      <el-table-column label="订单号" min-width="170">
        <template #default="{ row }">{{ row.order_no || `#${row.id}` }}</template>
      </el-table-column>
      <el-table-column label="服务" min-width="220">
        <template #default="{ row }">
          <div class="stack-cell">
            <strong>{{ row.product_name || row.service_name || '--' }}</strong>
            <span>{{ row.service_name || row.billing_cycle || row.type_label || '--' }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="金额" min-width="120">
        <template #default="{ row }">¥{{ row.amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="已付" min-width="120">
        <template #default="{ row }">¥{{ row.paid_amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="状态" min-width="110">
        <template #default="{ row }">
          <el-tag :type="resolveOrderTagType(row.status)" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="关联账单" min-width="170">
        <template #default="{ row }">{{ row.invoice_no || '--' }}</template>
      </el-table-column>
      <el-table-column label="创建时间" min-width="170" prop="created_at" />
      <el-table-column label="操作" width="88" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" size="small" :disabled="!row.invoice_id" @click="goToInvoice(row)">账单</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div v-else class="mobile-record-list" v-loading="loading">
      <div v-for="row in list" :key="row.id" class="mobile-record-card" @click="goToInvoice(row)">
        <div class="card-row card-row--top">
          <span class="record-no">{{ row.order_no || `#${row.id}` }}</span>
          <el-tag :type="resolveOrderTagType(row.status)" size="small" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </div>
        <strong>{{ row.product_name || row.service_name || '--' }}</strong>
        <span>{{ row.invoice_no || '未关联账单' }}</span>
        <div class="card-row">
          <span>订单：¥{{ row.amount || '0.00' }}</span>
          <span>{{ row.created_at || '--' }}</span>
        </div>
      </div>
    </div>

    <el-empty v-if="!loading && !list.length" description="暂无订单记录" />

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
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'
import { useViewport } from '@/composables/useViewport'

const router = useRouter()
const { isMobileScreen } = useViewport()

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const filters = reactive({
  keyword: '',
  status: '',
  type: '',
})

function resolveOrderTagType(status) {
  if (status === 1 || status === 3) return 'success'
  if (status === 0 || status === 2) return 'warning'
  if (status === 4) return 'info'
  return 'danger'
}

async function loadList() {
  loading.value = true
  try {
    const response = await clientApi.orders({
      page: page.value,
      page_size: pageSize.value,
      keyword: filters.keyword || undefined,
      status: filters.status === '' ? undefined : filters.status,
      type: filters.type || undefined,
    })
    list.value = Array.isArray(response.data?.list) ? response.data.list : []
    total.value = Number(response.data?.total || 0)
  } catch (error) {
    if (!error?.__handled) ElMessage.error(error?.message || '订单记录加载失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  void loadList()
}

function handlePageSizeChange() {
  page.value = 1
  void loadList()
}

function goToInvoice(row) {
  const invoiceId = Number(row?.invoice_id || 0)
  if (invoiceId > 0) router.push(`/client/invoices/${invoiceId}`)
}

onMounted(() => {
  void loadList()
})
</script>

<style scoped lang="scss">
.record-page {
  gap: 20px;
}

.toolbar-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: minmax(240px, 1.3fr) 180px 180px;
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

.pager-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.mobile-record-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.mobile-record-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
  cursor: pointer;

  strong {
    color: $text-color-primary;
    font-size: 14px;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
  }
}

.card-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.record-no {
  color: $text-color-placeholder;
  font-size: 12px;
  font-weight: 600;
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

  .pager-wrap {
    justify-content: flex-start;
  }
}
</style>
