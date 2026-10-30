<script setup>
// 美国服务器落地页：只静态引入本页文案，避免 5 页全量数据进入同一个 chunk。
import SeoLandingView from "@/pages/website/seo-landing/index.vue";
import page from "@/data/seoLanding/us-server.js";
</script>

<template>
  <SeoLandingView :page="page" />
</template>
