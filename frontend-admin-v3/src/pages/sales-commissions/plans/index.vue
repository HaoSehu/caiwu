<template>
  <div class="sales-commission-plans-page">
    <t-card :bordered="false" :loading="loading">
      <template #title>提成方案</template>
      <template #subtitle>计提取「启用中且有效期覆盖今天」的最新一条方案；阶梯与产品费率均随方案级联保存</template>
      <template #actions>
        <t-space>
          <t-button v-if="canManage" theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新建方案
          </t-button>
        </t-space>
      </template>

      <div class="table-scroll">
        <t-table row-key="id" :data="plans" :columns="columns" hover table-layout="fixed">
          <template #name="{ row }">
            <div class="stack-cell">
              <strong>{{ fieldValue(row.name) }}</strong>
              <span>{{ fieldValue(row.remark) || '--' }}</span>
            </div>
          </template>
          <template #basis="{ row }">
            <t-tag :theme="Number(row.basis) === 2 ? 'primary' : 'success'" variant="light">
              {{ row.basis_label || basisLabel(row.basis) }}
            </t-tag>
          </template>
          <template #validity="{ row }">
            <span>{{ row.valid_from || '不限' }} ~ {{ row.valid_to || '不限' }}</span>
          </template>
          <template #tiers="{ row }">
            <span v-if="Number(row.tiers?.length)">{{ row.tiers.length }} 档阶梯</span>
            <span v-else class="text-muted">无阶梯（统一走产品费率）</span>
          </template>
          <template #productRates="{ row }">
            <span v-if="Number(row.product_rates?.length)">{{ row.product_rates.length }} 个产品</span>
            <span v-else class="text-muted">未配置</span>
          </template>
          <template #status="{ row }">
            <t-tag :theme="Number(row.is_enabled) === 1 ? 'success' : 'default'" variant="light">
              {{ Number(row.is_enabled) === 1 ? '启用' : '停用' }}
            </t-tag>
          </template>
          <template #createdAt="{ row }">{{ formatDateTime(row.created_at) }}</template>
          <template #actions="{ row }">
            <t-space size="small">
              <t-button v-if="canManage" theme="primary" variant="text" @click="openEditDialog(row)">编辑</t-button>
              <t-button v-if="canManage" theme="danger" variant="text" @click="handleDelete(row)">删除</t-button>
            </t-space>
          </template>
        </t-table>
      </div>
    </t-card>

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑提成方案' : '新建提成方案'"
      width="860px"
      :confirm-btn="{ content: '保存', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form :data="form" label-align="top">
        <div class="plan-form-grid">
          <t-form-item label="方案名称" name="name">
            <t-input v-model="form.name" placeholder="例如：2026 年度销售提成" :maxlength="100" />
          </t-form-item>
          <t-form-item label="计提基数" name="basis">
            <t-select v-model="form.basis">
              <t-option label="实付金额" :value="1" />
              <t-option label="订单金额" :value="2" />
            </t-select>
          </t-form-item>
          <t-form-item label="生效日期" name="valid_from">
            <t-date-picker v-model="form.valid_from" clearable style="width: 100%" />
          </t-form-item>
          <t-form-item label="失效日期" name="valid_to">
            <t-date-picker v-model="form.valid_to" clearable style="width: 100%" />
          </t-form-item>
          <t-form-item label="状态" name="is_enabled">
            <t-switch v-model="form.is_enabled" :custom-value="[1, 0]" :label="['启用', '停用']" />
          </t-form-item>
          <t-form-item class="plan-form-span" label="备注" name="remark">
            <t-input v-model="form.remark" :maxlength="255" placeholder="选填" />
          </t-form-item>
        </div>

        <div class="plan-children">
          <div class="plan-children__header">
            <strong>阶梯费率</strong>
            <t-button size="small" variant="text" theme="primary" @click="addTier">
              <template #icon><add-icon /></template>
              加一档
            </t-button>
          </div>
          <p class="plan-children__tip">按基数金额区间匹配费率；区间不得重叠，上限留空表示 ∞（其后不可再追加阶梯）。</p>
          <div v-for="(tier, index) in form.tiers" :key="index" class="plan-tier-row">
            <t-input-number
              v-model="tier.min_amount"
              :min="0"
              :decimal-places="2"
              placeholder="金额下限"
              theme="column"
            />
            <span class="plan-tier-row__dash">~</span>
            <t-input-number
              v-model="tier.max_amount"
              :min="0"
              :decimal-places="2"
              placeholder="上限留空为 ∞"
              theme="column"
            />
            <t-input-number v-model="tier.rate" :min="0" :max="100" :decimal-places="2" placeholder="费率 %" />
            <t-button variant="text" theme="danger" size="small" @click="removeTier(index)">删除</t-button>
          </div>

          <div class="plan-children__header">
            <strong>产品费率</strong>
            <t-button size="small" variant="text" theme="primary" @click="addProductRate">
              <template #icon><add-icon /></template>
              加一项
            </t-button>
          </div>
          <p class="plan-children__tip">按产品覆盖费率；比例与固定金额二选一，同一产品不可重复配置。</p>
          <div v-for="(item, index) in form.product_rates" :key="index" class="plan-product-row">
            <t-select
              v-model="item.product_id"
              class="plan-product-row__product"
              clearable
              filterable
              :loading="productsLoading"
              placeholder="选择产品"
            >
              <t-option v-for="option in productOptions" :key="option.value" :label="option.label" :value="option.value" />
            </t-select>
            <t-input-number v-model="item.rate" :min="0" :max="100" :decimal-places="2" placeholder="比例 %" />
            <t-input-number v-model="item.fixed_amount" :min="0" :decimal-places="2" placeholder="固定金额" />
            <t-button variant="text" theme="danger" size="small" @click="removeProductRate(index)">删除</t-button>
          </div>
        </div>
      </t-form>
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import { AddIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, reactive, ref } from 'vue';

