/**
 * M7 配置联动规则 · 本地 Schema 校验（前端预检）。
 *
 * 校验口径与后端 ConfigLinkageRuleValidator 保持一致：
 * - op ∈ eq/neq/in/notin/gt/gte/lt/lte/between
 * - type ∈ show/hide/disable/price_adjust（price_adjust 必带 mode∈fixed/percent/set + 数值 value）
 * - target/条件字段必须存在于当前 config_options 的 field 列表
 * - show/hide 目标不能是自身条件字段；范围型字段仅允许 disable/price_adjust
 * - 同一 target 不能同时被 show 与 hide 命中
 * 前端仅预检一道，最终以后端保存校验为准。
 */

const LINKAGE_OPS = ['eq', 'neq', 'in', 'notin', 'gt', 'gte', 'lt', 'lte', 'between'] as const;

const LINKAGE_EFFECT_TYPES = ['show', 'hide', 'disable', 'price_adjust'] as const;

const LINKAGE_ADJUST_MODES = ['fixed', 'percent', 'set'] as const;

/** 范围/滑块型 option_type（镜像后端 ConfigLinkageRuleValidator::RANGE_TYPES） */
const RANGE_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

/** 与后端 resolveFields 的 typeFieldMap 同口径 */
const TYPE_FIELD_MAP: Record<number, string> = {
  4: 'ip_num',
  5: 'os',
  6: 'cpu',
  7: 'cpu',
  8: 'memory',
  9: 'memory',
  10: 'bw',
  11: 'bw',
  12: 'area',
  13: 'system_disk_size',
  14: 'system_disk_size',
  16: 'cpu',
  17: 'memory',
  18: 'bw',
  19: 'system_disk_size',
};

export interface LinkageFieldInfo {
  field: string;
  is_range: boolean;
  sub_ids: string[];
}

export type LinkageConfigOption = Record<string, unknown>;

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : {};
}

function subOptionsOf(option: Record<string, unknown>): unknown[] {
  for (const key of ['sub', 'sub_options']) {
    if (Array.isArray(option[key])) return option[key] as unknown[];
  }
  return [];
}

/**
 * 归集配置项字段：field 名 → { is_range, sub_ids }。
 * 字段解析与后端 resolveFields 同口径（field > TYPE_FIELD_MAP > option_name|spec_key 前缀）。
 */
export function buildLinkageFields(configOptions: LinkageConfigOption[]): Map<string, LinkageFieldInfo> {
  const fields = new Map<string, LinkageFieldInfo>();
  configOptions.forEach((optionValue) => {
    const option = asRecord(optionValue);
    let field = String(option.field ?? '').trim();
    if (!field) {
      const type = Number(option.option_type ?? -1);
      if (TYPE_FIELD_MAP[type]) {
        field = TYPE_FIELD_MAP[type];
      } else {
        const source = String(option.option_name ?? option.spec_key ?? '');
        field = source.split('|')[0].trim();
      }
    }
    if (!field || fields.has(field)) return;

    const subIds: string[] = [];
    subOptionsOf(option).forEach((subValue) => {
      const subId = String(asRecord(subValue).id ?? '').trim();
      if (subId) subIds.push(subId);
    });

    const hasQtyBounds =
      'qty_min' in option || 'qty_max' in option || 'qty_minimum' in option || 'qty_maximum' in option;
    const isRange =
      RANGE_TYPES.includes(Number(option.option_type ?? -1)) ||
      String(option.option_mode ?? '').trim() === 'range' ||
      (subIds.length === 0 && hasQtyBounds);

    fields.set(field, { field, is_range: isRange, sub_ids: subIds });
  });
  return fields;
}

/**
 * 校验规则集合（入参为 JSON.parse 结果），返回中文错误消息列表；空数组表示通过。
 */
