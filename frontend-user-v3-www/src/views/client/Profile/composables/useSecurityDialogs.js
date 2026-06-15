import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getEmailBindingFormError,
  getPhoneBindingFormError,
  getVerificationFormError,
  IDENTITY_CARD_PATTERN,
  normalizeEmail,
  normalizePhone,
  resolveVerificationQrUrl,
} from '@/utils/verification'
import { toUserMessage } from '@/utils/userMessage'

export function useSecurityDialogs(form, postCodeWithFallback, runWithCaptcha, userStore) {
  // --- 密码 ---
  const showPasswordDialog = ref(false)
  const passwordLoading = ref(false)
  const passwordFormRef = ref()
  const passwordForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' })
  const passwordRules = {
    oldPassword: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
    newPassword: [
      { required: true, message: '请输入新密码', trigger: 'blur' },
      { min: 6, message: '密码长度不能少于6位', trigger: 'blur' },
    ],
    confirmPassword: [
      { required: true, message: '请确认新密码', trigger: 'blur' },
      {
        validator: (rule, value, callback) => {
          if (value !== passwordForm.newPassword) callback(new Error('两次密码输入不一致'))
          else callback()
        },
        trigger: 'blur',
      },
    ],
  }

  // --- 手机 ---
  const showPhoneDialog = ref(false)
  const phoneLoading = ref(false)
  const phoneCodeSending = ref(false)
  const codeCountdown = ref(0)
  const phoneFormRef = ref()
  const phoneForm = reactive({ phone: '', code: '' })
  const phoneRules = {
    phone: [{ required: true, message: '请输入手机号', trigger: 'blur' }],
    code: [{ required: true, message: '请输入短信验证码', trigger: 'blur' }],
  }
  let phoneCodeTimer = null

  // --- 邮箱 ---
  const showEmailDialog = ref(false)
  const emailLoading = ref(false)
  const emailCodeSending = ref(false)
  const emailCodeCountdown = ref(0)
  const emailFormRef = ref()
  const emailForm = reactive({ email: '', code: '' })
  const emailRules = {
    email: [{ required: true, type: 'email', message: '请输入有效邮箱', trigger: 'blur' }],
    code: [{ required: true, message: '请输入邮箱验证码', trigger: 'blur' }],
  }
  let emailCodeTimer = null
  let verificationPollingTimer = null

  // --- 实名 ---
  const showVerificationDialog = ref(false)
  const showVerifiedInfoDialog = ref(false)
  const verificationLoading = ref(false)
  const checkingStatus = ref(false)
  const verificationUrl = ref('')
  const certifyId = ref('')
  const canRestartVerification = ref(false)
  const verificationFormRef = ref()
  const verificationForm = reactive({ realName: '', idCard: '' })
  const verificationRules = {
    realName: [{ required: true, message: '请输入真实姓名', trigger: 'blur' }],
    idCard: [
      { required: true, message: '请输入身份证号', trigger: 'blur' },
      { pattern: IDENTITY_CARD_PATTERN, message: '身份证号格式不正确', trigger: 'blur' },
    ],
  }

  watch(showPhoneDialog, (visible) => {
    if (!visible && phoneCodeTimer) {
      clearInterval(phoneCodeTimer)
      phoneCodeTimer = null
      codeCountdown.value = 0
    }
  })

  watch(showEmailDialog, (visible) => {
    if (!visible && emailCodeTimer) {
      clearInterval(emailCodeTimer)
      emailCodeTimer = null
      emailCodeCountdown.value = 0
    }
  })

  watch(showVerificationDialog, async (visible) => {
    if (!visible || form.is_verified) {
      stopVerificationPolling()
      return
    }

    const info = userStore.info || {}

    if (!verificationForm.realName && info.real_name) {
      verificationForm.realName = info.real_name
    }

    certifyId.value = info.verification_certify_id || certifyId.value

    if (certifyId.value && !verificationUrl.value) {
      await refreshVerificationLink()
    }
  })

  watch([showVerificationDialog, verificationUrl, certifyId], ([visible, currentUrl, currentCertifyId]) => {
    if (visible && currentUrl && currentCertifyId) {
      startVerificationPolling()
      return
    }

    stopVerificationPolling()
  })

  onBeforeUnmount(() => {
    if (phoneCodeTimer) { clearInterval(phoneCodeTimer); phoneCodeTimer = null }
    if (emailCodeTimer) { clearInterval(emailCodeTimer); emailCodeTimer = null }
    stopVerificationPolling()
  })

  function openPhoneDialog() {
    phoneForm.phone = form.phone || ''
    phoneForm.code = ''
    codeCountdown.value = 0
    showPhoneDialog.value = true
  }

  function openEmailDialog() {
    emailForm.email = form.email || ''
    emailForm.code = ''
    emailCodeCountdown.value = 0
    showEmailDialog.value = true
  }

  async function handleChangePassword(router) {
    if (!passwordFormRef.value) return
    await passwordFormRef.value.validate()
    passwordLoading.value = true
    try {
      const request = (await import('@/utils/request')).default
      await request.put('/client/password', passwordForm)
      ElMessage.success('密码修改成功，请重新登录')
      showPasswordDialog.value = false
      setTimeout(() => { userStore.logout(); router.push('/client/login') }, 1500)
    } catch (error) {
      ElMessage.error(toUserMessage(error?.message, '修改失败'))
    } finally {
      passwordLoading.value = false
    }
  }

  async function handleSendPhoneCode() {
    const formError = getPhoneBindingFormError(phoneForm, { requireCode: false })
    if (formError) {
      ElMessage.warning(formError)
      return
    }

    phoneCodeSending.value = true
    try {
      const phone = normalizePhone(phoneForm.phone)
      await runWithCaptcha(async (captcha) => {
        await postCodeWithFallback('/client/auth/phone-code', { phone, captcha })
      })
      ElMessage.success('短信验证码已发送')
      codeCountdown.value = 60
      if (phoneCodeTimer) clearInterval(phoneCodeTimer)
      phoneCodeTimer = setInterval(() => {
        codeCountdown.value -= 1
        if (codeCountdown.value <= 0) { clearInterval(phoneCodeTimer); phoneCodeTimer = null; codeCountdown.value = 0 }
      }, 1000)
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '短信验证码发送失败'))
    } finally {
      phoneCodeSending.value = false
    }
  }

  async function handleChangePhone() {
    const formError = getPhoneBindingFormError(phoneForm)
    if (formError) {
      ElMessage.warning(formError)
      return
    }

    phoneLoading.value = true
    try {
      const request = (await import('@/utils/request')).default
      const payload = {
        phone: normalizePhone(phoneForm.phone),
        code: String(phoneForm.code ?? '').trim(),
      }
      await request.put('/client/auth/phone', payload)
      form.phone = payload.phone
      showPhoneDialog.value = false
      ElMessage.success('手机号修改成功')
      await userStore.fetchUserInfo('client')
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '修改失败'))
    } finally {
      phoneLoading.value = false
    }
  }

  async function handleSendEmailCode() {
    const formError = getEmailBindingFormError(emailForm, { requireCode: false })
    if (formError) {
      ElMessage.warning(formError)
      return
    }

    emailCodeSending.value = true
    try {
      const email = normalizeEmail(emailForm.email)
      await runWithCaptcha(async (captcha) => {
        await postCodeWithFallback('/client/auth/email-code', { email, captcha })
      })
      ElMessage.success('邮箱验证码已发送')
      emailCodeCountdown.value = 60
      if (emailCodeTimer) clearInterval(emailCodeTimer)
      emailCodeTimer = setInterval(() => {
        emailCodeCountdown.value -= 1
        if (emailCodeCountdown.value <= 0) { clearInterval(emailCodeTimer); emailCodeTimer = null; emailCodeCountdown.value = 0 }
      }, 1000)
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '邮箱验证码发送失败'))
    } finally {
      emailCodeSending.value = false
    }
  }

  async function handleChangeEmail() {
    const formError = getEmailBindingFormError(emailForm)
    if (formError) {
      ElMessage.warning(formError)
      return
    }

    emailLoading.value = true
    try {
      const request = (await import('@/utils/request')).default
      const payload = {
        email: normalizeEmail(emailForm.email),
        code: String(emailForm.code ?? '').trim(),
      }
      await request.put('/client/auth/email', payload)
      form.email = payload.email
      showEmailDialog.value = false
      ElMessage.success('邮箱修改成功')
      await userStore.fetchUserInfo('client')
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '修改失败'))
    } finally {
      emailLoading.value = false
    }
  }

  async function handleVerification() {
    const formError = getVerificationFormError(verificationForm)
    if (formError) {
      ElMessage.warning(formError)
      return
    }

    verificationLoading.value = true
    try {
      const request = (await import('@/utils/request')).default
      const res = await request.post('/client/verification/init', {
        realname: verificationForm.realName.trim(),
        idcard: verificationForm.idCard.trim(),
      })
      certifyId.value = res.data.certify_id || ''
      verificationUrl.value = resolveVerificationQrUrl(res.data)

      if (!verificationUrl.value) {
        await refreshVerificationLink()
      }

      if (verificationUrl.value) {
        ElMessage.success('二维码已生成，请扫码继续认证')
      }
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '提交失败'))
    } finally {
      verificationLoading.value = false
    }
  }

  async function handleRestartVerification() {
    verificationLoading.value = true
    canRestartVerification.value = false
    stopVerificationPolling()
    try {
      const request = (await import('@/utils/request')).default
      const res = await request.post('/client/verification/restart')
      certifyId.value = res.data.certify_id || ''
      verificationUrl.value = resolveVerificationQrUrl(res.data)

      if (!verificationUrl.value) {
        await refreshVerificationLink()
      }

      if (verificationUrl.value) {
        ElMessage.success('已重新生成二维码，请重新扫码')
      }
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '重新认证失败'))
      canRestartVerification.value = true
    } finally {
      verificationLoading.value = false
    }
  }

  async function refreshVerificationLink() {
    verificationLoading.value = true
    try {
      const request = (await import('@/utils/request')).default
      const payload = certifyId.value ? { certify_id: certifyId.value } : {}
      const res = await request.post('/client/verification/qrcode', payload)
      verificationUrl.value = resolveVerificationQrUrl(res.data)

      if (!verificationUrl.value) {
        throw new Error('未获取到实名服务商链接')
      }
    } catch (error) {
      if (!error?.__handled) ElMessage.error(toUserMessage(error?.message, '获取认证链接失败'))
    } finally {
      verificationLoading.value = false
    }
  }

  function stopVerificationPolling() {
    if (verificationPollingTimer) {
      clearInterval(verificationPollingTimer)
      verificationPollingTimer = null
    }
  }

  function startVerificationPolling() {
    stopVerificationPolling()

    if (!showVerificationDialog.value || !verificationUrl.value || !certifyId.value) {
      return
    }

    verificationPollingTimer = setInterval(() => {
      void checkVerificationStatus(true)
    }, 1000)
  }

  async function checkVerificationStatus(silent = false) {
    if (checkingStatus.value) {
      return
    }

    checkingStatus.value = true
    try {
      const request = (await import('@/utils/request')).default
      const params = certifyId.value ? { certify_id: certifyId.value } : {}
      const res = await request.get('/client/verification/status', { params })
      if (res.data.status === 1) {
        ElMessage.success('认证成功')
        showVerificationDialog.value = false
        verificationUrl.value = ''
        canRestartVerification.value = false
        stopVerificationPolling()
        await userStore.fetchUserInfo('client')
        const info = userStore.info
        if (info) {
          form.is_verified = info.is_verified || 0
          form.real_name = info.real_name || ''
          form.id_card_masked = info.id_card_masked || ''
        }
      } else if (res.data.status === 4) {
        canRestartVerification.value = false
        if (!silent) {
          ElMessage.warning(res.data.msg || '认证处理中，请稍后再试')
        }
      } else {
        canRestartVerification.value = Boolean(res.data.can_restart)
        if (!silent) {
          ElMessage.warning(res.data.msg || '认证未完成')
        }
      }
    } catch (error) {
      if (!silent && !error?.__handled) ElMessage.error('查询失败')
    } finally {
      checkingStatus.value = false
    }
  }

  return {
    // 密码
    showPasswordDialog, passwordLoading, passwordFormRef, passwordForm, passwordRules,
    handleChangePassword,
    // 手机
    showPhoneDialog, phoneLoading, phoneCodeSending, codeCountdown,
    phoneFormRef, phoneForm, phoneRules,
    openPhoneDialog, handleSendPhoneCode, handleChangePhone,
    // 邮箱
    showEmailDialog, emailLoading, emailCodeSending, emailCodeCountdown,
    emailFormRef, emailForm, emailRules,
    openEmailDialog, handleSendEmailCode, handleChangeEmail,
    // 实名
    showVerificationDialog, showVerifiedInfoDialog, verificationLoading, checkingStatus,
    verificationUrl, certifyId, canRestartVerification, verificationFormRef, verificationForm, verificationRules,
    handleVerification, refreshVerificationLink, checkVerificationStatus, handleRestartVerification,
  }
}