import type {
  SalesCommissionPlanPayload,
  SalesCommissionPlanRecord,
  SalesCommissionProductRatePayload,
  SalesCommissionTierPayload,
} from '@/api/admin/salesCommission';
import { salesCommissionApi } from '@/api/admin/salesCommission';
import { productApi } from '@/api/product';
import { AdminPermissions, hasPermissionInList } from '@/constants/permissions';
import { useUserStore } from '@/store';
import { fieldValue, formatDateTime } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

defineOptions({
  name: 'AdminSalesCommissionPlans',
});

interface TierForm {
  min_amount: number | null;
  max_amount: number | null;
  rate: number | null;
}

interface ProductRateForm {
  product_id: number | null;
  rate: number | null;
  fixed_amount: number | null;
}

interface PlanForm {
  id: number | string | null;
  name: string;
  basis: number;
  is_enabled: number;
  valid_from: string | null;
  valid_to: string | null;
  remark: string;
  tiers: TierForm[];
  product_rates: ProductRateForm[];
}

const loading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const plans = ref<SalesCommissionPlanRecord[]>([]);
const productOptions = ref<Array<{ label: string; value: number }>>([]);
const productsLoading = ref(false);

const form = reactive<PlanForm>(createDefaultForm());

const columns: PrimaryTableCol<SalesCommissionPlanRecord>[] = [
  { colKey: 'name', title: '方案名称', minWidth: 220 },
  { colKey: 'basis', title: '计提基数', width: 120 },
  { colKey: 'validity', title: '有效期', minWidth: 180 },
  { colKey: 'tiers', title: '阶梯', width: 170 },
  { colKey: 'productRates', title: '产品费率', width: 110 },
  { colKey: 'status', title: '状态', width: 100 },
  { colKey: 'createdAt', title: '创建时间', width: 170 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 130 },
];

// 写操作需要 sales_commission.manage（页面路由仅 list；list-only 角色只读）
const userStore = useUserStore();
const canManage = computed(() =>
  hasPermissionInList(
    (userStore.userInfo as { permissions?: string[] }).permissions,
    AdminPermissions.SALES_COMMISSION_MANAGE,
  ),
);

function createDefaultForm(): PlanForm {
  return {
    id: null,
    name: '',
    basis: 1,
    is_enabled: 1,
    valid_from: null,
    valid_to: null,
    remark: '',
    tiers: [],
    product_rates: [],
  };
}

function basisLabel(basis: unknown) {
  return Number(basis) === 2 ? '订单金额' : '实付金额';
}

async function loadPlans() {
  loading.value = true;
  try {
    plans.value = (await salesCommissionApi.plans.list()) || [];
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载提成方案失败'));
  } finally {
    loading.value = false;
  }
}

async function loadProductOptions() {
  if (productOptions.value.length) return;
  productsLoading.value = true;
  try {
    const response = await productApi.list({ page: 1, page_size: 200 });
    productOptions.value = (response.list || []).map((item) => ({
      label: String(item.display_name || item.product_spec_display || `产品 #${item.id}`),
      value: Number(item.id),
    }));
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载产品选项失败'));
  } finally {
    productsLoading.value = false;
  }
}

function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  form.tiers = [];
  form.product_rates = [];
  dialogVisible.value = true;
  void loadProductOptions();
}

function openEditDialog(row: SalesCommissionPlanRecord) {
  Object.assign(form, {
    id: row.id,
    name: String(row.name || ''),
    basis: Number(row.basis ?? 1),
    is_enabled: Number(row.is_enabled ?? 1),
    valid_from: row.valid_from || null,
    valid_to: row.valid_to || null,
    remark: String(row.remark || ''),
  });
  form.tiers = (row.tiers || []).map((tier) => ({
    min_amount: Number(tier.min_amount || 0),
    max_amount: tier.max_amount === null ? null : Number(tier.max_amount),
    rate: Number(tier.rate || 0),
  }));
  form.product_rates = (row.product_rates || []).map((item) => ({
    product_id: Number(item.product_id),
    rate: item.rate === null ? null : Number(item.rate),
    fixed_amount: item.fixed_amount === null ? null : Number(item.fixed_amount),
  }));
  dialogVisible.value = true;
  void loadProductOptions();
}

