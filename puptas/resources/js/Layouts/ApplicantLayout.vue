<!-- ApplicantLayout — thin wrapper around AuthenticatedLayout (variant: applicant) -->
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
</script>

<template>
    <AuthenticatedLayout variant="applicant">
        <template v-if="$slots.title" #title><slot name="title" /></template>
        <template v-if="$slots['header-actions']" #header-actions><slot name="header-actions" /></template>
        <slot />
    </AuthenticatedLayout>
</template>
