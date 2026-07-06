import pluginVue from 'eslint-plugin-vue';

export default [
    // Pull in the Vue plugin's parser so .vue files are understood
    ...pluginVue.configs['flat/base'],

    {
        files: ['**/*.vue'],
        rules: {
            // ── Block order: <script setup> → <template> → <style scoped> ──
            'vue/component-tags-order': [
                'error',
                {
                    order: ['script', 'template', 'style'],
                },
            ],

            // ── Style blocks must be scoped (no global <style> leakage) ──
            'vue/enforce-style-attribute': ['error', { allow: ['scoped'] }],

            // Turn off noisy recommended rules that aren't in scope for this task
            'vue/html-indent': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-closing-bracket-spacing': 'off',
            'vue/attributes-order': 'off',
            'vue/require-default-prop': 'off',
        },
    },

    {
        // Ignore build output and vendor files
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'dist/**'],
    },
];
