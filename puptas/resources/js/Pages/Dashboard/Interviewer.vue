<script setup>
import { ref, computed, onMounted } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link, router } from "@inertiajs/vue3";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";
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
});

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const selectedProgramId = ref("");
const requiresPromissoryNote = ref(false);
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
        
        await fetchPrograms();
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
        showSnackbar("Failed to load applicant data", "error");
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    selectedProgramId.value = "";
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

const acceptApplication = async () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to accept the applicant into", "error");
        return;
    }

    try {
        await axios.post(
            `/interviewer-dashboard/accept/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
                requires_promissory_note: requiresPromissoryNote.value,
            }
        );
        showSnackbar("Application accepted successfully", "success");
        selectedUser.value = null;
        selectedProgramId.value = "";
        requiresPromissoryNote.value = false;
        router.reload({ only: ['pendingUsers', 'summary'] });
    } catch (e) {
        console.error("Accept failed:", e);
        const msg = e.response?.data?.message || "Failed to accept application";
        showSnackbar(msg, "error");
    }
};

const rejectApplication = async () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to reject the applicant from", "error");
        return;
    }

    try {
        await axios.post(
            `/interviewer-dashboard/reject/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
            }
        );
        showSnackbar("Application rejected successfully", "success");
        selectedUser.value = null;
        selectedProgramId.value = "";
        router.reload({ only: ['pendingUsers', 'summary'] });
    } catch (e) {
        console.error("Reject failed:", e);
        const msg = e.response?.data?.message || "Failed to reject application";
        showSnackbar(msg, "error");
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
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Interviewer Dashboard</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Conduct interviews and evaluate applicant responses.</p>
                </div>
                <div class="relative w-full md:w-64">
                    <input
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
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Applications Overview</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Daily interview trends (Last 30 days)</p>
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

        <!-- Applicant Detail Modal (Side Panel like Admin) -->
        <transition name="fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-2/5 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl transition duration-300 ease-in-out overflow-y-auto"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Interview Details</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Application ID: {{ selectedUser.application?.id || 'N/A' }}</p>
                    </div>
                    <button @click="closeUserCard" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition min-h-[44px] min-w-[44px]">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Interview Completed Notice (Top) -->
                <div v-if="selectedUser.is_evaluation_completed" class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Interview Completed</p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                This applicant has already been processed at the interview stage.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Applicant Info Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Personal Info -->
                    <div class="lg:col-span-2">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Applicant Information</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                                <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.firstname }} {{ selectedUser.lastname }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Email Address</p>
                                <p class="text-gray-900 dark:text-white">{{ selectedUser.email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Current Program (1st Choice)</p>
                                <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.program?.name || "—" }}</p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.program?.code || "" }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">{{ selectedUser.application?.program?.slots || 0 }} slots remaining</p>
                            </div>
                            <div v-if="selectedUser.application?.second_choice">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Second Choice Program</p>
                                <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.second_choice?.name || "—" }}</p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.second_choice?.code || "" }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">{{ selectedUser.application?.second_choice?.slots || 0 }} slots remaining</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
                                <span :class="getStatusClass(selectedUser.status)" 
                                      class="px-3 py-1 rounded-full text-sm font-semibold inline-block">
                                    {{ selectedUser.status || "Pending" }}
                                </span>
                            </div>
                            <div v-if="selectedUser.application?.requires_promissory_note">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Special Requirements</p>
                                <span class="px-3 py-1 rounded-full text-sm font-semibold inline-block bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                    📝 Promissory Note Required
                                </span>
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
                        >
                            <option disabled value="">Select Program</option>
                            <option
                                v-for="p in props.assignedPrograms"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.code }} - {{ p.name }}
                            </option>
                        </select>

                        <!-- Promissory Note Checkbox -->
                        <div class="mb-4">
                            <label class="flex items-start space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="requiresPromissoryNote"
                                    class="mt-1 w-4 h-4 text-[#9E122C] border-gray-300 dark:border-gray-600 rounded focus:ring-[#9E122C] focus:ring-2"
                                />
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Requires Promissory Note</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Check if applicant is approved but lacks optional documents
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="flex space-x-2">
                            <button
                                @click="acceptApplication"
                                :class="[getButtonClass('success'), 'flex-1 px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                :disabled="!selectedProgramId"
                            >
                                ✓ Accept
                            </button>
                            <button
                                @click="rejectApplication"
                                :class="[getButtonClass('danger'), 'flex-1 px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                :disabled="!selectedProgramId"
                            >
                                ✗ Reject
                            </button>
                        </div>
                    </div>

                    <!-- Interview Decision Summary (for completed interviews) -->
                    <div v-else>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Interview Decision</h4>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Decision</span>
                                <span :class="getStatusClass(selectedUser.status)" 
                                      class="px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ selectedUser.status || "Processed" }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Assigned Program</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ selectedUser.application?.program?.code || "—" }}
                                </span>
                            </div>
                            <div v-if="selectedUser.application?.requires_promissory_note" class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-2 text-orange-700 dark:text-orange-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span class="text-xs font-medium">Promissory Note Required</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grades Section -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Academic Grades</h4>
                    <div class="grid grid-cols-3 gap-4">
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
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div
                            v-for="(file, key) in selectedUserFiles"
                            :key="key"
                            class="group relative"
                        >
                            <!-- Document Card -->
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
                                    <div
                                        v-else
                                        class="w-full h-32 flex items-center justify-center bg-gray-50 dark:bg-gray-800"
                                    >
                                        <svg class="w-8 h-8 text-gray-400 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Document Label -->
                                <div class="p-2 border-t border-gray-200 dark:border-gray-700">
                                    <p class="block text-xs font-medium text-gray-700 dark:text-gray-300 truncate" :title="formatFileKey(key)">
                                        {{ formatFileKey(key) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application History -->
                <div v-if="selectedUser?.application?.processes?.length">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Application Timeline</h4>
                    <div class="space-y-3">
                        <div
                            v-for="(process, index) in selectedUser.application.processes"
                            :key="index"
                            class="flex items-start space-x-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
                        >
                            <div :class="[
                                'w-3 h-3 rounded-full mt-1.5 flex-shrink-0',
                                process.status === 'completed' ? 'bg-green-500' :
                                process.status === 'in_progress' ? 'bg-yellow-500' :
                                'bg-red-500'
                            ]"></div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ capitalize(process.stage) }}
                                        </p>
                                        <p v-if="process.notes" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            {{ process.notes }}
                                        </p>
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
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    {{ formatDate(process.created_at) }}
                                </p>
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