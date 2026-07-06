<!-- RecordStaffLayout — thin wrapper around AuthenticatedLayout (variant: record) -->
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
</script>

<template>
    <AuthenticatedLayout variant="record">
        <template v-if="$slots.title" #title><slot name="title" /></template>
        <template v-if="$slots['header-actions']" #header-actions><slot name="header-actions" /></template>
        <slot />
    </AuthenticatedLayout>
</template>
