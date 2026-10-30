<template>
  <t-dialog
    v-model:visible="visible"
    header="新建实例"
    width="min(760px, calc(100vw - 32px))"
    :confirm-btn="{ content: '确定', loading: submitting }"
    @cancel="visible = false"
    @confirm="submit"
  >
    <t-alert
      theme="info"
      message="确认后会创建本地服务；可按开关选择自动创建订单、账单和从余额扣款。余额扣款需同时创建账单，余额不足时会回滚本次创建。不会自动发起上游控制，需要时请在服务控制台绑定上游实例。"
    />
    <t-form ref="formRef" :data="form" :rules="rules" label-align="right" class="dialog-form">
      <div class="dialog-grid">
        <t-form-item label="选择商品" name="product_id" class="form-item-full">
          <product-binding-tree-select v-model="productIdArray" mode="single" @change="handleProductChange" />
        </t-form-item>
        <t-form-item label="计费周期" name="billing_cycle">
          <t-select v-model="form.billing_cycle" placeholder="请选择计费周期" @change="syncAmountFromCycle">
            <t-option v-for="item in billingOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="系统类型">
          <t-select v-model="form.os" clearable filterable :loading="osLoading" placeholder="请选择系统">
            <t-option v-for="item in osFlatOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="服务状态" name="status">
          <t-select v-model="form.status" placeholder="请选择状态">
            <t-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="服务金额" name="amount">
          <t-input-number v-model="form.amount" :min="0" :decimal-places="2" style="width: 100%" />
        </t-form-item>
        <t-form-item label="服务名称">
          <t-input v-model="form.name" placeholder="为空时默认使用配置名" />
        </t-form-item>
        <t-form-item class="form-item-full">
          <div class="add-service-automation-switches">
            <div class="add-service-automation-switch">
              <span>自动续费</span>
              <t-switch v-model="form.auto_renew" :custom-value="[1, 0]" />
            </div>
            <div class="add-service-automation-switch">
              <span>自动创建订单</span>
              <t-switch v-model="form.create_order" :custom-value="[1, 0]" />
            </div>
            <div class="add-service-automation-switch">
              <span>自动创建账单</span>
              <t-switch v-model="form.create_invoice" :custom-value="[1, 0]" @change="handleCreateInvoiceChange" />
            </div>
            <div class="add-service-automation-switch">
              <span>从余额扣款</span>
              <t-switch v-model="form.deduct_balance" :custom-value="[1, 0]" :disabled="!form.create_invoice" />
            </div>
          </div>
        </t-form-item>
      </div>
      <t-form-item label="备注">
        <t-textarea v-model="form.remark" :maxlength="200" placeholder="记录手工开通说明或交付信息" />
      </t-form-item>
    </t-form>
  </t-dialog>
</template>
<script setup lang="ts">
import { SERVICE_STATUS_MAP, toSelectOptions } from '@caiwu/shared/statusConfig';
import type { FormInstanceFunctions, FormRule } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref } from 'vue';

import type { ProductBindingRecord } from '@/api/admin';
import type { ProductRecord } from '@/api/product';
import { productApi } from '@/api/product';
import { userApi } from '@/api/user';
import ProductBindingTreeSelect from '@/components/product-binding-tree-select/index.vue';
import { fieldValue, formatMoney } from '@/utils/format';
import { required } from '@/utils/formRules';
import { errorMessage } from '@/utils/userMessage';

/**
 * 新建实例弹层。
 *
 * 从 users/detail/index.vue 抽出（审查条目 P2-6）：表单状态、商品联动、系统类型拉取与扣款二次确认都在组件内，
 * 父级只负责打开弹层，并在创建成功后刷新服务列表、概览与账单。
 */
type Row = Record<string, any>;

const props = defineProps<{ userId: number | string }>();
const emit = defineEmits<{ (event: 'created'): void }>();

const visible = ref(false);
const submitting = ref(false);
const loading = ref(false);
const osLoading = ref(false);
const formRef = ref<FormInstanceFunctions>();
const form = reactive({
  product_id: undefined as number | undefined,
  billing_cycle: '',
  status: 1,
  name: '',
  amount: 0,
  auto_renew: 1,
  create_order: 1,
  create_invoice: 1,
  deduct_balance: 1,
  os: '',
  remark: '',
});
const productId = ref<number | undefined>();
const productIdArray = computed<(string | number)[]>({
  get: () => (productId.value ? [productId.value] : []),
  set: (val) => {
    const values = normalizeSelectionArray(val);
    productId.value = values.length ? Number(values[0]) : undefined;
  },
});
const productDetail = ref<ProductRecord | null>(null);
const osOptions = ref<Row[]>([]);

const rules: Record<string, FormRule[]> = {
  product_id: [required('请选择商品')],
  billing_cycle: [required('请选择计费周期')],
  status: [required('请选择服务状态')],
  amount: [required('请输入服务金额')],
};
const statusOptions = toSelectOptions(SERVICE_STATUS_MAP, false);
const billingOptions = computed(() => resolveBillingOptions(productDetail.value));
const osFlatOptions = computed(() => flattenOptionTree(osOptions.value));

function open() {
  resetForm();
  visible.value = true;
}

function resetForm() {
  Object.assign(form, {
    product_id: undefined,
    billing_cycle: '',
    status: 1,
    name: '',
    amount: 0,
    auto_renew: 1,
    create_order: 1,
    create_invoice: 1,
    deduct_balance: 1,
    os: '',
    remark: '',
  });
  productId.value = undefined;
  productDetail.value = null;
  formRef.value?.clearValidate?.();
}

