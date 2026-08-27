<template>
  <div class="promotion-ambassadors-page">
    <t-card :bordered="false" :loading="loading">
      <template #title>推广大使档位</template>
      <template #subtitle>共 {{ ambassadors.length }} 个档位 · 新购/续费返利按用户所属档位分别生效，未指派走全局设置</template>
      <template #actions>
        <t-space>
          <t-button theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新增大使档位
          </t-button>
        </t-space>
      </template>

      <div v-if="!isMobile" class="table-scroll">
        <t-table row-key="id" :data="ambassadors" :columns="columns" hover table-layout="fixed">
          <template #name="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.name) }}</strong>
            </div>
          </template>
          <template #rate="{ row }">
            <t-tag theme="success" variant="light">{{ formatPercent(row.reward_rate) }}</t-tag>
          </template>
          <template #renewalRate="{ row }">
            <t-tag theme="warning" variant="light">{{ formatPercent(row.renewal_reward_rate) }}</t-tag>
          </template>
          <template #status="{ row }">
            <t-tag :theme="Number(row.status) === 1 ? 'success' : 'default'" variant="light">
              {{ Number(row.status) === 1 ? '启用' : '停用' }}
            </t-tag>
          </template>
          <template #updatedAt="{ row }">{{ formatDateTime(row.updated_at) }}</template>
          <template #remark="{ row }">{{ fieldValue(row.remark) }}</template>
          <template #actions="{ row }">
            <t-space size="small">
              <t-button theme="primary" variant="text" @click="openEditDialog(row)">编辑</t-button>
              <t-button theme="danger" variant="text" @click="handleDelete(row)">删除</t-button>
            </t-space>
          </template>
        </t-table>
      </div>

      <div v-else class="mobile-list">
        <article v-for="row in ambassadors" :key="row.id" class="ambassador-mobile-card">
          <div class="ambassador-mobile-card__head">
            <div>
              <strong>{{ fieldValue(row.name) }}</strong>
              <t-tag theme="success" variant="light">新购 {{ formatPercent(row.reward_rate) }}</t-tag>
              <t-tag theme="warning" variant="light">续费 {{ formatPercent(row.renewal_reward_rate) }}</t-tag>
            </div>
            <t-dropdown
              trigger="click"
              placement="bottom-right"
              :options="mobileActionOptions"
              @click="handleMobileAction(row, $event)"
            >
              <t-button class="ambassador-mobile-card__more" variant="text" shape="square">...</t-button>
            </t-dropdown>
          </div>
          <dl>
            <div>
              <dt>状态</dt>
              <dd>
                <t-tag :theme="Number(row.status) === 1 ? 'success' : 'default'" variant="light">
                  {{ Number(row.status) === 1 ? '启用' : '停用' }}
                </t-tag>
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </t-card>

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑大使档位' : '新增大使档位'"
      width="720px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="ambassador-form-grid">
          <t-form-item label="大使名称" name="name">
            <t-input v-model="form.name" placeholder="例如：推广大使" />
          </t-form-item>
          <t-form-item label="新购返利（%）" name="reward_rate">
            <t-input-number v-model="form.reward_rate" :min="0" :max="100" :decimal-places="2" />
          </t-form-item>
          <t-form-item label="续费返利（%）" name="renewal_reward_rate">
            <t-input-number v-model="form.renewal_reward_rate" :min="0" :max="100" :decimal-places="2" />
          </t-form-item>
          <t-form-item label="状态" name="status">
            <t-switch v-model="form.status" :custom-value="[1, 0]" :label="['启用', '停用']" />
          </t-form-item>
          <t-form-item class="ambassador-form-span" label="备注" name="remark">
            <t-textarea v-model="form.remark" :autosize="{ minRows: 3, maxRows: 5 }" :maxlength="255" />
          </t-form-item>
        </div>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { AddIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { onMounted, reactive, ref } from 'vue';

import type { PromotionAmbassadorPayload, PromotionAmbassadorRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { fieldValue, formatDateTime } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

interface AmbassadorForm {
  id: number | string | null;
  name: string;
  reward_rate: number;
  renewal_reward_rate: number;
  status: number;
  remark: string;
}

const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const formRef = ref<FormInstanceFunctions>();
const ambassadors = ref<PromotionAmbassadorRecord[]>([]);
const isMobile = useMediaQuery('(max-width: 768px)');

const form = reactive<AmbassadorForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  name: [required('请输入大使名称')],
  reward_rate: [required('请输入新购返利比例')],
  renewal_reward_rate: [required('请输入续费返利比例')],
};

const columns: PrimaryTableCol<PromotionAmbassadorRecord>[] = [
  { colKey: 'name', title: '大使名称', minWidth: 220 },
  { colKey: 'rate', title: '新购返利', width: 130 },
  { colKey: 'renewalRate', title: '续费返利', width: 130 },
  { colKey: 'status', title: '状态', width: 110 },
  { colKey: 'remark', title: '备注', minWidth: 180 },
  { colKey: 'updatedAt', title: '更新时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 130 },
];
const mobileActionOptions: DropdownOption[] = [
  { content: '编辑', value: 'edit' },
  { content: '删除', value: 'delete', theme: 'error' },
];

function createDefaultForm(): AmbassadorForm {
  return {
    id: null,
    name: '',
    reward_rate: 0,
    renewal_reward_rate: 0,
    status: 1,
    remark: '',
  };
}

async function loadAmbassadors() {
  loading.value = true;
  try {
    const response = await adminApi.promotionAmbassadors.list();
    ambassadors.value = response || [];
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载推广大使失败'));
  } finally {
    loading.value = false;
  }
}

function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  dialogVisible.value = true;
}

function openEditDialog(row: PromotionAmbassadorRecord) {
  Object.assign(form, {
    id: row.id,
    name: String(row.name || ''),
    reward_rate: Number(row.reward_rate || 0),
    renewal_reward_rate: Number(row.renewal_reward_rate || 0),
    status: Number(row.status ?? 1),
    remark: String(row.remark || ''),
  });
  dialogVisible.value = true;
}

async function submitForm() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload: PromotionAmbassadorPayload = {
    name: form.name.trim(),
    reward_rate: Number(form.reward_rate || 0),
    renewal_reward_rate: Number(form.renewal_reward_rate || 0),
    status: Number(form.status ?? 1),
    remark: form.remark.trim() || null,
  };

  saving.value = true;
  try {
    if (form.id) {
      await adminApi.promotionAmbassadors.update(form.id, payload);
      MessagePlugin.success('大使档位已更新');
    } else {
      await adminApi.promotionAmbassadors.create(payload);
      MessagePlugin.success('大使档位已创建');
    }
    dialogVisible.value = false;
    await loadAmbassadors();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存大使档位失败'));
  } finally {
    saving.value = false;
  }
}

function handleDelete(row: PromotionAmbassadorRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除大使档位',
    body: `确认删除大使档位“${fieldValue(row.name)}”吗？`,
    confirmBtn: { content: '确认删除', theme: 'danger' },
    async onConfirm() {
      try {
        await adminApi.promotionAmbassadors.delete(row.id);
        MessagePlugin.success('大使档位已删除');
        dialog.destroy();
        await loadAmbassadors();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除大使档位失败'));
      }
    },
  });
}

function handleMobileAction(row: PromotionAmbassadorRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'edit') {
    openEditDialog(row);
    return;
  }
  if (action === 'delete') handleDelete(row);
}

function formatPercent(value: unknown) {
  return `${Number(value || 0).toFixed(2)}%`;
}

onMounted(loadAmbassadors);
</script>
