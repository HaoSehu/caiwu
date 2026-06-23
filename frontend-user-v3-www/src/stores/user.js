import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { clientAuthApi } from '@/api/auth'
import { setToken, removeToken } from '@/utils/auth'

export const useUserStore = defineStore('user', () => {
  const info = ref(null)

  const isLoggedIn = computed(() => !!info.value)

  async function fetchUserInfo() {
    try {
      const res = await clientAuthApi.info()
      info.value = res.data
    } catch (error) {
      logout()
      throw error
    }
  }

  function hydrateUserFromToken(token) {
    setToken(token)
    return fetchUserInfo()
  }

  function logout() {
    info.value = null
    removeToken()
  }

  return {
    info,
    isLoggedIn,
    fetchUserInfo,
    hydrateUserFromToken,
    logout,
  }
})
