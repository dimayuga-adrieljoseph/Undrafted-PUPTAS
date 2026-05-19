<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from 'axios';

const props = defineProps({
    batches: Array,
    schoolYears: Array,
    strands: Array,
});

const passers = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);

const filterStatus = ref("qualified");
const filterBatch = ref("");
const filterSchoolYear = ref("");
const filterStrand = ref("");

let abortController = null;

const fetchReportData = async (page = 1) => {
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    loading.value = true;
    try {
        const params = {
            status: filterStatus.value,
            page: page,
        };
        if (filterBatch.value) params.batch = filterBatch.value;
        if (filterSchoolYear.value) params.school_year = filterSchoolYear.value;
        if (filterStrand.value) params.strand = filterStrand.value;

        const response = await axios.get(route('reports.test-passers.data'), { 
            params,
            signal: abortController.signal 
        });
        
        passers.value = response.data.data;
        currentPage.value = response.data.current_page;
        lastPage.value = response.data.last_page;
        total.value = response.data.total;
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
    let url = route('reports.test-passers.export.pdf') + `?status=${filterStatus.value}`;
    if (filterBatch.value) url += `&batch=${filterBatch.value}`;
    if (filterSchoolYear.value) url += `&school_year=${filterSchoolYear.value}`;
    if (filterStrand.value) url += `&strand=${filterStrand.value}`;
    window.open(url, '_blank');
};

const downloadExcel = () => {
    let url = route('reports.test-passers.export.excel') + `?status=${filterStatus.value}`;
    if (filterBatch.value) url += `&batch=${filterBatch.value}`;
    if (filterSchoolYear.value) url += `&school_year=${filterSchoolYear.value}`;
    if (filterStrand.value) url += `&strand=${filterStrand.value}`;
    window.open(url, '_blank');
};

const clearFilters = () => {
    filterStatus.value = "qualified";
    filterBatch.value = "";
    filterSchoolYear.value = "";
    filterStrand.value = "";
    fetchReportData(1);
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "qualified") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "waitlisted") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "waitlisted_below_cutoff") return "bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};
</script>

<template>
    <Head title="Test Passers Reports" />
    <AppLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Test Passers Dashboard</h1>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select v-model="filterStatus" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="qualified">Qualified</option>
                        <option value="waitlisted">Waitlisted</option>
                        <option value="waitlisted_below_cutoff">Waitlisted Below Cut Off</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batch</label>
                    <select v-model="filterBatch" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="">All Batches</option>
                        <option v-for="batch in batches" :key="batch" :value="batch">
                            {{ batch }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">School Year</label>
                    <select v-model="filterSchoolYear" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="">All School Years</option>
                        <option v-for="sy in schoolYears" :key="sy" :value="sy">
                            {{ sy }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Strand</label>
                    <select v-model="filterStrand" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="">All Strands</option>
                        <option v-for="strand in strands" :key="strand" :value="strand">
                            {{ strand }}
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
                            <th class="pb-2">Rank</th>
                            <th class="pb-2">Reference Number</th>
                            <th class="pb-2">Name</th>
                            <th class="pb-2">Strand</th>
                            <th class="pb-2">Score</th>
                            <th class="pb-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="passer in passers" :key="passer.test_passer_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                            <td class="py-3 text-gray-900 dark:text-white font-medium">#{{ passer.rank }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ passer.reference_number || 'N/A' }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ passer.full_name }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ passer.strand || 'N/A' }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300 font-semibold">{{ passer.pupcet_total_score }}</td>
                            <td class="py-3">
                                <span :class="getStatusClass(passer.passer_status_name)" class="px-2.5 py-1 rounded-full text-xs font-medium capitalize">
                                    {{ passer.passer_status_name }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="passers.length === 0">
                            <td colspan="6" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No records found matching the report criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1 && !loading" class="mt-4 flex flex-col sm:flex-row justify-between items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow gap-4">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing page <span class="font-semibold">{{ currentPage }}</span> of <span class="font-semibold">{{ lastPage }}</span> (Total: {{ total }} records)
                </div>
                <div class="flex gap-2">
                    <button 
                        @click="fetchReportData(currentPage - 1)" 
                        :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-md border text-sm font-medium transition-colors"
                        :class="currentPage === 1 ? 'text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed dark:text-gray-500' : 'text-gray-700 border-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700'"
                    >
                        Previous
                    </button>
                    <button 
                        @click="fetchReportData(currentPage + 1)" 
                        :disabled="currentPage === lastPage"
                        class="px-3 py-1.5 rounded-md border text-sm font-medium transition-colors"
                        :class="currentPage === lastPage ? 'text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed dark:text-gray-500' : 'text-gray-700 border-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700'"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
