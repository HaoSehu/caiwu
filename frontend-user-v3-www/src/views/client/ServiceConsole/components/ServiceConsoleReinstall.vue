<template>
  <!-- 重置密码弹窗 -->
  <el-dialog v-model="passwordVisible" title="重置实例密码" width="420px" destroy-on-close>
    <el-form
      ref="passwordFormRef"
      :model="passwordForm"
      :rules="passwordRules"
      label-width="90px"
    >
      <el-form-item label="新密码"><el-input v-model="passwordForm.password" type="password" show-password placeholder="至少 8 位" /></el-form-item>
      <el-form-item label="确认密码"><el-input v-model="passwordForm.password_confirmation" type="password" show-password /></el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="passwordVisible = false">取消</el-button>
      <el-button type="primary" :loading="actionLoading" @click="emit('submit-password')">提交</el-button>
    </template>
  </el-dialog>

  <!-- 重装系统弹窗 -->
  <el-dialog v-model="reinstallVisible" title="重装系统" width="560px" destroy-on-close>
    <div v-loading="reinstallState.loading">
      <el-form
        ref="reinstallFormRef"
        :model="reinstallState"
        :rules="reinstallRules"
        label-width="90px"
      >
        <el-form-item label="系统分组">
          <el-select v-model="reinstallState.os_group" placeholder="请选择系统分组" @change="emit('reinstall-group-change', $event)">
            <el-option v-for="group in reinstallGroupedOptions" :key="group.group_name" :label="group.group_name" :value="group.group_name" />
          </el-select>
        </el-form-item>
        <el-form-item label="系统版本">
          <el-select v-model="reinstallState.os_id" placeholder="请选择系统版本">
            <el-option v-for="item in currentReinstallOptions" :key="item.os_id" :label="item.name" :value="item.os_id" />
          </el-select>
        </el-form-item>
      </el-form>
    </div>
    <template #footer>
      <el-button @click="reinstallVisible = false">取消</el-button>
      <el-button type="primary" :loading="actionLoading" :disabled="!reinstallState.os_id" @click="emit('submit-reinstall')">提交重装</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  passwordForm: { type: Object, required: true },
  passwordRules: { type: Object, required: true },
  reinstallState: { type: Object, required: true },
  reinstallRules: { type: Object, required: true },
  reinstallGroupedOptions: { type: Array, default: () => [] },
  currentReinstallOptions: { type: Array, default: () => [] },
  actionLoading: { type: Boolean, default: false },
})

const passwordVisible = defineModel('passwordVisible', { type: Boolean, default: false })
const reinstallVisible = defineModel('reinstallVisible', { type: Boolean, default: false })
const passwordFormRef = ref(null)
const reinstallFormRef = ref(null)

const emit = defineEmits(['submit-password', 'submit-reinstall', 'reinstall-group-change'])

defineExpose({
  validatePasswordForm: () => passwordFormRef.value?.validate(),
  validateReinstallForm: () => reinstallFormRef.value?.validate(),
})
</script>
