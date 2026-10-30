// 路由懒加载包装：加载期间累加进度计数器，配合 routeLoading 的进度条使用。
import { beginRouteLoading } from './routeLoading'

export function lazyRouteView<T>(loader: () => Promise<T>) {
  return async () => {
    const stopLoading = beginRouteLoading()

    try {
      return await loader()
    } finally {
      stopLoading()
    }
  }
}
