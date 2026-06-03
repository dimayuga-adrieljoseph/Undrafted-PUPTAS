<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-6"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Review Grades</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Check your inputs before saving.</p>
                </div>
                <button
                    type="button"
                    class="text-2xl leading-none text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    @click="$emit('close')"
                >
                    &times;
                </button>
            </div>

            <div class="space-y-5 px-6 py-6">
                <div
                    v-for="section in displaySections"
                    :key="section.title"
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/60"
                >
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                        {{ section.title }}
                    </h3>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div
                            v-for="item in section.items"
                            :key="item.label"
                            class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900/70"
                        >
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ item.label }}
                            </p>
                            <p class="mt-1 break-words text-sm font-semibold text-gray-900 dark:text-white">
                                {{ formatValue(item.value) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Finality Warning -->
                <div class="rounded-xl border-2 border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="text-sm font-bold text-red-700 dark:text-red-400">Important: Submitted information is considered final.</p>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-300">
                                Once saved, your grades and program selections cannot be changed. Changes after submission may only be accommodated during the interview schedule. Make sure everything is correct before proceeding.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200">
                    Make sure everything is correct before you save. If there is a mistake, go back and edit it first.
                </div>
            </div>

            <div class="sticky bottom-0 flex gap-3 border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-3 font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    @click="$emit('close')"
                    :disabled="loading"
                >
                    Back
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-[#9E122C] px-4 py-3 font-medium text-white hover:bg-[#b51834] disabled:cursor-not-allowed disabled:opacity-50"
                    @click="$emit('confirm')"
                    :disabled="loading"
                >
                    <span v-if="loading" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                    <span v-else>Save Grades</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    loading: Boolean,
    formData: {
        type: Object,
        default: () => ({}),
    },
    sections: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['close', 'confirm']);

const humanizeKey = (key) => key
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());

const displaySections = computed(() => {
    // Start with the sections prop if provided, else empty array
    let sections = props.sections.length ? [...props.sections] : [];

    // If we have sections, we will collect their labels to avoid duplication
    const sectionLabels = new Set();
    sections.forEach(section => {
        section.items.forEach(item => {
            sectionLabels.add(item.label);
        });
    });

    // Prepare extra items from formData
    const extraItems = [];
    const ignoredKeysPatterns = [
        // Program choices
        /_choice_program$/,
        // Averages
        /_average$/,
        // GWA
        /_gwa$/,
        // Specific keys we know are not subjects
        'first_choice_program',
        'second_choice_program',
        'third_choice_program',
        'math_average',
        'english_average',
        'science_average',
        'g12_gwa',
        'g12_first_sem_gwa',
        'g12_second_sem_gwa',
    ];

    for (const [key, value] of Object.entries(props.formData || {})) {
        // Skip if value is empty (as in the original fallback)
        if (value === null || value === undefined || value === '') {
            continue;
        }

        // Check if key matches any ignored pattern
        const isIgnored = ignoredKeysPatterns.some(pattern => {
            if (typeof pattern === 'string') {
                return key === pattern;
            } else {
                return pattern.test(key);
            }
        });

        if (isIgnored) {
            continue;
        }

        const label = humanizeKey(key);
        // Avoid adding duplicates: if the label is already in sectionLabels, skip
        if (!sectionLabels.has(label)) {
            extraItems.push({ label, value });
            // Add to sectionLabels to avoid duplicates within extraItems
            sectionLabels.add(label);
        }
    }

    // If we have extra items, add them as a new section
    if (extraItems.length > 0) {
        sections.push({
            title: 'Additional Subjects',
            items: extraItems,
        });
    }

    // If we still have no sections (i.e., no props.sections and no extraItems), then fall back to the old behavior
    if (sections.length === 0) {
        const items = Object.entries(props.formData || {})
            .filter(([, value]) => value !== null && value !== undefined && value !== '')
            .map(([key, value]) => ({
                label: humanizeKey(key),
                value,
            }));

        return [
            {
                title: 'Entered Values',
                items,
            },
        ];
    }

    return sections;
});

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (Array.isArray(value)) {
        return value.length ? value.join(', ') : '—';
    }

    return String(value);
};
</script>