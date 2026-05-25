<template>
  <div class="admin-empty-state">
    <el-empty :description="description" :image-size="emptyImageSize">
      <template v-if="$slots.default">
        <slot></slot>
      </template>
    </el-empty>
  </div>
</template>

<script setup>
const props = defineProps({
  description: {
    type: String,
    default: '暂无数据',
  },
  image: {
    type: String,
    default: undefined,
  },
  imageSize: {
    type: [Number, String],
    default: undefined,
  },
})
</script>

<style lang="scss" scoped>
.admin-empty-state {
  padding: 60px 20px;
  text-align: center;
}
</style>
