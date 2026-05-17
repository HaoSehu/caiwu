<template>
  <div class="home-hero-editor" v-loading="loading">
    <el-alert
      class="home-hero-editor__intro"
      type="info"
      :closable="false"
      show-icon
      title="编辑完成后点击右上角「保存设置」即可生效，前台首页最长延迟约 2 分钟。"
      description="轮播项对应官网首页左侧 Tab 与中间大标题区；特色卡片对应底部横条的小卡片。右侧插画会根据顺序自动匹配，无需手动配置。"
    />

    <section class="home-hero-editor__section">
      <header class="home-hero-editor__section-head">
        <div>
          <h3>轮播项 ({{ form.slides.length }} / {{ MAX_SLIDES }})</h3>
          <p>每个轮播项包含导航名称、主标题、描述和两个按钮；按当前顺序依次展示在首页左侧 Tab。</p>
        </div>
        <div class="home-hero-editor__section-actions">
          <el-button :icon="RefreshLeft" @click="resetSlidesToDefault">恢复默认</el-button>
          <el-button
            type="primary"
            :icon="Plus"
            :disabled="form.slides.length >= MAX_SLIDES"
            @click="addSlide"
          >
            新增轮播
          </el-button>
        </div>
      </header>

      <el-collapse v-model="activeSlideKeys" class="home-hero-editor__collapse">
        <el-collapse-item
          v-for="(slide, index) in form.slides"
          :key="`slide-${index}`"
          :name="`slide-${index}`"
        >
          <template #title>
            <div class="home-hero-editor__item-title">
              <span class="home-hero-editor__item-index">{{ index + 1 }}</span>
              <strong>{{ slide.rail_title || '未命名轮播' }}</strong>
              <span class="home-hero-editor__item-subtitle">{{ slide.title || '待填写主标题' }}</span>
            </div>
          </template>

          <el-form label-position="top" class="home-hero-editor__slide-form">
            <div class="home-hero-editor__hero-grid">
              <div class="home-hero-editor__hero-left">
                <el-form-item class="home-hero-editor__field--narrow" required>
                  <template #label>
                    <span class="home-hero-editor__label">导航名称</span>
                    <span class="home-hero-editor__label-hint">左侧 Tab 文字</span>
                  </template>
                  <el-input
                    v-model="slide.rail_title"
                    maxlength="20"
                    show-word-limit
                    placeholder="例如：官网换新"
                  />
                </el-form-item>
                <div class="home-hero-editor__action-panel">
                  <div class="home-hero-editor__action-panel-head">
                    <span class="home-hero-editor__label">按钮设置</span>
                    <span class="home-hero-editor__label-hint">主按钮与次按钮分别维护文案和跳转。</span>
                  </div>
                  <div class="home-hero-editor__action-section">
                    <div class="home-hero-editor__action-row">
                      <div class="home-hero-editor__action-type">
                        <strong>主按钮</strong>
                        <span>主行动入口</span>
                      </div>
                      <div class="home-hero-editor__action-fields">
                        <div class="home-hero-editor__inline-field">
                          <span class="home-hero-editor__inline-label">文案</span>
                          <el-input v-model="slide.primary_text" maxlength="20" placeholder="例如：立即体验" />
                        </div>
                        <div class="home-hero-editor__inline-field home-hero-editor__inline-field--wide">
                          <span class="home-hero-editor__inline-label">跳转</span>
                          <el-input
                            v-model="slide.primary_path"
                            maxlength="255"
                            placeholder="内部路径如 /products 或完整 URL"
                          />
                        </div>
                      </div>
                    </div>
                    <div class="home-hero-editor__action-row">
                      <div class="home-hero-editor__action-type">
                        <strong>次按钮</strong>
                        <span>次级入口</span>
                      </div>
                      <div class="home-hero-editor__action-fields">
                        <div class="home-hero-editor__inline-field">
                          <span class="home-hero-editor__inline-label">文案</span>
                          <el-input v-model="slide.secondary_text" maxlength="20" placeholder="例如：查看详情" />
                        </div>
                        <div class="home-hero-editor__inline-field home-hero-editor__inline-field--wide">
                          <span class="home-hero-editor__inline-label">跳转</span>
                          <el-input
                            v-model="slide.secondary_path"
                            maxlength="255"
                            placeholder="内部路径如 /about 或完整 URL"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="home-hero-editor__hero-right">
                <el-form-item class="home-hero-editor__field--title" required>
                  <template #label>
                    <span class="home-hero-editor__label">主标题</span>
                    <span class="home-hero-editor__label-hint">居中大字，建议包含关键卖点</span>
                  </template>
                  <el-input
                    v-model="slide.title"
                    maxlength="80"
                    show-word-limit
                    placeholder="例如：官网焕新 · 云上新体验"
                  />
                </el-form-item>
                <el-form-item required class="home-hero-editor__field--desc">
                  <template #label>
                    <span class="home-hero-editor__label">描述文案</span>
                    <span class="home-hero-editor__label-hint">主标题下方的小字，介绍具体能力</span>
                  </template>
                  <el-input
                    v-model="slide.desc"
                    type="textarea"
                    :rows="6"
                    maxlength="300"
                    show-word-limit
                    placeholder="一段用于介绍该轮播卖点的长文案"
                  />
                </el-form-item>
              </div>
            </div>
          </el-form>

          <footer class="home-hero-editor__item-footer">
            <el-button-group>
              <el-button
                :icon="ArrowUp"
                :disabled="index === 0"
                @click="moveSlide(index, -1)"
              >
                上移
              </el-button>
              <el-button
                :icon="ArrowDown"
                :disabled="index === form.slides.length - 1"
                @click="moveSlide(index, 1)"
              >
                下移
              </el-button>
            </el-button-group>
            <el-button
              type="danger"
              plain
              :icon="Delete"
              :disabled="form.slides.length <= 1"
              @click="removeSlide(index)"
            >
              删除
            </el-button>
          </footer>
        </el-collapse-item>
      </el-collapse>
    </section>

    <section class="home-hero-editor__section">
      <header class="home-hero-editor__section-head">
        <div>
          <h3>底部特色卡片 ({{ form.features.length }} / {{ MAX_FEATURES }})</h3>
          <p>对应首页轮播下方的横向卡片组，可承接活动入口或产品亮点。</p>
        </div>
        <div class="home-hero-editor__section-actions">
          <el-button :icon="RefreshLeft" @click="resetFeaturesToDefault">恢复默认</el-button>
          <el-button
            type="primary"
            :icon="Plus"
            :disabled="form.features.length >= MAX_FEATURES"
            @click="addFeature"
          >
            新增卡片
          </el-button>
        </div>
      </header>

      <el-table :data="form.features" stripe border class="home-hero-editor__feature-table">
        <el-table-column label="排序" width="88" align="center">
          <template #default="{ $index }">
            <el-button-group>
              <el-button
                :icon="ArrowUp"
                size="small"
                text
                :disabled="$index === 0"
                @click="moveFeature($index, -1)"
              />
              <el-button
                :icon="ArrowDown"
                size="small"
                text
                :disabled="$index === form.features.length - 1"
                @click="moveFeature($index, 1)"
              />
            </el-button-group>
          </template>
        </el-table-column>

        <el-table-column label="标签 kicker" min-width="140">
          <template #default="{ row }">
            <el-input v-model="row.kicker" maxlength="20" placeholder="例如：产品动态" />
          </template>
        </el-table-column>

        <el-table-column label="标题" min-width="200">
          <template #default="{ row }">
            <el-input v-model="row.title" maxlength="50" placeholder="例如：香港 CN2 精品线路 上线" />
          </template>
        </el-table-column>

        <el-table-column label="描述" min-width="260">
          <template #default="{ row }">
            <el-input
              v-model="row.desc"
              maxlength="120"
              type="textarea"
              :autosize="{ minRows: 2, maxRows: 3 }"
              placeholder="两行以内的补充说明"
            />
          </template>
        </el-table-column>

        <el-table-column label="跳转路径" min-width="180">
          <template #default="{ row }">
            <el-input v-model="row.path" maxlength="255" placeholder="可选，如 /products" />
          </template>
        </el-table-column>

        <el-table-column label="操作" width="96" align="center">
          <template #default="{ $index }">
            <el-button
              type="danger"
              plain
              size="small"
              :icon="Delete"
              :disabled="form.features.length <= 1"
              @click="removeFeature($index)"
            />
          </template>
        </el-table-column>
      </el-table>
    </section>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowDown,
  ArrowUp,
  Delete,
  Plus,
  RefreshLeft,
} from '@element-plus/icons-vue'
import adminApi from '@/api/admin'

