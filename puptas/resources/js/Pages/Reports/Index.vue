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

// SIS Upload state
const sisSchoolYear = ref("");
const sisSchoolYears = ref([]);
const sisLoading = ref(false);

const fetchSisSchoolYears = async () => {
    try {
        const res = await axios.get(route('sis-upload.school-years'));
        sisSchoolYears.value = res.data.school_years || [];
        if (sisSchoolYears.value.length > 0) {
            sisSchoolYear.value = sisSchoolYears.value[0];
        }
    } catch (e) {
        console.error('Failed to load school years:', e);
    }
};

const downloadSisPassers = () => {
    sisLoading.value = true;
    let url = route('sis-upload.passers');
    if (sisSchoolYear.value) url += `?school_year=${encodeURIComponent(sisSchoolYear.value)}`;
    window.open(url, '_blank');
    setTimeout(() => { sisLoading.value = false; }, 2000);
};

const downloadSisRecon = () => {
    sisLoading.value = true;
    let url = route('sis-upload.recon');
    if (sisSchoolYear.value) url += `?school_year=${encodeURIComponent(sisSchoolYear.value)}`;
    window.open(url, '_blank');
    setTimeout(() => { sisLoading.value = false; }, 2000);
};

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
    fetchSisSchoolYears();
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

            <!-- Unified Control Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow mb-6 overflow-hidden">

                <!-- Section: Applicant Report Filters -->
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Applicant Report</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <!-- Report Type -->
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Report Type</label>
                            <select v-model="filterType" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C]">
                                <option value="overall">Overall Report</option>
                                <option value="interview">Finished Interview</option>
                                <option value="medical">Finished Medical Clearance</option>
                                <option value="enrollment">Finished Enrollment Process</option>
                                <option value="pulled_out">Pulled Out</option>
                            </select>
                        </div>
                        <!-- Date -->
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date</label>
                            <input type="date" v-model="filterDate" @change="filterMonth = ''"
                                class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C]" />
                        </div>
                        <!-- Month -->
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Month</label>
                            <input type="month" v-model="filterMonth" @change="filterDate = ''"
                                class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C]" />
                        </div>
                        <!-- Program -->
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Course / Program</label>
                            <select v-model="filterProgram" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C]">
                                <option value="">All Courses</option>
                                <option v-for="program in programs" :key="program.id" :value="program.id">
                                    {{ program.code }} – {{ program.name }}
                                </option>
                            </select>
                        </div>
                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 pb-0.5">
                            <button @click="fetchReportData(1)" :disabled="loading"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#9E122C] text-white text-sm font-medium rounded-lg hover:bg-[#800000] disabled:opacity-50 disabled:cursor-not-allowed transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16" />
                                </svg>
                                Generate
                            </button>
                            <button @click="clearFilters" :disabled="loading"
                                class="px-4 py-2 text-sm font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Export buttons row -->
                    <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mr-1">Export as:</span>
                        <button @click="downloadPdf" :disabled="loading"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-[#9E122C] text-[#9E122C] dark:text-red-400 rounded-lg hover:bg-[#9E122C] hover:text-white dark:hover:bg-[#9E122C] dark:hover:text-white disabled:opacity-50 transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            PDF
                        </button>
                        <button @click="downloadExcel" :disabled="loading"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-green-600 text-green-600 dark:text-green-400 rounded-lg hover:bg-green-600 hover:text-white dark:hover:bg-green-600 dark:hover:text-white disabled:opacity-50 transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Excel
                        </button>
                    </div>
                </div>

                <!-- Section: SIS Upload -->
                <div class="px-5 py-4">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">SIS Upload Reports (XLSX)</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- School Year -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">School Year</label>
                            <select v-model="sisSchoolYear" id="sis-school-year"
                                class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C]">
                                <option value="">All School Years</option>
                                <option v-for="yr in sisSchoolYears" :key="yr" :value="yr">{{ yr }}</option>
                            </select>
                        </div>

                        <!-- Download Passers -->
                        <button id="btn-download-sis-passers" @click="downloadSisPassers" :disabled="sisLoading"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] text-white rounded-lg text-sm font-medium hover:bg-[#800000] disabled:opacity-60 disabled:cursor-not-allowed transition mt-4">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {{ sisLoading ? 'Preparing…' : 'Passers XLSX' }}
                        </button>

                        <!-- Download Recon -->
                        <button id="btn-download-sis-recon" @click="downloadSisRecon" :disabled="sisLoading"
                            class="inline-flex items-center gap-2 px-4 py-2 border border-[#9E122C] text-[#9E122C] dark:text-white rounded-lg text-sm font-medium hover:bg-[#9E122C] hover:text-white disabled:opacity-60 disabled:cursor-not-allowed transition mt-4">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {{ sisLoading ? 'Preparing…' : 'Recon XLSX' }}
                        </button>

                        <!-- Legend pills -->
                        <div class="flex flex-wrap items-center gap-3 mt-4 ml-1">
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <span class="w-2 h-2 rounded-full bg-[#9E122C] inline-block"></span>
                                <strong class="text-gray-700 dark:text-gray-300">Passers</strong> — completed interview
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
                                <strong class="text-gray-700 dark:text-gray-300">Recon</strong> — on probation
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div v-if="loading" class="text-center text-gray-500 py-10 dark:text-gray-300">Loading data…</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900/40 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3">Reference No.</th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Course / Program</th>
                                <th class="px-4 py-3">Status</th>
                                <th v-if="filterType === 'pulled_out'" class="px-4 py-3">Pull-Out Notes</th>
                                <th class="px-4 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="app in applicants" :key="app.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">{{ app.reference_number }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ app.name }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ app.program }}</td>
                                <td class="px-4 py-3">
                                    <span :class="getStatusClass(app.status)" class="px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                                        {{ app.status }}
                                    </span>
                                </td>
                                <td v-if="filterType === 'pulled_out'" class="px-4 py-3 text-gray-500 dark:text-gray-400 text-sm italic">
                                    {{ app.pullout_notes || '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ app.date }}</td>
                            </tr>
                            <tr v-if="applicants.length === 0">
                                <td :colspan="filterType === 'pulled_out' ? 6 : 5" class="py-12 text-center text-gray-400 dark:text-gray-500">
                                    No applicants found matching the report criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="lastPage > 1" class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <span v-if="!total || total === 0">Showing 0 results</span>
                        <span v-else>
                            Showing {{ (currentPage - 1) * perPage + 1 }}–{{ (currentPage - 1) * perPage + applicants.length }} of {{ total }}
                        </span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button :disabled="currentPage === 1" @click.prevent="fetchReportData(currentPage - 1)"
                            class="inline-flex items-center px-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            Prev
                        </button>
                        <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                            <span>Page</span>
                            <input type="number" :value="currentPage" min="1" :max="lastPage || 1"
                                @change="fetchReportData(Math.max(1, Math.min($event.target.value, lastPage || 1)))"
                                class="w-14 px-2 py-1 text-center border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] focus:outline-none" />
                            <span>of <span class="font-semibold">{{ lastPage || 1 }}</span></span>
                        </div>
                        <button :disabled="currentPage === lastPage || lastPage === 0" @click.prevent="fetchReportData(currentPage + 1)"
                            class="inline-flex items-center px-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            Next
                            <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
