<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[#9E122C] ' +
          'text-lg font-medium leading-5 text-[#FBCB77] focus:outline-none ' +
          'focus:border-[#EE6A43] transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent ' +
          'text-lg font-medium leading-5 text-[#FFFFFF] hover:text-[#9E122C] ' +
          'hover:border-[#EE6A43] focus:outline-none focus:text-[#EE6A43] ' +
          'focus:border-[#EE6A43] transition duration-150 ease-in-out';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
