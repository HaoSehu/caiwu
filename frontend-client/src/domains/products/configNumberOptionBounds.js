export function resolveNumberOptionBounds(item) {
  const rawMin = Number(item?.qty_minimum ?? 0)
  const rawMax = Number(item?.qty_maximum ?? 0)
  const hasExplicitMin = item?.qty_minimum !== undefined && item?.qty_minimum !== null && item?.qty_minimum !== ''
  const min = hasExplicitMin ? rawMin : 1

  return {
    min,
    max: rawMax > 0 ? Math.max(rawMax, min) : Math.max(rawMax, min),
    hasExplicitMin,
  }
}
