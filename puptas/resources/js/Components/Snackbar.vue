<template>
  <transition name="slide-fade">
    <div v-if="show" :class="['fixed bottom-4 right-4 p-4 rounded shadow text-white', bgColor]">
      {{ message }}
    </div>
  </transition>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  show: Boolean,
  type: { type: String, default: "success" },
  message: String,
});

const bgColor = computed(() => {
  return props.type === "success"
    ? "bg-green-500"
    : props.type === "error"
    ? "bg-red-500"
    : "bg-gray-500";
});
</script>

<style>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}
.slide-fade-enter-from {
  opacity: 0;
  transform: translateY(20px);
}
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(20px);
}
</style>
