<template>
    <div class="space-y-4">
        <!-- Schedule Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                Schedule Name *
            </label>
            <input
                v-model="schedule.name"
                type="text"
                placeholder="e.g., Application Deadline"
                class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
            />
        </div>

        <!-- Date and Time -->
        <div class="flex flex-col md:flex-row gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                    Start Date *
                </label>
                <input
                    v-model="schedule.start_date"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                    Start Time *
                </label>
                <input
                    v-model="schedule.start_time"
                    type="time"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                    End Time *
                </label>
                <input
                    v-model="schedule.end_time"
                    type="time"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
                />
            </div>
        </div>

        <!-- Schedule Type -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                Type *
            </label>
            <select
                v-model="schedule.type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
            >
                <option value="">Select type...</option>
                <option value="application_deadline">
                    Application Deadline
                </option>
                <option value="exam_schedule">Exam Schedule</option>
                <option value="interview_schedule">
                    Interview Schedule
                </option>
                <option value="result_release">Result Release</option>
                <option value="enrollment_period">Enrollment Period</option>
            </select>
        </div>

        <!-- Description -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                Description
            </label>
            <textarea
                v-model="schedule.description"
                rows="2"
                placeholder="Additional details about this schedule..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
            />
        </div>

        <!-- Location -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-400">
                Location
            </label>
            <input
                v-model="schedule.location"
                type="text"
                placeholder="e.g., PUP Building A, Room 101"
                class="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600"
            />
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
            name: '',
            start_date: '',
            start_time: '09:00',
            end_time: '10:00',
            type: 'application_deadline',
            description: '',
            location: ''
        })
    }
})

const emit = defineEmits(['update:modelValue'])

const schedule = computed({
    get() {
        return props.modelValue
    },
    set(value) {
        emit('update:modelValue', value)
    }
})
</script>