async function handleProductChange(
  payload: { binding_ids?: unknown; bindings?: ProductBindingRecord[] } | string | number | Array<string | number>,
) {
  const bindingIds =
    typeof payload === 'object' && !Array.isArray(payload) && payload !== null && 'binding_ids' in payload
      ? normalizeSelectionArray(payload.binding_ids)
      : normalizeSelectionArray(payload);
  const id = Number(bindingIds[0] || 0);
  form.product_id = id || undefined;
  productDetail.value = null;
  form.billing_cycle = '';
  form.amount = 0;
  form.os = '';
  osOptions.value = [];
  if (!id) return;

  loading.value = true;
  try {
    const detail = await productApi.detail(id);
    productDetail.value = detail;
    form.name = productLabel(detail);
    const firstCycle = billingOptions.value[0];
    form.billing_cycle = firstCycle?.value || '';
    form.amount = firstCycle?.amount || 0;
    await fetchOsOptions();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载商品详情失败'));
  } finally {
    loading.value = false;
  }
}

async function fetchOsOptions() {
  osLoading.value = true;
  try {
    const response = await userApi.osOptions();
    osOptions.value = Array.isArray(response.groups) ? response.groups : [];
  } catch {
    osOptions.value = [];
  } finally {
    osLoading.value = false;
  }
}

function syncAmountFromCycle() {
  const matched = billingOptions.value.find((item) => item.value === form.billing_cycle);
  form.amount = matched?.amount || 0;
}

function handleCreateInvoiceChange(value: unknown) {
  if (Number(value) !== 1) {
    form.deduct_balance = 0;
  }
}

/** 勾选「从余额扣款」时会真实扣款，提交前确认金额与扣款动作。 */
function confirmDeduct() {
  return new Promise<boolean>((resolve) => {
    const dialog = DialogPlugin.confirm({
      header: '确认从余额扣款',
      theme: 'warning',
      body: `本次创建实例将从用户余额扣款 ${formatMoney(toNumber(form.amount))}，并同步生成账单。提交后不可撤销。`,
      confirmBtn: { content: '确认提交', theme: 'danger' },
      cancelBtn: '取消',
      onConfirm() {
        dialog.hide();
        resolve(true);
      },
      onCancel() {
        resolve(false);
      },
      onClose() {
        resolve(false);
      },
    });
  });
}

async function submit() {
  const result = await formRef.value?.validate?.();
  if (!isValidationPass(result)) return;
  if (form.deduct_balance && !(await confirmDeduct())) return;
  submitting.value = true;
  try {
    await userApi.storeService(props.userId, {
      product_id: Number(form.product_id || 0),
      billing_cycle: form.billing_cycle,
      status: form.status,
      name: form.name,
      amount: toNumber(form.amount),
      auto_renew: Number(form.auto_renew ? 1 : 0),
      create_order: Number(form.create_order ? 1 : 0),
      create_invoice: Number(form.create_invoice ? 1 : 0),
      deduct_balance: Number(form.deduct_balance ? 1 : 0),
      os: form.os,
      remark: form.remark,
    });
    MessagePlugin.success('实例已创建');
    visible.value = false;
    emit('created');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '添加实例失败'));
  } finally {
    submitting.value = false;
  }
}

function productLabel(product: ProductRecord | null | undefined) {
  if (!product) return '';
  return String(
    product.display_name || product.name || product.product_name || (product.id ? `商品 #${product.id}` : ''),
  ).trim();
}

function resolveBillingOptions(product: ProductRecord | null) {
  const pricing =
    product && product.pricing && typeof product.pricing === 'object' && !Array.isArray(product.pricing)
      ? product.pricing
      : {};
  return Object.entries(pricing)
    .filter(([, amount]) => toNumber(amount) > 0)
    .map(([value, amount]) => ({
      value,
      label: `${billingCycleLabel(value)} · ${formatMoney(amount)}`,
      amount: toNumber(amount),
    }));
}

function billingCycleLabel(value: unknown) {
  return (
    (
      {
        monthly: '月付',
        quarterly: '季付',
        semiannually: '半年付',
        annually: '年付',
        biennially: '两年付',
        triennially: '三年付',
        one_time: '一次性',
      } as Record<string, string>
    )[String(value)] || fieldValue(value)
  );
}

function flattenOptionTree(items: Row[] = []) {
  const result: Array<{ value: string | number; label: string }> = [];
  const walk = (nodes: Row[]) => {
    nodes.forEach((node) => {
      const value = node.value ?? node.id ?? node.name;
      const label = String(node.label || node.name || node.value || '').trim();
      if (value !== undefined && value !== null && label) result.push({ value, label });
      if (Array.isArray(node.children)) walk(node.children);
    });
  };
  walk(items);
  return result;
}

function normalizeSelectionArray(value: unknown): string[] {
  return (Array.isArray(value) ? value : [value]).map((item) => String(item || '').trim()).filter(Boolean);
}

function isValidationPass(result: unknown) {
  return result === true || result === undefined;
}

function toNumber(value: unknown) {
  const number = Number.parseFloat(String(value ?? 0));
  return Number.isFinite(number) ? number : 0;
}

defineExpose({ open });
</script>
<style lang="less" scoped>
// 从 users/detail/index.less 随弹层一起迁出（审查条目 P2-6），父级已无消费方
.dialog-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 var(--td-comp-margin-l);

  .form-item-full {
    grid-column: 1 / -1;
  }
}

.add-service-automation-switches,
.add-service-automation-switch {
  display: flex;
  align-items: center;
}

.add-service-automation-switches {
  flex-wrap: wrap;
  gap: var(--td-comp-margin-m);
}

.add-service-automation-switch {
  gap: var(--td-comp-margin-xs);
  white-space: nowrap;
}

@media (max-width: 767.98px) {
  .dialog-grid {
    grid-template-columns: 1fr;
  }
}
</style>
