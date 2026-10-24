<template>
  <section class="user-detail-section">
    <div class="detail-toolbar compact">
      <t-select v-model="state.filters.status" clearable placeholder="状态" @change="search">
        <t-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
      </t-select>
      <t-select v-model="state.filters.type" clearable placeholder="类型" @change="search">
        <t-option v-for="option in typeOptions" :key="option.value" :label="option.label" :value="option.value" />
      </t-select>
      <t-button v-if="canManualOrder" theme="primary" @click="openManualOrderDialog">补录订单</t-button>
    </div>
    <div class="table-scroll">
      <t-table
        row-key="id"
        :data="state.list"
        :columns="columns"
        :loading="state.loading"
        :pagination="pagination"
        table-layout="fixed"
        @page-change="handlePageChange"
      >
        <template #empty>
          <t-empty :description="state.loading ? '加载中…' : hasActiveFilters ? '未找到匹配结果' : '暂无数据'" />
        </template>
        <template #orderProduct="{ row }">
          <strong>{{ fieldValue(row.product_full_path || row.product_name) }}</strong>
        </template>
        <template #orderType="{ row }">{{ typeLabel(row.type) }}</template>
        <template #orderStatus="{ row }">
          <t-tag :theme="statusTheme(row.status)" variant="light">{{ statusLabel(row.status) }}</t-tag>
        </template>
        <template #orderAmount="{ row }">{{ formatMoney(row.amount) }}</template>
        <template #orderPaid="{ row }">{{ formatDateTime(row.paid_at) }}</template>
        <template #orderCreated="{ row }">{{ formatDateTime(row.created_at) }}</template>
        <template #orderOperation="{ row }">
          <div class="row-actions">
            <t-button variant="text" theme="primary" size="small" @click="goToOrderDetail(row)">详情</t-button>
          </div>
        </template>
      </t-table>
    </div>

    <t-dialog
      v-model:visible="manualOrderVisible"
      header="补录订单"
      width="560px"
      :confirm-btn="{ content: '确定', loading: submitting }"
      @cancel="manualOrderVisible = false"
      @confirm="handleManualOrderSubmit"
    >
      <t-alert
        theme="info"
        message="补录用于登记系统外收款的新购/续费/附加配置费用，仅生成已支付订单与账单记录，不会变更实例状态与到期时间。"
      />
      <t-form ref="formRef" :data="form" :rules="rules" label-align="top" class="dialog-form">
        <t-form-item label="服务实例" name="service_id">
          <t-select
            v-model="form.service_id"
            filterable
            :loading="servicesLoading"
            placeholder="请选择该用户名下的服务实例"
          >
            <t-option v-for="service in serviceOptions" :key="service.id" :label="service.label" :value="service.id" />
          </t-select>
        </t-form-item>
        <t-form-item label="订单类型" name="type">
          <t-radio-group v-model="form.type">
            <t-radio value="new">新购</t-radio>
            <t-radio value="renew">续费</t-radio>
            <t-radio value="upgrade">附加配置</t-radio>
          </t-radio-group>
        </t-form-item>
        <t-form-item label="金额" name="amount">
          <t-input-number v-model="form.amount" :min="0.01" :max="999999" :decimal-places="2" style="width: 100%" />
        </t-form-item>
        <t-form-item label="计费周期">
          <t-select v-model="form.billing_cycle" clearable placeholder="默认跟随实例当前周期">
            <t-option
              v-for="option in billingOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </t-select>
        </t-form-item>
        <t-form-item label="支付方式" name="payment_gateway">
          <t-select v-model="form.payment_gateway" placeholder="登记系统外收款渠道">
            <t-option
              v-for="option in paymentMethodOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </t-select>
        </t-form-item>
        <t-form-item label="交易号">
          <t-input v-model="form.trade_no" clearable placeholder="线下收款凭证号（选填，同用户内查重）" />
        </t-form-item>
        <t-form-item label="备注" name="remark">
          <t-textarea v-model="form.remark" :maxlength="200" placeholder="请填写补录原因" />
        </t-form-item>
      </t-form>
    </t-dialog>
  </section>
