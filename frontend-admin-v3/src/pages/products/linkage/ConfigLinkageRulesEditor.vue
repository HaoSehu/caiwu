<template>
  <div class="linkage-editor">
    <div class="linkage-editor-head">
      <div class="linkage-editor-title">
        <strong>配置联动规则</strong>
        <span>根据配置项条件联动显示 / 隐藏 / 禁用其他配置项或调整价格，最终以保存时服务端校验为准。</span>
      </div>
      <t-space size="small">
        <t-button size="small" variant="outline" :loading="checking" @click="runLocalCheck">格式校验</t-button>
        <t-button size="small" variant="outline" @click="fillExample">插入示例</t-button>
      </t-space>
    </div>
    <t-textarea
      :value="modelValue"
      class="linkage-editor-textarea"
      :autosize="{ minRows: 8, maxRows: 22 }"
      placeholder='请输入 JSON 数组格式的联动规则，留空并保存将清空全部规则。可点击「插入示例」查看写法。'
      @change="handleChange"
    />
    <div v-if="localCheckResult" class="linkage-editor-result" :class="{ 'is-error': !localCheckPassed }">
      {{ localCheckResult }}
    </div>
    <t-collapse class="linkage-editor-help" :default-value="[]" borderless>
      <t-collapse-panel value="schema" header="规则 Schema 帮助说明">
        <div class="linkage-help-section">
          <p>规则为数组，每条规则结构：</p>
          <pre><code>[
  {
    "id": "rule_os_win",                    // 规则唯一标识，不可重复
    "when": {                               // 条件块，多个条件为「且」关系
      "all": [
        { "field": "os", "op": "eq", "value": "windows" }
      ]
    },
    "effects": [                            // 命中后执行的效果，至少一项
      { "type": "show", "target": "rdp_edition" },
      { "type": "price_adjust", "target": "memory", "scope": { "sub_option_id": "8g" },
        "mode": "fixed", "value": 10.00 }
    ]
  }
]</code></pre>
        </div>
        <div class="linkage-help-section">
          <p><strong>条件操作符 op</strong>：eq / neq / in / notin / gt / gte / lt / lte / between（between 的 value 须为 [min, max]）。</p>
          <p><strong>效果类型 type</strong>：</p>
          <ul>
            <li><code>show</code>：目标默认不可见，条件命中才显示；</li>
            <li><code>hide</code>：目标默认可见，条件命中才隐藏（被隐藏字段不参与计价）；</li>
            <li><code>disable</code>：禁用目标字段，值保持参与计价；</li>
            <li><code>price_adjust</code>：价格调整，必带 <code>mode</code>（fixed 固定加价 / percent 百分比 / set 直接设定）与数值 <code>value</code>，多规则叠加即求和；<code>scope.sub_option_id</code> 可选，用于仅调整目标字段的某个子选项。</li>
          </ul>
          <p><strong>限制</strong>：target 必须存在于当前配置项 field 列表；同一字段不能同时被 show 与 hide 命中；show/hide 目标不能是自身条件字段；范围型（数量区间）字段仅允许 disable / price_adjust。</p>
        </div>
        <div class="linkage-help-section">
          <p>hide 与 percent 调价示例：</p>
          <pre><code>[
  {
    "id": "rule_hide_panel",
    "when": { "all": [ { "field": "edition", "op": "eq", "value": "basic" } ] },
    "effects": [ { "type": "hide", "target": "panel_choice" } ]
  },
  {
    "id": "rule_mem_up",
    "when": { "all": [ { "field": "cpu", "op": "gte", "value": 8 } ] },
    "effects": [ { "type": "price_adjust", "target": "memory", "mode": "percent", "value": 5 } ]
  }
]</code></pre>
        </div>
      </t-collapse-panel>
    </t-collapse>
  </div>
</template>

<script setup lang="ts">
import { MessagePlugin } from 'tdesign-vue-next';
import { ref } from 'vue';

import { validateLinkageRules, type LinkageConfigOption } from './linkageSchema';

const props = defineProps<{
  /** JSON 文本（编辑回显时由父组件格式化填入） */
  modelValue: string;
  /** 当前商品配置项列表，用于 target / 条件字段的本地存在性预检 */
  configOptions: LinkageConfigOption[];
}>();

const emit = defineEmits<{
  /** 文本变更 */
  (e: 'update:modelValue', value: string): void;
  /** 用户触碰过该区块（父组件据此决定是否随 payload 提交） */
  (e: 'touched'): void;
}>();

const checking = ref(false);
const localCheckPassed = ref(false);
const localCheckResult = ref('');

