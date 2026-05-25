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

    <section class="dashboard-layout">
      <div class="dashboard-layout__main">
        <HeadlineGrid :cards="headlineCards" />

        <RecentInvoicesPanel
          :invoices="recentInvoices"
          :status-text="statusText"
          :status-type="statusType"
          :format-currency="formatCurrency"
          @view-all="router.push('/admin/orders')"
        />
      </div>

      <aside class="dashboard-layout__side">
        <InsightLower :status-distribution="statusDistribution" :progress-items="progressItems" />
      </aside>
    </section>
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

.dashboard-layout {
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(280px, 2fr);
  align-items: start;
  gap: 20px;
}

.dashboard-layout__main {
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-width: 0;
}

.dashboard-layout__side {
  position: sticky;
  top: 12px;
}

@include desktop-lg-and-below {
  .dashboard-layout {
    grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
    gap: 16px;
  }
}

@include tablet-and-below {
  .dashboard-layout {
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
  }

  .dashboard-layout__side {
    position: static;
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
