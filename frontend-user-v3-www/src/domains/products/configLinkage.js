// M7 产品配置联动规则：报价响应 linkage 投影的前端消费层。
// 契约见 docs/_local/parallel-impl-2026-10-11/m7/INTEGRATION.md：
// linkage = { visibility: {field:bool}, disabled: [field],
//             adjustments: {field: {sub_option_id|"*": 金额}}, applied_rule_ids: [], price_delta }
// 无联动规则的商品响应不含 linkage 键，所有函数返回空态（回归红线：零变化）。

// visibility 为 false 的字段已被服务端从计价剔除，前端应隐藏其选择区
export function resolveHiddenConfigFieldSet(linkage) {
  const set = new Set();
  const visibility = linkage?.visibility;
  if (visibility && typeof visibility === "object") {
    Object.keys(visibility).forEach((field) => {
      if (visibility[field] === false) {
        set.add(field);
      }
    });
  }
  return set;
}

// 被禁用的字段列表（保留展示，仅禁选）
export function resolveDisabledConfigFieldSet(linkage) {
  const disabled = linkage?.disabled;
  return new Set(Array.isArray(disabled) ? disabled : []);
}

// 从 config_options 构建 field -> label 映射（linkage 键与 parseField 的 key 同源）
export function buildConfigFieldLabelMap(configOptions = []) {
  const map = new Map();
  (Array.isArray(configOptions) ? configOptions : []).forEach((item) => {
    const raw = String(item?.field || item?.spec_key || "").trim();
    if (!raw) {
      return;
    }
    const key = raw.split("|")[0]?.trim() || "";
    if (!key || map.has(key)) {
      return;
    }
    const label =
      (raw.includes("|") ? raw.split("|").slice(1).join("|").trim() : "") ||
      String(item?.name || "").trim() ||
      key;
    map.set(key, label);
  });
  return map;
}

// 从 config_options 构建 field -> sub_option_id -> label 映射，
// 供 adjustments 明细行展示子项名；sub 的 label 取 version/option_name 的最后一段（与配置区口径一致）
export function buildConfigSubLabelMap(configOptions = []) {
  const map = new Map();
  (Array.isArray(configOptions) ? configOptions : []).forEach((item) => {
    const raw = String(item?.field || item?.spec_key || "").trim();
    const key = raw.split("|")[0]?.trim() || "";
    if (!key || !Array.isArray(item?.sub)) {
      return;
    }
    const subMap = new Map();
    item.sub.forEach((subItem) => {
      const id = String(subItem?.id ?? "").trim();
      if (!id) {
        return;
      }
      const version = String(
        subItem.version || subItem.option_name || subItem.option_name_first || "",
      );
      const parts = version.split("^");
      subMap.set(id, parts[parts.length - 1] || version || id);
    });
    if (subMap.size) {
      map.set(key, subMap);
    }
  });
  return map;
}

// adjustments -> 明细行列表 [{ key, fieldLabel, subLabel, amountText }]
// amountText 不带正号（负数带 -），展示层自加 ¥ 前缀
function formatAmountText(amount) {
  const value = Number(amount || 0);
  if (!Number.isFinite(value)) {
    return "";
  }
  const text = Math.abs(value).toFixed(2);
  return value < 0 ? `-${text}` : text;
}

export function resolveLinkageAdjustmentRows(linkage, configOptions = []) {
  const adjustments = linkage?.adjustments;
  if (!adjustments || typeof adjustments !== "object") {
    return [];
  }

  const fieldLabels = buildConfigFieldLabelMap(configOptions);
  const subLabels = buildConfigSubLabelMap(configOptions);
  const rows = [];

  Object.keys(adjustments).forEach((field) => {
    const fieldAdjustments = adjustments[field];
    if (!fieldAdjustments || typeof fieldAdjustments !== "object") {
      return;
    }
    const fieldLabel = fieldLabels.get(field) || field;
    Object.keys(fieldAdjustments).forEach((subKey) => {
      const amountText = formatAmountText(fieldAdjustments[subKey]);
      if (!amountText) {
        return;
      }
      const subLabel =
        subKey === "*"
          ? ""
          : subLabels.get(field)?.get(subKey) || `选项 ${subKey}`;
      rows.push({
        key: `${field}::${subKey}`,
        fieldLabel,
        subLabel,
        amountText,
        // 展示层用：sign 为 "+"/"-"，absText 为不带符号金额（负数行不再出现 "+ ... ¥-5.00"）
        sign: amountText.startsWith("-") ? "-" : "+",
        absText: amountText.replace("-", ""),
      });
    });
  });

  return rows;
}
