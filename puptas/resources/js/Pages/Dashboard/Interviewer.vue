<script setup>
import { ref, computed, onMounted } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link, router } from "@inertiajs/vue3";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue';
import BlurText from "@/Components/BlurText.vue";
import {
    Chart as ChartJS,
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Filler,
    Legend,
} from "chart.js";

ChartJS.register(
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Filler,
    Legend
);

import { usePage } from "@inertiajs/vue3";

const page = usePage();

const props = defineProps({
    user: Object,
    pendingUsers: Array,
    assignedPrograms: Array,
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            accepted: 0,
            pending: 0,
            returned: 0,
        }),
    },
    chartData: {
        type: Object,
        default: () => ({ submitted: [], accepted: [], returned: [], labels: [] }),
    },
    filters: {
        type: Object,
        default: () => ({ start_date: '', end_date: '' })
    }
});

const startDateFilter = ref(props.filters?.start_date || '');
const endDateFilter = ref(props.filters?.end_date || '');
const showDateFilter = ref(false);

const applyFilters = () => {
    router.get(window.location.pathname, {
        start_date: startDateFilter.value,
        end_date: endDateFilter.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const selectedProgramId = ref("");
const showAcceptModal = ref(false);
const showRejectModal = ref(false);
const isSubmitting = ref(false);
const interviewNotes = ref("");
const snackbar = ref({
    visible: false,
    message: "",
    type: "success", // success, error, info
});

const showSnackbar = (msg, type = "success", duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.type = type;
    snackbar.value.visible = true;
    setTimeout(() => {
        snackbar.value.visible = false;
    }, duration);
};

// Summary items with icons and percentages
const summaryItems = computed(() => [
    { 
        label: "Total Applications", 
        value: props.summary?.total ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>' },
        percentage: 100,
        color: 'blue'
    },
    { 
        label: "Accepted", 
        value: props.summary?.accepted ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
        percentage: props.summary?.total > 0 ? Math.round((props.summary.accepted / props.summary.total) * 100) : 0,
        color: 'green'
    },
    { 
        label: "Pending", 
        value: props.summary?.pending ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
        percentage: props.summary?.total > 0 ? Math.round((props.summary.pending / props.summary.total) * 100) : 0,
        color: 'yellow'
    },
    { 
        label: "Returned", 
        value: props.summary?.returned ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>' },
        percentage: props.summary?.total > 0 ? Math.round((props.summary.returned / props.summary.total) * 100) : 0,
        color: 'red'
    },
]);

// Chart options
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#1f2937',
            bodyColor: '#374151',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            cornerRadius: 8,
            padding: 12,
        }
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { color: '#6b7280' }
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(107, 114, 128, 0.1)' },
            ticks: { 
                color: '#6b7280',
                callback: (value) => value.toLocaleString()
            }
        }
    }
};

// Chart data - computed from props
const chartDataset = computed(() => ({
    labels: props.chartData.labels ?? props.chartData.years ?? [],
    datasets: [
        {
            label: "Accepted",
            data: props.chartData.accepted || [],
            borderColor: "#10B981",
            backgroundColor: "rgba(16, 185, 129, 0.1)",
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#10B981",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 4,
        },
        {
            label: "Pending",
            data: props.chartData.submitted || [],
            borderColor: "#EAB308",
            backgroundColor: "rgba(234, 179, 8, 0.1)",
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#EAB308",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 4,
        },
        {
            label: "Returned",
            data: props.chartData.returned || [],
            borderColor: "#EF4444",
            backgroundColor: "rgba(239, 68, 68, 0.1)",
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#EF4444",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 4,
        },
    ],
}));

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
};

const getButtonClass = (type) => {
    const classes = {
        primary: 'bg-[#9E122C] text-white hover:bg-[#b51834]',
        success: 'bg-green-600 text-white hover:bg-green-700',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        secondary: 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
    };
    return classes[type] || classes.secondary;
};

onMounted(() => {
    fetchPrograms();
});