</template>
<script setup lang="ts">
import {
  MANUAL_PAYMENT_METHOD_MAP,
  ORDER_STATUS_MAP,
  toLabelMap,
  toSelectOptions,
  toTagTypeMap,
} from '@caiwu/shared/statusConfig';
import type { FormInstanceFunctions, FormRule, PageInfo, PrimaryTableCol, TableRowData } from 'tdesign-vue-next';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import type { ManualPaymentGateway, PageParams } from '@/api/user';
import { userApi } from '@/api/user';
import { AdminPermissions } from '@/constants/permissions';
import { fieldValue, formatDateTime, formatMoney } from '@/utils/format';
import { required } from '@/utils/formRules';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

type Row = Record<string, any>;

interface TabPageState {
  loading: boolean;
  list: Row[];
  total: number;
  page: number;
  pageSize: number;
  filters: Record<string, string | number>;
}

const props = defineProps<{ userId: number | string }>();
const emit = defineEmits<{ (event: 'changed'): void }>();

const canManualOrder = computed(() => hasAdminPermission(AdminPermissions.ORDER_MANUAL_ENTRY));
const statusOptions = toSelectOptions(ORDER_STATUS_MAP, false);
const statusLabelMap = toLabelMap(ORDER_STATUS_MAP);
const statusTypeMap = toTagTypeMap(ORDER_STATUS_MAP);
const typeOptions = [
  { label: '新购', value: 'new' },
  { label: '续费', value: 'renew' },
  { label: '附加配置', value: 'upgrade' },
];
const billingOptions = [
  { label: '月付', value: 'monthly' },
  { label: '季付', value: 'quarterly' },
  { label: '半年付', value: 'semiannually' },
  { label: '年付', value: 'annually' },
];
const paymentMethodOptions = toSelectOptions(MANUAL_PAYMENT_METHOD_MAP, false);

const state = reactive<TabPageState>({
  loading: false,
  list: [],
  total: 0,
  page: 1,
  pageSize: 10,
  filters: { status: '', type: '' },
});

// 空态文案区分「未筛选但无数据」与「筛选后无匹配」（docs/DESIGN.md 术语表）
const hasActiveFilters = computed(() => Boolean(state.filters.status) || Boolean(state.filters.type));

const router = useRouter();

/** 列表请求序号，用于丢弃过期响应 */
let requestSeq = 0;

/** 跳转财务订单详情页（订单↔账单联动详情，支持退款标记等操作入口）。 */
function goToOrderDetail(row: TableRowData) {
  const id = Number(row?.id ?? 0);
  if (!id) return;
  router.push(`/admin/finance/orders/${id}`);
}

const columns: PrimaryTableCol<TableRowData>[] = [
  { title: '订单号', colKey: 'order_no', width: 200 },
  { title: '产品', colKey: 'orderProduct', minWidth: 240 },
  { title: '类型', colKey: 'orderType', width: 110 },
  { title: '金额', colKey: 'orderAmount', width: 120 },
  { title: '状态', colKey: 'orderStatus', width: 110 },
  { title: '支付时间', colKey: 'orderPaid', width: 180 },
  { title: '创建时间', colKey: 'orderCreated', width: 180 },
  { title: '操作', colKey: 'orderOperation', width: 100 },
];

const pagination = computed(() => ({
  current: state.page,
  pageSize: state.pageSize,
  total: state.total,
  showJumper: true,
}));

