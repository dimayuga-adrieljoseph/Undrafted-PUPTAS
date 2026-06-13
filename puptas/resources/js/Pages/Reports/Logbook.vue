<script setup>
import { ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    entries:     { type: Object, default: () => ({ data: [], total: 0, links: [] }) },
    currentStep: { type: Number, default: 1 },
    currentDate: { type: String, default: '' },
    programs:    { type: Array, default: () => [] },
});

const step = ref(props.currentStep);
const date = ref(props.currentDate || new Date().toISOString().slice(0, 10));
const loading = ref(false);

const stepLabels = {
    1: 'CHECKING OF COMPLETENESS AND AUTHENTICITY OF DOCUMENTS',
    2: 'GRADE COMPUTATION AND VERIFICATION',
    3: 'INTERVIEW AND SUBMISSION OF ENTRANCE CREDENTIALS',
};

const applyFilter = (page = 1) => {
    router.get(route('reports.logbook.index'), { step: step.value, date: date.value, page: page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Auto-apply when filters change
watch([step, date], () => applyFilter());

const downloadPdf = () => {
    loading.value = true;
    let url = route('reports.logbook.export.pdf') + `?step=${step.value}&date=${date.value}`;
    window.open(url, '_blank');
    setTimeout(() => { loading.value = false; }, 1000);
};
</script>

<template>
    <Head title="Admission Logbook" />
    <AppLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Admission Logbook</h1>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Process Step</label>
                    <select v-model.number="step" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option :value="1">CHECKING OF COMPLETENESS AND AUTHENTICITY OF DOCUMENTS</option>
                        <option :value="2">GRADE COMPUTATION AND VERIFICATION</option>
                        <option :value="3">INTERVIEW AND SUBMISSION OF ENTRANCE CREDENTIALS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                    <input type="date" v-model="date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]" />
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-4 mb-6">
                <button @click="downloadPdf" :disabled="loading || entries.total === 0" class="px-4 py-2 bg-[#9E122C] text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#800918] flex items-center gap-2 shadow">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export PDF Logbook
                </button>
            </div>

            <!-- Preview Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Preview — {{ stepLabels[step] }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Showing entries for {{ date }}</p>
                    </div>
                    <span class="text-sm font-semibold text-[#9E122C]">{{ entries.total }} entr{{ entries.total === 1 ? 'y' : 'ies' }}</span>
                </div>

                <div v-if="entries.data.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Date/Time Requested</th>
                                <th class="px-4 py-3 text-left">Client Name</th>
                                <th class="px-4 py-3 text-left">Program</th>
                                <th class="px-4 py-3 text-left">Sex</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Date/Time Processed</th>
                                <th class="px-4 py-3 text-left">Minutes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="(entry, index) in entries.data" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ entries.from + index }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ entry.requested_at || '—' }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ entry.client_name || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ entry.program || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 capitalize">{{ entry.sex || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-xs">{{ entry.email || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ entry.processed_at || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ entry.minutes_processed !== '' ? entry.minutes_processed + ' min' : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="entries.last_page > 1 && !loading" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-400">
                            <span v-if="!entries.total || entries.total === 0">
                                Showing 0 to 0 of 0 results
                            </span>
                            <span v-else>
                                Showing {{ (entries.current_page - 1) * entries.per_page + 1 }} 
                                to {{ (entries.current_page - 1) * entries.per_page + entries.data.length }} 
                                of {{ entries.total }} results
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button
                                :disabled="entries.current_page === 1"
                                @click.prevent="applyFilter(entries.current_page - 1)"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                Previous
                            </button>
                            <div class="flex items-center space-x-2 mx-2 text-sm text-gray-700 dark:text-gray-300">
                                <span>Page</span>
                                <input
                                    type="number"
                                    :value="entries.current_page"
                                    min="1"
                                    :max="entries.last_page || 1"
                                    @change="applyFilter(Math.max(1, Math.min($event.target.value, entries.last_page || 1)))"
                                    class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm"
                                />
                                <span>of <span class="font-semibold">{{ entries.last_page || 1 }}</span></span>
                            </div>
                            <button
                                :disabled="entries.current_page === entries.last_page || entries.last_page === 0"
                                @click.prevent="applyFilter(entries.current_page + 1)"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                Next
                                <svg class="h-5 w-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else-if="entries.data.length === 0" class="text-center py-16">
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400">No entries found for this step and date.</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Try selecting a different date or process step.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
