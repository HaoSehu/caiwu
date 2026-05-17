<template>
  <div class="site-ops-page admin-page">
    <section class="admin-page-head">
      <div class="admin-page-heading">
        <h1 class="admin-page-title">{{ pageTitle }}</h1>
        <p class="admin-page-desc">{{ pageDesc }}</p>
      </div>
      <div v-if="showPageActions" class="page-actions">
        <el-button :icon="RefreshRight" @click="handleRefresh">刷新</el-button>
        <el-button
          type="primary"
          :icon="Check"
          :loading="saving || heroSaving"
          @click="handleSave"
        >
          保存设置
        </el-button>
      </div>
    </section>

    <el-card
      v-if="activeTab === 'basic' || activeTab === 'seo'"
      shadow="never"
      class="site-ops-card"
      v-loading="loading"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        class="site-ops-form"
      >
        <template v-for="section in currentSections" :key="section.key">
          <div v-if="section.title" class="form-section-head">
            <div class="form-section-title">{{ section.title }}</div>
            <div v-if="section.desc" class="form-section-desc">{{ section.desc }}</div>
          </div>
          <div class="field-grid" :class="{ 'field-grid--compact': section.compact }">
            <div
              v-for="field in section.fields"
              :key="field.key"
              class="field-cell"
              :class="{ 'is-wide': field.wide }"
            >
              <el-form-item :label="field.label" :prop="field.key">
                <el-input
                  v-if="field.type === 'textarea'"
                  v-model="form[field.key]"
                  type="textarea"
                  :rows="field.rows || 3"
                  :maxlength="field.maxlength"
                  :placeholder="field.placeholder || ''"
                  show-word-limit
                />
                <el-input
                  v-else-if="field.type === 'image'"
                  v-model="form[field.key]"
                  :maxlength="field.maxlength"
                  :placeholder="field.placeholder || ''"
                >
                  <template #append>
                    <el-button @click="handleUploadImage(field)">上传图片</el-button>
                  </template>
                </el-input>
                <el-input
                  v-else
                  v-model="form[field.key]"
                  :maxlength="field.maxlength"
                  :placeholder="field.placeholder || ''"
                />
              </el-form-item>
              <p v-if="field.help" class="field-help">{{ field.help }}</p>
              <div v-if="field.preview === 'image' && form[field.key]" class="field-preview">
                <img :src="form[field.key]" :alt="field.label" />
              </div>
            </div>
          </div>
        </template>
      </el-form>
    </el-card>

    <el-card v-else-if="heroEditorLoaded" v-show="activeTab === 'home_hero'" shadow="never" class="site-ops-card site-ops-card--hero">
      <el-alert
        v-if="heroDirty"
        class="hero-dirty-alert"
        type="warning"
        :closable="false"
        show-icon
        title="你当前看到的是未保存修改，前台不会立即同步。"
        description="请点击右上角“保存设置”后再到前台核对；如果直接刷新或离开页面，当前改动会丢失。"
      />
      <Suspense timeout="0">
        <template #default>
          <HomeHeroEditor ref="heroEditorRef" @dirty-change="heroDirty = $event" />
        </template>
        <template #fallback>
          <AdminAsyncPane />
        </template>
      </Suspense>
    </el-card>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, reactive, ref, watch } from 'vue'
import { Check, RefreshRight } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import adminApi from '@/api/admin'
import AdminAsyncPane from '@/components/common/AdminAsyncPane.vue'

const HomeHeroEditor = defineAsyncComponent(() => import('./SiteOps/HomeHeroEditor.vue'))

const props = defineProps({
  mode: {
    type: String,
    default: 'basic',
    validator: (value) => ['basic', 'seo', 'home_hero'].includes(value),
  },
})

const VALID_MODES = ['basic', 'seo', 'home_hero']

const activeTab = computed(() => (
  VALID_MODES.includes(props.mode) ? props.mode : 'basic'
))
const heroEditorLoaded = ref(activeTab.value === 'home_hero')

