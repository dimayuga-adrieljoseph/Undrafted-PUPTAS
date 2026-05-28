<script setup>
import { ref, onMounted } from "vue";
import { Head } from "@inertiajs/vue3";
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

// Filters
const filterSearch = ref("");
const filterDate = ref("");
const filterMonth = ref("");
const filterProgram = ref("");

let abortController = null;

const fetchMasterlistData = async (page = 1) => {
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    loading.value = true;
    try {
        const params = {
            page: page,
        };
        if (filterSearch.value) params.search = filterSearch.value;
        if (filterDate.value) params.date_filter = filterDate.value;
        if (filterMonth.value) params.month_filter = filterMonth.value;
        if (filterProgram.value) params.program_id = filterProgram.value;

        const response = await axios.get(route('reports.masterlist.data'), { 
            params,
            signal: abortController.signal 
        });
        
        applicants.value = response.data.paginator.data;
        currentPage.value = response.data.paginator.current_page;
        lastPage.value = response.data.paginator.last_page;
        total.value = response.data.paginator.total;
    } catch (err) {
        if (axios.isCancel(err)) {
            console.log("Request canceled due to new request.");
        } else {
            console.error("Failed to fetch masterlist data:", err);
        }
    } finally {
        if (!abortController.signal.aborted) {
            loading.value = false;
        }
    }
};

onMounted(() => {
    fetchMasterlistData(1);
});

const downloadPdf = () => {
    let url = route('reports.masterlist.export.pdf') + '?';
    if (filterSearch.value) url += `&search=${encodeURIComponent(filterSearch.value)}`;
    if (filterDate.value) url += `&date_filter=${filterDate.value}`;
    if (filterMonth.value) url += `&month_filter=${filterMonth.value}`;
    if (filterProgram.value) url += `&program_id=${filterProgram.value}`;
    window.open(url, '_blank');
};

const downloadExcel = () => {
    let url = route('reports.masterlist.export.excel') + '?';
    if (filterSearch.value) url += `&search=${encodeURIComponent(filterSearch.value)}`;
    if (filterDate.value) url += `&date_filter=${filterDate.value}`;
    if (filterMonth.value) url += `&month_filter=${filterMonth.value}`;
    if (filterProgram.value) url += `&program_id=${filterProgram.value}`;
    window.open(url, '_blank');
};

const clearFilters = () => {
    filterSearch.value = "";
    filterDate.value = "";
    filterMonth.value = "";
    filterProgram.value = "";
    fetchMasterlistData(1);
};
</script>

<template>
    <Head title="Accepted Applicants Dashboard" />
    <AppLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Accepted Applicants Dashboard</h1>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                    <input 
                        type="text" 
                        v-model="filterSearch" 
                        placeholder="Search name, student or reference no..." 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C] text-sm"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Program / Course</label>
                    <select v-model="filterProgram" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]">
                        <option value="">All Programs</option>
                        <option v-for="prog in programs" :key="prog.id" :value="prog.id">
                            {{ prog.code }} - {{ prog.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Accepted</label>
                    <input 
                        type="date" 
                        v-model="filterDate" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]" 
                        @change="filterMonth = ''" 
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Month Accepted</label>
                    <input 
                        type="month" 
                        v-model="filterMonth" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]" 
                        @change="filterDate = ''" 
                    />
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-4 mb-6">
                <button @click="fetchMasterlistData(1)" :disabled="loading" class="px-4 py-2 bg-[#9E122C] text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#800000]">
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
                            <th class="pb-2">Email</th>
                            <th class="pb-2">Accepted Program</th>
                            <th class="pb-2">Date Accepted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="app in applicants" :key="app.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                            <td class="py-3 text-gray-700 dark:text-gray-300 font-medium">{{ app.reference_number || 'N/A' }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300 font-medium text-black dark:text-white">{{ app.name }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ app.email }}</td>
                            <td class="py-3">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-[#9E122C] dark:bg-red-950/40 dark:text-red-300">
                                    {{ app.program }}
                                </span>
                            </td>
                            <td class="py-3 text-gray-500 dark:text-gray-400">{{ app.date_accepted }}</td>
                        </tr>
                        <tr v-if="applicants.length === 0">
                            <td colspan="5" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No records found matching the report criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1 && !loading" class="mt-4 flex flex-col sm:flex-row justify-between items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow gap-4">
                <div class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300">
                    <span>Showing page</span>
                    <input
                        type="number"
                        :value="currentPage"
                        min="1"
                        :max="lastPage || 1"
                        @change="fetchMasterlistData(Math.max(1, Math.min($event.target.value, lastPage || 1)))"
                        class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C]"
                    />
                    <span>of <span class="font-semibold">{{ lastPage }}</span> (Total: {{ total }} records)</span>
                </div>
                <div class="flex gap-2">
                    <button 
                        @click="fetchMasterlistData(currentPage - 1)" 
                        :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-md border text-sm font-medium transition-colors"
                        :class="currentPage === 1 ? 'text-gray-400 border-gray-200 dark:border-gray-700 cursor-not-allowed dark:text-gray-500' : 'text-gray-700 border-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700'"
                    >
                        Previous
                    </button>
                    <button 
                        @click="fetchMasterlistData(currentPage + 1)" 
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
