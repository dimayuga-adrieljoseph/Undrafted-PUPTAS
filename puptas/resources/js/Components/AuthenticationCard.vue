<script setup>
defineProps({
  wide: Boolean
});
</script>

<template>
  <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

    <div>
      <slot name="logo" />
    </div>

    <div
      :class="[ 
        'mt-2 px-4 py-2 bg-[rgba(128,0,0,0.5)] dark:bg-[rgba(128,0,0,0.3)] shadow-md overflow-hidden sm:rounded-lg', 
        wide ? 'w-full sm:max-w-5xl' : 'w-full sm:max-w-md'
      ]"
    >
      <div class="space-y-2"> <!-- Control space between each field -->
        <slot />
      </div>
    </div>
  </div>
</template>