watch(activeTab, (value) => {
  if (value === 'home_hero') {
    heroEditorLoaded.value = true
  }
})

const PAGE_META = {
  basic: {
    title: '基础信息',
    desc: '维护站点名称、Logo、Favicon、官方联系方式与备案号，保存后前台会同步使用，最长延迟约 2 分钟。',
  },
  seo: {
    title: 'SEO 设置',
    desc: '维护站点级 SEO 默认值（描述、关键词、canonical、robots、各搜索引擎站长平台验证码），页面未单独配置时使用。',
  },
  home_hero: {
    title: '首页 Banner',
    desc: '维护官网首页 Banner 与轮播媒体素材，保存后前台会同步使用，最长延迟约 2 分钟。',
  },
}
const pageTitle = computed(() => PAGE_META[activeTab.value]?.title || PAGE_META.basic.title)
const pageDesc = computed(() => PAGE_META[activeTab.value]?.desc || PAGE_META.basic.desc)

const tabConfigs = {
  basic: {
    group: 'basic',
    fields: [
      {
        key: 'site_name',
        label: '站点名称',
        maxlength: 50,
        placeholder: '例如：创欧云',
        help: '显示在浏览器标题、页头 Logo 旁以及版权声明中。',
      },
      {
        key: 'browser_title',
        label: '浏览器标题',
        maxlength: 80,
        placeholder: '留空则默认使用站点名称',
        help: '浏览器标签页的文案，可与站点名称不同用于 SEO。',
      },
      {
        key: 'site_logo',
        label: '站点 Logo',
        maxlength: 255,
        placeholder: '/branding/logo.svg',
        help: '填写可访问的图片路径或外链 URL。',
        preview: 'image',
        wide: true,
      },
      {
        key: 'site_favicon',
        label: '站点 Favicon',
        maxlength: 255,
        placeholder: '/branding/logo1.svg',
        help: '浏览器标签页图标，建议使用方形图像。',
        preview: 'image',
        wide: true,
      },
      {
        key: 'service_phone',
        label: '官方QQ群',
        maxlength: 40,
        placeholder: '例如：123456789',
        help: '显示在用户中心官方群聊卡片的群号。',
      },
      {
        key: 'support_group_qr',
        label: '官方群聊二维码',
        type: 'image',
        maxlength: 255,
        placeholder: '上传二维码图片或填写可访问图片地址',
        help: '建议上传清晰的方形群聊二维码，保存后前台会同步展示。',
        preview: 'image',
        wide: true,
      },
      {
        key: 'support_group_link',
        label: '入群链接',
        maxlength: 255,
        placeholder: '例如：https://qm.qq.com/cgi-bin/qm/qr?k=xxxxx',
        help: '用户点击"加入群聊"按钮后跳转的链接，留空则隐藏按钮。',
      },
      {
        key: 'terms_url',
        label: '服务条款链接',
        maxlength: 255,
        placeholder: '例如：https://www.example.com/terms',
        help: '注册页"服务条款"的跳转地址，留空则隐藏该链接。',
      },
      {
        key: 'privacy_url',
        label: '隐私政策链接',
        maxlength: 255,
        placeholder: '例如：https://www.example.com/privacy',
        help: '注册页"隐私政策"的跳转地址，留空则隐藏该链接。',
      },
    ],
  },
  seo: {
    group: 'seo',
    sections: [
      {
        key: 'content',
        title: '内容默认值',
        desc: '全站 meta / OG / JSON-LD 的兜底值，页面单独配置时会覆盖这里。',
        fields: [
          {
            key: 'site_description',
            label: '站点默认描述',
            type: 'textarea',
            rows: 3,
            maxlength: 300,
            placeholder: '出现在搜索结果与社交卡片上的介绍文案，建议 60–150 字',
            help: '写入 meta description 与 OG description；页面未单独设置时使用。',
            wide: true,
          },
          {
            key: 'site_keywords',
            label: '站点默认关键词',
            type: 'textarea',
            rows: 2,
            maxlength: 255,
            placeholder: '多个关键词用英文逗号分隔，例如：云服务器, 独立服务器, 香港服务器',
            help: '写入 meta keywords。主流搜索引擎权重已低，对站内搜索仍有帮助。',
            wide: true,
          },
          {
            key: 'canonical_base',
            label: 'canonical 主域名',
            maxlength: 200,
            placeholder: '例如：https://www.example.com',
            help: '用于拼接页面 canonical 与 og:url 的绝对地址；留空则使用当前域名。',
            wide: true,
          },
          {
            key: 'robots_directive',
            label: 'robots 全局指令',
            maxlength: 60,
            placeholder: 'index,follow',
            help: '默认 meta robots 值。常用：index,follow 或 noindex,nofollow。',
            wide: true,
          },
        ],
      },
      {
        key: 'verify',
        title: '搜索引擎站长验证',
        desc: '在各平台后台拿到验证码后填入即可，留空对应 meta 不输出。只填 content 值，不要带 <meta> 标签。',
        compact: true,
        fields: [
          {
            key: 'verify_google',
            label: 'Google',
            maxlength: 200,
            placeholder: '粘贴验证码',
            help: 'Search Console → <meta name="google-site-verification">',
          },
          {
            key: 'verify_baidu',
            label: '百度',
            maxlength: 200,
            placeholder: '粘贴验证码',
            help: '百度搜索资源平台 → <meta name="baidu-site-verification">',
          },
          {
            key: 'verify_bing',
            label: '必应',
            maxlength: 200,
            placeholder: '粘贴验证码',
            help: 'Bing Webmaster Tools → <meta name="msvalidate.01">',
          },
          {
            key: 'verify_360',
            label: '360',
            maxlength: 200,
            placeholder: '粘贴验证码',
            help: '360 站长平台 → <meta name="360-site-verification">',
          },
          {
            key: 'verify_sogou',
            label: '搜狗',
            maxlength: 200,
            placeholder: '粘贴验证码',
            help: '搜狗站长平台 → <meta name="sogou_site_verification">',
          },
        ],
      },
      {
        key: 'indexnow',
        title: 'IndexNow 推送',
        desc: '填入 8–128 位字母 / 数字 / 横线组合的密钥，保存后后端会自动在官网 dist 根目录写入 {key}.txt 验证文件；留空则关闭 IndexNow。',
        compact: true,
        fields: [
          {
            key: 'indexnow_key',
            label: 'IndexNow Key',
            maxlength: 128,
            placeholder: '例如：a1b2c3d4e5f6a7b8c9d0e1f2',
            help: 'Bing Webmaster / IndexNow 协议用于站点所有权校验，保存后执行 `php artisan site:indexnow-submit-sitemap` 可提交已收录 URL。',
            wide: true,
          },
        ],
      },
    ],
  },
}