const filteredUsers = computed(() => {
    const pending = props.pendingUsers || [];
    if (!searchQuery.value.trim()) return pending;
    const query = searchQuery.value.toLowerCase();
    return pending.filter((user) => {
        return (
            user.firstname?.toLowerCase().includes(query) ||
            user.lastname?.toLowerCase().includes(query) ||
            user.email?.toLowerCase().includes(query)
        );
    });
});

const displayedUsers = computed(() => {
    if (searchQuery.value.trim()) return filteredUsers.value;
    return (props.pendingUsers || []).slice(0, 5);
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/interviewer-dashboard/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
            },
            grades: response.data.user.grades || null,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
        
        // Check if there is an interview already in progress
        const interviewerInProgress = selectedUser.value.application?.processes?.find(
            p => p.stage === 'interviewer' && p.status === 'in_progress'
        );
        if (interviewerInProgress && interviewerInProgress.started_at) {
            // Append Z if it's not present to ensure it's treated as UTC if the backend sends UTC
            interviewStartTime.value = interviewerInProgress.started_at.endsWith('Z') 
                ? interviewerInProgress.started_at 
                : interviewerInProgress.started_at + 'Z';
        } else {
            interviewStartTime.value = null;
        }
        
        await fetchPrograms();
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
        showSnackbar("Failed to load applicant data", "error");
    }
};

