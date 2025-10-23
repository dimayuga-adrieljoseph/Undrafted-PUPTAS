<template>
    <TheMask
      class="ant-input"
      :value="modelValue"
      :mask="mask"
      :masked="masked"
      @input="$emit('update:modelValue', $event)"
    />
  </template>
  
  <script setup>
  import { TheMask } from 'vue-the-mask';
  defineProps({
    modelValue: String,
    mask: [String, Array],
    masked: {
      type: Boolean,
      default: false
    }
  });
  defineEmits(['update:modelValue']);
  </script>
  