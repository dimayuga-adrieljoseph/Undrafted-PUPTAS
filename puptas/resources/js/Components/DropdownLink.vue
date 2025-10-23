<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div>
        <!-- Button Type -->
        <button 
            v-if="as == 'button'" 
            type="submit" 
            class="block w-full px-4 py-2 text-start text-sm leading-5 text-[#FCECDF] bg-[#EE6A43] hover:bg-[#FBCB77] focus:bg-[#FBCB77] transition duration-150 ease-in-out"
        >
            <slot />
        </button>

        <!-- Anchor Tag -->
        <a 
            v-else-if="as =='a'" 
            :href="href" 
            class="block px-4 py-2 text-sm leading-5 text-[#FCECDF]  hover:bg-[#FBCB77] focus:bg-[#FBCB77] transition duration-150 ease-in-out"
        >
            <slot />
        </a>

        <!-- Inertia Link -->
        <Link 
            v-else 
            :href="href" 
            class="block px-4 py-2 text-sm leading-5 text-[#FCECDF]  hover:bg-[#FBCB77] focus:bg-[#FBCB77] transition duration-150 ease-in-out"
        >
            <slot />
        </Link>
    </div>
</template>
