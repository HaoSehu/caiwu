<template>
  <div class="client-page record-page payments-page">
    <div class="record-toolbar-grid">
      <el-input v-model="filters.keyword" placeholder="搜索支付号、交易号或账单号" clearable @keyup.enter="handleSearch" />
      <el-select v-model="filters.status" placeholder="全部状态" clearable @change="handleSearch">
        <el-option label="待支付" :value="0" />
        <el-option label="成功" :value="1" />
        <el-option label="失败" :value="2" />
        <el-option label="已退款" :value="3" />
      </el-select>
      <el-select v-model="filters.gateway" placeholder="全部渠道" clearable @change="handleSearch">
        <el-option label="支付宝" value="alipay" />
        <el-option label="微信支付" value="wechat" />
      </el-select>
    </div>

    <el-table v-if="!isMobileScreen" :data="list" v-loading="loading">
      <el-table-column label="支付号" min-width="190">
        <template #default="{ row }">{{ row.payment_no || `#${row.id}` }}</template>
      </el-table-column>
      <el-table-column label="渠道" min-width="100" prop="gateway_label" />
      <el-table-column label="金额" min-width="120">
        <template #default="{ row }">¥{{ row.amount || '0.00' }}</template>
      </el-table-column>
      <el-table-column label="状态" min-width="110">
        <template #default="{ row }">
          <el-tag :type="resolvePaymentTagType(row.status)" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="渠道交易号" min-width="180">
        <template #default="{ row }">{{ row.trade_no || '--' }}</template>
      </el-table-column>
      <el-table-column label="关联账单" min-width="170">
        <template #default="{ row }">{{ row.invoice_no || '--' }}</template>
      </el-table-column>
      <el-table-column label="时间" min-width="170">
        <template #default="{ row }">{{ row.paid_at || row.created_at || '--' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="88" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" size="small" :disabled="!row.invoice_id" @click="goToInvoice(row)">账单</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div v-else class="record-mobile-list" v-loading="loading">
      <div v-for="row in list" :key="row.id" class="record-mobile-card" @click="goToInvoice(row)">
        <div class="record-card-row">
          <span class="record-card-no">{{ row.payment_no || `#${row.id}` }}</span>
          <el-tag :type="resolvePaymentTagType(row.status)" size="small" effect="light">
            {{ row.status_label || '--' }}
          </el-tag>
        </div>
        <div class="record-card-title">{{ row.gateway_label || row.gateway || '--' }}</div>
        <div class="record-card-sub">{{ row.trade_no || row.invoice_no || '等待渠道回调' }}</div>
        <div class="record-card-row">
          <span class="record-card-amount">充值：<strong>¥{{ row.amount || '0.00' }}</strong></span>
          <span class="record-card-time">{{ row.paid_at || row.created_at || '--' }}</span>
        </div>
      </div>
    </div>

    <el-empty v-if="!loading && !list.length" description="暂无充值记录" />

    <div v-if="total > 0" class="record-pager-wrap">
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
  gateway: '',
})

function resolvePaymentTagType(status) {
  if (status === 1) return 'success'
  if (status === 0) return 'warning'
  if (status === 3) return 'info'
  return 'danger'
}

async function loadList() {
  loading.value = true
  try {
    const response = await clientApi.payments({
      page: page.value,
      page_size: pageSize.value,
      keyword: filters.keyword || undefined,
      status: filters.status === '' ? undefined : filters.status,
      gateway: filters.gateway || undefined,
    })
    list.value = Array.isArray(response.data?.list) ? response.data.list : []
    total.value = Number(response.data?.total || 0)
  } catch (error) {
    if (!error?.__handled) ElMessage.error(error?.message || '充值记录加载失败')
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
