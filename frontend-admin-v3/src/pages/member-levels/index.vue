<template>
  <div class="member-levels-page">
    <t-card :bordered="false" :loading="loading">
      <template #title>等级列表</template>
      <template #subtitle>共 {{ levels.length }} 个档位</template>
      <template #actions>
        <t-space>
          <t-button theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新增等级
          </t-button>
        </t-space>
      </template>

      <div v-if="!isMobile" class="table-scroll">
        <t-table row-key="id" :data="levels" :columns="columns" hover table-layout="fixed">
          <template #level="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.name) }}</strong>
            </div>
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
        <article v-for="row in levels" :key="row.id" class="member-mobile-card">
          <div class="member-mobile-card__head">
            <div>
              <strong>{{ fieldValue(row.name) }}</strong>
            </div>
            <t-dropdown
              trigger="click"
              placement="bottom-right"
              :options="mobileActionOptions"
              @click="handleMobileAction(row, $event)"
            >
              <t-button class="member-mobile-card__more" variant="text" shape="square">...</t-button>
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
      :header="form.id ? '编辑会员等级' : '新增会员等级'"
      width="720px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top">
        <div class="member-form-grid">
          <t-form-item label="等级名称" name="name">
            <t-input v-model="form.name" placeholder="例如：黄金会员" />
          </t-form-item>
          <t-form-item label="状态" name="status">
            <t-switch v-model="form.status" :custom-value="[1, 0]" :label="['启用', '停用']" />
          </t-form-item>
          <t-form-item class="member-form-span" label="备注" name="remark">
            <t-textarea v-model="form.remark" :autosize="{ minRows: 3, maxRows: 5 }" :maxlength="255" />
          </t-form-item>
        </div>
      </t-form>

      <section v-if="form.id && matrixLoaded" class="discount-matrix-section">
        <div class="discount-matrix-head">
          <strong>产品组价格折扣</strong>
          <span>按「等级 × 营销组」为新购与续费打折；百分比为折后保留比例（90=九折），未配置不打折；保存等级时一并生效</span>
        </div>

        <div v-if="matrixGroups.length" class="discount-matrix-table">
          <div class="matrix-head">
            <span class="col-group">营销组</span>
            <span class="col-type">折扣类型</span>
            <span class="col-value">数值</span>
          </div>
          <div v-for="item in matrixGroups" :key="item.id" class="matrix-row">
            <span class="col-group">
              <strong>{{ item.name }}</strong>
              <em>{{ Number(item.product_count || 0) }} 个商品</em>
            </span>
            <t-select
              v-model="item.discountType"
              class="col-type"
              :options="discountTypeOptions"
              clearable
              placeholder="无折扣"
            />
            <div class="col-value">
              <t-input-number
                v-if="item.discountType === 1"
                v-model="item.discountValue"
                :min="1"
                :max="100"
                :decimal-places="2"
                placeholder="折后保留百分比"
              />
              <t-input-number
                v-else-if="item.discountType === 2"
                v-model="item.discountValue"
                :min="0.01"
                :decimal-places="2"
                placeholder="固定减免金额"
              />
              <span v-else class="matrix-none">原价</span>
            </div>
          </div>
        </div>

        <t-empty v-else description="暂无营销组，请先到「营销推广 > 产品营销组」创建并圈选商品" />
      </section>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { AddIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { onMounted, reactive, ref } from 'vue';

import type { MemberLevelPayload, MemberLevelRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { fieldValue, formatDateTime, formatMoney } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

interface MemberLevelForm {
  id: number | string | null;
  name: string;
  status: number;
  remark: string;
}

interface MatrixGroupRow {
  id: number | string;
  name: string;
  product_count: number | null;
  /** 1=百分比（折后保留） 2=固定金额；null=无折扣 */
  discountType: 1 | 2 | null;
  discountValue: number;
}

const loading = ref(false);
const saving = ref(false);
const matrixLoaded = ref(false);
const dialogVisible = ref(false);
const formRef = ref<FormInstanceFunctions>();
const levels = ref<MemberLevelRecord[]>([]);
const matrixGroups = ref<MatrixGroupRow[]>([]);
const isMobile = useMediaQuery('(max-width: 768px)');

const discountTypeOptions = [
  { label: '百分比（折后保留，90=九折）', value: 1 },
  { label: '固定金额减免', value: 2 },
];

const form = reactive<MemberLevelForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  name: [required('请输入等级名称')],
};

const columns: PrimaryTableCol<MemberLevelRecord>[] = [
  { colKey: 'level', title: '等级信息', minWidth: 220 },
  { colKey: 'status', title: '状态', width: 110 },
  { colKey: 'remark', title: '备注', minWidth: 180 },
  { colKey: 'updatedAt', title: '更新时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 130 },
];
const mobileActionOptions: DropdownOption[] = [
  { content: '编辑', value: 'edit' },
  { content: '删除', value: 'delete', theme: 'error' },
];

function createDefaultForm(): MemberLevelForm {
  return {
    id: null,
    name: '',
    status: 1,
    remark: '',
  };
}

async function loadLevels() {
  loading.value = true;
  try {
    const response = await adminApi.memberLevels.list();
    levels.value = response || [];
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载会员等级失败'));
  } finally {
    loading.value = false;
  }
}

function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  dialogVisible.value = true;
}

function openEditDialog(row: MemberLevelRecord) {
  Object.assign(form, {
    id: row.id,
    name: String(row.name || ''),
    status: Number(row.status ?? 1),
    remark: String(row.remark || ''),
  });
  dialogVisible.value = true;
  loadMatrix(row.id);
}

async function submitForm() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload = buildPayload();
  if (!payload) return;

  saving.value = true;
  try {
    if (form.id) {
      await adminApi.memberLevels.update(form.id, payload);
    } else {
      await adminApi.memberLevels.create(payload);
    }

    // 编辑时折扣矩阵随等级一并保存；矩阵未加载完则跳过，保留线上原有折扣
    if (form.id && matrixLoaded.value) {
      try {
        await adminApi.memberLevels.syncGroupDiscounts(form.id, buildDiscountRules());
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '等级信息已保存，但折扣矩阵同步失败'));
        return;
      }
    }

    MessagePlugin.success(form.id ? '会员等级已更新' : '会员等级已创建');
    dialogVisible.value = false;
    await loadLevels();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存会员等级失败'));
  } finally {
    saving.value = false;
  }
}