const manualOrderVisible = ref(false);
const submitting = ref(false);
const servicesLoading = ref(false);
const serviceOptions = ref<Array<{ id: number; label: string }>>([]);
const formRef = ref<FormInstanceFunctions>();
const form = reactive({
  service_id: undefined as number | undefined,
  type: 'renew' as 'new' | 'renew' | 'upgrade',
  amount: 0,
  billing_cycle: '',
  payment_gateway: 'bank_transfer' as ManualPaymentGateway,
  trade_no: '',
  remark: '',
});
const rules: Record<string, FormRule[]> = {
  service_id: [required('请选择服务实例')],
  type: [required('请选择订单类型')],
  amount: [required('请输入补录金额')],
  payment_gateway: [required('请选择支付方式')],
  remark: [required('请填写补录原因')],
};

function typeLabel(value: unknown) {
  return (typeOptions.find((option) => option.value === String(value)) || { label: String(value ?? '-') }).label;
}
function statusLabel(value: unknown) {
  return String(statusLabelMap[Number(value)] ?? value ?? '-');
}
function statusTheme(value: unknown): 'default' | 'success' | 'warning' | 'danger' {
  return (statusTypeMap[Number(value)] as 'default' | 'success' | 'warning' | 'danger') || 'default';
}

async function load() {
  // 请求竞态防护：连续筛选/翻页时只允许最后一次请求回写结果（docs/FRONTEND.md）
  const currentSeq = ++requestSeq;
  state.loading = true;
  try {
    const params: PageParams = { ...state.filters, page: state.page, page_size: state.pageSize };
    const response = await userApi.orders(props.userId, params);
    if (currentSeq !== requestSeq) return;
    state.list = Array.isArray(response.list) ? response.list : [];
    state.total = Number(response.total || 0);
    state.page = Number(response.page || state.page);
    state.pageSize = Number(response.page_size || state.pageSize);
  } catch (error) {
    if (currentSeq !== requestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载订单列表失败'));
  } finally {
    if (currentSeq === requestSeq) state.loading = false;
  }
}

function search() {
  state.page = 1;
  load();
}

function handlePageChange(pageInfo: PageInfo) {
  state.page = pageInfo.current;
  state.pageSize = pageInfo.pageSize;
  load();
}

async function loadServiceOptions() {
  servicesLoading.value = true;
  try {
    const response = await userApi.services(props.userId, { page: 1, page_size: 100 });
    serviceOptions.value = (response.list || []).map((service: Row) => {
      const name = String(service.name || service.domain || '');
      return {
        id: Number(service.id),
        label: name ? `${name}（#${service.id}）` : `实例 #${service.id}`,
      };
    });
    if (Number(response.total || 0) > serviceOptions.value.length) {
      MessagePlugin.warning('该用户实例较多，仅展示前 100 个，可通过搜索定位后再补录');
    }
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载服务实例失败'));
  } finally {
    servicesLoading.value = false;
  }
}

function openManualOrderDialog() {
  form.service_id = undefined;
  form.type = 'renew';
  form.amount = 0;
  form.billing_cycle = '';
  form.payment_gateway = 'bank_transfer';
  form.trade_no = '';
  form.remark = '';
  if (!serviceOptions.value.length) loadServiceOptions();
  manualOrderVisible.value = true;
}

async function handleManualOrderSubmit() {
  const result = await formRef.value?.validate?.();
  if (!isValidationPass(result)) return;
  if (!form.service_id) return;
  submitting.value = true;
  try {
    await userApi.storeManualOrder(props.userId, {
      service_id: form.service_id,
      type: form.type,
      amount: form.amount,
      ...(form.billing_cycle ? { billing_cycle: form.billing_cycle } : {}),
      ...(form.trade_no.trim() ? { trade_no: form.trade_no.trim() } : {}),
      payment_gateway: form.payment_gateway,
      remark: form.remark,
    });
    MessagePlugin.success('补录订单成功');
    manualOrderVisible.value = false;
    await load();
    // 订单会派生已支付账单，通知父级联动刷新账单 tab。
    emit('changed');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '补录订单失败'));
  } finally {
    submitting.value = false;
  }
}

function isValidationPass(result: unknown) {
  return result === true || result === undefined;
}

defineExpose({ reload: load });

onMounted(load);
</script>
