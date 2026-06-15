declare module '@shared/statusConfig' {
  export const SERVICE_STATUS: Record<string, number>;

  export const SERVICE_STATUS_MAP: Record<
    string | number,
    {
      label: string;
      color?: string;
      tagType?: string;
      theme?: string;
      [key: string]: unknown;
    }
  >;

  export function toSelectOptions(
    statusMap: Record<string | number, unknown>,
    includeAll?: boolean,
  ): Array<{
    label: string;
    value: string | number;
    [key: string]: unknown;
  }>;

  export function getStatusConfig(statusMap: Record<string | number, unknown>, status: string | number): unknown;
  export function getStatusLabel(statusMap: Record<string | number, unknown>, status: string | number): string;
  export function getStatusTagType(statusMap: Record<string | number, unknown>, status: string | number): string;
}
