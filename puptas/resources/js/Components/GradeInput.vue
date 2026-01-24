<template>
  <div class="space-y-3">
    <!-- Grade Inputs with Decimal Support -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Math Grade</label>
        <input 
          v-model.number="grades.mathematics" 
          type="number" 
          step="0.01" 
          min="0" 
          max="100"
          class="w-full px-3 py-2 border border-gray-300 rounded-md"
          placeholder="0.00"
        />
        <p class="text-xs text-gray-500 mt-1">0.00 - 100.00</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Science Grade</label>
        <input 
          v-model.number="grades.science" 
          type="number" 
          step="0.01" 
          min="0" 
          max="100"
          class="w-full px-3 py-2 border border-gray-300 rounded-md"
          placeholder="0.00"
        />
        <p class="text-xs text-gray-500 mt-1">0.00 - 100.00</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">English Grade</label>
        <input 
          v-model.number="grades.english" 
          type="number" 
          step="0.01" 
          min="0" 
          max="100"
          class="w-full px-3 py-2 border border-gray-300 rounded-md"
          placeholder="0.00"
        />
        <p class="text-xs text-gray-500 mt-1">0.00 - 100.00</p>
      </div>
    </div>

    <!-- Semester Grades -->
    <div class="border-t pt-4">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Semester Grades</h3>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Grade 11 - 1st Sem</label>
          <input 
            v-model.number="grades.g11_first_sem" 
            type="number" 
            step="0.01" 
            min="0" 
            max="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="0.00"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Grade 11 - 2nd Sem</label>
          <input 
            v-model.number="grades.g11_second_sem" 
            type="number" 
            step="0.01" 
            min="0" 
            max="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="0.00"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Grade 12 - 1st Sem</label>
          <input 
            v-model.number="grades.g12_first_sem" 
            type="number" 
            step="0.01" 
            min="0" 
            max="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="0.00"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Grade 12 - 2nd Sem</label>
          <input 
            v-model.number="grades.g12_second_sem" 
            type="number" 
            step="0.01" 
            min="0" 
            max="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="0.00"
          />
        </div>
      </div>
    </div>

    <slot></slot>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      english: 0,
      mathematics: 0,
      science: 0,
      g11_first_sem: 0,
      g11_second_sem: 0,
      g12_first_sem: 0,
      g12_second_sem: 0
    })
  }
})

const emit = defineEmits(['update:modelValue'])

const grades = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  }
})
</script>
