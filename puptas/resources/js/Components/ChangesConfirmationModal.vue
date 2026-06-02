<script setup>
defineProps({
    show: { type: Boolean, default: false },
    changes: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm Changes' },
    subtitle: { type: String, default: 'Review the following changes before saving' },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 z-50"
            @click.self="emit('cancel')"
        >
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ title }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ subtitle }}</p>
                    </div>
                    <button
                        @click="emit('cancel')"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                    >
                        <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Changes Table -->
                <div class="overflow-y-auto flex-1 p-5">
                    <div v-if="changes.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <svg class="h-12 w-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-sm font-medium">No changes detected.</p>
                    </div>

                    <div v-else class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[30%]">Field</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[35%]">Old Value</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[35%]">New Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="(change, idx) in changes"
                                    :key="idx"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-900/50 transition"
                                >
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-200 whitespace-nowrap">
                                        {{ change.field }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            {{ change.oldValue || '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-200 font-medium">
                                        <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                            <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            {{ change.newValue || '—' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex justify-end gap-3 p-5 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="emit('cancel')"
                        :disabled="loading"
                        class="px-5 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-400 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 text-sm font-medium"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="emit('confirm')"
                        :disabled="loading || changes.length === 0"
                        class="px-5 py-2.5 bg-[#9E122C] text-white rounded-xl hover:bg-[#800918] disabled:opacity-50 disabled:cursor-not-allowed transition text-sm font-medium flex items-center gap-2"
                    >
                        <span v-if="loading" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ loading ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>