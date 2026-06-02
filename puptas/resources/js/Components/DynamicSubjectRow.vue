<script setup>
import { computed } from 'vue';

const props = defineProps({
    subject: {
        type: Object,
        required: true,
        // { id: string, name: string, grade: number | null }
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    category: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:name', 'update:grade', 'remove']);

const isGradeInvalid = computed(() => {
    const grade = props.subject.grade;
    if (grade === null || grade === '' || grade === undefined) {
        return false;
    }
    const numericGrade = Number(grade);
    return isNaN(numericGrade) || numericGrade < 0 || numericGrade > 100;
});

function onNameInput(event) {
    emit('update:name', event.target.value);
}

function onGradeInput(event) {
    const value = event.target.value;
    if (value === '' || value === null) {
        emit('update:grade', null);
    } else {
        emit('update:grade', Number(value));
    }
}
</script>

<template>
    <div class="flex items-start gap-3">
        <!-- Subject Name Input -->
        <div class="flex-1">
            <input
                type="text"
                :value="subject.name"
                @input="onNameInput"
                maxlength="100"
                :disabled="disabled"
                class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C] disabled:opacity-50 disabled:cursor-not-allowed"
                placeholder="Subject name"
            />
        </div>

        <!-- Grade Input -->
        <div class="w-32">
            <input
                type="number"
                :value="subject.grade"
                @input="onGradeInput"
                min="0"
                max="100"
                step="1"
                :disabled="disabled"
                :class="[
                    'w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed',
                    isGradeInvalid
                        ? 'border-red-500 focus:ring-red-500'
                        : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]'
                ]"
                placeholder="Grade"
            />
            <p v-if="isGradeInvalid" class="text-xs text-red-500 mt-1">
                <i class="fas fa-exclamation-triangle mr-1"></i>Grade must be 0-100
            </p>
        </div>

        <!-- Remove Button -->
        <button
            type="button"
            @click="emit('remove')"
            :disabled="disabled"
            class="flex-shrink-0 mt-1 w-8 h-8 flex items-center justify-center rounded-full text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-700 dark:hover:text-red-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent"
            title="Remove subject"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
