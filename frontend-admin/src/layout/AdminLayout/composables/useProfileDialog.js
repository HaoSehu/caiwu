import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { adminAuthApi } from '@/api/auth'
import { useUserStore } from '@/stores/user'

export function useProfileDialog() {
  const userStore = useUserStore()

  const profileDialogVisible = ref(false)
  const profileSaving = ref(false)
  const profileFormRef = ref(null)

  const profileForm = reactive({
    nickname: '',
    email: '',
  })

  const profileRules = {
    email: [{ type: 'email', message: '请输入有效邮箱', trigger: 'blur' }],
  }

  function openProfileDialog() {
    profileForm.nickname = userStore.info?.nickname || ''
    profileForm.email = userStore.info?.email || ''
    profileDialogVisible.value = true
  }

  function handleProfileDialogClosed() {
    profileFormRef.value?.clearValidate?.()
  }

  async function handleSaveProfile() {
    const valid = await profileFormRef.value?.validate?.().catch(() => false)
    if (valid === false) return

    profileSaving.value = true
    try {
      const res = await adminAuthApi.updateProfile({
        nickname: profileForm.nickname,
        email: profileForm.email,
      })

      userStore.info = {
        ...(userStore.info || {}),
        ...(res.data || {}),
      }

      ElMessage.success('账号资料已更新')
      profileDialogVisible.value = false
    } finally {
      profileSaving.value = false
    }
  }

  return {
    profileDialogVisible,
    profileSaving,
    profileFormRef,
    profileForm,
    profileRules,
    openProfileDialog,
    handleProfileDialogClosed,
    handleSaveProfile,
  }
}
