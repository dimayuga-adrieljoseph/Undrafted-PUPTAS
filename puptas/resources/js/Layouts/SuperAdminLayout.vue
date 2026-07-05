<!-- SuperAdminLayout — thin wrapper around AuthenticatedLayout (variant: superadmin) -->
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
</script>

<template>
    <AuthenticatedLayout variant="superadmin">
        <template v-if="$slots.title" #title><slot name="title" /></template>
        <template v-if="$slots['header-actions']" #header-actions><slot name="header-actions" /></template>
        <slot />
    </AuthenticatedLayout>
</template>
