// 说明：当前只有官网（frontend-user-v3-www）使用路由加载态（App.vue 的顶部进度条）。
// 保留在 shared 是为了让 lazyRouteView 与进度条共用同一计数器；管理端与控制台
// 不使用进度条，若将来接入需同样通过本模块，不要在端内另建一份 ref。
import { computed, ref } from 'vue'

const pendingRouteLoads = ref(0)

export const isRouteLoading = computed(() => pendingRouteLoads.value > 0)

export function beginRouteLoading() {
  pendingRouteLoads.value += 1

  let settled = false

  return () => {
    if (settled) {
      return
    }

    settled = true
    pendingRouteLoads.value = Math.max(0, pendingRouteLoads.value - 1)
  }
}