const currentTabConfig = computed(() => tabConfigs[activeTab.value] || tabConfigs.basic)
const currentSections = computed(() => {
  const cfg = currentTabConfig.value
  if (Array.isArray(cfg.sections)) return cfg.sections
  return [{ key: 'default', title: '', desc: '', fields: cfg.fields || [] }]
})
const currentFields = computed(() => currentSections.value.flatMap((s) => s.fields || []))

const form = reactive({})
const formRef = ref(null)
const loading = ref(false)
const saving = ref(false)

const heroEditorRef = ref(null)
const heroDirty = ref(false)
const heroSaving = computed(() => heroEditorRef.value?.saving?.value ?? false)

const rules = computed(() => {
  const map = {}
  for (const field of currentFields.value) {
    if (!field.required) continue
    map[field.key] = [
      { required: true, message: `请填写${field.label}`, trigger: 'blur' },
    ]
  }
  return map
})

function resetForm() {
  for (const key of Object.keys(form)) delete form[key]
  for (const field of currentFields.value) {
    form[field.key] = ''
  }
}

async function loadSettings() {
  loading.value = true
  resetForm()
  try {
    const response = await adminApi.settings.list({ group: currentTabConfig.value.group })
    const list = Array.isArray(response?.data) ? response.data : []
    const map = Object.fromEntries(list.map((item) => [item.key, item.value]))
    for (const field of currentFields.value) {
      const raw = map[field.key]
      form[field.key] = raw === undefined || raw === null ? '' : String(raw)
    }
    formRef.value?.clearValidate?.()
  } catch (error) {
    ElMessage.error(error?.message || '加载站务设置失败')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  const valid = await formRef.value?.validate?.().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    const payload = {}
    for (const field of currentFields.value) {
      payload[field.key] = form[field.key] ?? ''
    }
    await adminApi.settings.save({
      group: currentTabConfig.value.group,
      settings: payload,
    })
    ElMessage.success('站务设置已保存')
  } catch (error) {
    ElMessage.error(error?.message || '保存站务设置失败')
  } finally {
    saving.value = false
  }
}

