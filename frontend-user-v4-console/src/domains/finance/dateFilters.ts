export type FinanceQuickFilter = '' | 'week' | 'month' | 'pending' | string;

export interface DateRangeFilter {
  start_date?: string;
  end_date?: string;
}

export interface QuickFilterOption {
  key: FinanceQuickFilter;
  label: string;
}

// 财务记录列表页快捷筛选的单一真源
export const QUICK_FILTER_OPTIONS: QuickFilterOption[] = [
  { key: '', label: '全部' },
  { key: 'week', label: '最近7天' },
  { key: 'month', label: '本月' },
  { key: 'pending', label: '待支付' },
];

function formatYmd(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function resolveQuickDateRange(key: FinanceQuickFilter, now = new Date()): DateRangeFilter {
  if (key === 'week') {
    const start = new Date(now);
    start.setDate(now.getDate() - 6);
    return {
      start_date: formatYmd(start),
      end_date: formatYmd(now),
    };
  }

  if (key === 'month') {
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    return {
      start_date: formatYmd(start),
      end_date: formatYmd(end),
    };
  }

  return {};
}

// 手动选择的区间恰好等于某快捷标签折算区间时，回写该标签的选中态；否则视为自定义区间
export function matchQuickFilterByRange(start?: string, end?: string): FinanceQuickFilter {
  if (!start || !end) return '';
  return (
    QUICK_FILTER_OPTIONS.find((item) => {
      if (!item.key) return false;
      const range = resolveQuickDateRange(item.key);
      return range.start_date === start && range.end_date === end;
    })?.key || ''
  );
}
