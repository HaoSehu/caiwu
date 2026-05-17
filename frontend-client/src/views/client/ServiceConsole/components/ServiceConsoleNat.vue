<template>
  <section class="console-panel">
    <div class="console-panel__header console-panel__header--stack">
      <div>
        <h3>端口转发</h3>
        <p>保留现有 NAT 转发能力，便于在同一处完成网络开放与映射维护。</p>
      </div>
      <div class="console-toolbar">
        <el-button :loading="natState.loading" @click="emit('refresh')">刷新</el-button>
      </div>
    </div>

    <div class="console-panel__body">
      <el-alert
        v-if="natState.error"
        class="console-inline-alert"
        type="warning"
        :closable="false"
        show-icon
        :title="natState.error"
      />

      <el-empty
        v-else-if="natState.supported === false"
        :description="natState.message || '当前实例暂不支持 NAT 转发'"
        :image-size="64"
      />

      <template v-else>
        <el-form ref="natFormRef" class="nat-form" :model="natForm" :rules="natRules" @submit.prevent>
          <el-form-item prop="name">
            <el-input v-model="natForm.name" placeholder="规则名称" />
          </el-form-item>
          <el-form-item prop="ext_port">
            <el-input v-model="natForm.ext_port" placeholder="公网端口，可留空" />
          </el-form-item>
          <el-form-item prop="int_port">
            <el-input v-model="natForm.int_port" placeholder="内部端口" />
          </el-form-item>
          <el-form-item prop="protocol">
            <el-select v-model="natForm.protocol" placeholder="协议">
              <el-option
                v-for="item in natState.protocols"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button
              class="nat-form__submit"
              type="primary"
              :loading="natState.submitting"
              :disabled="!natState.can_create"
              @click="handleSubmit"
            >
              创建
            </el-button>
          </el-form-item>
        </el-form>

        <div v-if="natState.list.length" class="nat-table nat-table--desktop">
          <el-table :data="natState.list" row-key="id" size="small">
            <el-table-column prop="name" label="名称" min-width="140" />
            <el-table-column prop="external_address" label="公网地址" min-width="200" />
            <el-table-column prop="internal_port" label="内网端口" min-width="120" />
            <el-table-column prop="protocol_label" label="协议" min-width="100" />
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button
                  text
                  type="danger"
                  :disabled="!row.can_delete || natState.submitting"
                  @click="emit('delete', row)"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <div v-if="natState.list.length" class="nat-mobile-list">
          <article
            v-for="row in natState.list"
            :key="`mobile-nat-${row.id}`"
            class="console-mobile-card"
          >
            <div class="console-mobile-card__top">
              <strong>{{ row.name || '未命名规则' }}</strong>
              <span class="console-mobile-card__time">{{ row.protocol_label || '--' }}</span>
            </div>
            <div class="console-mobile-card__meta">
              <span>公网地址：{{ row.external_address || '--' }}</span>
              <span>内网端口：{{ row.internal_port || '--' }}</span>
            </div>
            <div class="security-group-mobile__actions">
              <el-button
                size="small"
                text
                type="danger"
                :disabled="!row.can_delete || natState.submitting"
                @click="emit('delete', row)"
              >
                删除
              </el-button>
            </div>
          </article>
        </div>

        <el-empty
          v-if="!natState.list.length"
          description="当前没有端口转发规则"
          :image-size="60"
        />
      </template>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  natState: { type: Object, required: true },
  natForm: { type: Object, required: true },
  natRules: { type: Object, required: true },
})

const emit = defineEmits(['refresh', 'submit', 'delete'])
const natFormRef = ref(null)

async function handleSubmit() {
  if (!natFormRef.value) return
  await natFormRef.value.validate()
  emit('submit')
}
</script>
