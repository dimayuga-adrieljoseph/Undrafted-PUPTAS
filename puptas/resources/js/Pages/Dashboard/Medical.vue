<script setup>
import { ref, computed, onMounted } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link } from "@inertiajs/vue3";
import MedicalLayout from "@/Layouts/MedicalLayout.vue";
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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBolt, faFileMedical, faHeartbeat, faSyringe, faStethoscope } from "@fortawesome/free-solid-svg-icons";

const page = usePage();
const users = ref(page.props.users || []);

const props = defineProps({
    user: Object,
    allUsers: Array,
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            accepted: 0,
            pending: 0,
            returned: 0,
        }),
    },
});

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const medicalFindings = ref("");
const snackbar = ref({
    visible: false,
    message: "",
    type: "success",
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
        label: "Cleared", 
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

const chartData = {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
    datasets: [
        {
            label: "Submitted",
            data: [5, 20, 35, 50, 70, 90],
            borderColor: "#2563EB",
            backgroundColor: "rgba(37, 99, 235, 0.1)",
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#2563EB",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 4,
        },
        {
            label: "Cleared",
            data: [2, 10, 15, 25, 40, 60],
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
            label: "Pending Medical",
            data: [3, 7, 12, 18, 22, 25],
            borderColor: "#F59E0B",
            backgroundColor: "rgba(245, 158, 11, 0.1)",
            fill: true,
            tension: 0.4,
            pointBackgroundColor: "#F59E0B",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 4,
        },
    ],
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted" || s === "cleared") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
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

const fetchUsers = async () => {
    try {
        const response = await fetch("/medical-dashboard/applicants", {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json();
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchUsers();
});

const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return users.value;
    const query = searchQuery.value.toLowerCase();
    return users.value.filter((user) => {
        return (
            user.firstname?.toLowerCase().includes(query) ||
            user.lastname?.toLowerCase().includes(query) ||
            user.email?.toLowerCase().includes(query)
        );
    });
});

const displayedUsers = computed(() => {
    if (searchQuery.value.trim()) return filteredUsers.value;
    return users.value.slice(0, 5);
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/medical-dashboard/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
            },
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
        medicalFindings.value = "";
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
        showSnackbar("Failed to load applicant data", "error");
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    medicalFindings.value = "";
};

const formatFileKey = (key) => {
    const map = {
        file11: "Grade 11 Report",
        file12: "Grade 12 Report",
        schoolId: "School ID",
        nonEnrollCert: "Non-Enrollment Cert",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
        medical: "Medical Certificate",
        dental: "Dental Certificate",
        vaccine: "Vaccination Record",
    };
    return map[key] || key;
};

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (src) => {
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

const isEvaluating = ref(false);
const filesToReturn = ref({});
const returnNote = ref("");

const startEvaluation = () => {
    isEvaluating.value = true;
    filesToReturn.value = {};
    returnNote.value = "";
};

const cancelEvaluation = () => {
    isEvaluating.value = false;
    filesToReturn.value = {};
    returnNote.value = "";
};

const submitReturn = async () => {
    const selected = Object.keys(filesToReturn.value).filter(
        (k) => filesToReturn.value[k]
    );
    if (selected.length === 0 || !returnNote.value.trim()) {
        showSnackbar("Please select files and enter a return note", "error");
        return;
    }

    try {
        await axios.post(`/medical/return-files/${selectedUser.value.id}`, {
            files: selected,
            note: returnNote.value.trim(),
        });

        showSnackbar("Files returned successfully", "success");
        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";
        await fetchUsers();
        await selectUser(selectedUser.value);
    } catch (error) {
        console.error(error);
        showSnackbar("Return failed", "error");
    }
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

const clearMedical = async () => {
    try {
        await axios.post(`/medical-dashboard/accept/${selectedUser.value.id}`, {
            findings: medicalFindings.value
        });
        showSnackbar("Medical clearance completed", "success");
        selectedUser.value = null;
        await fetchUsers();
    } catch (e) {
        console.error("Medical clearance failed:", e);
        const msg = e.response?.data?.message || "Failed to clear medical requirements";
        showSnackbar(msg, "error");
    }
};
</script>

<template>
    <Head title="Medical Dashboard" />
    <MedicalLayout>
        <!-- Header Section -->
        <div class="px-4 md:px-8 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Medical Dashboard</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Review medical requirements and clear applicants.</p>
                </div>
                <div class="relative w-full md:w-64">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search applicants..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Medical Clearance Overview</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Monthly clearance trends</p>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#2563EB]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Submitted</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#10B981]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Cleared</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#F59E0B]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Pending Medical</span>
                        </div>
                    </div>
                    
                    <div class="h-80">
                        <LineChart :chart-data="chartData" :options="chartOptions" class="w-full h-full" />
                    </div>
                </div>
            </div>

            <!-- Right Column: Pending Medical Clearance -->
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Pending Medical</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Awaiting medical clearance</p>
                        </div>
                        <Link href="/medical-applications" 
                              class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition">
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
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-[#9E122C] rounded-full flex items-center justify-center text-white font-semibold">
                                        {{ applicant.firstname?.[0] || '' }}{{ applicant.lastname?.[0] || '' }}
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">
                                            {{ applicant.firstname }} {{ applicant.lastname }}
                                        </h4>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm">{{ applicant.email }}</p>
                                    </div>
                                </div>
                                <span :class="getStatusClass(applicant.status)" 
                                      class="px-3 py-1 rounded-full text-xs font-semibold">
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
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400">No pending medical clearances</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Clearance Modal -->
        <transition name="fade">
            <div v-if="selectedUser" class="fixed inset-0 z-50">
                <div class="fixed inset-0 bg-black/50" @click="closeUserCard"></div>
                
                <div class="relative min-h-screen flex items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Medical Clearance</h3>
                                    <p class="text-gray-600 dark:text-gray-400 text-sm">Application ID: {{ selectedUser.application?.id || 'N/A' }}</p>
                                </div>
                                <button @click="closeUserCard" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Modal Content -->
                        <div class="p-6 overflow-y-auto flex-1">
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
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Program</p>
                                            <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.program?.name || "—" }}</p>
                                            <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.program?.code || "" }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
                                            <span :class="getStatusClass(selectedUser.status)" 
                                                  class="px-3 py-1 rounded-full text-sm font-semibold inline-block">
                                                {{ selectedUser.status || "Pending" }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Medical Actions -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Medical Clearance</h4>
                                    
                                    <!-- Medical Findings -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Medical Findings / Notes
                                        </label>
                                        <textarea
                                            v-model="medicalFindings"
                                            rows="3"
                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                            placeholder="Enter medical findings or clearance notes..."
                                        ></textarea>
                                    </div>

                                    <div class="flex space-x-2">
                                        <button
                                            @click="clearMedical"
                                            :class="[getButtonClass('success'), 'flex-1 px-4 py-2 rounded-lg transition font-medium']"
                                        >
                                            Clear Medical
                                        </button>
                                        <button
                                            v-if="!isEvaluating"
                                            @click="startEvaluation"
                                            :class="[getButtonClass('secondary'), 'px-4 py-2 rounded-lg transition font-medium']"
                                        >
                                            <font-awesome-icon :icon="faBolt" class="mr-1" /> Actions
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Evaluation Section -->
                            <div v-if="isEvaluating" class="mb-8 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Return Documents</h4>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Return Reason / Note
                                    </label>
                                    <textarea
                                        v-model="returnNote"
                                        rows="2"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Reason for returning documents..."
                                    ></textarea>
                                </div>

                                <div class="flex space-x-2">
                                    <button
                                        @click="submitReturn"
                                        :class="[getButtonClass('danger'), 'flex-1 px-4 py-2 rounded-lg transition font-medium']"
                                    >
                                        Return Selected Files
                                    </button>
                                    <button
                                        @click="cancelEvaluation"
                                        :class="[getButtonClass('secondary'), 'px-4 py-2 rounded-lg transition font-medium']"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>

                            <!-- Uploaded Documents -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Medical Documents</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                    <div
                                        v-for="(src, key) in selectedUserFiles"
                                        :key="key"
                                        class="group relative"
                                    >
                                        <!-- Document Card -->
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                            <div class="relative">
                                                <img
                                                    v-if="src"
                                                    :src="src"
                                                    :alt="formatFileKey(key)"
                                                    class="w-full h-32 object-cover cursor-pointer hover:opacity-90 transition"
                                                    @click="openImageModal(src)"
                                                />
                                                <div
                                                    v-else
                                                    class="w-full h-32 flex items-center justify-center bg-gray-50 dark:bg-gray-800"
                                                >
                                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                                
                                                <!-- Checkbox overlay for evaluation mode -->
                                                <div v-if="isEvaluating && src" class="absolute top-2 left-2">
                                                    <input
                                                        type="checkbox"
                                                        :id="key"
                                                        v-model="filesToReturn[key]"
                                                        class="h-4 w-4 rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]"
                                                    />
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
                                                    <p v-if="process.performed_by" class="text-xs text-gray-500 dark:text-gray-500">
                                                        By: {{ process.performed_by.firstname }} {{ process.performed_by.lastname }}
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
                    </div>
                </div>
            </div>
        </transition>

        <!-- Image Preview Modal -->
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
                        class="absolute top-4 right-4 p-2 bg-white/10 backdrop-blur-sm rounded-full hover:bg-white/20 transition"
                    >
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
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
    </MedicalLayout>
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