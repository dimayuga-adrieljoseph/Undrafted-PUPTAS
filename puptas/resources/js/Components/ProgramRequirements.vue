<template>
    <div class="space-y-2">
        <!-- Grade Requirements -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-600">Math</p>
                <p class="text-lg font-semibold text-blue-600">
                    {{ formatDecimal(program.math) }}
                </p>
            </div>
            <div class="bg-blue-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-600">Science</p>
                <p class="text-lg font-semibold text-blue-600">
                    {{ formatDecimal(program.science) }}
                </p>
            </div>
            <div class="bg-blue-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-600">English</p>
                <p class="text-lg font-semibold text-blue-600">
                    {{ formatDecimal(program.english) }}
                </p>
            </div>
            <div class="bg-green-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-600">GWA</p>
                <p class="text-lg font-semibold text-green-600">
                    {{ formatDecimal(program.gwa, 2) }} / 5.0
                </p>
            </div>
        </div>

        <!-- PUPCET and Slots -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-orange-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-600">PUPCET Score</p>
                <p class="text-lg font-semibold text-orange-600">
                    {{ formatDecimal(program.pupcet) }}
                </p>
            </div>
            <div class="bg-purple-50 p-3 rounded">
                <p class="text-sm font-medium text-gray-600">
                    Available Slots
                </p>
                <p class="text-lg font-semibold text-purple-600">
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
