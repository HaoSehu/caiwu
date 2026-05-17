import { defineStore } from 'pinia'
import { ref } from 'vue'
import { adminAuthApi, clientAuthApi } from '@/api/auth'
import { setToken, removeToken } from '@/utils/auth'

export const useUserStore = defineStore('user', () => {
  const info = ref(null)
  const permissions = ref([])
  const userType = ref('') // 'admin' | 'client'

  // 管理员登录
  async function adminLogin(loginData) {
    const res = await adminAuthApi.login(loginData)
    setToken(res.data.token, 'admin')
    info.value = res.data.admin
    permissions.value = res.data.admin.permissions || []
    userType.value = 'admin'
    return res
  }

  // 客户登录
  async function clientLogin(loginData) {
    const res = await clientAuthApi.login(loginData)
    setToken(res.data.token, 'client')
    info.value = res.data.user
    userType.value = 'client'
    return res
  }

  // 客户注册
  async function clientRegister(data) {
    const res = await clientAuthApi.register(data)
    setToken(res.data.token, 'client')
    info.value = res.data.user
    userType.value = 'client'
    return res
  }

  // 获取用户信息
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

  // 登出
  function logout() {
    info.value = null
    permissions.value = []
    userType.value = ''
    removeToken()
  }

  return {
    info, permissions, userType,
    adminLogin, clientLogin, clientRegister,
    fetchUserInfo, logout,
  }
})