const formatFileKey = (key) => {
    const map = {
        file10Front: 'Grade 10 Report Front',
        file10: 'Grade 10 Report Back',
        file11Front: "Grade 11 Report Front",
        file11: "Grade 11 Report Back",
        file12Front: "Grade 12 Report Front",
        file12: "Grade 12 Report Back",
        schoolId: "School ID",
        nonEnrollCert: "Non-Enrollment Cert",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const getFileUrl = (file) => {
    return typeof file === "string" ? file : file?.url || "";
};

const hasImagePreview = (file) => {
    const url = getFileUrl(file);
    const isImage = typeof file === "string" || file?.isImage !== false;
    return Boolean(url) && isImage;
};

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (file) => {
    const src = getFileUrl(file);
    
    if (!src || !hasImagePreview(file)) {
        return;
    }

    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ') : "";

const formatStage = (stage) => {
    const map = {
        'evaluator': 'DE, GE',
        'interviewer': 'Interviewer',
        'medical': 'Medical',
        'record_staff': 'Record Staff'
    };
    return map[stage] || (stage ? stage.charAt(0).toUpperCase() + stage.slice(1).replace(/_/g, " ") : "");
};

const getInterviewerName = () => {
    // Find the completed interviewer process with passed action
    const interviewerProcess = selectedUser.value?.application?.processes?.find(
        p => p.stage === 'interviewer' && p.status === 'completed' && p.action === 'passed'
    );
    
    if (!interviewerProcess) {
        return '—';
    }
    
    // Check if performed_by is an object with user data
    if (typeof interviewerProcess.performed_by === 'object' && interviewerProcess.performed_by !== null) {
        if (interviewerProcess.performed_by.firstname && interviewerProcess.performed_by.lastname) {
            return `${interviewerProcess.performed_by.firstname} ${interviewerProcess.performed_by.lastname}`;
        }
    }
    
    // Check if we have the performedBy relationship loaded (camelCase from Laravel)
    if (interviewerProcess.performedBy?.firstname && interviewerProcess.performedBy?.lastname) {
        return `${interviewerProcess.performedBy.firstname} ${interviewerProcess.performedBy.lastname}`;
    }
    
    // Check performed_by_user
    if (interviewerProcess.performed_by_user?.firstname && interviewerProcess.performed_by_user?.lastname) {
        return `${interviewerProcess.performed_by_user.firstname} ${interviewerProcess.performed_by_user.lastname}`;
    }
    
    // If performed_by is just a number (ID)
    if (typeof interviewerProcess.performed_by === 'number') {
        return `User ID: ${interviewerProcess.performed_by}`;
    }
    
    return '—';
};

const formatDate = (date) => {
    if (!date) return "—";
    return new Date(date).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const interviewStartTime = ref(null);

const beginInterview = async () => {
    try {
        const response = await axios.post(`/interviewer-dashboard/start/${selectedUser.value.id}`);
        const startedAt = response.data.started_at;
        // Ensure proper UTC parsing
        interviewStartTime.value = startedAt.endsWith('Z') ? startedAt : startedAt + 'Z';
    } catch (e) {
        console.error("Failed to start interview:", e);
        const msg = e.response?.data?.message || "Failed to start interview";
        showSnackbar(msg, "error");
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    selectedProgramId.value = "";
    interviewStartTime.value = null;
    interviewNotes.value = "";
};

const isCancellingInterview = ref(false);
const showCancelModal = ref(false);

const cancelInterview = () => {
    showCancelModal.value = true;
};

const confirmCancelInterview = async () => {

    isCancellingInterview.value = true;
    try {
        await axios.post(`/interviewer-dashboard/cancel/${selectedUser.value.id}`);
        // Update local state directly
        interviewStartTime.value = null;
        if (selectedUser.value && selectedUser.value.application) {
            const processes = selectedUser.value.application.processes;
            const idx = processes.findIndex(p => p.stage === 'interviewer');
            if (idx !== -1) {
                processes[idx].started_at = null;
                processes[idx].performed_by = null;
            }
        }
        showSnackbar("Interview cancelled.", "info");
        showCancelModal.value = false;
    } catch (e) {
        console.error("Failed to cancel interview:", e);
        const msg = e.response?.data?.message || "Failed to cancel interview";
        showSnackbar(msg, "error");
    } finally {
        isCancellingInterview.value = false;
    }
};

const promptAccept = () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to accept the applicant into", "error");
        return;
    }
    showAcceptModal.value = true;
};

const acceptApplication = async () => {
    isSubmitting.value = true;
    try {
        await axios.post(
            `/interviewer-dashboard/accept/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
                start_time: interviewStartTime.value,
                notes: interviewNotes.value
            }
        );
        showSnackbar("Application accepted successfully", "success");
        closeUserCard();
        showAcceptModal.value = false;
        router.reload({ only: ['pendingUsers', 'summary', 'assignedPrograms'] });
    } catch (e) {
        console.error("Accept failed:", e);
        const msg = e.response?.data?.message || "Failed to accept application";
        showSnackbar(msg, "error");
    } finally {
        isSubmitting.value = false;
    }
};

const promptReject = () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to reject the applicant from", "error");
        return;
    }
    showRejectModal.value = true;
};

const rejectApplication = async () => {
    isSubmitting.value = true;
    try {
        await axios.post(
            `/interviewer-dashboard/reject/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
                start_time: interviewStartTime.value,
                notes: interviewNotes.value
            }
        );
        showSnackbar("Application rejected successfully", "success");
        closeUserCard();
        showRejectModal.value = false;
        router.reload({ only: ['pendingUsers', 'summary', 'assignedPrograms'] });
    } catch (e) {
        console.error("Reject failed:", e);
        const msg = e.response?.data?.message || "Failed to reject application";
        showSnackbar(msg, "error");
    } finally {
        isSubmitting.value = false;
    }
};

const availablePrograms = ref([]);

const fetchPrograms = async () => {
    // Programs are passed as props (assignedPrograms)
    // No need to fetch
};
</script>

<template>
    <Head title="Interviewer Dashboard" />
    <InterviewerLayout>
        <!-- Header Section -->
        <div class="px-4 md:px-8 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <BlurText
                        text="Interviewer Dashboard"
                        :delay="100"
                        animate-by="words"
                        direction="top"
                        class-name="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"
                    />
                    <BlurText
                        text="Conduct interviews and evaluate applicant responses."
                        :delay="60"
                        animate-by="words"
                        direction="top"
                        :step-duration="0.3"
                        class-name="text-gray-600 dark:text-gray-400 mt-2"
                    />
                </div>
                <div class="relative w-full md:w-64">
                    <input
                        id="searchQuery"
                        name="searchQuery"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search applicants..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 px-4 md:px-8 mb-8">
            <div
                v-for="(item, index) in summaryItems"
                :key="index"
                class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">{{ item.label }}</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ item.value.toLocaleString() }}</p>
                    </div>
                    <div :class="[
                        'p-3 rounded-lg',
                        item.color === 'blue' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
                        item.color === 'green' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300' :
                        item.color === 'yellow' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300' :
                        'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300'
                    ]">
                        <component :is="item.icon" class="w-6 h-6" />
                    </div>
                </div>
                <div class="mt-4">
                    <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div 
                            :class="[
                                'h-full rounded-full',
                                item.color === 'blue' ? 'bg-blue-500' :
                                item.color === 'green' ? 'bg-green-500' :
                                item.color === 'yellow' ? 'bg-yellow-500' :
                                'bg-red-500'
                            ]"
                            :style="{ width: item.percentage + '%' }"
                        ></div>
                    </div>
                    <p class="text-right text-xs text-gray-500 dark:text-gray-400 mt-2">{{ item.percentage }}% of total</p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8">
            <!-- Left Column: Chart -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Applications Overview</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Daily interview trends</p>
                        </div>
            <div class="relative">
              <button 
                @click="showDateFilter = !showDateFilter"
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50"
              >
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Date Filter
                <svg class="w-4 h-4 ml-1 text-gray-400 transition-transform duration-200" :class="{'rotate-180': showDateFilter}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div 
                v-if="showDateFilter" 
                class="absolute left-0 sm:left-auto sm:right-0 mt-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl z-10 w-72 max-w-[calc(100vw-2rem)] origin-top-left sm:origin-top-right transition-all"
              >
                <div class="flex justify-between items-center mb-4">
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Custom Range</h4>
                  <button @click="showDateFilter = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                  </button>
                </div>
                <div class="space-y-4">
                  <div>
                    <label for="startDateFilter" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Start Date</label>
                    <input 
                      id="startDateFilter"
                      name="startDateFilter"
                      type="date" 
                      v-model="startDateFilter"
                      class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#9E122C] focus:ring-[#9E122C] rounded-lg shadow-sm transition-colors"
                    />
                  </div>
                  <div>
                    <label for="endDateFilter" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">End Date</label>
                    <input 
                      id="endDateFilter"
                      name="endDateFilter"
                      type="date" 
                      v-model="endDateFilter"
                      class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#9E122C] focus:ring-[#9E122C] rounded-lg shadow-sm transition-colors"
                    />
                  </div>
                  <div class="pt-2">
                    <button 
                      @click="applyFilters(); showDateFilter = false;"
                      class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2.5 bg-[#9E122C] text-white text-sm font-semibold rounded-lg hover:bg-[#b51834] transition-all shadow-md active:scale-95"
                    >
                      Apply Filter
                    </button>
                  </div>
                </div>
              </div>
            </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#10B981]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Accepted</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#EAB308]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Pending</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#EF4444]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Returned</span>
                        </div>
                    </div>
                    
                    <div class="h-64 md:h-80 w-full">
                        <LineChart :chart-data="chartDataset" :options="chartOptions" class="w-full h-full" />
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Applications -->
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Pending Interviews</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Applicants ready for interview</p>
                        </div>
                        <Link href="/interviewer-applications" 
                              class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition dark:text-white dark:hover:text-white">
                            View All
                        </Link>
                    </div>
                    
                    <div class="space-y-3">
                        <div
                            v-for="applicant in displayedUsers"
                            :key="applicant.id"
                            @click="selectUser(applicant)"
                            class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer"
                        >
                            <div class="flex items-center justify-between mb-3 gap-2">
                                <div class="flex items-center space-x-3 min-w-0">
                                    <div class="w-10 h-10 bg-[#9E122C] rounded-full flex items-center justify-center text-white font-semibold dark:bg-gray-900 dark:text-gray-900 shrink-0">
                                        {{ applicant.firstname?.[0] || '' }}{{ applicant.lastname?.[0] || '' }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                            {{ applicant.firstname }} {{ applicant.lastname }}
                                        </h4>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm truncate">{{ applicant.email }}</p>
                                    </div>
                                </div>
                                <span :class="getStatusClass(applicant.status)" 
                                      class="px-3 py-1 rounded-full text-xs font-semibold shrink-0">
                                    {{ applicant.status || "Pending" }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Course</p>
                                    <p class="text-gray-900 dark:text-white font-medium">{{ applicant.application?.program?.code || "—" }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Applied</p>
                                    <p class="text-gray-900 dark:text-white font-medium">{{ formatDate(applicant.application?.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Empty state -->
                        <div v-if="displayedUsers.length === 0" class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-3 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400">No pending interviews</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Detail Modal -->
        <transition name="fade">
            <div v-if="selectedUser" class="fixed inset-0 z-50">
                <div class="fixed inset-0 bg-black/50" @click="closeUserCard"></div>

                <div class="relative min-h-screen flex items-center justify-center p-2 sm:p-4">
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
                        <!-- Modal Header -->
                        <div class="p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white truncate">Interview Details</h3>
                                    <p class="text-gray-600 dark:text-gray-400 text-sm">Application ID: {{ selectedUser.application?.id || 'N/A' }}</p>
                                </div>
                                <button @click="closeUserCard" class="flex-shrink-0 p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition min-h-[44px] min-w-[44px]">
                                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Modal Content -->
                        <div class="p-4 sm:p-6 overflow-y-auto flex-1">

                            <!-- Applicant Info Grid -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                            <!-- Personal Info -->
                            <div class="lg:col-span-2">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Applicant Information</h4>

                                <!-- Basic info row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                                        <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.firstname }} {{ selectedUser.lastname }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Email Address</p>
                                        <p class="text-gray-900 dark:text-white">{{ selectedUser.email }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">SHS Strand</p>
                                        <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.strand || "—" }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
                                        <span :class="getStatusClass(selectedUser.status)"
                                              class="px-3 py-1 rounded-full text-sm font-semibold inline-block">
                                            {{ selectedUser.status || "Pending" }}
                                        </span>
                                    </div>
                                    <div v-if="selectedUser.application?.requires_promissory_note" class="sm:col-span-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Special Requirements</p>
                                        <span class="px-3 py-1 rounded-full text-sm font-semibold inline-block bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                            📝 Promissory Note Required
                                        </span>
                                    </div>
                                </div>

                                <!-- Program choices row -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <!-- 1st Choice -->
                                    <div class="p-3 rounded-lg border border-[#9E122C]/30 bg-[#9E122C]/5 dark:bg-[#9E122C]/10">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-5 h-5 rounded-full bg-[#9E122C] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                                            <p class="text-xs font-semibold text-[#9E122C] dark:text-red-400 uppercase tracking-wide">1st Choice</p>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ selectedUser.application?.program?.name || "—" }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application?.program?.code || "" }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ selectedUser.application?.program?.slots || 0 }} slots remaining</p>
                                    </div>

                                    <!-- 2nd Choice -->
                                    <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !selectedUser.application?.second_choice }">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">2nd Choice</p>
                                        </div>
                                        <template v-if="selectedUser.application?.second_choice">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ selectedUser.application.second_choice.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application.second_choice.code }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ selectedUser.application.second_choice.slots || 0 }} slots remaining</p>
                                        </template>
                                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Not specified</p>
                                    </div>

                                    <!-- 3rd Choice -->
                                    <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !selectedUser.application?.third_choice }">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">3rd Choice</p>
                                        </div>
                                        <template v-if="selectedUser.application?.third_choice">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ selectedUser.application.third_choice.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application.third_choice.code }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ selectedUser.application.third_choice.slots || 0 }} slots remaining</p>
                                        </template>
                                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Not specified</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Program Selection for Accept/Reject -->
                            <div v-if="!selectedUser.is_evaluation_completed">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Select Your Program</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    Choose the program you are interviewing for:
                                </p>
                                <select
                                    v-model="selectedProgramId"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white mb-4 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                    :disabled="!interviewStartTime"
                                >
                                    <option disabled value="">Select Program</option>
                                    <option v-for="p in props.assignedPrograms" :key="p.id" :value="p.id">
                                        {{ p.code }} - {{ p.name }}
                                    </option>
                                </select>

                                <div v-if="interviewStartTime" class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comments/Notes (Optional)</label>
                                    <textarea
                                        v-model="interviewNotes"
                                        rows="3"
                                        placeholder="Add any additional notes or comments here..."
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent resize-none"
                                    ></textarea>
                                </div>

                                <div v-if="!interviewStartTime">
                                    <button
                                        @click="beginInterview"
                                        class="w-full px-4 py-2 rounded-lg font-medium transition flex items-center justify-center gap-2 bg-[#9E122C] text-white hover:bg-[#b51834]"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Begin Interview
                                    </button>
                                </div>
                                <div v-else>
                                    <div class="mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-lg text-sm flex items-center justify-between border border-blue-200 dark:border-blue-800">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Interview in progress since {{ new Date(interviewStartTime).toLocaleTimeString() }}
                                        </div>
                                        <button @click="cancelInterview" :disabled="isCancellingInterview" class="text-xs font-semibold hover:underline text-red-600 dark:text-red-400 disabled:opacity-50">
                                            {{ isCancellingInterview ? 'Cancelling...' : 'Cancel' }}
                                        </button>
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <button
                                            @click="promptAccept"
                                            :class="[getButtonClass('success'), 'flex-1 px-4 py-2 rounded-lg font-medium transition flex items-center justify-center gap-2']"
                                        >
                                            ✓ Accept
                                        </button>
                                        <button
                                            @click="promptReject"
                                            :class="[getButtonClass('danger'), 'flex-1 px-4 py-2 rounded-lg font-medium transition flex items-center justify-center gap-2']"
                                        >
                                            ✗ Reject
                                        </button>
                                    </div>
                                </div>

                                <Link
                                    :href="`/applications/user/${selectedUser.id}`"
                                    :class="[getButtonClass('secondary'), 'w-full px-4 py-2 rounded-lg transition font-medium text-center block mt-3']"
                                >
                                    View Full Details
                                </Link>
                            </div>

                            <!-- Interview Completed Summary -->
                            <div v-else>
                                <div class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 text-center">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Interview Completed</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                                        This interview has been completed.
                                    </p>
                                    <div class="border-t border-gray-300 dark:border-gray-600 my-6"></div>
                                    <div class="space-y-3 text-left">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Program:</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedUser.application?.program?.code || "—" }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Interviewer:</p>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ getInterviewerName() }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedUser.application?.requires_promissory_note" class="mt-6 pt-6 border-t border-gray-300 dark:border-gray-600">
                                        <div class="flex items-center justify-center gap-2 text-orange-700 dark:text-orange-300">
                                            <span class="text-sm font-medium">Promissory Note: Required</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- Grades Section -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Academic Grades</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser?.grades?.mathematics || "—" }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Science</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser?.grades?.science || "—" }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">English</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser?.grades?.english || "—" }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Uploaded Documents -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Required Documents</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                                    <div v-for="(file, key) in selectedUserFiles" :key="key" class="group relative">
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                            <div
                                                class="relative cursor-pointer"
                                                @click="hasImagePreview(file) ? openImageModal(file) : null"
                                            >
                                                <img
                                                    v-if="hasImagePreview(file)"
                                                    :src="getFileUrl(file)"
                                                    :alt="formatFileKey(key)"
                                                    class="w-full h-32 object-cover hover:opacity-90 transition pointer-events-none"
                                                />
                                                <div v-else class="w-full h-32 flex items-center justify-center bg-gray-50 dark:bg-gray-800">
                                                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="p-2 border-t border-gray-200 dark:border-gray-700">
                                                <p class="block text-xs font-medium text-gray-700 dark:text-gray-300 truncate" :title="formatFileKey(key)">
                                                    {{ formatFileKey(key) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Timeline -->
                            <div v-if="selectedUser?.application?.processes?.length">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Application Timeline</h4>
                                <div class="space-y-3">
                                    <div
                                        v-for="(process, index) in selectedUser.application.processes"
                                        :key="index"
                                        class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
                                    >
                                        <div :class="[
                                            'w-3 h-3 rounded-full mt-1.5 flex-shrink-0',
                                            process.status === 'completed' ? 'bg-green-500' :
                                            process.status === 'in_progress' ? 'bg-yellow-500' : 'bg-red-500'
                                        ]"></div>
                                        <div class="flex-1">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <p class="font-semibold text-gray-900 dark:text-white">{{ formatStage(process.stage) }}</p>
                                                    <p v-if="process.reviewer_notes" class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ process.reviewer_notes }}</p>
                                                </div>
                                                <span :class="[
                                                    'px-2 py-1 rounded-full text-xs font-semibold',
                                                    process.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' :
                                                    process.status === 'in_progress' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' :
                                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                                ]">
                                                    {{ capitalize(process.status) }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ formatDate(process.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Snackbar Notification -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                :class="[
                    'fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg z-50',
                    snackbar.type === 'success' ? 'bg-green-600 text-white' :
                    snackbar.type === 'error' ? 'bg-red-600 text-white' :
                    'bg-blue-600 text-white'
                ]"
            >
                {{ snackbar.message }}
            </div>
        </transition>
    </InterviewerLayout>

    <!-- Image Preview Modal (outside layout for proper z-index) -->
    <transition name="fade">
        <div v-if="showImageModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/80" @click="closeImageModal"></div>
            <div class="relative z-10 max-w-4xl max-h-[90vh]">
                <img
                    :src="previewImage"
                    alt="Document Preview"
                    class="max-w-full max-h-[80vh] rounded-lg shadow-2xl"
                />
                <button
                    @click="closeImageModal"
                    class="absolute top-4 right-4 p-2 bg-white/10 backdrop-blur-sm rounded-full hover:bg-white/20 transition dark:bg-gray-900/10 dark:hover:bg-gray-900/20 min-h-[44px] min-w-[44px]"
                >
                    <svg class="w-6 h-6 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </transition>

    <!-- Confirmation Modals -->
    <ChangesConfirmationModal
        :show="showAcceptModal"
        :loading="isSubmitting"
        title="Accept Application"
        subtitle="Accept this application to the selected program? This cannot be undone."
        confirm-text="Confirm Accept"
        confirm-button-class="bg-green-600 hover:bg-green-700 text-white"
        :hide-table="true"
        @cancel="showAcceptModal = false"
        @confirm="acceptApplication"
    />

    <ChangesConfirmationModal
        :show="showRejectModal"
        :loading="isSubmitting"
        title="Reject Application"
        subtitle="Reject this application for the selected program? This cannot be undone."
        confirm-text="Confirm Reject"
        confirm-button-class="bg-red-600 hover:bg-red-700 text-white"
        :hide-table="true"
        @cancel="showRejectModal = false"
        @confirm="rejectApplication"
    />

    <ChangesConfirmationModal
        :show="showCancelModal"
        :loading="isCancellingInterview"
        title="Cancel Interview"
        subtitle="Are you sure you want to cancel your current interview? Your progress will not be saved."
        confirm-text="Cancel Interview"
        confirm-button-class="bg-red-600 hover:bg-red-700 text-white"
        :hide-table="true"
        @cancel="showCancelModal = false"
        @confirm="confirmCancelInterview"
    />
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>