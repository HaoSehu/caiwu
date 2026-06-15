<template>
  <div class="home-page">
    <HomeHeroCarousel :hero="homeHero" />

    <HomeProductTabs
      :loading="loading"
      :product-types="productTypes"
      :root-groups="rootGroups"
      :group-catalog-map="groupCatalogMap"
    />

    <template v-if="mountDeferredSections">
      <HomeSolutionSection />
      <HomeNewsSection :notices="notices" />
      <HomeRegisterBar />
    </template>
    <template v-else>
      <HomeSectionSkeleton type="solutions" />
      <HomeSectionSkeleton type="news" />
      <HomeSectionSkeleton type="register" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import siteApi from '@/api/site'
import { useAppStore } from '@/stores/app'
import HomeHeroCarousel from '@/views/website/Home/components/HomeHeroCarousel.vue'
import HomeProductTabs from '@/views/website/Home/components/HomeProductTabs.vue'
import HomeNewsSection from '@/views/website/Home/components/HomeNewsSection.vue'
import HomeRegisterBar from '@/views/website/Home/components/HomeRegisterBar.vue'
import HomeSectionSkeleton from '@/views/website/Home/components/HomeSectionSkeleton.vue'
import HomeSolutionSection from '@/views/website/Home/components/HomeSolutionSection.vue'

interface ProductTypeItem {
  id?: number
  value?: string
  label?: string
  product_count?: number
}

const appStore = useAppStore()
const loading = ref(false)
const mountDeferredSections = ref(false)
const homeHero = ref<Record<string, unknown>>({})
const notices = ref<any[]>([])
const rootGroups = ref<any[]>([])
const groupCatalogMap = ref<Record<string, unknown>>({})
const productTypes = ref<ProductTypeItem[]>([])

function deriveProductTypesFromGroups(groups: any[]) {
  const map = new Map<string, ProductTypeItem>()

  groups.forEach((group, index) => {
    const value = String(group?.product_type || '')
    if (!value) {
      return
    }

    const current = map.get(value) || {
      id: Number(group?.product_type_id || index + 1),
      value,
      label: String(group?.product_type_label || `产品分类 ${index + 1}`),
      product_count: 0,
    }

    current.product_count = Number(current.product_count || 0) + Number(group?.product_count || 0)
    map.set(value, current)
  })

  return Array.from(map.values())
}

async function loadHomePage() {
  loading.value = true

  try {
    const [homeRes, heroRes, typeRes] = await Promise.allSettled([
      siteApi.home(),
      siteApi.homeHero(),
      siteApi.productTypes(),
    ])

    if (homeRes.status === 'fulfilled') {
      const data = homeRes.value.data || {}
      notices.value = Array.isArray(data.notices) ? data.notices : []
      rootGroups.value = Array.isArray(data.root_groups) ? data.root_groups : []
      groupCatalogMap.value = data.group_catalog_map && typeof data.group_catalog_map === 'object'
        ? data.group_catalog_map
        : {}

      if (data.site_config) {
        appStore.hydrateSiteConfig(data.site_config)
      }
    }

    if (heroRes.status === 'fulfilled') {
      homeHero.value = heroRes.value.data || {}
    }

    if (typeRes.status === 'fulfilled') {
      productTypes.value = Array.isArray(typeRes.value.data?.list) ? typeRes.value.data.list : []
    }

    if (!productTypes.value.length) {
      productTypes.value = deriveProductTypesFromGroups(rootGroups.value)
    }
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadHomePage()

  if (typeof window !== 'undefined') {
    window.requestAnimationFrame(() => {
      mountDeferredSections.value = true
    })
    return
  }

  mountDeferredSections.value = true
})
</script>
