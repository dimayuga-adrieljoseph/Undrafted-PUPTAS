<script setup>
import { ref, watch } from "vue";
import { Head, router, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    programs: { type: Array, default: () => [] },
    applicants: { type: Object, default: () => null },
    selectedProgramId: { type: [String, Number], default: "" },
});

// Ensure the program ID is a number to strictly match the select option value
const controlListProgram = ref(
    props.selectedProgramId ? Number(props.selectedProgramId) : ""
);

const isLoading = ref(false);

watch(controlListProgram, (newProgramId) => {
    if (!newProgramId) return; // Do not fetch if "Select a Program" is chosen

    isLoading.value = true;
    router.get(
        '/admin/control-list',
        { program_id: newProgramId },
        { 
            preserveState: true, 
            replace: true,
            onFinish: () => { isLoading.value = false; }
        }
    );
});

const goToPage = (pageNum) => {
    if (!controlListProgram.value) return;
    isLoading.value = true;
    router.get(
        '/admin/control-list',
        { program_id: controlListProgram.value, page: pageNum },
        { 
            preserveState: true, 
            replace: true,
            onFinish: () => { isLoading.value = false; }
        }
    );
};

const getCurrentAY = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = d.getMonth(); // 0 = Jan, 5 = Jun
    if (month >= 5) {
        return `${year}-${year + 1}`;
    } else {
        return `${year - 1}-${year}`;
    }
};
const controlListYear = ref(getCurrentAY());

const downloadControlList = () => {
    if (!controlListProgram.value || !controlListYear.value) return;
    let url = route('reports.control-list.export') + `?program_id=${controlListProgram.value}&academic_year=${encodeURIComponent(controlListYear.value)}`;
    window.open(url, '_blank');
};
</script>

<template>
    <Head title="Control List Export" />
    <AppLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Control List Export</h1>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
                <p class="text-gray-600 dark:text-gray-300 mb-4">
                    Export the Control List for Interview and Submission of Entrance Credentials for a specific program and academic year.
                </p>

                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full md:w-96">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Export Control List (Program)</label>
                        <select v-model="controlListProgram" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                            <option value="">Select a Program</option>
                            <option v-for="program in programs" :key="program.id" :value="program.id">
                                {{ program.code }} - {{ program.name }}
                            </option>
                        </select>
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Academic Year</label>
                        <input type="text" v-model="controlListYear" placeholder="e.g. 2026-2027" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]" />
                    </div>
                    <div class="w-full md:w-auto">
                        <button @click="downloadControlList" :disabled="!controlListProgram || !controlListYear" class="w-full px-4 py-2 bg-[#9E122C] text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#800918] flex items-center justify-center gap-2 shadow">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            Export Control List PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Table Area -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mt-6">
                
                <!-- Table Content -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Control List Preview</h2>
                    <span v-if="applicants" class="text-sm text-gray-500 dark:text-gray-400">Total: {{ applicants.total }} Applicants</span>
                    <span v-else class="text-sm text-gray-500 dark:text-gray-400">Please select a program</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300 text-sm border-b border-gray-200 dark:border-gray-700">
                                <th class="p-4 font-semibold whitespace-nowrap">Name of Candidate</th>
                                <th class="p-4 font-semibold whitespace-nowrap">Strand/Track</th>
                                <th class="p-4 font-semibold whitespace-nowrap">GWA</th>
                                <th class="p-4 font-semibold whitespace-nowrap">Math</th>
                                <th class="p-4 font-semibold whitespace-nowrap">Science</th>
                                <th class="p-4 font-semibold whitespace-nowrap">English</th>
                                <th class="p-4 font-semibold whitespace-nowrap">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-800 dark:text-gray-200 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="!applicants || !applicants.data || applicants.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-gray-500">
                                    <span v-if="isLoading" class="flex items-center justify-center">
                                        <svg class="animate-spin h-5 w-5 mr-3 text-[#9E122C]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Loading...
                                    </span>
                                    <span v-else>No applicants to display.</span>
                                </td>
                            </tr>
                            <template v-else>
                                <tr v-for="app in applicants.data" :key="app.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="p-4 whitespace-nowrap font-medium">{{ app.full_name }}</td>
                                    <td class="p-4 whitespace-nowrap">{{ app.strand }}</td>
                                    <td class="p-4 whitespace-nowrap">{{ app.gwa }}</td>
                                    <td class="p-4 whitespace-nowrap">{{ app.math_gwa }}</td>
                                    <td class="p-4 whitespace-nowrap">{{ app.science_gwa }}</td>
                                    <td class="p-4 whitespace-nowrap">{{ app.english_gwa }}</td>
                                    <td class="p-4 whitespace-nowrap">{{ app.notes }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="applicants && applicants.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-700 dark:text-gray-400 text-center sm:text-left">
                            <span v-if="!applicants.total || applicants.total === 0">
                                Showing 0 to 0 of 0 results
                            </span>
                            <span v-else>
                                Showing {{ applicants.from || 0 }} 
                                to {{ applicants.to || 0 }} 
                                of {{ applicants.total }} results
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button
                                :disabled="applicants.current_page === 1 || isLoading"
                                @click.prevent="goToPage(applicants.current_page - 1)"
                                class="inline-flex items-center px-3 sm:px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                <svg class="h-5 w-5 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span class="hidden sm:inline">Previous</span>
                            </button>
                            <div class="flex items-center space-x-2 mx-2 text-sm text-gray-700 dark:text-gray-300">
                                <span class="hidden sm:inline">Page</span>
                                <input
                                    type="number"
                                    :value="applicants.current_page"
                                    min="1"
                                    :max="applicants.last_page || 1"
                                    @change="goToPage(Math.max(1, Math.min($event.target.value, applicants.last_page || 1)))"
                                    class="w-14 sm:w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm"
                                    :disabled="isLoading"
                                />
                                <span>of <span class="font-semibold">{{ applicants.last_page || 1 }}</span></span>
                            </div>
                            <button
                                :disabled="applicants.current_page === applicants.last_page || isLoading"
                                @click.prevent="goToPage(applicants.current_page + 1)"
                                class="inline-flex items-center px-3 sm:px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                <span class="hidden sm:inline">Next</span>
                                <svg class="h-5 w-5 sm:ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
