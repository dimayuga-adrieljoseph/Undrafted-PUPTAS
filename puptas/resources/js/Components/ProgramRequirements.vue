<template>
    <div class="w-full space-y-2">
        <!-- Grade Requirements -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 p-3 rounded dark:bg-blue-900">
                <p class="text-xs md:text-sm font-medium text-gray-600 dark:text-gray-400">Math</p>
                <p class="text-base md:text-lg font-semibold text-blue-600 dark:text-blue-400">
                    {{ formatDecimal(program.math) }}
                </p>
            </div>
            <div class="bg-blue-50 p-3 rounded dark:bg-blue-900">
                <p class="text-xs md:text-sm font-medium text-gray-600 dark:text-gray-400">Science</p>
                <p class="text-base md:text-lg font-semibold text-blue-600 dark:text-blue-400">
                    {{ formatDecimal(program.science) }}
                </p>
            </div>
            <div class="bg-blue-50 p-3 rounded dark:bg-blue-900">
                <p class="text-xs md:text-sm font-medium text-gray-600 dark:text-gray-400">English</p>
                <p class="text-base md:text-lg font-semibold text-blue-600 dark:text-blue-400">
                    {{ formatDecimal(program.english) }}
                </p>
            </div>
            <div class="bg-green-50 p-3 rounded dark:bg-green-900">
                <p class="text-xs md:text-sm font-medium text-gray-600 dark:text-gray-400">GWA</p>
                <p class="text-base md:text-lg font-semibold text-green-600 dark:text-green-400">
                    {{ formatDecimal(program.gwa, 2) }} / 5.0
                </p>
            </div>
        </div>

        <!-- Slots -->
        <div class="grid grid-cols-1 gap-4">
            <div class="bg-purple-50 p-3 rounded dark:bg-purple-900">
                <p class="text-xs md:text-sm font-medium text-gray-600 dark:text-gray-400">
                    Available Slots
                </p>
                <p class="text-base md:text-lg font-semibold text-purple-600 dark:text-purple-400">
                    {{ program.slots }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from 'vue'
import { formatDecimal } from '@/utils/enums'

defineProps({
    program: {
        type: Object,
        required: true
    }
})
</script>
