<template>
  <div class="notifications-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <span class="admin-page-kicker">通知通道</span>
        <h2>邮件与短信接口</h2>
        <p>统一维护邮件 SMTP 和短信网关参数，启用状态与必填项在同一个表单闭环里校验。</p>
      </div>
    </section>

    <div class="channel-grid">
      <el-card shadow="never" class="channel-card">
        <template #header>
          <div class="card-title">
            <el-icon class="card-icon email"><Message /></el-icon>
            <span>邮件接口</span>
          </div>
        </template>

        <el-form
          ref="emailFormRef"
          :model="emailForm"
          :rules="emailRules"
          label-position="top"
          status-icon
          @submit.prevent
        >
          <el-form-item label="启用邮件" prop="enabled">
            <el-switch v-model="emailForm.enabled" />
          </el-form-item>
          <el-form-item label="SMTP 主机" prop="host">
            <el-input v-model="emailForm.host" placeholder="例如 smtp.qq.com" />
          </el-form-item>
          <el-form-item label="SMTP 端口" prop="port">
            <el-input v-model="emailForm.port" placeholder="例如 465 / 587" />
          </el-form-item>
          <el-form-item label="发件邮箱" prop="username">
            <el-input v-model="emailForm.username" placeholder="请输入发件邮箱" />
          </el-form-item>
          <el-form-item label="授权密码" prop="password">
            <el-input v-model="emailForm.password" type="password" show-password placeholder="请输入邮箱授权码" />
          </el-form-item>
          <el-form-item label="发件名称" prop="from_name">
            <el-input v-model="emailForm.from_name" placeholder="例如 创欧云" />
          </el-form-item>
          <el-form-item class="form-actions">
            <el-button type="primary" :loading="savingEmail" @click="saveEmailSettings">保存邮件配置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <el-card shadow="never" class="channel-card">
        <template #header>
          <div class="card-title">
            <el-icon class="card-icon sms"><Iphone /></el-icon>
            <span>短信接口</span>
          </div>
        </template>

        <el-form
          ref="smsFormRef"
          :model="smsForm"
          :rules="smsRules"
          label-position="top"
          status-icon
          @submit.prevent
        >
          <el-form-item label="启用短信" prop="enabled">
            <el-switch v-model="smsForm.enabled" />
          </el-form-item>
          <el-form-item label="Access Key" prop="access_key">
            <el-input v-model="smsForm.access_key" placeholder="请输入 Access Key" />
          </el-form-item>
          <el-form-item label="Secret Key" prop="secret_key">
            <el-input v-model="smsForm.secret_key" type="password" show-password placeholder="请输入 Secret Key" />
          </el-form-item>
          <el-form-item label="签名" prop="sign_name">
            <el-input v-model="smsForm.sign_name" placeholder="请输入短信签名" />
          </el-form-item>
          <el-form-item label="验证码模板" prop="template_code">
            <el-input v-model="smsForm.template_code" placeholder="请输入模板编号" />
          </el-form-item>
          <el-form-item class="form-actions">
            <el-button type="primary" :loading="savingSms" @click="saveSmsSettings">保存短信配置</el-button>
          </el-form-item>
        </el-form>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Iphone, Message } from '@element-plus/icons-vue'
import adminApi from '@/api/admin'

const emailFormRef = ref(null)
const smsFormRef = ref(null)
const savingEmail = ref(false)
const savingSms = ref(false)

const emailForm = reactive({
  enabled: false,
  host: '',
  port: '',
  username: '',
  password: '',
  from_name: '',
})

const smsForm = reactive({
  enabled: false,
  provider: 'aliyun',
  access_key: '',
  secret_key: '',
  sign_name: '',
  template_code: '',
})

const toBool = (value) => value === true || value === '1' || value === 1 || value === 'true'

function validateRequiredWhenEnabled(enabledRef, label) {
  return (_rule, value, callback) => {
    if (!enabledRef.value) {
      callback()
      return
    }

    if (String(value ?? '').trim() === '') {
      callback(new Error(`启用后必须填写${label}`))
      return
    }

    callback()
  }
}

const emailEnabled = computed(() => Boolean(emailForm.enabled))
const smsEnabled = computed(() => Boolean(smsForm.enabled))

