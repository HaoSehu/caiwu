export default {
  defaultSeverity: 'error',
  extends: ['stylelint-config-standard'],
  plugins: ['stylelint-order'],
  rules: {
    'no-descending-specificity': null,
    'import-notation': 'string',
    'no-empty-source': null,
    'custom-property-pattern': null,
    'selector-class-pattern': null,
    'selector-pseudo-class-no-unknown': [
      true,
      {
        ignorePseudoClasses: ['deep', 'global'],
      },
    ],
    'media-query-no-invalid': null, // 官方表示此规则应当仅对于原生CSS启用，对于预处理器（Less）不应启用
    // DESIGN.md §5.1 明令媒体查询只用 min-width / max-width 前缀写法、禁止 Level 4 区间语法
    // （width <= 991px）：区间语法要 Chrome 104 / Safari 16.4，远高于 browserslist 基线，
    // 构建不会降级，旧浏览器会整条丢规则。'prefix' 正是该口径的机器化，置 null 等于放行。
    'media-feature-range-notation': 'prefix',
    'declaration-property-value-no-unknown': null, // 官方表示此规则应当仅对于原生CSS启用，对于预处理器（Less）不应启用
  },
  overrides: [
    {
      files: ['**/*.html', '**/*.vue'],
      customSyntax: 'postcss-html',
    },
    {
      files: ['**/*.less'],
      customSyntax: 'postcss-less',
    },
  ],
};