const emit = defineEmits(['dirty-change'])

const loading = ref(false)
const saving = ref(false)
const MAX_SLIDES = 5
const MAX_FEATURES = 5

const form = reactive({
  slides: [],
  features: [],
})
const defaults = reactive({ slides: [], features: [] })

const serverSnapshot = ref(JSON.stringify(form))
const activeSlideKeys = ref([])

const isDirty = computed(() => JSON.stringify(form) !== serverSnapshot.value)

watch(isDirty, (dirty) => emit('dirty-change', dirty), { immediate: true })

function cloneList(list) {
  return Array.isArray(list) ? list.map((item) => ({ ...item })) : []
}

function applyHeroPayload(payload = {}) {
  const slides = cloneList(payload.slides)
  const features = cloneList(payload.features)
  form.slides = slides.length ? slides : cloneList(defaults.slides)
  form.features = features.length ? features : cloneList(defaults.features)
  serverSnapshot.value = JSON.stringify(form)
  activeSlideKeys.value = []
}

async function load() {
  loading.value = true
  try {
    const response = await adminApi.siteHero.get()
    const data = response?.data || {}
    Object.assign(defaults, {
      slides: cloneList(data?.defaults?.slides),
      features: cloneList(data?.defaults?.features),
    })
    applyHeroPayload(data)
  } catch (error) {
    ElMessage.error(error?.message || '加载首页 Banner 配置失败')
  } finally {
    loading.value = false
  }
}

