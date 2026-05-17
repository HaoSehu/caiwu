import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import clientApi from '@/api/client'

/**
 * 管理工具模块 composable
 * 封装黑洞查询、白名单、规则等工具操作
 */
export function useTools() {
  const querying = ref(false)
  const submitting = ref(false)
  const queryResult = ref('暂无查询结果')

  const queryForm = reactive({ ip: '' })
  const ningboForm = reactive({ ip: '', domain: '' })
  const layer4Form = reactive({ ip: '', mode: 1 })
  const layer7Form = reactive({ ip: '', rule_id: 1, enabled: true })

  function ensureIp(ip) {
    if (!ip.trim()) {
      ElMessage.warning('请输入 IP 地址')
      return false
    }
    return true
  }

  async function handleQuery() {
    if (!ensureIp(queryForm.ip)) return
    querying.value = true
    try {
      const response = await clientApi.blackholeQuery({ ip: queryForm.ip.trim() })
      queryResult.value = JSON.stringify(response.data || {}, null, 2)
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '黑洞查询失败')
    } finally {
      querying.value = false
    }
  }

  async function handleNingboWhitelist() {
    if (!ensureIp(ningboForm.ip) || !ningboForm.domain.trim()) {
      if (!ningboForm.domain.trim()) ElMessage.warning('请输入域名')
      return
    }
    submitting.value = true
    try {
      await clientApi.blackholeAddNingboWhitelist({
        ip: ningboForm.ip.trim(),
        domain: ningboForm.domain.trim(),
      })
      ElMessage.success('宁波白名单提交成功')
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '宁波白名单提交失败')
    } finally {
      submitting.value = false
    }
  }

  async function handleAddLayer4() {
    if (!ensureIp(layer4Form.ip)) return
    submitting.value = true
    try {
      await clientApi.blackholeAddShiyanLayer4Rule({
        ip: layer4Form.ip.trim(),
        mode: layer4Form.mode,
      })
      ElMessage.success('十堰四层规则已提交')
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '十堰四层规则提交失败')
    } finally {
      submitting.value = false
    }
  }

  async function handleToggleLayer7() {
    if (!ensureIp(layer7Form.ip)) return
    submitting.value = true
    try {
      await clientApi.blackholeToggleShiyanLayer7Rule({
        ip: layer7Form.ip.trim(),
        rule_id: layer7Form.rule_id,
        enabled: layer7Form.enabled,
      })
      ElMessage.success('十堰七层规则已更新')
    } catch (error) {
      if (!error?.__handled) ElMessage.error(error?.message || '十堰七层规则提交失败')
    } finally {
      submitting.value = false
    }
  }

  return {
    querying,
    submitting,
    queryResult,
    queryForm,
    ningboForm,
    layer4Form,
    layer7Form,
    handleQuery,
    handleNingboWhitelist,
    handleAddLayer4,
    handleToggleLayer7,
  }
}
