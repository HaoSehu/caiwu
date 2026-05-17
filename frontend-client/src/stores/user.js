import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { adminAuthApi, clientAuthApi } from '@/api/auth'
import { setToken, removeToken } from '@/utils/auth'

export const useUserStore = defineStore('user', () => {
  const info = ref(null)
  const permissions = ref([])
  const userType = ref('') // 'admin' | 'client'

  const isLoggedIn = computed(() => !!info.value)

  async function adminLogin(loginData) {
    const res = await adminAuthApi.login(loginData)
    setToken(res.data.token)
    info.value = res.data.admin
    permissions.value = res.data.admin.permissions || []
    userType.value = 'admin'
    return res
  }

  async function clientLogin(loginData) {
    const res = await clientAuthApi.login(loginData)
    setToken(res.data.token)
    info.value = res.data.user
    userType.value = 'client'
    return res
  }

  async function exchangeLoginAsCode(code) {
    const res = await clientAuthApi.exchangeLoginAsCode({ code })
    setToken(res.data.token)
    userType.value = 'client'
    await fetchUserInfo('client')
    return res
  }

  async function clientRegister(data) {
    const res = await clientAuthApi.register(data)
    setToken(res.data.token)
    info.value = res.data.user
    userType.value = 'client'
    return res
  }

  async function fetchUserInfo(type = 'admin') {
    try {
      const api = type === 'admin' ? adminAuthApi : clientAuthApi
      const res = await api.info()
      info.value = res.data
      if (type === 'admin') {
        permissions.value = res.data.permissions || []
      }
      userType.value = type
    } catch {
      logout()
      throw new Error('获取用户信息失败')
    }
  }

  function logout() {
    info.value = null
    permissions.value = []
    userType.value = ''
    removeToken()
  }

  return {
    info, permissions, userType, isLoggedIn,
    adminLogin, clientLogin, clientRegister, exchangeLoginAsCode,
    fetchUserInfo, logout,
  }
})
