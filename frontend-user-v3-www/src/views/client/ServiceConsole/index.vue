<template>
  <div class="service-console-router" v-loading="loading">
    <component
      :is="currentConsoleComponent"
      v-if="ready"
      :key="`${currentConsoleProfile.key}-${serviceId}`"
      :permissions="currentConsoleProfile.permissions"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import clientApi from '@/api/client'
import CloudConsolePage from '@/views/client/ServiceConsole/pages/CloudConsolePage.vue'
import NatConsolePage from '@/views/client/ServiceConsole/pages/NatConsolePage.vue'
import { CONSOLE_PROFILE_KEYS, getConsoleProfile, resolveConsoleProfileKey } from '@/views/client/ServiceConsole/consoleProfiles.js'

const route = useRoute()

const loading = ref(false)
const ready = ref(false)
const currentConsoleKey = ref(CONSOLE_PROFILE_KEYS.CLOUD)

const serviceId = computed(() => {
  const id = Number(route.params.id)
  return Number.isFinite(id) && id > 0 ? id : 0
})

const currentConsoleProfile = computed(() => getConsoleProfile(currentConsoleKey.value))
const currentConsoleComponent = computed(() => (
  currentConsoleKey.value === CONSOLE_PROFILE_KEYS.NAT ? NatConsolePage : CloudConsolePage
))

watch(
  () => route.params.id,
  async () => {
    await resolveConsoleComponent()
  },
  { immediate: true }
)

async function resolveConsoleComponent() {
  ready.value = false
  currentConsoleKey.value = CONSOLE_PROFILE_KEYS.CLOUD

  if (!serviceId.value) {
    ready.value = true
    return
  }

  loading.value = true
  try {
    const res = await clientApi.serviceBaseDetail(serviceId.value)
    const detail = res.data || {}
    currentConsoleKey.value = resolveConsoleProfileKey(detail)
  } catch {
    currentConsoleKey.value = CONSOLE_PROFILE_KEYS.CLOUD
  } finally {
    loading.value = false
    ready.value = true
  }
}
</script>

<style scoped lang="scss">
.service-console-router {
  min-height: 240px;
}
</style>
