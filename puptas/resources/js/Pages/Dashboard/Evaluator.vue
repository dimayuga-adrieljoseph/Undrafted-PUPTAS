<template>
    <Head title="Dashboard" />
    <EvaluatorLayout>
        <!-- Header -->
        <div class="px-4 md:px-8 mb-8">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Evaluator Dashboard</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Review and evaluate application submissions.</p>
            </div>
        </div>

        <!-- Stats Cards -->
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
                        index === 0 ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
                        index === 1 ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300' :
                        index === 2 ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300' :
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
                                index === 0 ? 'bg-blue-500' :
                                index === 1 ? 'bg-green-500' :
                                index === 2 ? 'bg-yellow-500' :
                                'bg-red-500'
                            ]"
                            :style="{ width: item.percentage + '%' }"
                        ></div>
                    </div>
                    <p class="text-right text-xs text-gray-500 dark:text-gray-400 mt-2">{{ item.percentage }}% of total</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8">
            <!-- Chart Section -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Applications Overview</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Monthly application trends</p>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#2563EB]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Submitted</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#10B981]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Accepted</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full bg-[#F59E0B]"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Returned</span>
                        </div>
                    </div>
                    
                    <div class="h-80">
                        <LineChart :chart-data="chartData" :options="chartOptions" class="w-full h-full" />
                    </div>
                </div>
            </div>

            <!-- Recent Applications -->
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Recent Applications</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Awaiting evaluation</p>
                        </div>
                        <Link href="/evaluator-applications" 
                              class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition">
                            View All
                        </Link>
                    </div>
                    
                    <div class="space-y-3">
                        <div
                            v-for="applicant in displayedApplicants"
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
                    </div>
                </div>
            </div>
        </div>

        <!-- User Detail Modal -->
        <transition name="fade">
            <div v-if="selectedUser" class="fixed inset-0 z-50">
                <div class="fixed inset-0 bg-black/50" @click="closeUserCard"></div>
                
                <div class="relative min-h-screen flex items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Applicant Evaluation</h3>
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
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Personal Info -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Applicant Information</h4>
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                                            <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.firstname }} {{ selectedUser.lastname }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Email Address</p>
                                            <p class="text-gray-900 dark:text-white">{{ selectedUser.email }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Course/Program</p>
                                            <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.program?.name || "—" }}</p>
                                            <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.program?.code || "" }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Current Status</p>
                                            <span :class="getStatusClass(selectedUser.status)" 
                                                  class="px-3 py-1 rounded-full text-sm font-semibold">
                                                {{ selectedUser.status || "Pending" }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Evaluation Controls -->
                                <div>
                                    <div v-if="!isEvaluating" class="flex space-x-3 mb-6">
                                        <button
                                            @click="startEvaluation"
                                            class="px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium flex-1"
                                        >
                                            Start Evaluation
                                        </button>
                                        <Link :href="`/applications/${selectedUser.application?.id || ''}`"
                                              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex-1 text-center">
                                            View Full Details
                                        </Link>
                                    </div>

                                    <!-- Return note textarea -->
                                    <div v-if="isEvaluating" class="mb-4">
                                        <label for="returnNote" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Return Reason / Note
                                        </label>
                                        <textarea
                                            id="returnNote"
                                            v-model="returnNote"
                                            rows="3"
                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                                            placeholder="Enter reason for returning documents..."
                                        ></textarea>
                                    </div>

                                    <!-- Submit buttons -->
                                    <div v-if="isEvaluating" class="flex space-x-3">
                                        <button
                                            @click="submitPass"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex-1"
                                        >
                                            Pass Application
                                        </button>
                                        <button
                                            @click="submitReturn"
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium flex-1"
                                        >
                                            Return Files
                                        </button>
                                        <button
                                            @click="cancelEvaluation"
                                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Uploaded Documents -->
                            <div class="mt-8">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Uploaded Documents</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    <div
                                        v-for="(src, key) in selectedUserFiles"
                                        :key="key"
                                        class="flex flex-col"
                                    >
                                        <div class="flex items-center space-x-2 mb-2">
                                            <input
                                                v-if="isEvaluating"
                                                type="checkbox"
                                                :id="key"
                                                v-model="filesToReturn[key]"
                                                class="h-4 w-4"
                                            />
                                            <label
                                                :for="key"
                                                class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate"
                                            >
                                                {{ formatFileKey(key) }}
                                            </label>
                                        </div>
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                            <img
                                                v-if="src"
                                                :src="src"
                                                alt="Document"
                                                class="w-full h-32 object-contain bg-gray-50 dark:bg-gray-800 cursor-pointer hover:opacity-90 transition"
                                                @click="openImageModal(src)"
                                            />
                                            <div
                                                v-else
                                                class="w-full h-32 flex items-center justify-center text-sm italic text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800"
                                            >
                                                No Document
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Application History -->
                            <div v-if="selectedUser?.application?.processes?.length" class="mt-8">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Application History</h4>
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
                                                <p class="font-semibold text-gray-900 dark:text-white">
                                                    {{ capitalize(process.stage) }}
                                                </p>
                                                <span :class="[
                                                    'px-2 py-1 rounded-full text-xs font-semibold',
                                                    process.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' :
                                                    process.status === 'in_progress' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' :
                                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                                ]">
                                                    {{ capitalize(process.status) }}
                                                </span>
                                            </div>
                                            <p v-if="process.notes" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                {{ process.notes }}
                                            </p>
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
    </EvaluatorLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link } from "@inertiajs/vue3";
