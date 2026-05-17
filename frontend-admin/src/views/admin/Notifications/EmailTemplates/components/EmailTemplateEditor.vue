<template>
  <div class="email-template-editor">
    <div class="template-meta">
      <div class="token-list">
        <span class="token-label">可用变量</span>
        <el-tag
          v-for="variable in templateDefinition.variables"
          :key="`${templateDefinition.code}-${variable}`"
          size="small"
          effect="plain"
        >
          {{ variable }}
        </el-tag>
      </div>
    </div>

    <div class="preview-params">
      <div
        v-for="variable in templateDefinition.variables"
        :key="`${templateDefinition.code}-sample-${variable}`"
        class="preview-param"
      >
        <span>{{ variable }}</span>
        <strong>{{ getPreviewValue(templateDefinition, variable) }}</strong>
      </div>
    </div>

    <el-form :model="templateDefinition" label-width="88px" class="template-form">
      <el-form-item label="邮件主题">
        <div class="subject-block">
          <el-input v-model="templateDefinition.subject" />
          <div class="subject-preview">
            <span>主题预览</span>
            <strong>{{ renderPreviewSubject(templateDefinition) }}</strong>
          </div>
        </div>
      </el-form-item>

      <el-form-item label="邮件正文">
        <div class="editor-grid">
          <section class="editor-pane">
            <div class="pane-header">
              <strong>HTML 正文片段</strong>
              <span>支持旧文本模板，未检测到 HTML 时会自动转为样式化段落。</span>
            </div>
            <el-input
              v-model="templateDefinition.content"
              type="textarea"
              :rows="18"
              resize="vertical"
              placeholder="请输入 HTML 正文片段"
              class="template-code-input"
            />
          </section>

          <section class="preview-pane">
            <div class="pane-header">
              <strong>实时预览</strong>
              <span>{{ siteName }} 站点风格</span>
            </div>
            <iframe
              class="preview-frame"
              :srcdoc="buildPreviewDocument(templateDefinition)"
              sandbox=""
              title="邮件模板预览"
            />
          </section>
        </div>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
defineProps({
  templateDefinition: {
    type: Object,
    required: true,
  },
  siteName: {
    type: String,
    default: '创欧云',
  },
  getPreviewValue: {
    type: Function,
    required: true,
  },
  renderPreviewSubject: {
    type: Function,
    required: true,
  },
  buildPreviewDocument: {
    type: Function,
    required: true,
  },
})
</script>

<style lang="scss" scoped>
.email-template-editor {
  padding: 8px 4px 12px;
}

.template-meta {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.token-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.token-label {
  color: #6b7280;
  font-size: 12px;
}

.preview-params {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 18px;
}

.preview-param {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafbfd;

  span {
    color: #6b7280;
    font-size: 12px;
    white-space: nowrap;
  }

  strong {
    color: #111827;
    font-size: 12px;
    text-align: right;
    word-break: break-word;
  }
}

.template-form {
  :deep(.el-form-item) {
    align-items: stretch;
  }

  :deep(.el-form-item__content) {
    align-items: stretch;
  }

  :deep(.el-form-item:last-child) {
    margin-bottom: 0;
  }
}

.subject-block {
  width: 100%;
}

.subject-preview {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  background: #f8fbff;
  border: 1px solid #dbe7ff;

  span {
    color: #6b7280;
    font-size: 12px;
  }

  strong {
    color: #111827;
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
  }
}

.editor-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
  width: 100%;
}

.editor-pane,
.preview-pane {
  display: flex;
  flex-direction: column;
  min-width: 0;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  background: #fff;
}

.pane-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid #eef1f6;
  background: #fafbfd;

  strong {
    color: #111827;
    font-size: 13px;
  }

  span {
    color: #86909c;
    font-size: 12px;
    text-align: right;
  }
}

.template-code-input {
  flex: 1;

  :deep(.el-textarea__inner) {
    min-height: 460px;
    border: none;
    border-radius: 0;
    box-shadow: none;
    font-family: "Cascadia Code", "SFMono-Regular", Consolas, "Liberation Mono", monospace;
    font-size: 13px;
    line-height: 1.75;
    color: #1f2329;
  }
}

.preview-frame {
  flex: 1;
  width: 100%;
  min-height: 460px;
  border: none;
  background: #f5f7fa;
}

@media (max-width: 1200px) {
  .editor-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 960px) {
  .template-meta {
    flex-direction: column;
    align-items: stretch;
  }

  .preview-params {
    grid-template-columns: 1fr;
  }

  .pane-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
