import {
  BillIcon,
  CatalogIcon,
  ChartLineDataIcon,
  DashboardIcon,
  DesktopIcon,
  ForwardIcon,
  LockOnIcon,
} from 'tdesign-icons-vue-next';
import type { Component } from 'vue';

export interface ServiceConsoleNavItem {
  key: string;
  label: string;
  icon: Component;
}

const consoleTabMeta: Record<string, Omit<ServiceConsoleNavItem, 'key'>> = {
  overview: { label: '控制台总览', icon: DashboardIcon },
  monitor: { label: '监控信息', icon: ChartLineDataIcon },
  security: { label: '安全组', icon: LockOnIcon },
  nat: { label: '端口转发', icon: ForwardIcon },
  logs: { label: '操作日志', icon: CatalogIcon },
  finance: { label: '财务日志', icon: BillIcon },
  vnc: { label: 'VNC 控制台', icon: DesktopIcon },
};

export function resolveConsoleNavItems(tabKeys: string[]): ServiceConsoleNavItem[] {
  return tabKeys.map((key) => ({
    key,
    label: consoleTabMeta[key]?.label || key,
    icon: consoleTabMeta[key]?.icon || DashboardIcon,
  }));
}
