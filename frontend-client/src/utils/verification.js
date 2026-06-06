export const IDENTITY_CARD_PATTERN = /^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/
export const PHONE_PATTERN = /^1[3-9]\d{9}$/
export const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function normalizePhone(phone) {
  const digits = String(phone ?? '').trim().replace(/\D+/g, '')

  if (digits.startsWith('86') && digits.length === 13) {
    return digits.slice(2)
  }

  return digits
}

export function normalizeEmail(email) {
  return String(email ?? '').trim().toLowerCase()
}

export function getPhoneBindingFormError(form, options = {}) {
  const phone = normalizePhone(form?.phone)
  const code = String(form?.code ?? '').trim()
  const requireCode = options.requireCode !== false

  if (!phone) {
    return '请输入手机号'
  }

  if (!PHONE_PATTERN.test(phone)) {
    return '请输入正确的手机号'
  }

  if (requireCode && !code) {
    return '请输入短信验证码'
  }

  if (requireCode && !/^\d{6}$/.test(code)) {
    return '请输入6位短信验证码'
  }

  return ''
}

export function getEmailBindingFormError(form, options = {}) {
  const email = normalizeEmail(form?.email)
  const code = String(form?.code ?? '').trim()
  const requireCode = options.requireCode !== false

  if (!email) {
    return '请输入邮箱'
  }

  if (!EMAIL_PATTERN.test(email)) {
    return '请输入有效邮箱'
  }

  if (requireCode && !code) {
    return '请输入邮箱验证码'
  }

  if (requireCode && !/^\d{6}$/.test(code)) {
    return '请输入6位邮箱验证码'
  }

  return ''
}

export function getVerificationFormError(form) {
  const realName = typeof form?.realName === 'string' ? form.realName.trim() : ''
  const idCard = typeof form?.idCard === 'string' ? form.idCard.trim() : ''

  if (!realName) {
    return '请输入真实姓名'
  }

  if (!idCard) {
    return '请输入身份证号'
  }

  if (!IDENTITY_CARD_PATTERN.test(idCard)) {
    return '身份证号格式不正确'
  }

  return ''
}

export function resolveVerificationQrUrl(payload) {
  if (typeof payload === 'string') {
    return payload.trim()
  }

  if (!payload || typeof payload !== 'object') {
    return ''
  }

  const proxyUrl = typeof payload.proxy_url === 'string' ? payload.proxy_url.trim() : ''
  if (proxyUrl) {
    return proxyUrl
  }

  return typeof payload.url === 'string' ? payload.url.trim() : ''
}
