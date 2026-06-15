declare module '@shared/statusConfig' {
  export const INVOICE_STATUS_MAP: Record<string | number, { label: string; tagType: string }>;
  export const ORDER_STATUS_MAP: Record<string | number, { label: string; tagType: string }>;
  export const REWARD_STATUS_MAP: Record<string | number, { label: string; tagType: string }>;
  export const SERVICE_STATUS_MAP: Record<string | number, { label: string; tagType: string }>;
  export function toLabelMap(statusMap: Record<string | number, { label: string }>): Record<string, string>;
  export function toSelectOptions(
    statusMap: Record<string | number, { label: string }>,
    includeAll?: boolean,
  ): Array<{ label: string; value: string | number }>;
  export function toTagTypeMap(statusMap: Record<string | number, { tagType: string }>): Record<string, string>;
}