function buildBlankSlide() {
  return {
    rail_title: '新轮播项',
    title: '',
    desc: '',
    primary_text: '立即体验',
    primary_path: '/products',
    secondary_text: '查看详情',
    secondary_path: '/about',
  }
}

function buildBlankFeature() {
  return {
    kicker: '新卡片',
    title: '',
    desc: '',
    path: '',
  }
}

function addSlide() {
  if (form.slides.length >= MAX_SLIDES) return
  form.slides.push(buildBlankSlide())
  activeSlideKeys.value = [`slide-${form.slides.length - 1}`]
}

function removeSlide(index) {
  if (form.slides.length <= 1) return
  form.slides.splice(index, 1)
}

function moveSlide(index, offset) {
  const target = index + offset
  if (target < 0 || target >= form.slides.length) return
  const [item] = form.slides.splice(index, 1)
  form.slides.splice(target, 0, item)
}

function addFeature() {
  if (form.features.length >= MAX_FEATURES) return
  form.features.push(buildBlankFeature())
}

function removeFeature(index) {
  if (form.features.length <= 1) return
  form.features.splice(index, 1)
}

function moveFeature(index, offset) {
  const target = index + offset
  if (target < 0 || target >= form.features.length) return
  const [item] = form.features.splice(index, 1)
  form.features.splice(target, 0, item)
}

async function resetSlidesToDefault() {
  try {
    await ElMessageBox.confirm('恢复默认会覆盖当前轮播项（下次保存才会写入数据库），确认继续？', '恢复默认轮播', {
      type: 'warning',
      confirmButtonText: '恢复默认',
      cancelButtonText: '取消',
    })
  } catch {
    return
  }
  form.slides = cloneList(defaults.slides)
  activeSlideKeys.value = []
}

async function resetFeaturesToDefault() {
  try {
    await ElMessageBox.confirm('恢复默认会覆盖当前特色卡片（下次保存才会写入数据库），确认继续？', '恢复默认卡片', {
      type: 'warning',
      confirmButtonText: '恢复默认',
      cancelButtonText: '取消',
    })
  } catch {
    return
  }
  form.features = cloneList(defaults.features)
}

function validate() {
  if (form.slides.length > MAX_SLIDES) {
    ElMessage.warning(`轮播项最多 ${MAX_SLIDES} 个`)
    return false
  }

  if (form.features.length > MAX_FEATURES) {
    ElMessage.warning(`特色卡片最多 ${MAX_FEATURES} 个`)
    return false
  }

  for (const [index, slide] of form.slides.entries()) {
    const fields = [
      ['rail_title', '导航名称'],
      ['title', '主标题'],
      ['desc', '描述'],
      ['primary_text', '主按钮文案'],
      ['primary_path', '主按钮跳转'],
      ['secondary_text', '次按钮文案'],
      ['secondary_path', '次按钮跳转'],
    ]
    for (const [key, label] of fields) {
      if (!String(slide?.[key] ?? '').trim()) {
        ElMessage.warning(`第 ${index + 1} 个轮播项的「${label}」不能为空`)
        activeSlideKeys.value = [`slide-${index}`]
        return false
      }
    }
  }

  for (const [index, feature] of form.features.entries()) {
    const fields = [
      ['kicker', '标签'],
      ['title', '标题'],
      ['desc', '描述'],
    ]
    for (const [key, label] of fields) {
      if (!String(feature?.[key] ?? '').trim()) {
        ElMessage.warning(`第 ${index + 1} 张卡片的「${label}」不能为空`)
        return false
      }
    }
  }
  return true
}

