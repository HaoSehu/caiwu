<template>
  <div class="marketing-groups-page">
    <t-card :bordered="false" :loading="loading">
      <template #title>产品营销组</template>
      <template #subtitle>共 {{ groups.length }} 个营销组，按「等级 × 营销组」配置会员价格折扣</template>
      <template #actions>
        <t-space v-if="canManage">
          <t-button theme="primary" @click="openCreateDialog">
            <template #icon><add-icon /></template>
            新建营销组
          </t-button>
        </t-space>
      </template>

      <div v-if="!isMobile" class="table-scroll">
        <t-table
          row-key="id"
          :data="groups"
          :columns="columns"
          :loading="loading"
          hover
          table-layout="fixed"
          aria-label="营销分组列表"
        >
          <template #empty>
            <t-empty description="暂无营销组" />
          </template>
          <template #name="{ row }">
            <div class="stack-cell">
              <strong>{{ row.name }}</strong>
              <span>排序 {{ Number(row.sort_order || 0) }}</span>
            </div>
          </template>
          <template #count="{ row }">
            <t-tag theme="primary" variant="light">{{ Number(row.product_count || 0) }} 个商品</t-tag>
          </template>
          <template #actions="{ row }">
            <t-space v-if="canManage" size="small">
              <t-button theme="primary" variant="text" @click="openEditDialog(row)">编辑</t-button>
              <t-button theme="primary" variant="text" @click="openPickDialog(row)">圈选商品</t-button>
              <t-button theme="danger" variant="text" @click="handleDelete(row)">删除</t-button>
            </t-space>
            <span v-else class="muted-text">只读</span>
          </template>
        </t-table>
      </div>

      <div v-else class="mobile-list">
        <article v-for="row in groups" :key="row.id" class="mg-mobile-card">
          <div class="mg-mobile-card__head">
            <div>
              <strong>{{ row.name }}</strong>
              <t-tag theme="primary" variant="light">{{ Number(row.product_count || 0) }} 个商品</t-tag>
            </div>
            <t-dropdown
              v-if="canManage"
              trigger="click"
              placement="bottom-right"
              :options="mobileActionOptions"
              @click="handleMobileAction(row, $event)"
            >
              <t-button class="mg-mobile-card__more" variant="text" shape="square" aria-label="更多操作">
                <template #icon><more-icon /></template>
              </t-button>
            </t-dropdown>
          </div>
          <dl>
            <div>
              <dt>排序</dt>
              <dd>{{ Number(row.sort_order || 0) }}</dd>
            </div>
          </dl>
        </article>
      </div>

      <t-empty v-if="!loading && groups.length === 0" description="暂无营销组，点击右上角新建" />
    </t-card>

    <t-dialog
      v-model:visible="dialogVisible"
      :header="form.id ? '编辑营销组' : '新建营销组'"
      width="min(560px, calc(100vw - 32px))"
      :confirm-btn="{ content: '确定', theme: 'primary' }"
      :confirm-loading="saving"
      @confirm="submitForm"
    >
      <t-form ref="formRef" :data="form" :rules="rules" label-align="right">
        <t-form-item label="营销组名称" name="name">
          <t-input v-model="form.name" placeholder="例如：新用户专享组" maxlength="50" />
        </t-form-item>
        <t-form-item label="排序值" name="sort_order">
          <t-input-number v-model="form.sort_order" :min="0" :max="999999" />
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="pickVisible"
      header="圈选商品"
      width="min(760px, calc(100vw - 32px))"
      :confirm-btn="{ content: '确定', theme: 'primary' }"
      :confirm-loading="picking"
      @confirm="submitPick"
    >
      <t-alert
        theme="info"
        message="商品可归属多个营销组；同商品命中多条折扣时自动取最终价最低的一条。"
        class="pick-alert"
      />
      <product-binding-tree-select v-model="pickProductIds" mode="batch" placeholder="按商品目录勾选商品" />
    </t-dialog>
  </div>
</template>
<script setup lang="ts">
import './index.less';