function buildPayload(): MemberLevelPayload | null {
  return {
    name: form.name.trim(),
    status: Number(form.status ?? 1),
    remark: form.remark.trim() || null,
  };
}

async function loadMatrix(levelId: number | string) {
  matrixLoaded.value = false;
  matrixGroups.value = [];
  try {
    const data = await adminApi.memberLevels.groupDiscounts(levelId);
    matrixGroups.value = (data.groups || []).map((group) => {
      const rawType = group.discount ? Number(group.discount.discount_type) : 0;

      return {
        id: group.id,
        name: group.name,
        product_count: group.product_count ?? null,
        discountType: (rawType === 1 || rawType === 2 ? rawType : null) as 1 | 2 | null,
        discountValue: group.discount ? Number(group.discount.discount_value) : 0,
      };
    });
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载折扣矩阵失败'));
  } finally {
    matrixLoaded.value = true;
  }
}

function buildDiscountRules() {
  return matrixGroups.value
    // 清空下拉会得到 undefined，必须显式限定有效折扣类型，避免发出缺 discount_type 的规则
    .filter((item) => item.discountType === 1 || item.discountType === 2)
    .map((item) => ({
      marketing_product_group_id: item.id,
      discount_type: item.discountType,
      discount_value: Number(item.discountValue || 0),
    }));
}

function handleDelete(row: MemberLevelRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除会员等级',
    body: `确认删除等级“${fieldValue(row.name)}”吗？`,
    confirmBtn: { content: '确认删除', theme: 'danger' },
    async onConfirm() {
      try {
        await adminApi.memberLevels.delete(row.id);
        MessagePlugin.success('会员等级已删除');
        dialog.destroy();
        await loadLevels();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除会员等级失败'));
      }
    },
  });
}

function handleMobileAction(row: MemberLevelRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'edit') {
    openEditDialog(row);
    return;
  }
  if (action === 'delete') handleDelete(row);
}

onMounted(loadLevels);
</script>
