import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useGeeTestCaptcha } from '@/composables/useGeeTestCaptcha'
import { useUserStore } from '@/stores/user'
import request from '@/utils/request'
import { toUserMessage } from '@/utils/userMessage'

export function useProfileForm() {
  const router = useRouter()
  const userStore = useUserStore()
  const { runWithCaptcha } = useGeeTestCaptcha()

  const profileLoading = ref(false)
  const profileFormRef = ref()

  const form = reactive({
    id: '',
    email: '',
    nickname: '',
    phone: '',
    balance: '0.00',
    avatar: '',
    createdAt: '',
    is_verified: 0,
    real_name: '',
    id_card_masked: '',
  })

  const profileRules = {
    nickname: [{ max: 50, message: '用户名不能超过50个字符', trigger: 'blur' }],
  }

  onMounted(async () => {
    if (!userStore.info) {
      await userStore.fetchUserInfo('client')
    }
    const info = userStore.info
    if (info) {
      form.id = info.id || ''
      form.email = info.email || ''
      form.nickname = info.nickname || ''
      form.phone = info.phone || ''
      form.balance = info.balance || '0.00'
      form.is_verified = info.is_verified || 0
      form.real_name = info.real_name || ''
      form.id_card_masked = info.id_card_masked || ''
      form.createdAt = info.created_at || ''
    }
  })

  function copyText(text) {
    navigator.clipboard.writeText(text)
      .then(() => ElMessage.success('复制成功'))
      .catch(() => ElMessage.warning('复制失败，请手动复制'))
  }

  function handleUploadAvatar() {
    ElMessage.info('头像上传功能开发中')
  }

  async function handleUpdateProfile() {
    await profileFormRef.value?.validate()
    profileLoading.value = true
    try {
      await request.put('/client/auth/profile', { nickname: form.nickname })
      await userStore.fetchUserInfo('client')
      const info = userStore.info
      if (info) form.nickname = info.nickname || ''
      ElMessage.success('用户名修改成功')
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '资料保存失败'))
    } finally {
      profileLoading.value = false
    }
  }

  async function postCodeWithFallback(path, payload) {
    return request.post(path, payload)
  }

  return {
    form,
    profileFormRef,
    profileLoading,
    profileRules,
    copyText,
    handleUploadAvatar,
    handleUpdateProfile,
    postCodeWithFallback,
    runWithCaptcha,
    router,
    userStore,
  }
}