import { AddIcon, MoreIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, FormInstanceFunctions, FormRule, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';

import type { MarketingProductGroupPayload, MarketingProductGroupRecord } from '@/api/admin';
import { adminApi } from '@/api/admin';
import ProductBindingTreeSelect from '@/components/product-binding-tree-select/index.vue';
import { AdminPermissions } from '@/constants/permissions';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { required } from '@/utils/formRules';
import { hasAdminPermission } from '@/utils/permission';
import { errorMessage } from '@/utils/userMessage';

defineOptions({ name: 'AdminMarketingGroups' });

interface GroupForm {
  id: number | string | null;
  name: string;
  sort_order: number;
}

const loading = ref(false);
const saving = ref(false);
const picking = ref(false);
const dialogVisible = ref(false);
const pickVisible = ref(false);
const formRef = ref<FormInstanceFunctions>();
const groups = ref<MarketingProductGroupRecord[]>([]);
const pickTarget = ref<MarketingProductGroupRecord | null>(null);
const pickProductIds = ref<Array<number | string>>([]);
const isMobile = useMediaQuery('(max-width: 767.98px)');
// 路由只要求 PRODUCT_LIST，写操作必须另判 PRODUCT_MANAGE，否则只读岗能看到并点到写入口
const canManage = computed(() => hasAdminPermission(AdminPermissions.PRODUCT_MANAGE));

const form = reactive<GroupForm>(createDefaultForm());

const rules: Record<string, FormRule[]> = {
  name: [required('请输入营销组名称')],
};

const columns: PrimaryTableCol<MarketingProductGroupRecord>[] = [
  { colKey: 'name', title: '营销组', minWidth: 240 },
  { colKey: 'count', title: '商品数', width: 140, align: 'right' },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 180 },
];
const mobileActionOptions: DropdownOption[] = [
  { content: '编辑', value: 'edit' },
  { content: '圈选商品', value: 'pick' },
  { content: '删除', value: 'delete', theme: 'error' },
];

function createDefaultForm(): GroupForm {
  return {
    id: null,
    name: '',
    sort_order: 0,
  };
}

// 请求竞态防护：删除/新建后的静默刷新可能与首次加载并发，旧响应不得覆盖新数据
let loadRequestSeq = 0;

async function loadGroups() {
  const currentSeq = ++loadRequestSeq;
  loading.value = true;
  try {
    const response = await adminApi.marketingProductGroups.list();
    if (currentSeq !== loadRequestSeq) return;
    groups.value = response;
  } catch (error) {
    if (currentSeq !== loadRequestSeq) return;
    MessagePlugin.error(errorMessage(error, '加载产品营销组失败'));
  } finally {
    if (currentSeq === loadRequestSeq) loading.value = false;
  }
}

function openCreateDialog() {
  Object.assign(form, createDefaultForm());
  dialogVisible.value = true;
}

function openEditDialog(row: MarketingProductGroupRecord) {
  Object.assign(form, {
    id: row.id,
    name: String(row.name || ''),
    sort_order: Number(row.sort_order || 0),
  });
  dialogVisible.value = true;
}

async function submitForm() {
  const result = await formRef.value?.validate?.();
  if (result !== true) return;

  const payload: MarketingProductGroupPayload = {
    name: form.name.trim(),
    sort_order: Number(form.sort_order || 0),
  };

  saving.value = true;
  try {
    if (form.id) {
      await adminApi.marketingProductGroups.update(form.id, payload);
      MessagePlugin.success('营销组已更新');
    } else {
      await adminApi.marketingProductGroups.create(payload);
      MessagePlugin.success('营销组已创建');
    }
    dialogVisible.value = false;
    await loadGroups();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存营销组失败'));
  } finally {
    saving.value = false;
  }
}

async function openPickDialog(row: MarketingProductGroupRecord) {
  pickTarget.value = row;
  pickProductIds.value = [];
  try {
    const detail = await adminApi.marketingProductGroups.show(row.id);
    pickProductIds.value = Array.isArray(detail.product_ids) ? detail.product_ids : [];
    pickVisible.value = true;
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载营销组商品失败'));
  }
}

async function submitPick() {
  if (!pickTarget.value) return;
  picking.value = true;
  try {
    await adminApi.marketingProductGroups.syncProducts(pickTarget.value.id, pickProductIds.value);
    MessagePlugin.success('商品圈选已保存');
    pickVisible.value = false;
    await loadGroups();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存商品圈选失败'));
  } finally {
    picking.value = false;
  }
}

function handleDelete(row: MarketingProductGroupRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除营销组',
    body: `确认删除营销组“${row.name}”吗？组内仍有商品或已配置折扣时无法删除。`,
    confirmBtn: { content: '确定', theme: 'danger' },
    async onConfirm() {
      dialog.setConfirmLoading(true);
      try {
        await adminApi.marketingProductGroups.delete(row.id);
        MessagePlugin.success('营销组已删除');
        dialog.destroy();
        await loadGroups();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除营销组失败'));
      } finally {
        dialog.setConfirmLoading(false);
      }
    },
  });
}

function handleMobileAction(row: MarketingProductGroupRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'edit') {
    openEditDialog(row);
    return;
  }
  if (action === 'pick') openPickDialog(row);
  if (action === 'delete') handleDelete(row);
}

onMounted(loadGroups);
</script>
