import globals from 'globals'
import tseslint from '@typescript-eslint/eslint-plugin'
import tsParser from '@typescript-eslint/parser'
import vueParser from 'vue-eslint-parser'
import vuePlugin from 'eslint-plugin-vue'

const sharedIgnores = [
  '**/dist/**',
  '**/node_modules/**',
  '**/.vite/**',
  '**/coverage/**',
  '**/*.d.ts',
]

export default [
  {
    ignores: sharedIgnores,
  },
  {
    files: ['frontend-admin/src/**/*.{js,ts,vue}', 'frontend-client/src/**/*.{js,ts,vue}', 'shared/**/*.{js,ts,vue}'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        parser: tsParser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    plugins: {
      '@typescript-eslint': tseslint,
      vue: vuePlugin,
    },
    rules: {
      'no-undef': 'off',
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      'vue/multi-word-component-names': 'off',
      'vue/no-mutating-props': 'off',
    },
  },
]
