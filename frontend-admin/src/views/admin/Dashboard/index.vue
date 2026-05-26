<template>
  <div class="dashboard-page admin-page" v-loading="loading">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">数据总览</span>
        <h2>运营总览</h2>
        <p>{{ refreshedAt }}</p>
      </div>
    </section>

    <section class="dashboard-layout">
        <HeadlineGrid :cards="headlineCards" />

        <div class="chart-row">
          <RevenuePieChart :chart-data="revenueByProduct" :month-label="monthLabel" />
          <RevenueLineChart :chart-data="dailyRevenue" :month-label="monthLabel" />
        </div>

        <RecentInvoicesPanel
          :invoices="recentInvoices"
          :status-text="statusText"
          :status-type="statusType"
          :format-currency="formatCurrency"
          @view-all="router.push('/admin/orders')"
        />
    </section>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboard } from './composables/useDashboard'
import HeadlineGrid from './components/HeadlineGrid.vue'
import RecentInvoicesPanel from './components/RecentInvoicesPanel.vue'
import RevenuePieChart from './components/RevenuePieChart.vue'
import RevenueLineChart from './components/RevenueLineChart.vue'

const router = useRouter()

const {
  loading,
  refreshedAt,
  headlineCards,
  recentInvoices,
  revenueByProduct,
  dailyRevenue,
  monthLabel,
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

.dashboard-layout {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.chart-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
  gap: 20px;
  align-items: start;
}

@include tablet-and-below {
  .chart-row {
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
  }
}

@include mobile-and-below {
  .page-actions {
    flex-direction: column;

    :deep(.el-button) {
      width: 100%;
    }
  }
}
</style>
