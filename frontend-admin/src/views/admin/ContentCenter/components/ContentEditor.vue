<template>
  <el-dialog
    v-model="visible"
    :title="articleDialogTitle"
    width="1100px"
    top="4vh"
    destroy-on-close
    class="content-dialog content-editor-dialog"
    @closed="emit('closed')"
  >
    <div v-loading="articleDetailLoading">
      <div class="dialog-intro">
        <strong>{{ pageTitle }}编辑器</strong>
        <p>统一使用 Markdown 编写正文，支持标题、列表、表格、引用和代码块，并兼容已有 HTML 内容。</p>
      </div>

      <el-form
        ref="articleFormRef"
        :model="articleForm"
        :rules="articleRules"
        label-position="top"
      >
        <section class="dialog-section">
          <div class="dialog-section-header">
            <strong>基础信息</strong>
            <span>设置标题、分类、状态和发布时间</span>
          </div>

          <div class="dialog-grid">
            <el-form-item class="dialog-span-2" label="标题" prop="title">
              <el-input v-model="articleForm.title" maxlength="200" show-word-limit placeholder="请输入标题" />
            </el-form-item>

            <el-form-item label="所属分类" prop="category_id">
              <el-select
                v-model="articleForm.category_id"
                filterable
                placeholder="请选择分类"
                style="width: 100%"
              >
                <el-option
                  v-for="item in categories"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>

            <el-form-item label="状态" prop="status">
              <el-select v-model="articleForm.status" placeholder="请选择状态">
                <el-option
                  v-for="item in statusOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>

            <el-form-item label="别名">
              <el-input v-model="articleForm.slug" maxlength="220" placeholder="留空自动生成" />
            </el-form-item>

            <el-form-item label="发布时间">
              <el-date-picker
                v-model="articleForm.publish_at"
                type="datetime"
                value-format="YYYY-MM-DD HH:mm:ss"
                placeholder="留空则按保存时间或草稿状态处理"
                style="width: 100%"
              />
            </el-form-item>

            <el-form-item label="排序值">
              <el-input-number v-model="articleForm.sort_order" :min="0" :max="999999" controls-position="right" />
            </el-form-item>

            <el-form-item label="操作人">
              <el-input v-model="articleForm.operator" maxlength="50" placeholder="例如：admin#1" />
            </el-form-item>

            <el-form-item class="dialog-span-2" label="关键词">
              <el-input v-model="articleForm.keywords" maxlength="255" placeholder="多个关键词用逗号分隔" />
              <p class="field-help">同时作为 SEO <code>meta keywords</code>；前台页面标题与描述留空时也会尝试按该词语生成。</p>
            </el-form-item>

            <el-form-item class="dialog-span-2" label="SEO 标题（可选）">
              <el-input
                v-model="articleForm.meta_title"
                maxlength="200"
                show-word-limit
                placeholder="留空则使用主标题；建议 20 - 50 字，含核心关键词"
              />
              <p class="field-help">浏览器标签页与搜索结果标题。留空时将使用上方“标题”字段。</p>
            </el-form-item>

            <el-form-item class="dialog-span-2" label="SEO 描述（可选）">
              <el-input
                v-model="articleForm.meta_description"
                type="textarea"
                :rows="2"
                maxlength="500"
                show-word-limit
                placeholder="留空则使用摘要；建议 60 - 160 字，准确描述本文核心内容"
              />
              <p class="field-help">用作 <code>meta description</code> 与 OG / Twitter Card 描述。留空时优先使用摘要。</p>
            </el-form-item>

            <el-form-item class="dialog-span-2" label="内容属性">
              <div class="switch-row">
                <el-switch
                  v-model="articleForm.is_pinned"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="置顶"
                  inactive-text="普通"
                  @change="handlePinnedChange"
                />
                <el-switch
                  v-model="articleForm.is_recommended"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="推荐"
                  inactive-text="不推荐"
                  @change="handleRecommendedChange"
                />
              </div>
              <p class="field-help">置顶与推荐互斥：置顶公告会显示在首页左上角带封面图，推荐公告会显示在首页下方推荐位，一个公告只能选择其中一种展位。</p>
            </el-form-item>

            <el-form-item
              v-if="articleForm.is_pinned === 1"
              class="dialog-span-2"
              label="置顶封面图"
              prop="cover_image"
              :rules="[{ required: true, message: '置顶公告必须上传封面图', trigger: 'change' }]"
            >
              <div class="cover-upload-area">
                <div v-if="articleForm.cover_image" class="cover-current">
                  <img :src="articleForm.cover_image" class="cover-preview" />
                </div>
                <div v-else class="cover-empty">
                  <el-icon :size="28" color="var(--el-text-color-placeholder)"><Picture /></el-icon>
                  <span>未选择图片</span>
                </div>
                <div class="cover-actions">
                  <el-button type="primary" plain size="small" @click="showImageLibrary = true">
                    <el-icon><FolderOpened /></el-icon>
                    <span>从图库选择</span>
                  </el-button>
                  <el-button v-if="articleForm.cover_image" size="small" type="danger" plain @click="articleForm.cover_image = ''">移除图片</el-button>
                </div>
              </div>
              <p class="field-help">置顶公告封面图将显示在首页公告区左上角，建议尺寸 400×220，支持 jpg/png/webp，最大 5MB。</p>

              <ImageLibraryPicker
                v-model="showImageLibrary"
                group="content"
                @confirm="handleImageConfirm"
              />
            </el-form-item>

            <el-form-item class="dialog-span-2" label="摘要">
              <el-input
                v-model="articleForm.summary"
                type="textarea"
                :rows="3"
                maxlength="500"
                show-word-limit
                placeholder="用于列表摘要和前台简介"
              />
            </el-form-item>

            <el-form-item class="dialog-span-2" label="备注">
              <el-input
                v-model="articleForm.remark"
                type="textarea"
                :rows="2"
                maxlength="255"
                show-word-limit
                placeholder="用于后台备注，不在前台展示"
              />
            </el-form-item>
          </div>
        </section>

        <section class="dialog-section">
          <div class="dialog-section-header">
            <strong>正文内容</strong>
          </div>

          <div class="editor-layout">
            <el-form-item class="editor-source" label="Markdown 正文" prop="content">
              <el-input
                v-model="articleForm.content"
                type="textarea"
                :rows="18"
                maxlength="30000"
                show-word-limit
                placeholder="请输入 Markdown 正文，例如：# 标题、## 小标题、- 列表、```代码块```"
              />
              <p class="field-help">支持标准 Markdown 语法，保存后客户端按 Markdown 渲染；旧 HTML 内容也可继续兼容显示。</p>
              <el-alert
                v-if="suspiciousMarkdownIssues.length"
                class="editor-markdown-alert"
                title="检测到可能损坏的 Markdown 标记"
                type="warning"
                :closable="false"
                show-icon
              >
                <template #default>
                  <ul class="editor-markdown-alert__list">
                    <li v-for="issue in suspiciousMarkdownIssues" :key="issue">
                      {{ issue }}
                    </li>
                  </ul>
                </template>
              </el-alert>
            </el-form-item>
          </div>
        </section>
      </el-form>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :loading="articleSaving" @click="emit('submit')">保存</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { FolderOpened, Picture } from '@element-plus/icons-vue'