async function save() {
  if (saving.value) return false
  if (!validate()) return false

  saving.value = true
  try {
    const payload = {
      slides: form.slides.map((slide) => ({ ...slide })),
      features: form.features.map((feature) => ({ ...feature })),
    }
    const response = await adminApi.siteHero.save(payload)
    const data = response?.data || {}
    applyHeroPayload({
      slides: data.slides || payload.slides,
      features: data.features || payload.features,
    })
    ElMessage.success(response?.message || '首页 Banner 已保存')
    return true
  } catch (error) {
    ElMessage.error(error?.message || '保存失败')
    return false
  } finally {
    saving.value = false
  }
}

defineExpose({ load, save, isDirty, saving })
</script>

<style scoped lang="scss">
.home-hero-editor {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.home-hero-editor__intro {
  border-radius: $base-border-radius;
}

.home-hero-editor__section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.home-hero-editor__section-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;

  h3 {
    margin: 0;
    color: $text-color-primary;
    font-size: 15px;
    font-weight: 600;
  }

  p {
    margin: 4px 0 0;
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.7;
  }
}

.home-hero-editor__section-actions {
  display: inline-flex;
  gap: 10px;
}

.home-hero-editor__collapse {
  border: 1px solid $border-color;
  border-radius: $base-border-radius;
  background: $bg-color-card;
}

.home-hero-editor__item-title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;

  strong {
    color: $text-color-primary;
    font-size: 14px;
  }
}

.home-hero-editor__item-index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 999px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
}

.home-hero-editor__item-subtitle {
  color: $text-color-placeholder;
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 320px;
}

.home-hero-editor__slide-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 4px 4px 0;
}

.home-hero-editor__slide-form :deep(.el-form-item) {
  margin-bottom: 0;
}

.home-hero-editor__row {
  display: block;
}

.home-hero-editor__hero-grid {
  display: grid;
  grid-template-columns: minmax(0, 480px) minmax(0, 1fr);
  gap: 24px 20px;
  align-items: start;
}

.home-hero-editor__hero-left {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.home-hero-editor__hero-right {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.home-hero-editor__field--narrow :deep(.el-input) {
  max-width: 100%;
}

.home-hero-editor__field--title :deep(.el-input) {
  max-width: 100%;
}

.home-hero-editor__field--desc :deep(.el-textarea) {
  max-width: 100%;
}

.home-hero-editor__label {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
  margin-right: 8px;
}

.home-hero-editor__label-hint {
  color: $text-color-placeholder;
  font-size: 12px;
  font-weight: 400;
}

.home-hero-editor__action-panel {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.home-hero-editor__action-panel-head {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  min-height: 22px;
  flex-wrap: wrap;
}

.home-hero-editor__action-section {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  align-items: start;
  min-width: 0;
}

.home-hero-editor__action-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  min-width: 0;
}

.home-hero-editor__action-type {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 20px;

  strong {
    color: $text-color-primary;
    font-size: 12px;
    font-weight: 600;
  }

  span {
    color: $text-color-placeholder;
    font-size: 11px;
  }
}

.home-hero-editor__action-fields {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
  width: 100%;
  align-content: start;
}

.home-hero-editor__inline-field {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.home-hero-editor__inline-field--wide {
  min-width: 0;
}

.home-hero-editor__inline-field :deep(.el-input) {
  flex: 1 1 auto;
  min-width: 0;
}

.home-hero-editor__inline-label {
  flex: 0 0 32px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1;
}

.home-hero-editor__item-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 12px;
  border-top: 1px dashed $divider-color;
  margin-top: 12px;
}

.home-hero-editor__feature-table {
  border-radius: $base-border-radius;
}

@media (max-width: 1280px) {
  .home-hero-editor__hero-grid {
    grid-template-columns: 1fr;
  }
}
</style>
