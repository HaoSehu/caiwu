<template>
  <div class="user-segment-filter">
    <t-divider align="left" class="segment-divider">客群条件（全部可选填）</t-divider>
    <t-form label-align="top">
      <div class="segment-grid">
        <t-form-item label="账号状态">
          <t-select v-model="form.status" :options="statusOptions" multiple clearable placeholder="不限" />
        </t-form-item>
        <t-form-item label="实名认证">
          <t-select v-model="form.verified" :options="verifiedOptions" clearable placeholder="不限" />
        </t-form-item>
        <t-form-item label="会员等级">
          <t-select
            v-model="form.member_level_ids"
            :options="levelOptions"
            :loading="levelsLoading"
            multiple
            clearable
            placeholder="不限"
          />
        </t-form-item>
        <t-form-item label="注册时间区间">
          <t-date-range-picker
            v-model="form.registered_range"
            value-type="YYYY-MM-DD"
            allow-input
            clearable
            placeholder="不限"
          />
        </t-form-item>
        <t-form-item label="最近登录早于">
          <t-date-picker
            v-model="form.last_login_before"
            value-type="YYYY-MM-DD"
            allow-input
            clearable
            style="width: 100%"
            placeholder="不限（从未登录视为满足）"
          />
        </t-form-item>
        <t-form-item label="名下产品">
          <t-select
            v-model="form.product_ids"
            :options="productOptions"
            :loading="productsLoading"
            multiple
            clearable
            filterable
            placeholder="输入关键词搜索产品"
            :empty="productsSearching ? '搜索中...' : '暂无匹配产品'"
            @search="handleProductSearch"
            @clear="handleProductSearch('')"
          />
        </t-form-item>
        <t-form-item label="现金余额区间" class="segment-span">
          <div class="segment-balance">
            <t-input-number
              v-model="form.balance_min"
              :min="0"
              :decimal-places="2"
              theme="column"
              placeholder="最低（元）"
            />
            <span class="segment-balance__divider">至</span>
            <t-input-number
              v-model="form.balance_max"
              :min="0"
              :decimal-places="2"
              theme="column"
              placeholder="最高（元）"
            />
          </div>
        </t-form-item>
      </div>
    </t-form>
  </div>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';

import { memberLevelsApi } from '@/api/admin/memberLevel';
import { productApi } from '@/api/product';
import { errorMessage } from '@/utils/userMessage';
import { MessagePlugin } from 'tdesign-vue-next';

interface SelectOption {
  label: string;
  value: string | number;
}

const props = defineProps<{
  /** 客群条件对象（filter_json），组件内部按白名单键构建 */
  modelValue: Record<string, unknown>;
}>();

const emit = defineEmits<{
  (event: 'update:modelValue', value: Record<string, unknown>): void;
}>();

const statusOptions: SelectOption[] = [
  { label: '正常', value: 1 },
  { label: '停用', value: 0 },
];
const verifiedOptions: SelectOption[] = [
  { label: '已实名', value: 'true' },
  { label: '未实名', value: 'false' },
];

const form = reactive({
  status: [] as number[],
  verified: '',
  member_level_ids: [] as number[],
  registered_range: [] as string[],
  last_login_before: '',
  product_ids: [] as Array<number | string>,
  balance_min: undefined as number | undefined,
  balance_max: undefined as number | undefined,
});

const levelOptions = ref<SelectOption[]>([]);
const levelsLoading = ref(false);
const productOptions = ref<SelectOption[]>([]);
const productsLoading = ref(false);
const productsSearching = ref(false);

let lastEmittedJson = '{}';

function asNumberArray(value: unknown): number[] {
  if (!Array.isArray(value)) return [];
  return value.map((item) => Number(item)).filter((item) => Number.isFinite(item));
}

