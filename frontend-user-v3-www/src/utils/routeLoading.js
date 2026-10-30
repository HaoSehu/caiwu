// 仅转发子路径：经 '@caiwu/shared/runtime' 根桶会连带加载 http/client 等未使用模块。
export { beginRouteLoading, isRouteLoading } from '@caiwu/shared/runtime/routeLoading'
