import type { FormRule } from 'tdesign-vue-next';

type Trigger = 'change' | 'blur' | 'submit' | 'all';

/** 必填规则 */
export function required(message: string, trigger: Trigger = 'all'): FormRule {
  return { required: true, message, type: 'error', trigger };
}

/** 手机号规则（中国大陆 11 位） */
export function phoneRule(message = '请输入正确的手机号'): FormRule {
  return { pattern: /^1[3-9]\d{9}$/, message, type: 'warning', trigger: 'blur' };
}

/** 密码规则（最少 min 位） */
export function passwordRule(min = 6, message?: string): FormRule {
  return { min, message: message || `密码至少 ${min} 位`, type: 'error', trigger: 'blur' };
}

/** 组合规则工厂：返回 FormRule[] */
export function composeRules(...rules: FormRule[]): FormRule[] {
  return rules;
}