function addTier() {
  form.tiers.push({ min_amount: null, max_amount: null, rate: null });
}

function removeTier(index: number) {
  form.tiers.splice(index, 1);
}

function addProductRate() {
  form.product_rates.push({ product_id: null, rate: null, fixed_amount: null });
}

function removeProductRate(index: number) {
  form.product_rates.splice(index, 1);
}

function buildPayload(): SalesCommissionPlanPayload {
  const tiers: SalesCommissionTierPayload[] = form.tiers
    .filter((tier) => tier.min_amount !== null && tier.rate !== null)
    .map((tier) => ({
      min_amount: tier.min_amount as number,
      max_amount: tier.max_amount,
      rate: tier.rate as number,
    }));
  const productRates: SalesCommissionProductRatePayload[] = form.product_rates
    .filter((item) => item.product_id !== null)
    .map((item) => ({
      product_id: item.product_id as number,
      rate: item.rate,
      fixed_amount: item.fixed_amount,
    }));
  return {
    name: form.name.trim(),
    basis: form.basis,
    is_enabled: form.is_enabled,
    valid_from: form.valid_from || null,
    valid_to: form.valid_to || null,
    remark: form.remark.trim() || null,
    tiers,
    product_rates: productRates,
  };
}

function validateForm(): string | null {
  if (!form.name.trim()) return '请输入方案名称';
  if (form.valid_from && form.valid_to && form.valid_from > form.valid_to) {
    return '生效日期不能晚于失效日期';
  }
  for (const tier of form.tiers) {
    if (tier.min_amount === null || tier.rate === null) return '阶梯的下限与费率必填';
    if (tier.max_amount !== null && Number(tier.min_amount) >= Number(tier.max_amount)) {
      return '阶梯区间不合法：下限必须小于上限';
    }
  }
  const productIds = form.product_rates.map((item) => item.product_id).filter((id) => id !== null);
  if (new Set(productIds).size !== productIds.length) return '同一产品不可重复配置费率';
  for (const item of form.product_rates) {
    if (item.product_id === null) continue;
    if (item.rate === null && item.fixed_amount === null) return '产品费率必须填写比例或固定金额（二选一）';
  }
  return null;
}

async function submitForm() {
  const invalid = validateForm();
  if (invalid) {
    MessagePlugin.warning(invalid);
    return;
  }

  const payload = buildPayload();
  saving.value = true;
  try {
    if (form.id) {
      await salesCommissionApi.plans.update(form.id, payload);
      MessagePlugin.success('提成方案已更新');
    } else {
      await salesCommissionApi.plans.create(payload);
      MessagePlugin.success('提成方案已创建');
    }
    dialogVisible.value = false;
    await loadPlans();
  } catch (error) {
    // 阶梯区间重叠 / 产品重复等由后端校验并回显
    MessagePlugin.error(errorMessage(error, '保存提成方案失败'));
  } finally {
    saving.value = false;
  }
}

function handleDelete(row: SalesCommissionPlanRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除提成方案',
    body: `确认删除方案“${fieldValue(row.name)}”吗？删除后不影响已产生的计提记录。`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      try {
        await salesCommissionApi.plans.delete(row.id);
        MessagePlugin.success('提成方案已删除');
        dialog.destroy();
        await loadPlans();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除提成方案失败'));
      }
    },
  });
}

loadPlans();
</script>
<style scoped>
.plan-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 24px;
}

.plan-form-span {
  grid-column: span 2;
}

.plan-children {
  margin-top: 16px;
  border-top: 1px solid var(--td-component-border, #e7e7e7);
  padding-top: 12px;
}

.plan-children__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 12px 0 4px;
}

.plan-children__tip {
  margin: 0 0 8px;
  font-size: 12px;
  color: var(--td-text-color-placeholder, rgba(0, 0, 0, 0.4));
}

.plan-tier-row,
.plan-product-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.plan-tier-row .t-input-number {
  flex: 1;
}

.plan-product-row__product {
  flex: 2;
}

.plan-product-row .t-input-number {
  flex: 1;
}

.plan-tier-row__dash {
  color: var(--td-text-color-placeholder, rgba(0, 0, 0, 0.4));
}

.text-muted {
  color: var(--td-text-color-placeholder, rgba(0, 0, 0, 0.4));
}

@media (max-width: 768px) {
  .plan-form-grid {
    grid-template-columns: 1fr;
  }

  .plan-form-span {
    grid-column: span 1;
  }
}
</style>
