/**
 * 财务快照展示兜底的跨端单一实现。
 *
 * 计价配置明细（orders/invoices 的 config_pricing_snapshot.items）由后端下单链路生成，
 * 商品未配置系统盘项时明细缺「系统盘」行；服务实例 specs 同理可能缺系统盘规格。
 * 本模块承载平台默认系统盘口径与计价快照补行逻辑，管理端与用户控制台共用。
 */

/**
 * 无系统盘配置项时的平台默认系统盘展示值（套餐固定包含，不单独计价）。
 * 数值口径为业务拍板结论（2026-10），与上游缺省行为（CloudConfigTemplate 默认 50G）无关。
 */
export const DEFAULT_SYSTEM_DISK_ITEM: Readonly<{
  field: string;
  label: string;
  value_label: string;
}> = Object.freeze({
  field: 'system_disk_size',
  label: '系统盘',
  value_label: 'Linux 30G / Windows 40G',
});

/** 服务实例规格缺系统盘项时的展示值（与计价快照兜底同口径）。 */
export const DEFAULT_SYSTEM_DISK_LABEL: string = DEFAULT_SYSTEM_DISK_ITEM.value_label;

function toRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

/**
 * 计价快照兜底：完整配置单（含 os 项）但未携带系统盘时补默认系统盘行。
 * 续费/升级/流量包快照的 items 为单条业务行（无 os 项），不会命中；
 * label 比对覆盖历史数据中 field 非标准但展示名为「系统盘」的行，避免双行与重复 key。
 */
export function withDefaultSystemDisk(snapshot: unknown): Record<string, unknown> {
  const source = toRecord(snapshot);
  const items = Array.isArray(source.items) ? source.items : [];
  if (!items.length) return source;
  const records = items.map(toRecord);
  const hasOs = records.some((item) => String(item.field || '') === 'os');
  const hasSystemDisk = records.some(
    (item) => String(item.field || '') === 'system_disk_size' || String(item.label || '') === '系统盘',
  );
  if (!hasOs || hasSystemDisk) return source;
  return { ...source, items: [...items, DEFAULT_SYSTEM_DISK_ITEM] };
}