import EvaluatorLayout from "@/Layouts/EvaluatorLayout.vue";
import { 
    Chart as ChartJS, 
    LineController, 
    LineElement, 
    CategoryScale, 
    LinearScale, 
    PointElement, 
    Tooltip, 
    Legend, 
    Filler 
} from "chart.js";

ChartJS.register(
    LineController, 
    LineElement, 
    CategoryScale, 
    LinearScale, 
    PointElement, 
    Tooltip, 
    Legend, 
    Filler
);

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

// State
const selectedUser = ref(null);
const selectedUserFiles = ref({});
const searchQuery = ref("");
const previewImage = ref(null);
const showImageModal = ref(false);
const isEvaluating = ref(false);
const filesToReturn = ref({});
const returnNote = ref("");

// Filter to get only applicants (users with applications)
const applicantsOnly = computed(() => {
    return (props.allUsers || []).filter(user => {
        // Check if user has an application
        return user.application && 
               user.application.id && 
               user.application.status !== undefined;
    });
});

// Summary items with icons and percentages
const summaryItems = computed(() => [
    { 
        label: "Total Applications", 
        value: props.summary?.total ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>' },
        percentage: 100
    },
    { 
        label: "Accepted", 
        value: props.summary?.accepted ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
        percentage: props.summary?.total > 0 ? Math.round((props.summary.accepted / props.summary.total) * 100) : 0
    },
    { 
        label: "Pending", 
        value: props.summary?.pending ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
        percentage: props.summary?.total > 0 ? Math.round((props.summary.pending / props.summary.total) * 100) : 0
    },
    { 
        label: "Returned", 
        value: props.summary?.returned ?? 0, 
        icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>' },
        percentage: props.summary?.total > 0 ? Math.round((props.summary.returned / props.summary.total) * 100) : 0
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

// Chart data
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
            label: "Accepted", 
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
    ],
};

// Display only applicants in the recent applications section
const displayedApplicants = computed(() => {
    const applicants = applicantsOnly.value;
    const query = searchQuery.value.trim().toLowerCase();
    
    if (!query) return applicants.slice(0, 5);
    
    return applicants.filter(applicant => 
        `${applicant.firstname} ${applicant.lastname}`.toLowerCase().includes(query) ||
        applicant.email?.toLowerCase().includes(query)
    );
});

// Methods
const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
};

const formatDate = (dateString) => {
    if (!dateString) return "—";
    return new Date(dateString).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
};

const formatFileKey = (key) => {
    const map = {
        file10Front: "Grade 10 Front",
        file11: "Grade 11 Report",
        file12: "Grade 12 Report",
        schoolId: "School ID",
        nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ') : "";

// User selection and file fetching
const selectUser = async (user) => {
    try {
        const response = await axios.get(`/dashboard/user-files/${user.id}`);

        selectedUser.value = {
            ...user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
            },
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    isEvaluating.value = false;
    filesToReturn.value = {};
    returnNote.value = "";
};

// Image modal
const openImageModal = (src) => {
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

// Evaluation controls
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
        alert("Please select files and enter a return note.");
        return;
    }

    try {
        await axios.post(`/dashboard/return-files/${selectedUser.value.id}`, {
            files: selected,
            note: returnNote.value.trim(),
        });

        alert("Files returned and application status logged.");
        closeUserCard();
        
        // Note: You might want to refresh the user list here
    } catch (error) {
        console.error(error);
        alert("Return failed.");
    }
};

const submitPass = async () => {
    try {
        await axios.post(
            `/evaluator/pass-application/${selectedUser.value.id}`,
            {
                note: returnNote.value || "",
            }
        );

        alert("Application successfully passed to the next step.");
        closeUserCard();
        
        // Note: You might want to refresh the user list here
    } catch (error) {
        console.error("Error passing application:", error);
        alert("Failed to pass application.");
    }
};
</script>

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