import ImageLibraryPicker from './ImageLibraryPicker.vue'

const props = defineProps({
  articleForm: { type: Object, required: true },
  articleFormRef: { type: Object, default: null },
  articleDialogTitle: { type: String, default: '' },
  pageTitle: { type: String, default: '' },
  articleDetailLoading: { type: Boolean, default: false },
  articleSaving: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  articleRules: { type: Object, default: () => ({}) },
})

const visible = defineModel({ type: Boolean })
const emit = defineEmits(['submit', 'closed'])

const showImageLibrary = ref(false)

const suspiciousMarkdownIssues = computed(() => {
  const source = String(props.articleForm.content || '')

  if (!source.trim()) {
    return []
  }

  const issues = []
  const lines = source.split(/\r?\n/)
  const orphanMarkerLines = lines
    .map((line, index) => ({ text: line.trim(), lineNumber: index + 1 }))
    .filter(({ text }) => ['**', '__', '****', '____'].includes(text))
    .map(({ lineNumber }) => lineNumber)

  if (orphanMarkerLines.length) {
    issues.push(`第 ${orphanMarkerLines.slice(0, 3).join('、')} 行存在孤立的加粗标记，前台会直接显示星号。`)
  }

  if (/\*{4,}/.test(source)) {
    issues.push('检测到连续 4 个及以上的 `*`，通常表示加粗标记写断了。')
  }

  const doubleStarCount = (source.match(/\*\*/g) || []).length

  if (doubleStarCount % 2 !== 0) {
    issues.push('检测到 `**` 数量不成对，部分加粗内容可能不会生效。')
  }

  return issues
})

function handleImageConfirm(url) {
  props.articleForm.cover_image = url
}

function handlePinnedChange(value) {
  if (Number(value) === 1 && Number(props.articleForm.is_recommended) === 1) {
    props.articleForm.is_recommended = 0
  }
  if (Number(value) === 0) {
    props.articleForm.cover_image = ''
    props.articleFormRef?.clearValidate?.('cover_image')
  }
}

function handleRecommendedChange(value) {
  if (Number(value) === 1 && Number(props.articleForm.is_pinned) === 1) {
    props.articleForm.is_pinned = 0
    props.articleForm.cover_image = ''
    props.articleFormRef?.clearValidate?.('cover_image')
  }
}
</script>

<style scoped lang="scss">
.dialog-intro {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-soft;
}

.dialog-intro strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.dialog-intro p {
  margin-top: 6px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.dialog-section {
  padding: 16px;
  border: 1px solid $divider-color;
  border-radius: 12px;
  background: $bg-color-card;
}

.dialog-section + .dialog-section {
  margin-top: 14px;
}

.dialog-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.dialog-section-header strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.dialog-section-header span {
  color: $text-color-secondary;
  font-size: 12px;
}

.dialog-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
}

.dialog-span-2 {
  grid-column: span 2;
}

.switch-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.editor-layout {
  display: block;
}

.editor-source {
  margin-bottom: 0;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.field-help {
  margin-top: 6px;
  color: $text-color-placeholder;
  font-size: 12px;
  line-height: 1.6;
}

.editor-markdown-alert {
  margin-top: 12px;
}

.editor-markdown-alert__list {
  margin: 0;
  padding-left: 18px;
}

.editor-markdown-alert__list li + li {
  margin-top: 4px;
}

.cover-upload-area {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.cover-current {
  width: 200px;
  height: 120px;
  border: 1px solid $divider-color;
  border-radius: 8px;
  overflow: hidden;
}

.cover-preview {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 200px;
  height: 120px;
  border: 1px dashed $divider-color;
  border-radius: 8px;
  color: $text-color-placeholder;
  font-size: 12px;
}

.cover-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 4px;
}

.content-dialog :deep(.el-dialog__body) {
  padding-top: 12px;
}

.content-editor-dialog :deep(.el-dialog__body) {
  max-height: calc(100vh - 180px);
  overflow: auto;
}

@media (max-width: 900px) {
  .dialog-grid {
    grid-template-columns: 1fr;
  }

  .dialog-span-2 {
    grid-column: span 1;
  }

  .dialog-section-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
