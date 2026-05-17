<template>
  <div class="client-page tickets-page">
    <TicketListTable
      v-model:keyword="keyword"
      v-model:status="status"
      v-model:page="page"
      v-model:page-size="pageSize"
      :list="list"
      :loading="loading"
      :total="total"
      :resolve-ticket-status-label="resolveTicketStatusLabel"
      :resolve-ticket-tag-type="resolveTicketTagType"
      :resolve-priority-label="resolvePriorityLabel"
      @search="handleSearch"
      @reset="resetFilters"
      @create="openCreateDialog"
      @page-change="loadTickets"
      @size-change="handlePageSizeChange"
      @view-detail="openDetail"
    />

    <TicketCreateDialog
      v-model="createDialogVisible"
      :creating="creating"
      :service-options="serviceOptions"
      @submit="handleCreateSubmit"
    />

  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useTickets } from '@/composables/useTickets'
import TicketListTable from '@/widgets/tickets/TicketListTable.vue'
import TicketCreateDialog from '@/features/tickets/TicketCreateDialog.vue'

const router = useRouter()

const {
  loading,
  creating,
  list,
  total,
  page,
  pageSize,
  keyword,
  status,
  serviceOptions,
  resolveTicketStatusLabel,
  resolveTicketTagType,
  resolvePriorityLabel,
  loadTickets,
  loadServiceOptions,
  handleSearch,
  handlePageSizeChange,
  resetFilters,
  submitTicket,
} = useTickets()

const createDialogVisible = ref(false)

async function openCreateDialog() {
  createDialogVisible.value = true
  if (!serviceOptions.value.length) {
    try {
      await loadServiceOptions()
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '服务选项加载失败')
    }
  }
}

async function handleCreateSubmit(form) {
  const success = await submitTicket(form)
  if (success) {
    createDialogVisible.value = false
  }
}

async function openDetail(row) {
  await router.push(`/client/ticket-conversations/${row.id}`)
}

onMounted(() => {
  void loadTickets()
})
</script>

<style scoped lang="scss">
.tickets-page {
  gap: 20px;
}
</style>
