<template>
  <div class="client-page ticket-detail-page">
    <div class="ticket-detail-page__head">
      <el-button text class="back-button" @click="goBack">返回工单列表</el-button>
      <h1>工单详情</h1>
    </div>

    <TicketDetailDialog
      embedded
      :detail="detail"
      :detail-loading="detailLoading"
      :replying="replying"
      :closing="closing"
      :current-user-id="detail?.user_id"
      :resolve-ticket-status-label="resolveTicketStatusLabel"
      :resolve-department-label="resolveDepartmentLabel"
      :resolve-priority-label="resolvePriorityLabel"
      @reply="handleReply"
      @recall="handleRecall"
      @close="handleCloseTicket"
    />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useTickets } from '@/composables/useTickets'
import TicketDetailDialog from '@/features/tickets/TicketDetailDialog.vue'

const route = useRoute()
const router = useRouter()

const {
  replying,
  closing,
  detailLoading,
  detail,
  resolveTicketStatusLabel,
  resolvePriorityLabel,
  resolveDepartmentLabel,
  loadTicketDetail,
  submitReply,
  closeTicket,
  recallReply,
} = useTickets()

function resolveTicketId() {
  return Number(route.params.id || 0)
}

async function loadDetail() {
  const id = resolveTicketId()
  if (!id) {
    ElMessage.error('工单不存在')
    await router.replace('/client/tickets')
    return
  }

  const result = await loadTicketDetail(id)
  if (!result) {
    await router.replace('/client/tickets')
  }
}

async function handleReply(payload) {
  await submitReply(payload)
}

async function handleRecall(replyId) {
  await recallReply(replyId)
}

async function handleCloseTicket() {
  const success = await closeTicket()
  if (success) {
    await router.replace('/client/tickets')
  }
}

function goBack() {
  router.push('/client/tickets')
}

onMounted(() => {
  void loadDetail()
})
</script>

<style scoped lang="scss">
.ticket-detail-page {
  gap: 12px;
  min-height: calc(100vh - 96px);
}

.ticket-detail-page__head {
  display: flex;
  align-items: center;
  gap: 10px;

  h1 {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 700;
  }
}

.back-button {
  padding-left: 0;
}

@media (max-width: 768px) {
  .ticket-detail-page {
    position: fixed;
    inset: 64px 0 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    gap: 0;
  }

  .ticket-detail-page__head {
    flex-shrink: 0;
    padding: 12px 14px 0;
  }
}
</style>
