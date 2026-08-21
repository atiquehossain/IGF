const js = require('@eslint/js');
const globals = require('globals');
const vue = require('eslint-plugin-vue');

module.exports = [
  {
    ignores: ['node_modules/**', 'public/**', 'vendor/**'],
  },
  js.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    files: ['resources/js/**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.vitest,
        axios: 'readonly',
        route: 'readonly',
      },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/no-reserved-component-names': 'off',
      'vue/html-self-closing': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/html-indent': 'off',
      'vue/html-closing-bracket-newline': 'off',
      'vue/html-closing-bracket-spacing': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/multiline-html-element-content-newline': 'off',
      'vue/first-attribute-linebreak': 'off',
      'vue/attributes-order': 'off',
      'vue/v-slot-style': 'off',
      'vue/v-on-event-hyphenation': 'off',
      'vue/attribute-hyphenation': 'off',
      'vue/order-in-components': 'off',
      'vue/component-tags-order': 'off',
      'vue/component-definition-name-casing': 'off',
      'vue/block-order': 'off',
      'quote-props': 'off',
      'space-before-function-paren': 'off',
      semi: 'off',
      quotes: 'off',
    },
  },
];
