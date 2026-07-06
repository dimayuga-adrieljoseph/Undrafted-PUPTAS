<template>
  <div v-if="links && links.length > 3">
    <div class="flex flex-wrap -mb-1">
      <template v-for="(link, p) in links" :key="p">
        <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded"
          v-html="link.label" />
        <Link v-else
          class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-[#9E122C] focus:text-[#9E122C] transition-colors"
          :class="{ 'bg-[#9E122C] text-white hover:bg-[#800918]': link.active, 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300': !link.active }" :href="link.url" v-html="link.label" />
      </template>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  links: Array
})
</script>