export function validateLinkageRules(parsed: unknown, configOptions: LinkageConfigOption[]): string[] {
  if (!Array.isArray(parsed)) {
    return ['配置联动规则必须是数组（顶层为 [...]）'];
  }
  if (parsed.length === 0) {
    return [];
  }

  const fields = buildLinkageFields(configOptions);
  if (fields.size === 0) {
    return ['请先在「产品配置」中添加配置项，再配置联动规则'];
  }

  const errors: string[] = [];
  const showTargets = new Set<string>();
  const hideTargets = new Set<string>();
  const seenIds = new Set<string>();

  parsed.forEach((ruleValue, index) => {
    const ruleNo = index + 1;
    if (!ruleValue || typeof ruleValue !== 'object' || Array.isArray(ruleValue)) {
      errors.push(`配置联动规则第 ${ruleNo} 项格式错误`);
      return;
    }
    const rule = ruleValue as Record<string, unknown>;

    const ruleId = String(rule.id ?? '').trim();
    if (!ruleId) {
      errors.push(`配置联动规则第 ${ruleNo} 项缺少 id`);
      return;
    }
    if (seenIds.has(ruleId)) {
      errors.push(`配置联动规则 id「${ruleId}」重复`);
      return;
    }
    seenIds.add(ruleId);

    const conditionFields = validateWhen(rule, ruleId, fields, errors);
    const effects = rule.effects;
    if (!Array.isArray(effects) || effects.length === 0) {
      errors.push(`联动规则「${ruleId}」的 effects 必须是非空数组`);
      return;
    }

    effects.forEach((effectValue, effectIndex) => {
      const effectNo = effectIndex + 1;
      if (!effectValue || typeof effectValue !== 'object' || Array.isArray(effectValue)) {
        errors.push(`联动规则「${ruleId}」第 ${effectNo} 个效果格式错误`);
        return;
      }
      const effect = effectValue as Record<string, unknown>;

      const type = String(effect.type ?? '').trim();
      if (!(LINKAGE_EFFECT_TYPES as readonly string[]).includes(type)) {
        errors.push(`联动规则「${ruleId}」的效果类型「${type || '（空）'}」不支持（可选 show/hide/disable/price_adjust）`);
        return;
      }

      const target = String(effect.target ?? '').trim();
      if (!target || !fields.has(target)) {
        errors.push(`联动规则「${ruleId}」的目标字段「${target || '（空）'}」不存在于商品配置项`);
        return;
      }

      if ((type === 'show' || type === 'hide') && conditionFields.includes(target)) {
        errors.push(`联动规则「${ruleId}」的 show/hide 目标「${target}」不能同时作为自身条件字段`);
      }

      const targetInfo = fields.get(target)!;
      if ((type === 'show' || type === 'hide') && targetInfo.is_range) {
        errors.push(`联动规则「${ruleId}」的范围型字段「${target}」不支持 show/hide，仅允许 disable/price_adjust`);
      }

      if (type === 'show') showTargets.add(target);
      if (type === 'hide') hideTargets.add(target);

      if (type === 'price_adjust') {
        validatePriceAdjust(effect, ruleId, targetInfo, errors);
      }
    });
  });

  const conflicts = [...showTargets].filter((target) => hideTargets.has(target));
  if (conflicts.length) {
    errors.push(`字段「${conflicts.join('、')}」同时被 show 与 hide 规则命中，请调整规则`);
  }

  return errors;
}

function validateWhen(
  rule: Record<string, unknown>,
  ruleId: string,
  fields: Map<string, LinkageFieldInfo>,
  errors: string[],
): string[] {
  const when = asRecord(rule.when);
  const conditions = when.all;
  if (!Array.isArray(conditions)) {
    errors.push(`联动规则「${ruleId}」的 when.all 必须是条件数组`);
    return [];
  }

  const conditionFields: string[] = [];
  conditions.forEach((conditionValue, conditionIndex) => {
    const conditionNo = conditionIndex + 1;
    if (!conditionValue || typeof conditionValue !== 'object' || Array.isArray(conditionValue)) {
      errors.push(`联动规则「${ruleId}」第 ${conditionNo} 个条件格式错误`);
      return;
    }
    const condition = conditionValue as Record<string, unknown>;

    const field = String(condition.field ?? '').trim();
    if (!field || !fields.has(field)) {
      errors.push(`联动规则「${ruleId}」的条件字段「${field || '（空）'}」不存在于商品配置项`);
      return;
    }

    const op = String(condition.op ?? '').trim();
    if (!(LINKAGE_OPS as readonly string[]).includes(op)) {
      errors.push(`联动规则「${ruleId}」的条件操作符「${op || '（空）'}」不支持（可选 ${LINKAGE_OPS.join('/')}）`);
      return;
    }

    if (!('value' in condition)) {
      errors.push(`联动规则「${ruleId}」的条件缺少比较值`);
      return;
    }

    if (op === 'between' && (!Array.isArray(condition.value) || condition.value.length !== 2)) {
      errors.push(`联动规则「${ruleId}」的 between 条件值必须是 [min, max]`);
    }

    conditionFields.push(field);
  });

  return conditionFields;
}

function validatePriceAdjust(
  effect: Record<string, unknown>,
  ruleId: string,
  targetField: LinkageFieldInfo,
  errors: string[],
): void {
  const mode = String(effect.mode ?? '').trim();
  if (!(LINKAGE_ADJUST_MODES as readonly string[]).includes(mode)) {
    errors.push(`联动规则「${ruleId}」的 price_adjust.mode 必须是 fixed/percent/set`);
  }

  const value = effect.value;
  if (value === null || value === undefined || typeof value === 'boolean' || value === '' || !Number.isFinite(Number(value))) {
    errors.push(`联动规则「${ruleId}」的 price_adjust.value 必须是数值`);
  }

  if (effect.scope === null || effect.scope === undefined) return;
  const scope = asRecord(effect.scope);
  if (Object.keys(scope).length === 0) {
    errors.push(`联动规则「${ruleId}」的 price_adjust.scope 格式错误`);
    return;
  }

  const subOptionId = String(scope.sub_option_id ?? '').trim();
  if (!subOptionId) return;
  if (!targetField.sub_ids.includes(subOptionId)) {
    errors.push(`联动规则「${ruleId}」的 price_adjust.scope.sub_option_id「${subOptionId}」不存在于字段子选项`);
  }
}
