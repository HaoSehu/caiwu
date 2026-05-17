import { computed, ref } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'
import { useUserStore } from '@/stores/user'
import { SERVICE_STATUS } from '@shared/statusConfig'

/**
 * 账户充值模块 composable
 * 封装充值下单与支付状态轮询
 */
export function useRecharge() {
  const userStore = useUserStore()
  const amount = ref(100)
  const submitting = ref(false)
  const polling = ref(false)
  const paymentPayload = ref(null)
  const summaryLoading = ref(false)
  const rechargeSummary = ref({
    balance: '0.00',
    renewNeeded7Days: '0.00',
  })
  let pollingTimer = null

  const summaryCards = computed(() => [
    {
      key: 'balance',
      label: '当前余额',
      value: rechargeSummary.value.balance,
      suffix: '元',
    },
    {
      key: 'renew-needed-7-days',
      label: '续费需要',
      value: rechargeSummary.value.renewNeeded7Days,
      suffix: '元',
      hint: '检测 7 天内到期的续费金额',
      quickFilter: 'expiring_7d',
    },
  ])

  async function createRechargeOrder(overrideAmount) {
    const targetAmount = Number((overrideAmount ?? amount.value) || 0)

    submitting.value = true
    try {
      const response = await clientApi.recharge({ amount: targetAmount })
      amount.value = targetAmount
      paymentPayload.value = response.data || null
      ElMessage.success('充值二维码已生成')
      return paymentPayload.value
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '创建充值订单失败')
      return null
    } finally {
      submitting.value = false
    }
  }

  async function pollRechargeStatus(options = {}) {
    const { silentPending = false } = options
    const paymentNo = String(paymentPayload.value?.payment_no || '')
    const pollToken = String(paymentPayload.value?.poll_token || '')
    if (!paymentNo || !pollToken) {
      ElMessage.warning('缺少支付轮询凭证')
      return
    }

    polling.value = true
    try {
      const response = await clientApi.rechargeStatus(paymentNo, { poll_token: pollToken })
      if (response.data?.paid) {
        stopAutoPolling()
        await userStore.fetchUserInfo('client')
        ElMessage.success('充值成功，余额已刷新')
      } else {
        if (!silentPending) {
          ElMessage.info(response.data?.message || '当前仍未支付成功')
        }
      }
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '查询充值状态失败')
    } finally {
      polling.value = false
    }
  }

  function startAutoPolling(interval = 5000) {
    stopAutoPolling()
    pollingTimer = window.setInterval(() => {
      if (!paymentPayload.value?.payment_no || polling.value) {
        return
      }

      void pollRechargeStatus({ silentPending: true })
    }, interval)
  }

  function stopAutoPolling() {
    if (pollingTimer) {
      window.clearInterval(pollingTimer)
      pollingTimer = null
    }
  }

  function clearPaymentPayload() {
    stopAutoPolling()
    paymentPayload.value = null
  }

  function formatMoney(value) {
    const amountValue = Number(value || 0)
    return Number.isFinite(amountValue) ? amountValue.toFixed(2) : '0.00'
  }

  function parseDateTime(value) {
    const text = String(value || '').trim()
    if (!text) return null
    const normalized = text.replace(' ', 'T')
    const timestamp = new Date(normalized).getTime()
    return Number.isFinite(timestamp) ? timestamp : null
  }

  function isRenewableService(service) {
    return Number(service?.status) === SERVICE_STATUS.ACTIVE && Number(service?.id || 0) > 0
  }

  async function resolvePreviewRenewAmount(service) {
    const serviceId = Number(service?.id || 0)
    if (serviceId <= 0) return 0

    try {
      const response = await clientApi.serviceRenewPreview(serviceId)
      const renewPrice = Number(response.data?.renew_price || 0)
      return Number.isFinite(renewPrice) ? renewPrice : 0
    } catch {
      return 0
    }
  }

  async function sumRenewAmounts(services, chunkSize = 4) {
    let totalAmount = 0

    for (let index = 0; index < services.length; index += chunkSize) {
      const currentChunk = services.slice(index, index + chunkSize)
      const amounts = await Promise.all(currentChunk.map((service) => resolvePreviewRenewAmount(service)))
      totalAmount += amounts.reduce((sum, value) => sum + Number(value || 0), 0)
    }

    return totalAmount
  }

  async function loadRechargeSummary() {
    summaryLoading.value = true

    try {
      const collectedServices = []
      let page = 1
      let total = 0
      let pageSize = 50

      do {
        const response = await clientApi.services({
          page,
          page_size: pageSize,
          status_scope: 'active_pending',
        })

        const payload = response.data || {}
        const list = Array.isArray(payload.list) ? payload.list : []
        total = Number(payload.total || 0)
        pageSize = Number(payload.page_size || pageSize || 50)
        collectedServices.push(...list)
        page += 1
      } while (collectedServices.length < total)

      const now = Date.now()
      const sevenDaysLater = now + 7 * 24 * 60 * 60 * 1000
      const renewableServices = collectedServices.filter((service) => {
        const expiresAt = parseDateTime(service?.expires_at)
        return isRenewableService(service) && expiresAt && expiresAt >= now
      })

      const renewNeededServices = renewableServices.filter((service) => {
        const expiresAt = parseDateTime(service?.expires_at)
        return expiresAt !== null && expiresAt <= sevenDaysLater
      })

      const renewNeeded7Days = await sumRenewAmounts(renewNeededServices)

      rechargeSummary.value = {
        balance: formatMoney(userStore.info?.balance || 0),
        renewNeeded7Days: formatMoney(renewNeeded7Days),
      }
    } catch (error) {
      rechargeSummary.value = {
        ...rechargeSummary.value,
        balance: formatMoney(userStore.info?.balance || 0),
      }
      if (!error?.__handled) {
        ElMessage.error(error?.message || '加载充值摘要失败')
      }
    } finally {
      summaryLoading.value = false
    }
  }

  return {
    userStore,
    amount,
    submitting,
    polling,
    paymentPayload,
    summaryLoading,
    summaryCards,
    rechargeSummary,
    createRechargeOrder,
    pollRechargeStatus,
    startAutoPolling,
    stopAutoPolling,
    clearPaymentPayload,
    loadRechargeSummary,
  }
}
