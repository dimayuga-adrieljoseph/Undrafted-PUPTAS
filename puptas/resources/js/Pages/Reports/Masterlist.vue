<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from 'axios';

const props = defineProps({
    programs: Array
});

const applicants = ref([]);
const programStats = ref([]);
const overallCount = ref(0);
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
let searchTimeout = null;

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
        
        programStats.value = response.data.programStats || [];
        overallCount.value = response.data.overallCount || 0;
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

// Trigger search with a slight debounce
watch(filterSearch, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchMasterlistData(1);
    }, 300);
});

// Watch other filters for immediate fetch
watch([filterDate, filterMonth, filterProgram], () => {
    fetchMasterlistData(1);
});

const bsitCount = computed(() => {
    const bsit = programStats.value.find(p => p.code.toUpperCase() === 'BSIT');
    return bsit ? bsit.count : 0;
});

const otherPrograms = computed(() => {
    return programStats.value.filter(p => p.code.toUpperCase() !== 'BSIT');
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
    <Head title="Accepted Masterlist" />
    <AppLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8">
            
            <!-- Breadcrumbs / Top Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Accepted Applicants Masterlist</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Real-time repository of applicants who have completed their interviews and been accepted.</p>
                </div>
                <div class="flex gap-3">
                    <button 
                        @click="downloadPdf" 
                        :disabled="loading" 
                        class="inline-flex items-center px-4 py-2.5 bg-red-700 hover:bg-red-800 text-white rounded-lg font-semibold text-sm transition shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download PDF
                    </button>
                    <button 
                        @click="downloadExcel" 
                        :disabled="loading" 
                        class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold text-sm transition shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download Excel
                    </button>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Stat Card: Overall Accepted -->
                <div class="bg-gradient-to-br from-[#9E122C] to-[#730C1E] rounded-2xl shadow-xl p-6 text-white relative overflow-hidden">
                    <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-4 translate-y-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-40 w-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-200">Overall Accepted</p>
                    <h3 class="text-4xl font-extrabold mt-2">{{ overallCount }}</h3>
                    <p class="text-xs text-red-100 mt-4 flex items-center">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-2"></span>
                        Active Admission Season
                    </p>
                </div>

                <!-- Stat Card: BSIT Accepted -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-6 relative overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute right-0 bottom-0 text-gray-100 dark:text-gray-700/50 opacity-50 transform translate-x-4 translate-y-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-40 w-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">BSIT Accepted Passers</p>
                    <h3 class="text-4xl font-extrabold mt-2 text-[#9E122C] dark:text-red-400">{{ bsitCount }}</h3>
                    <p class="text-xs text-gray-500 mt-4 dark:text-gray-400">Bachelor of Science in Information Technology</p>
                </div>

                <!-- Stat Card: Quick Breakdown per Program -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-6 border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Passers Per Program Summary</p>
                        <div class="max-h-24 overflow-y-auto pr-1 space-y-2 custom-scrollbar">
                            <div v-for="program in programStats" :key="program.id" class="flex justify-between items-center text-sm">
                                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ program.code }}</span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ program.count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-6 mb-8 border border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4 border-b border-gray-100 dark:border-gray-700 pb-3">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200">Filter Applicants</h4>
                    </div>
                    <button @click="clearFilters" class="text-xs font-semibold text-[#9E122C] hover:text-[#800000] dark:text-red-400 dark:hover:text-red-300 transition">Clear All Filters</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Search Filter -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Search</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                v-model="filterSearch" 
                                placeholder="Name, student no, or ref no..." 
                                class="w-full pl-9 pr-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] text-sm"
                            />
                            <div class="absolute left-3 top-3 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <!-- Course Filter -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Program / Course</label>
                        <select 
                            v-model="filterProgram" 
                            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] text-sm"
                        >
                            <option value="">All Programs</option>
                            <option v-for="prog in programs" :key="prog.id" :value="prog.id">
                                {{ prog.code }} - {{ prog.name }}
                            </option>
                        </select>
                    </div>
                    <!-- Date Filter -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Date Accepted</label>
                        <input 
                            type="date" 
                            v-model="filterDate" 
                            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] text-sm" 
                            @change="filterMonth = ''" 
                        />
                    </div>
                    <!-- Month Filter -->
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Month Accepted</label>
                        <input 
                            type="month" 
                            v-model="filterMonth" 
                            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] text-sm" 
                            @change="filterDate = ''" 
                        />
                    </div>
                </div>
            </div>

            <!-- Masterlist Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">Showing accepted applicants</span>
                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 text-xs font-bold">{{ total }} Records Found</span>
                </div>
                <div class="overflow-x-auto">
                    <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-[#9E122C]"></div>
                        <span class="mt-4 text-sm text-gray-500 dark:text-gray-400 font-medium">Fetching accepted masterlist...</span>
                    </div>
                    <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Student Number</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference Number</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Accepted Program</th>
                                <th scope="col" class="px-6 py-4 class text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date Accepted</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="app in applicants" :key="app.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ app.student_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-medium">
                                    {{ app.reference_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                    {{ app.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    {{ app.email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-[#9E122C] dark:bg-red-950/40 dark:text-red-300">
                                        {{ app.program }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ app.date_accepted }}
                                </td>
                            </tr>
                            <tr v-if="applicants.length === 0">
                                <td colspan="6" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300 dark:text-gray-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <p class="text-base font-semibold">No accepted applicants found</p>
                                        <p class="text-xs text-gray-400 mt-1">Try adjusting your filters or search keywords.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Section -->
                <div v-if="lastPage > 1 && !loading" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4 bg-gray-50 dark:bg-gray-800/50">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        Page {{ currentPage }} of {{ lastPage }} &middot; Showing {{ applicants.length }} of {{ total }} records
                    </div>
                    <div class="flex gap-2">
                        <button 
                            @click="fetchMasterlistData(currentPage - 1)" 
                            :disabled="currentPage === 1"
                            class="px-4 py-2 border rounded-xl text-xs font-bold transition disabled:opacity-40 disabled:cursor-not-allowed border-gray-200 text-gray-700 bg-white hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                        >
                            Previous
                        </button>
                        <button 
                            @click="fetchMasterlistData(currentPage + 1)" 
                            :disabled="currentPage === lastPage"
                            class="px-4 py-2 border rounded-xl text-xs font-bold transition disabled:opacity-40 disabled:cursor-not-allowed border-gray-200 text-gray-700 bg-white hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
}
</style>
