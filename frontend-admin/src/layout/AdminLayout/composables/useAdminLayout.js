import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Bell,
  Box,
  ChatDotRound,
  Cpu,
  DataLine,
  Document,
  Grid,
  Help,
  Medal,
  Odometer,
  OfficeBuilding,
  Present,
  Promotion,
  Reading,
  Setting,
  Ticket,
  Tickets,
  User,
} from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import { useResponsive } from '@/composables/useResponsive'

export function useAdminLayout() {
  const route = useRoute()
  const router = useRouter()
  const userStore = useUserStore()
  const appStore = useAppStore()

  const { isMobile } = useResponsive()
  const mobileNavVisible = ref(false)

  const navigationSections = [
    {
      key: 'overview',
      label: '数据总览',
      items: [
        { index: '/admin/dashboard', title: '仪表盘', icon: Odometer },
      ],
    },
    {
      key: 'user-system',
      label: '用户体系',
      items: [
        { index: '/admin/users', title: '用户管理', icon: User },
        { index: '/admin/member-levels', title: '会员等级', icon: Medal },
      ],
    },
    {
      key: 'product',
      label: '产品',
      items: [
        { index: '/admin/products', title: '商品目录', icon: Box },
        { index: '/admin/specs', title: '规格管理', icon: Grid },
        { index: '/admin/cpu-models', title: 'CPU型号管理', icon: Cpu },
        { index: '/admin/products/traffic-packages', title: '流量包', icon: DataLine },
        { index: '/admin/products/suppliers', title: '供应商', icon: OfficeBuilding },
      ],
    },
    {
      key: 'finance',
      label: '财务',
      items: [
        { index: '/admin/finance/orders', title: '订单管理', icon: Tickets },
        { index: '/admin/finance/invoices', title: '账单管理', icon: Document },
        { index: '/admin/finance/recharges', title: '充值管理', icon: DataLine },
        { index: '/admin/finance/new-customers', title: '新客户', icon: User },
        { index: '/admin/services', title: '服务列表', icon: Box },
        { index: '/admin/finance/renewals', title: '续费订单', icon: Promotion },
        { index: '/admin/finance/addons', title: '附加配置订单', icon: Setting },
      ],
    },
    {
      key: 'ticket',
      label: '工单',
      items: [
        { index: '/admin/tickets', title: '工单管理', icon: ChatDotRound },
      ],
    },
    {
      key: 'operation',
      label: '运营',
      items: [
        { index: '/admin/coupons', title: '优惠券', icon: Ticket },
        { index: '/admin/coupon-campaigns', title: '活动券', icon: Present },
        { index: '/admin/referral', title: '推广返利', icon: Promotion },
        { index: '/admin/notifications', title: '通知管理', icon: Bell },
      ],
    },
    {
      key: 'content',
      label: '内容',
      items: [
        { index: '/admin/content/notices', title: '系统公告', icon: Reading },
        { index: '/admin/content/help', title: '帮助中心', icon: Help },
      ],
    },
    {
      key: 'system',
      label: '系统管理',
      items: [
        { index: '/admin/settings', title: '系统设置', icon: Setting },
        { index: '/admin/logs', title: '日志', icon: Tickets },
      ],
    },
  ]

  const activeMenu = computed(() => {
    const path = route.path
    const tab = route.query.tab

    if (path.startsWith('/admin/users')) return '/admin/users'
    if (path.startsWith('/admin/member-levels')) return '/admin/member-levels'
    if (path.startsWith('/admin/tickets') || path.startsWith('/admin/ticket-conversations')) return '/admin/tickets'
    if (
      path.startsWith('/admin/orders')
    ) return '/admin/finance/invoices'
    if (path.startsWith('/admin/services')) return '/admin/services'
    if (path.startsWith('/admin/finance')) return path

    if (path.startsWith('/admin/products')) {
      if (tab === 'traffic-packages') return '/admin/products/traffic-packages'
      if (tab === 'suppliers') return '/admin/products/suppliers'
      return '/admin/products'
    }
    if (path.startsWith('/admin/specs')) return '/admin/specs'
    if (path.startsWith('/admin/cpu-models')) return '/admin/cpu-models'

    if (path.startsWith('/admin/coupons')) return '/admin/coupons'
    if (path.startsWith('/admin/coupon-campaigns')) return '/admin/coupon-campaigns'
    if (path.startsWith('/admin/referral')) return '/admin/referral'

    if (path.startsWith('/admin/content')) {
      if (path.startsWith('/admin/content/help')) return '/admin/content/help'
      return '/admin/content/notices'
    }
    if (path.startsWith('/admin/notifications')) return '/admin/notifications'
    if (
      path.startsWith('/admin/logs')
      || path.startsWith('/admin/schedules')
    ) return '/admin/logs'
    if (
      path.startsWith('/admin/settings')
      || path.startsWith('/admin/site-ops')
    ) return '/admin/settings'

    return path
  })

  const defaultOpeneds = computed(() => {
    return []
  })

  const adminDisplayName = computed(() => userStore.info?.nickname || userStore.info?.username || '管理员')
  const adminAccountSubtitle = computed(() => userStore.info?.email || '未绑定邮箱')

  const breadcrumbItems = computed(() => (
    route.matched
      .filter((item) => item.path.startsWith('/admin') && item.path !== '/admin' && item.meta?.title)
      .map((item) => ({
        path: item.path,
        title: item.meta.title,
      }))
  ))

  const currentPageTitle = computed(() => breadcrumbItems.value.at(-1)?.title || '管理控制台')

  watch(isMobile, (val) => {
    if (!val) mobileNavVisible.value = false
  })

  function toggleNavigation() {
    if (isMobile.value) {
      mobileNavVisible.value = true
      return
    }

    appStore.toggleSidebar()
  }

  function handleMenuSelect() {
    if (isMobile.value) {
      mobileNavVisible.value = false
    }
  }

  function handleLogout() {
    userStore.logout()
    router.push('/admin/login')
  }

  watch(() => route.fullPath, () => {
    mobileNavVisible.value = false
  })

  return {
    isMobile,
    mobileNavVisible,
    navigationSections,
    activeMenu,
    defaultOpeneds,
    adminDisplayName,
    adminAccountSubtitle,
    breadcrumbItems,
    currentPageTitle,
    toggleNavigation,
    handleMenuSelect,
    handleLogout,
    appStore,
  }
}
