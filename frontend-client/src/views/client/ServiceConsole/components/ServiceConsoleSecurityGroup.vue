<template>
  <section class="console-panel">
    <div class="console-panel__header console-panel__header--stack">
      <div>
        <h3>安全组</h3>
        <p>管理实例入站与出站规则，支持查看、创建、应用与删除安全组。</p>
      </div>
      <div class="console-toolbar">
        <el-button :loading="securityState.loading" @click="emit('refresh', { fresh: true })">刷新</el-button>
        <el-button
          type="primary"
          :disabled="securityState.supported === false"
          @click="emit('open-group-dialog')"
        >
          新建安全组
        </el-button>
      </div>
    </div>

    <div class="console-panel__body">
      <el-alert
        v-if="securityState.error"
        class="console-inline-alert"
        type="warning"
        :closable="false"
        show-icon
        :title="securityState.error"
      />

      <el-empty
        v-else-if="securityState.supported === false"
        :description="securityState.message || '当前实例暂不支持安全组管理'"
        :image-size="64"
      />

      <template v-else>
        <div v-if="securityState.groups.length" class="security-group-list security-group-list--desktop">
          <el-table
            :data="securityState.groups"
            row-key="id"
            size="small"
            class="security-group-table"
            :row-class-name="resolveSecurityGroupRowClassName"
            v-loading="securityState.loading"
            @row-click="handleGroupSelect"
          >
            <el-table-column label="安全组" min-width="240">
              <template #default="{ row }">
                <div class="security-row__name">
                  <strong>{{ row.name }}</strong>
                  <el-tag size="small" effect="plain">ID {{ row.id }}</el-tag>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="备注说明" min-width="220">
              <template #default="{ row }">
                <span class="security-row__description">{{ row.description || '暂无备注说明' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="120">
              <template #default="{ row }">
                <el-tag v-if="row.is_applied" type="success" effect="plain">已应用</el-tag>
                <el-tag v-else effect="plain">未应用</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" min-width="320">
              <template #default="{ row }">
                <div class="security-row__actions">
                  <el-button :disabled="!row.can_view || securityState.submitting" @click.stop="handleGroupSelect(row)">查看</el-button>
                  <el-button
                    v-if="!row.is_applied"
                    type="primary"
                    plain
                    :disabled="!row.can_apply || row.apply_disabled || securityState.submitting"
                    @click.stop="emit('apply-group', row)"
                  >
                    应用
                  </el-button>
                  <el-button
                    type="danger"
                    plain
                    :disabled="!row.can_delete || row.delete_disabled || securityState.submitting"
                    @click.stop="emit('delete-group', row)"
                  >
                    删除
                  </el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <div v-if="securityState.groups.length" class="security-group-mobile">
          <article
            v-for="row in securityState.groups"
            :key="`mobile-group-${row.id}`"
            class="console-mobile-card security-group-mobile__card"
            :class="{ 'is-active': activeSecurityGroup?.id === row.id }"
            @click="handleGroupSelect(row)"
          >
            <div class="console-mobile-card__top">
              <div class="security-row__name">
                <strong>{{ row.name }}</strong>
                <el-tag size="small" effect="plain">ID {{ row.id }}</el-tag>
              </div>
              <el-tag v-if="row.is_applied" size="small" type="success" effect="plain">已应用</el-tag>
              <el-tag v-else size="small" effect="plain">未应用</el-tag>
            </div>
            <p class="security-row__description">{{ row.description || '暂无备注说明' }}</p>
            <div class="security-group-mobile__actions">
              <el-button size="small" :disabled="!row.can_view || securityState.submitting" @click.stop="handleGroupSelect(row)">查看</el-button>
              <el-button
                v-if="!row.is_applied"
                size="small"
                type="primary"
                plain
                :disabled="!row.can_apply || row.apply_disabled || securityState.submitting"
                @click.stop="emit('apply-group', row)"
              >
                应用
              </el-button>
              <el-button
                size="small"
                type="danger"
                plain
                :disabled="!row.can_delete || row.delete_disabled || securityState.submitting"
                @click.stop="emit('delete-group', row)"
              >
                删除
              </el-button>
            </div>
          </article>
        </div>

        <el-empty
          v-else
          description="当前没有安全组，可先创建后再添加规则"
          :image-size="64"
        />

        <div v-if="activeSecurityGroup" class="rules-panel">
          <div class="rules-panel__head">
            <strong>{{ activeSecurityGroup.name }} 规则</strong>
            <el-button type="primary" :disabled="!activeSecurityGroup.can_add_rule || securityState.submitting" @click="emit('open-rule-dialog')">新增规则</el-button>
          </div>

          <el-table
            class="rules-table"
            :data="securityRules"
            row-key="id"
            size="small"
            v-loading="securityState.rulesLoading"
          >
            <el-table-column prop="direction_label" label="方向" min-width="100" />
            <el-table-column prop="protocol" label="协议" min-width="100" />
            <el-table-column prop="port" label="端口" min-width="120" />
            <el-table-column prop="ip" label="IP 范围" min-width="180" />
            <el-table-column prop="description" label="备注" min-width="160" />
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button
                  text
                  type="danger"
                  :disabled="securityState.submitting"
                  @click="emit('delete-rule', row)"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>

          <div v-if="securityRules.length" class="rules-mobile-list">
            <article
              v-for="row in securityRules"
              :key="`mobile-rule-${row.id}`"
              class="console-mobile-card"
            >
              <div class="console-mobile-card__top">
                <strong>{{ row.direction_label }} / {{ row.protocol }}</strong>
                <span class="console-mobile-card__time">ID {{ row.id }}</span>
              </div>
              <div class="console-mobile-card__meta">
                <span>端口：{{ row.port || '--' }}</span>
                <span>IP：{{ row.ip || '--' }}</span>
              </div>
              <p class="security-row__description">{{ row.description || '暂无备注说明' }}</p>
              <div class="security-group-mobile__actions">
                <el-button
                  size="small"
                  text
                  type="danger"
                  :disabled="securityState.submitting"
                  @click="emit('delete-rule', row)"
                >
                  删除
                </el-button>
              </div>
            </article>
          </div>
        </div>
      </template>
    </div>
  </section>

  <el-dialog v-model="groupVisible" title="新增安全组" width="420px" destroy-on-close>
    <el-form ref="groupFormRef" :model="groupForm" :rules="groupRules" label-width="80px">
      <el-form-item label="名称" prop="name">
        <el-input v-model="groupForm.name" placeholder="请输入安全组名称" />
      </el-form-item>
      <el-form-item label="描述" prop="description">
        <el-input v-model="groupForm.description" type="textarea" :rows="3" placeholder="选填" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="groupVisible = false">取消</el-button>
      <el-button type="primary" :loading="securityState.submitting" @click="emit('submit-group')">创建</el-button>
    </template>
  </el-dialog>

  <el-dialog v-model="ruleVisible" title="新增安全组规则" width="520px" destroy-on-close>
    <el-form ref="ruleFormRef" :model="ruleForm" :rules="ruleRules" label-width="90px">
      <el-form-item label="方向" prop="direction">
        <el-select v-model="ruleForm.direction" placeholder="请选择方向">
          <el-option
            v-for="item in securityState.directions"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="协议" prop="protocol">
        <el-select v-model="ruleForm.protocol" placeholder="请选择协议">
          <el-option
            v-for="item in securityState.protocols"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="端口" prop="port">
        <el-input v-model="ruleForm.port" placeholder="例如 22 或 80-90" />
      </el-form-item>
      <el-form-item label="IP 范围" prop="ip">
        <el-input v-model="ruleForm.ip" placeholder="例如 0.0.0.0/0" />
      </el-form-item>
      <el-form-item label="备注" prop="description">
        <el-input v-model="ruleForm.description" type="textarea" :rows="3" placeholder="选填" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="ruleVisible = false">取消</el-button>
      <el-button type="primary" :loading="securityState.submitting" @click="emit('submit-rule')">创建规则</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  securityState: { type: Object, required: true },
  securityRules: { type: Array, default: () => [] },
  activeSecurityGroup: { type: Object, default: null },
  groupForm: { type: Object, required: true },
  groupRules: { type: Object, required: true },
  ruleForm: { type: Object, required: true },
  ruleRules: { type: Object, required: true },
  resolveSecurityGroupRowClassName: { type: Function, required: true },
})

const groupVisible = defineModel('groupVisible', { type: Boolean, default: false })
const ruleVisible = defineModel('ruleVisible', { type: Boolean, default: false })
const groupFormRef = ref(null)
const ruleFormRef = ref(null)

const emit = defineEmits([
  'refresh',
  'open-group-dialog',
  'open-rule-dialog',
  'select-group',
  'apply-group',
  'delete-group',
  'delete-rule',
  'submit-group',
  'submit-rule',
])

function handleGroupSelect(group) {
  if (!group?.can_view) {
    return
  }

  emit('select-group', group)
}

defineExpose({
  validateGroupForm: () => groupFormRef.value?.validate(),
  validateRuleForm: () => ruleFormRef.value?.validate(),
})
</script>
