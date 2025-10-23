<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block font-bold text-sm text-white dark:text-gray-300">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