/** 将外部传入的 filter_json 回填到表单（仅识别本组件支持的键） */
function applyFromProps(value: Record<string, unknown>) {
  const filter = value || {};
  form.status = asNumberArray(filter.status);
  const verified = filter.verified;
  form.verified = verified === true || verified === 'true' ? 'true' : verified === false || verified === 'false' ? 'false' : '';
  form.member_level_ids = asNumberArray(filter.member_level_ids);
  const range = Array.isArray(filter.registered_between) ? filter.registered_between : [];
  form.registered_range = range.length === 2 ? [String(range[0]).slice(0, 10), String(range[1]).slice(0, 10)] : [];
  const before = filter.last_login_before;
  form.last_login_before = typeof before === 'string' && before ? before.slice(0, 10) : '';
  form.product_ids = Array.isArray(filter.product_ids) ? filter.product_ids.map((item) => Number(item)) : [];
  const balance = Array.isArray(filter.balance_between) ? filter.balance_between : [];
  form.balance_min = balance.length === 2 && balance[0] !== null && balance[0] !== undefined ? Number(balance[0]) : undefined;
  form.balance_max = balance.length === 2 && balance[1] !== null && balance[1] !== undefined ? Number(balance[1]) : undefined;
}

function buildFilter(): Record<string, unknown> {
  const filter: Record<string, unknown> = {};
  if (form.status.length > 0) filter.status = form.status.map(Number);
  if (form.verified !== '') filter.verified = form.verified === 'true';
  if (form.member_level_ids.length > 0) filter.member_level_ids = form.member_level_ids.map(Number);
  if (form.registered_range.length === 2 && form.registered_range[0] && form.registered_range[1]) {
    filter.registered_between = [`${form.registered_range[0]} 00:00:00`, `${form.registered_range[1]} 23:59:59`];
  }
  if (form.last_login_before) filter.last_login_before = `${form.last_login_before} 23:59:59`;
  if (form.product_ids.length > 0) filter.product_ids = form.product_ids.map(Number);
  if (form.balance_min !== undefined || form.balance_max !== undefined) {
    // 后端 balance_between 为闭区间二元组（cash_balance >= min AND <= max），不支持 null 边界：
    // 单边条件用边界值表达——余额非负故下界 0，上界取业务极大值（百亿，远超真实余额）
    const BALANCE_CEILING = 9999999999.99;
    const min = form.balance_min !== undefined && form.balance_min !== null ? Number(form.balance_min) : 0;
    const max = form.balance_max !== undefined && form.balance_max !== null ? Number(form.balance_max) : BALANCE_CEILING;
    filter.balance_between = [min, max];
  }
  return filter;
}

function emitFilter() {
  const filter = buildFilter();
  lastEmittedJson = JSON.stringify(filter);
  emit('update:modelValue', filter);
}

watch(
  () => props.modelValue,
  (value) => {
    // 仅在值并非本组件最近一次产出时回填（避免父组件回显触发的循环）
    if (JSON.stringify(value || {}) !== lastEmittedJson) applyFromProps(value || {});
  },
  { deep: true },
);

watch(form, emitFilter, { deep: true });

async function loadMemberLevels() {
  levelsLoading.value = true;
  try {
    const levels = await memberLevelsApi.list();
    levelOptions.value = levels.map((level) => ({ label: String(level.name ?? `等级 #${level.id}`), value: Number(level.id) }));
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载会员等级失败'));
  } finally {
    levelsLoading.value = false;
  }
}

async function handleProductSearch(keyword: string) {
  productsSearching.value = true;
  productsLoading.value = true;
  try {
    const response = await productApi.list({ keyword: String(keyword || '').trim(), page: 1, page_size: 50 });
    productOptions.value = (response.list || []).map((product) => ({
      label: String(
        product.display_name || product.combined_display_name || product.custom_display_name || product.name || `产品 #${product.id}`,
      ),
      value: Number(product.id),
    }));
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '搜索产品失败'));
  } finally {
    productsLoading.value = false;
    productsSearching.value = false;
  }
}

onMounted(() => {
  applyFromProps(props.modelValue || {});
  void loadMemberLevels();
  void handleProductSearch('');
});
</script>
<style scoped>
.user-segment-filter {
  width: 100%;
}

.segment-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0 16px;
}

.segment-span {
  grid-column: span 3;
}

.segment-balance {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 8px;
}

.segment-balance :deep(.t-input-number) {
  flex: 1;
}

.segment-balance__divider {
  color: var(--td-text-color-secondary, rgba(0, 0, 0, 0.6));
  flex-shrink: 0;
}

.segment-divider {
  margin: 4px 0 0;
}

@media (max-width: 768px) {
  .segment-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .segment-span {
    grid-column: span 1;
  }
}
</style>