async function handleUploadImage(field) {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/*'

  input.onchange = async () => {
    const file = input.files?.[0]
    if (!file) return

    const formData = new FormData()
    formData.append('file', file)
    formData.append('group', 'site-settings')

    try {
      const res = await adminApi.content.media.upload(formData)
      form[field.key] = String(res.data?.url || '')
      ElMessage.success('图片上传成功')
    } catch (error) {
      ElMessage.error(error?.message || '图片上传失败')
    }
  }

  input.click()
}

function handleRefresh() {
  if (activeTab.value === 'home_hero') {
    heroEditorRef.value?.load?.()
    return
  }
  loadSettings()
}

function handleSave() {
  if (activeTab.value === 'home_hero') {
    heroEditorRef.value?.save?.()
    return
  }
  saveSettings()
}

const showPageActions = computed(() => (
  activeTab.value === 'basic' || activeTab.value === 'seo' || activeTab.value === 'home_hero'
))

watch([activeTab, heroEditorRef], ([tab, editor]) => {
  if (tab === 'home_hero') {
    editor?.load?.()
    return
  }
  loadSettings()
}, { immediate: true })
</script>

<style scoped lang="scss">
.site-ops-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.admin-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.admin-page-heading {
  min-width: 0;
  flex: 1 1 auto;
}

.admin-page-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: $text-color-primary;
}

.admin-page-desc {
  margin: 6px 0 0;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.8;
}

.page-actions {
  display: inline-flex;
  gap: 10px;
  flex-shrink: 0;
}

.site-ops-tabs {
  margin: 0;
}

.site-ops-card {
  border-radius: $base-border-radius;
}

.site-ops-card--hero {
  background: $bg-color-soft;
}

.hero-dirty-alert {
  margin-bottom: 16px;
}

.form-section-head {
  margin-top: 4px;
  padding-bottom: 8px;
  border-bottom: 1px solid $divider-color;
}

.form-section-head + .field-grid {
  margin-top: 12px;
}

.field-grid + .form-section-head {
  margin-top: 24px;
}

.form-section-title {
  font-size: 14px;
  font-weight: 600;
  color: $text-color-primary;
}

.form-section-desc {
  margin-top: 4px;
  font-size: 12px;
  color: $text-color-placeholder;
  line-height: 1.6;
}

.field-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 24px;
}

.field-grid--compact {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 24px;
}

.field-grid--compact .field-cell .field-help {
  font-size: 11px;
  color: $text-color-placeholder;
}

.field-grid--compact .field-cell :deep(.el-form-item) {
  margin-bottom: 4px;
}

.field-cell {
  min-width: 0;
}

.field-cell.is-wide {
  grid-column: 1 / -1;
}

.field-help {
  margin: 6px 0 0;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

.field-preview {
  margin-top: 10px;
  padding: 10px;
  border: 1px dashed $border-color;
  border-radius: $sm-border-radius;
  background: $bg-color-soft;
}

.field-preview img {
  display: block;
  max-width: 240px;
  max-height: 80px;
  object-fit: contain;
}

@media (max-width: 720px) {
  .field-grid {
    grid-template-columns: 1fr;
  }

  .field-cell.is-wide {
    grid-column: auto;
  }
}
</style>