const emailRules = computed(() => ({
  host: [{ validator: validateRequiredWhenEnabled(emailEnabled, 'SMTP 主机'), trigger: 'blur' }],
  port: [
    {
      validator: (_rule, value, callback) => {
        if (!emailEnabled.value) {
          callback()
          return
        }

        const port = Number(value)
        if (!Number.isInteger(port) || port <= 0 || port > 65535) {
          callback(new Error('SMTP 端口必须是 1-65535 的整数'))
          return
        }

        callback()
      },
      trigger: 'blur',
    },
  ],
  username: [{ validator: validateRequiredWhenEnabled(emailEnabled, '发件邮箱'), trigger: 'blur' }],
  password: [{ validator: validateRequiredWhenEnabled(emailEnabled, '授权密码'), trigger: 'blur' }],
  from_name: [{ validator: validateRequiredWhenEnabled(emailEnabled, '发件名称'), trigger: 'blur' }],
}))

const smsRules = computed(() => ({
  access_key: [{ validator: validateRequiredWhenEnabled(smsEnabled, 'Access Key'), trigger: 'blur' }],
  secret_key: [{ validator: validateRequiredWhenEnabled(smsEnabled, 'Secret Key'), trigger: 'blur' }],
  sign_name: [{ validator: validateRequiredWhenEnabled(smsEnabled, '签名'), trigger: 'blur' }],
  template_code: [{ validator: validateRequiredWhenEnabled(smsEnabled, '验证码模板'), trigger: 'blur' }],
}))

async function loadSettings() {
  try {
    const { data } = await adminApi.settings.list({ group: 'notification' })

    data.forEach((item) => {
      switch (item.key) {
        case 'email_enabled':
          emailForm.enabled = toBool(item.value)
          break
        case 'email_host':
          emailForm.host = item.value
          break
        case 'email_port':
          emailForm.port = item.value
          break
        case 'email_username':
          emailForm.username = item.value
          break
        case 'email_password':
          emailForm.password = item.value
          break
        case 'email_from_name':
          emailForm.from_name = item.value
          break
        case 'sms_enabled':
          smsForm.enabled = toBool(item.value)
          break
        case 'sms_provider':
          smsForm.provider = item.value || 'aliyun'
          break
        case 'sms_access_key':
          smsForm.access_key = item.value
          break
        case 'sms_secret_key':
          smsForm.secret_key = item.value
          break
        case 'sms_sign_name':
          smsForm.sign_name = item.value
          break
        case 'sms_template_code':
          smsForm.template_code = item.value
          break
      }
    })

    emailFormRef.value?.clearValidate?.()
    smsFormRef.value?.clearValidate?.()
  } catch {
    ElMessage.error('加载通知配置失败')
  }
}

async function saveEmailSettings() {
  const valid = await emailFormRef.value?.validate?.().catch(() => false)
  if (!valid) {
    return
  }

  savingEmail.value = true
  try {
    await adminApi.settings.save({
      group: 'notification',
      settings: {
        email_enabled: emailForm.enabled ? 1 : 0,
        email_host: String(emailForm.host || '').trim(),
        email_port: String(emailForm.port || '').trim(),
        email_username: String(emailForm.username || '').trim(),
        email_password: String(emailForm.password || '').trim(),
        email_from_name: String(emailForm.from_name || '').trim(),
      },
    })
    ElMessage.success('邮件配置已保存')
  } catch {
    ElMessage.error('保存邮件配置失败')
  } finally {
    savingEmail.value = false
  }
}

async function saveSmsSettings() {
  const valid = await smsFormRef.value?.validate?.().catch(() => false)
  if (!valid) {
    return
  }

  savingSms.value = true
  try {
    await adminApi.settings.save({
      group: 'notification',
      settings: {
        sms_enabled: smsForm.enabled ? 1 : 0,
        sms_provider: smsForm.provider,
        sms_access_key: String(smsForm.access_key || '').trim(),
        sms_secret_key: String(smsForm.secret_key || '').trim(),
        sms_sign_name: String(smsForm.sign_name || '').trim(),
        sms_template_code: String(smsForm.template_code || '').trim(),
      },
    })
    ElMessage.success('短信配置已保存')
  } catch {
    ElMessage.error('保存短信配置失败')
  } finally {
    savingSms.value = false
  }
}

onMounted(loadSettings)
</script>

<style lang="scss" scoped>
.notifications-page {
  gap: 20px;
}

.channel-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

.channel-card {
  min-height: 100%;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: $text-color-primary;
}

.card-icon {
  font-size: 18px;
}

.card-icon.email {
  color: $color-primary;
}

.card-icon.sms {
  color: $color-success;
}

.form-actions {
  margin-top: 8px;
}

@media (max-width: 960px) {
  .channel-grid {
    grid-template-columns: 1fr;
  }
}
</style>
