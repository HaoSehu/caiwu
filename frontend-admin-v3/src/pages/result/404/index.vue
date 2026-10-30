<template>
  <result :title="t('pages.result.404.title')" :tip="t('pages.result.404.subtitle')" type="404">
    <div class="result-actions">
      <t-button @click="goHome">{{ t('pages.result.404.back') }}</t-button>
      <t-button theme="default" variant="outline" @click="goBack">{{ t('pages.result.404.backPrev') }}</t-button>
    </div>
  </result>
</template>
<script setup lang="ts">
import { useRouter } from 'vue-router';

import Result from '@/components/result/index.vue';
import { t } from '@/locales';

defineOptions({
  name: 'Result404',
});

const router = useRouter();

const goHome = () => router.push('/admin/dashboard');
const goBack = () => router.back();
</script>