const EXAMPLE_TEXT = JSON.stringify(
  [
    {
      id: 'rule_os_win',
      when: { all: [{ field: 'os', op: 'eq', value: 'windows' }] },
      effects: [
        { type: 'show', target: 'rdp_edition' },
        { type: 'price_adjust', target: 'memory', scope: { sub_option_id: '8g' }, mode: 'fixed', value: 10 },
      ],
    },
  ],
  null,
  2,
);

function handleChange(value: unknown) {
  emit('update:modelValue', String(value ?? ''));
  emit('touched');
  localCheckResult.value = '';
}

function fillExample() {
  if (props.modelValue.trim()) {
    MessagePlugin.warning('当前已有规则内容，插入示例将覆盖，请先备份');
    return;
  }
  emit('update:modelValue', EXAMPLE_TEXT);
  emit('touched');
  localCheckResult.value = '';
}

/**
 * 本地预检：JSON.parse + Schema 校验，结果通过 MessagePlugin 与内嵌提示条反馈。
 * 返回 true 表示文本为空（视为清空）或校验通过。
 */
function runLocalCheck(): boolean {
  checking.value = true;
  try {
    const text = props.modelValue.trim();
    if (!text) {
      localCheckPassed.value = true;
      localCheckResult.value = '规则为空，保存后将清空该商品的全部联动规则';
      return true;
    }
    let parsed: unknown;
    try {
      parsed = JSON.parse(text);
    } catch (error) {
      localCheckPassed.value = false;
      localCheckResult.value = `JSON 解析失败：${error instanceof Error ? error.message : String(error)}`;
      MessagePlugin.error('联动规则不是合法的 JSON，请检查格式');
      return false;
    }
    const errors = validateLinkageRules(parsed, props.configOptions);
    if (errors.length) {
      localCheckPassed.value = false;
      localCheckResult.value = `校验未通过（${errors.length} 项）：${errors.slice(0, 3).join('；')}`;
      errors.slice(0, 3).forEach((message) => MessagePlugin.error(message));
      return false;
    }
    localCheckPassed.value = true;
    localCheckResult.value = '校验通过';
    MessagePlugin.success('联动规则格式校验通过');
    return true;
  } finally {
    checking.value = false;
  }
}

defineExpose({
  /** 保存前调用：文本为空或校验通过返回 true；失败返回 false（错误已透出） */
  validate: runLocalCheck,
});
</script>

<style lang="less" scoped>
.linkage-editor {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
  margin-top: var(--td-comp-margin-m);
}

.linkage-editor-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--td-comp-margin-s);
}

.linkage-editor-title {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;

  strong {
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-size-3, 14px);
    font-weight: 600;
    line-height: 22px;
  }

  span {
    color: var(--td-text-color-secondary);
    font-size: var(--td-font-size-size-1, 12px);
    line-height: 18px;
  }
}

.linkage-editor-textarea :deep(textarea) {
  font-family: var(--td-font-family-code, 'SFMono-Regular', Consolas, monospace);
  font-size: var(--td-font-size-size-2, 13px);
  line-height: 20px;
}

.linkage-editor-result {
  border-radius: var(--td-radius-default, 4px);
  padding: 6px 10px;
  background: var(--td-success-color-1, var(--td-brand-color-light));
  color: var(--td-success-color, var(--td-brand-color));
  font-size: var(--td-font-size-size-1, 12px);
  line-height: 18px;
  word-break: break-all;

  &.is-error {
    background: var(--td-error-color-1);
    color: var(--td-error-color);
  }
}

.linkage-editor-help {
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-default, 4px);
  padding: 0 var(--td-comp-paddingLR-m);
  background: var(--td-bg-color-page);
}

.linkage-help-section {
  p {
    margin: 0 0 var(--td-comp-margin-xs);
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-size-2, 13px);
    line-height: 20px;
  }

  ul {
    margin: 0 0 var(--td-comp-margin-xs);
    padding-left: 18px;
    color: var(--td-text-color-primary);
    font-size: var(--td-font-size-size-2, 13px);
    line-height: 22px;
  }

  code {
    border-radius: var(--td-radius-small);
    padding: 0 4px;
    background: var(--td-bg-color-secondarycontainer);
    color: var(--td-text-color-primary);
    font-family: var(--td-font-family-code, 'SFMono-Regular', Consolas, monospace);
    font-size: var(--td-font-size-size-1, 12px);
  }

  pre {
    margin: 0 0 var(--td-comp-margin-xs);
    overflow-x: auto;
    border-radius: var(--td-radius-default, 4px);
    padding: var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-s);
    background: var(--td-bg-color-secondarycontainer);

    code {
      padding: 0;
      background: transparent;
      font-size: var(--td-font-size-size-1, 12px);
      line-height: 18px;
      white-space: pre;
    }
  }
}
</style>
