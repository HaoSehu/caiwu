import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { clientAuthApi } from '@/api/auth'
import { getToken, removeToken } from '@/utils/auth'

export interface ClientUserInfo {
  display_name?: string
  nickname?: string
  email?: string
  [key: string]: unknown
}

export const useUserStore = defineStore('user', () => {
  const info = ref<ClientUserInfo | null>(null)

  const isLoggedIn = computed(() => !!info.value)

  async function fetchUserInfo(): Promise<ClientUserInfo | null> {
    if (!getToken()) {
      info.value = null
      return null
    }

    try {
      const res = await clientAuthApi.info()
      info.value = res.data as ClientUserInfo | null
      return info.value
    } catch (error) {
      void logout()
      throw error
    }
  }

  async function logout(): Promise<void> {
    try {
      if (getToken()) {
        await clientAuthApi.logout()
      }
    } catch {
      // Always clear local state even if the revoke request fails.
    } finally {
      info.value = null
      removeToken()
    }
  }

  return {
    info,
    isLoggedIn,
    fetchUserInfo,
    logout,
  }
})
