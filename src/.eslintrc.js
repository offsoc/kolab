module.exports = {
  extends: [
    // add more generic rulesets here, such as:
    // 'eslint:recommended',
    'plugin:vue/recommended'
  ],
  parserOptions: {
    parser: "@babel/eslint-parser",
    requireConfigFile: false
  },
  rules: {
    "vue/attributes-order": "off",
    "vue/html-indent": ["error", 4],
    "vue/html-self-closing": "off",
    "vue/max-attributes-per-line": "off",
    "vue/no-unused-components": "off",
    "vue/no-v-html": "off",
    "vue/singleline-html-element-content-newline": "off",
    "vue/multiline-html-element-content-newline": "off"
  }
}
