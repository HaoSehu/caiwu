import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'
import { useUserStore } from '@/stores/user'

/**
 * 推荐奖励模块 composable
 * 封装推荐概览、奖励流水、账户流水和提现记录
 */
export function useReferral() {
  const userStore = useUserStore()
  const loading = ref(false)
  const overview = reactive({})
  const rewards = ref([])
  const accountLogs = ref([])
  const withdrawals = ref([])

  async function loadAll() {
    loading.value = true
    try {
      const [overviewRes, rewardsRes, logsRes, withdrawalsRes] = await Promise.all([
        clientApi.referralOverview(),
        clientApi.referralRewards({ page: 1, page_size: 10 }),
        clientApi.referralAccountLogs({ page: 1, page_size: 10 }),
        clientApi.referralWithdrawals({ page: 1, page_size: 10 }),
      ])
      Object.assign(overview, overviewRes.data || {})
      rewards.value = Array.isArray(rewardsRes.data?.list) ? rewardsRes.data.list : []
      accountLogs.value = Array.isArray(logsRes.data?.list) ? logsRes.data.list : []
      withdrawals.value = Array.isArray(withdrawalsRes.data?.list) ? withdrawalsRes.data.list : []
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '推荐奖励数据加载失败')
    } finally {
      loading.value = false
    }
  }

  return {
    userStore,
    loading,
    overview,
    rewards,
    accountLogs,
    withdrawals,
    loadAll,
  }
}
