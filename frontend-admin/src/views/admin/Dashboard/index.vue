<template>
  <div class="dashboard-page admin-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">数据总览</span>
        <h2>运营总览</h2>
        <p>{{ refreshedAt }}</p>
      </div>
      <div class="page-actions">
        <el-button @click="loadDashboard">刷新</el-button>
        <el-button type="primary" @click="router.push('/admin/orders')">查看账单</el-button>
      </div>
    </section>

    <HeadlineGrid :cards="headlineCards" />

    <InsightLower :status-distribution="statusDistribution" :progress-items="progressItems" />

    <RecentInvoicesPanel
      :invoices="recentInvoices"
      :status-text="statusText"
      :status-type="statusType"
      :format-currency="formatCurrency"
      @view-all="router.push('/admin/orders')"
    />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboard } from './composables/useDashboard'
import HeadlineGrid from './components/HeadlineGrid.vue'
import InsightLower from './components/InsightLower.vue'
import RecentInvoicesPanel from './components/RecentInvoicesPanel.vue'

const router = useRouter()

const {
  loading,
  refreshedAt,
  headlineCards,
  progressItems,
  statusDistribution,
  recentInvoices,
  statusText,
  statusType,
  formatCurrency,
  loadDashboard,
} = useDashboard()

onMounted(loadDashboard)
</script>

<style lang="scss" scoped>
.page-actions {
  display: flex;
  gap: 12px;
}
</style>
