<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button 
        :type="type" 
        class="inline-flex items-center px-4 py-2 bg-[#9E122C] border border-transparent rounded-full font-semibold text-xs text-[#FCECDF] uppercase tracking-widest hover:bg-[#EE6A43] focus:bg-[#EE6A43] active:bg-[#FBCB77] focus:outline-none focus:ring-2 focus:ring-[#FBCB77] focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
    >
        <slot />
    </button>
</template>
