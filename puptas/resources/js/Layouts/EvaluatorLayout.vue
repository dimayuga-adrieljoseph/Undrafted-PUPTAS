<!--
  EvaluatorLayout — thin wrapper around AuthenticatedLayout.
  Dynamically selects 'document_evaluator' or 'evaluator' based on
  the page-level `stage` prop, mirroring the original behavior.
-->
<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()

const variant = computed(() => {
    const user = page.props.auth?.user ?? null
    const stage = page.props.stage ?? (user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator')
    return stage === 'document_evaluator' ? 'document_evaluator' : 'evaluator'
})
</script>

<template>
    <AuthenticatedLayout :variant="variant">
        <template v-if="$slots.title" #title><slot name="title" /></template>
        <template v-if="$slots['header-actions']" #header-actions><slot name="header-actions" /></template>
        <slot />
    </AuthenticatedLayout>
</template>
