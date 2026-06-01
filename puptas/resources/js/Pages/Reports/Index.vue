<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from 'axios';

const props = defineProps({
    programs: Array
});

const applicants = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const perPage = ref(15);

const filterType = ref("overall");
const filterDate = ref("");
const filterMonth = ref("");
const filterProgram = ref("");

let abortController = null;

const fetchReportData = async (page = 1) => {
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    loading.value = true;
    try {
        const params = {
            type: filterType.value,
            page: page,
        };
        if (filterDate.value) params.date_filter = filterDate.value;
        if (filterMonth.value) params.month_filter = filterMonth.value;
        if (filterProgram.value) params.program_id = filterProgram.value;

        const response = await axios.get(route('reports.data'), { 
            params,
            signal: abortController.signal 
        });
        
        applicants.value = response.data.data;
        currentPage.value = response.data.current_page;
        lastPage.value = response.data.last_page;
        total.value = response.data.total;
        perPage.value = response.data.per_page || 15;
    } catch (err) {
        if (axios.isCancel(err)) {
            console.log("Request canceled due to new request.");
        } else {
            console.error("Failed to fetch report data:", err);
        }
    } finally {
        if (!abortController.signal.aborted) {
            loading.value = false;
        }
    }
};

onMounted(() => {
    fetchReportData(1);
});

const downloadPdf = () => {
    let url = route('reports.export.pdf') + `?type=${filterType.value}`;
    if (filterDate.value) url += `&date_filter=${filterDate.value}`;
    if (filterMonth.value) url += `&month_filter=${filterMonth.value}`;
    if (filterProgram.value) url += `&program_id=${filterProgram.value}`;
    window.open(url, '_blank');
};

const downloadExcel = () => {
    let url = route('reports.export.excel') + `?type=${filterType.value}`;
    if (filterDate.value) url += `&date_filter=${filterDate.value}`;
    if (filterMonth.value) url += `&month_filter=${filterMonth.value}`;
    if (filterProgram.value) url += `&program_id=${filterProgram.value}`;
    window.open(url, '_blank');
};

const clearFilters = () => {
    filterType.value = "overall";
    filterDate.value = "";
    filterMonth.value = "";
    filterProgram.value = "";
    fetchReportData(1);
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s.includes("enrolled")) return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s.includes("medical")) return "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300";
    if (s.includes("interview")) return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};
</script>

<template>
    <Head title="Reports Dashboard" />
    <AppLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Reports Dashboard</h1>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Type</label>
                    <select v-model="filterType" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="overall">Overall Report</option>
                        <option value="interview">Finished Interview</option>
                        <option value="medical">Finished Medical Clearance</option>
                        <option value="enrollment">Finished Enrollment Process</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                    <input type="date" v-model="filterDate" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]" @change="filterMonth = ''" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Month</label>
                    <input type="month" v-model="filterMonth" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]" @change="filterDate = ''" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Course/Program</label>
                    <select v-model="filterProgram" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="">All Courses</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">
                            {{ program.code }} - {{ program.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-4 mb-6">
                <button @click="fetchReportData(1)" :disabled="loading" class="px-4 py-2 bg-[#9E122C] text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#800000]">
                    Generate Report
                </button>
                <button @click="clearFilters" :disabled="loading" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700">
                    Clear Filters
                </button>
                <button @click="downloadPdf" :disabled="loading" class="px-4 py-2 border border-[#9E122C] text-[#9E122C] rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#9E122C] hover:text-white dark:text-white dark:hover:text-gray-900">
                    Export PDF
                </button>
                <button @click="downloadExcel" :disabled="loading" class="px-4 py-2 border border-green-600 text-green-600 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-green-600 hover:text-white dark:text-green-400 dark:hover:text-white">
                    Export Excel
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-2 overflow-x-auto">
                <div v-if="loading" class="text-center text-gray-500 py-8 dark:text-gray-300">Loading data…</div>
                <table v-else class="min-w-full text-base">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white border-b dark:border-gray-700">
                            <th class="pb-2">Reference Number</th>
                            <th class="pb-2">Name</th>
                            <th class="pb-2">Course/Program</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="app in applicants" :key="app.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                            <td class="py-3 text-gray-900 dark:text-white font-medium">{{ app.reference_number }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ app.name }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ app.program }}</td>
                            <td class="py-3">
                                <span :class="getStatusClass(app.status)" class="px-2.5 py-1 rounded-full text-xs font-medium">
                                    {{ app.status }}
                                </span>
                            </td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ app.date }}</td>
                        </tr>
                        <tr v-if="applicants.length === 0">
                            <td colspan="5" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No applicants found matching the report criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1 && !loading" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-b-xl shadow mt-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700 dark:text-gray-400">
                        <span v-if="!total || total === 0">
                            Showing 0 to 0 of 0 results
                        </span>
                        <span v-else>
                            Showing {{ (currentPage - 1) * perPage + 1 }} 
                            to {{ (currentPage - 1) * perPage + applicants.length }} 
                            of {{ total }} results
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button
                            :disabled="currentPage === 1"
                            @click.prevent="fetchReportData(currentPage - 1)"
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
                                :value="currentPage"
                                min="1"
                                :max="lastPage || 1"
                                @change="fetchReportData(Math.max(1, Math.min($event.target.value, lastPage || 1)))"
                                class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm"
                            />
                            <span>of <span class="font-semibold">{{ lastPage || 1 }}</span></span>
                        </div>
                        <button
                            :disabled="currentPage === lastPage || lastPage === 0"
                            @click.prevent="fetchReportData(currentPage + 1)"
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
        </div>
    </AppLayout>
</template>
