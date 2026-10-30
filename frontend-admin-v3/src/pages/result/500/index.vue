<template>
  <result :title="t('pages.result.500.title')" :tip="t('pages.result.500.subtitle')" type="500">
    <div class="result-actions">
      <t-button @click="goHome">{{ t('pages.result.500.back') }}</t-button>
      <t-button theme="default" variant="outline" @click="goBack">{{ t('pages.result.500.backPrev') }}</t-button>
    </div>
  </result>
</template>
<script setup lang="ts">
import { useRouter } from 'vue-router';

import Result from '@/components/result/index.vue';
import { t } from '@/locales';

defineOptions({
  name: 'Result500',
});

const router = useRouter();

const goHome = () => router.push('/admin/dashboard');
const goBack = () => router.back();
</script>
