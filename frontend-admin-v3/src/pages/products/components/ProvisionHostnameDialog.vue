<template>
  <t-dialog
    v-model:visible="visible"
    header="批量主机名规则"
    width="min(620px, calc(100vw - 32px))"
    :confirm-btn="{ content: '保存规则', loading: submitting }"
    @confirm="submit"
  >
    <div class="split-dialog-intro">
      <strong>设置商品开通主机名</strong>
      <p>该配置会在自动开通时优先用于上游 host 参数，保存后会覆盖所选商品的现有规则。</p>
    </div>

    <div class="split-product-meta">
      <span>已选商品 {{ targetKeys.length }} 个</span>
      <span v-if="hasMixedRules">当前包含多种主机名规则</span>
    </div>

    <t-alert v-if="hasMixedRules" theme="warning" message="当前选中的商品存在不同规则，保存后会统一覆盖为当前设置。" />

    <t-form class="hostname-rule-form" :data="form" label-width="120px">
      <t-form-item label="开通策略" name="mode">
        <t-radio-group v-model="form.mode" variant="default-filled">
          <t-radio-button value="system">跟随上游</t-radio-button>
          <t-radio-button value="fixed">固定主机名</t-radio-button>
          <t-radio-button value="prefix">指定前缀</t-radio-button>
        </t-radio-group>
      </t-form-item>
      <t-form-item v-if="form.mode === 'fixed'" label="固定主机名" name="value">
        <t-input v-model="form.value" maxlength="200" placeholder="例如 hk-vps-core" />
      </t-form-item>
      <template v-if="form.mode === 'prefix'">
        <t-form-item label="主机名前缀" name="value">
          <t-input v-model="form.value" maxlength="20" placeholder="例如 hk / sg / us" />
        </t-form-item>
        <t-form-item label="主机名总长度" name="length">
          <t-input-number v-model="form.length" :min="4" :max="63" />
        </t-form-item>
      </template>
    </t-form>

    <t-alert theme="info" :message="previewText" />
  </t-dialog>
</template>
<script setup lang="ts">
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, reactive, ref } from 'vue';

import { productApi } from '@/api/product';

import { errorMessage, toPlainRecord } from '../composables/useProductShared';

// 从 ProductCatalog.vue 抽离：主机名规则的读取归一、混合规则判定、预览文案与批量保存。
// 父级只负责收集选中行，保存成功后清空选择并刷新商品列表。
defineOptions({ name: 'ProvisionHostnameDialog' });

const emit = defineEmits<{
  (event: 'done'): void;
  (event: 'loading-change', value: boolean): void;
}>();

type ProvisionHostnameMode = 'system' | 'fixed' | 'prefix';

interface ProvisionHostnameTargetRow {
  id: number | string;
  provision_hostname?: unknown;
}

const visible = ref(false);
const submitting = ref(false);
const targetKeys = ref<Array<string | number>>([]);
const hasMixedRules = ref(false);
const form = reactive({
  mode: 'system' as ProvisionHostnameMode,
  value: '',
  length: 12,
});

const previewText = computed(() => {
  if (form.mode === 'fixed') {
    const value = form.value.trim();
    return value
      ? `开通时将固定提交主机名：${value}。若上游不允许重复主机名，可能会被拒绝。`
      : '固定主机名模式需要填写一个上游允许的 host 值。';
  }

  if (form.mode === 'prefix') {
    const prefix = form.value.trim() || 'prefix';
    return `开通时将按"${prefix} + 随机后缀"生成主机名，总长度 ${Number(form.length || 12)}。`;
  }

  return '默认优先按上游商品规则生成；若上游未返回规则，则回退到系统默认规则或账单快照。';
});

function normalizeRule(ruleValue: unknown) {
  const rule = toPlainRecord(ruleValue);
  const modeValue = String(rule.mode || 'system');
  const mode: ProvisionHostnameMode = ['system', 'fixed', 'prefix'].includes(modeValue)
    ? (modeValue as ProvisionHostnameMode)
    : 'system';
  const rawLength = Number(rule.length || 12);

  return {
    mode,
    value: String(rule.value || '').trim(),
    length: Number.isFinite(rawLength) ? Math.min(63, Math.max(4, rawLength)) : 12,
  };
}

function isSameRule(left: unknown, right: unknown) {
  const current = normalizeRule(left);
  const target = normalizeRule(right);
  return (
    current.mode === target.mode && current.value === target.value && Number(current.length) === Number(target.length)
  );
}

function normalizeProductIds(keys: Array<string | number>) {
  return keys.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
}

function open(rows: ProvisionHostnameTargetRow[]) {
  if (!rows.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }

  const currentRule = normalizeRule(rows[0]?.provision_hostname);
  targetKeys.value = rows.map((row) => row.id);
  hasMixedRules.value = rows.some((row) => !isSameRule(row.provision_hostname, currentRule));
  form.mode = currentRule.mode;
  form.value = currentRule.value;
  form.length = currentRule.length;
  visible.value = true;
}

async function submit() {
  const productIds = normalizeProductIds(targetKeys.value);
  if (!productIds.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }
  if (form.mode !== 'system' && !form.value.trim()) {
    MessagePlugin.warning(form.mode === 'fixed' ? '请输入固定主机名' : '请输入主机名前缀');
    return;
  }

  submitting.value = true;
  emit('loading-change', true);
  try {
    await productApi.batchUpdateProvisionHostname({
      product_ids: productIds,
      provision_hostname: {
        mode: form.mode,
        value: form.value.trim(),
        length: Number(form.length || 12),
      },
    });
    MessagePlugin.success('商品开通主机名规则已更新');
    visible.value = false;
    targetKeys.value = [];
    emit('done');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新主机名规则失败'));
  } finally {
    submitting.value = false;
    emit('loading-change', false);
  }
}

defineExpose({ open });
</script>
<style lang="less" scoped>
.hostname-rule-form {
  margin: var(--td-comp-margin-m) 0;
}
</style>
