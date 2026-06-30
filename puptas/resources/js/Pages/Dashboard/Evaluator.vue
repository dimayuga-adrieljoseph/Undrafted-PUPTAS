<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link, router } from "@inertiajs/vue3";
import EvaluatorLayout from "@/Layouts/EvaluatorLayout.vue";
import ChangesConfirmationModal from "@/Components/ChangesConfirmationModal.vue";
import BlurText from "@/Components/BlurText.vue";
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
    pendingUsers: Array,
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
    },
    stage: {
        type: String,
        default: ''
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

// State
const selectedUser = ref(null);
const selectedUserFiles = ref({});
const searchQuery = ref("");
const previewImage = ref(null);
const showImageModal = ref(false);
const isEvaluating = ref(false);
const filesToReturn = ref({});
const returnNote = ref("");
const requiresPromissoryNote = ref(false);
const autoRefreshTimer = ref(null);
const POLL_INTERVAL_MS = 10000;

const currentStage = computed(() => props.stage || (props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator'));

const refreshDashboard = () => {
    router.reload({
        only: ["pendingUsers", "summary", "chartData"],
        preserveState: true,
        preserveScroll: true,
    });
};

onMounted(() => {
    autoRefreshTimer.value = setInterval(refreshDashboard, POLL_INTERVAL_MS);
});

onBeforeUnmount(() => {
    if (autoRefreshTimer.value) {
        clearInterval(autoRefreshTimer.value);
        autoRefreshTimer.value = null;
    }
});

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
    labels: props.chartData.labels || [],
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

// Display only pending applicants in the recent applications section
const displayedApplicants = computed(() => {
    const applicants = props.pendingUsers || [];
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
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
};

const getEvaluationStatusText = (pipelineStatus) => {
    switch (pipelineStatus) {
        case 'for_evaluation': return 'For Evaluation';
        case 'evaluation_returned': return 'Returned for Revision';
        case 'evaluation_passed': return 'Evaluation Passed';
        case 'for_interview': return 'For Interview';
        case 'interview_returned': return 'Returned for Revision';
        case 'interview_passed': return 'Interview Passed';
        case 'interview_transferred': return 'Course Transferred';
        case 'for_medical': return 'For Medical';
        case 'medical_cleared': return 'Medical Cleared';
        case 'medical_rejected': return 'Medical Rejected';
        case 'for_records': return 'For Records';
        case 'officially_enrolled': return 'Officially Enrolled';
        case 'rejected': return 'Rejected';
        case 'submitted': return 'Submitted';
        case 'returned': return 'Returned';
        default: return pipelineStatus ? (pipelineStatus.charAt(0).toUpperCase() + pipelineStatus.slice(1).replace(/_/g, ' ')) : 'Unknown';
    }
};

const getEvaluationStatusClass = (pipelineStatus) => {
    switch (pipelineStatus) {
        case 'for_evaluation': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'evaluation_returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'evaluation_passed': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'for_interview': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'interview_returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'interview_passed': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'interview_transferred': return 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300';
        case 'for_medical': return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'medical_cleared': return 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-300';
        case 'medical_rejected': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'for_records': return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300';
        case 'officially_enrolled': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300';
        case 'rejected': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'submitted': return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        default: return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }
};

const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) return false;
    const targetStage = props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const evaluatorProcess = selectedUser.value.application.processes.find(p => p.stage === targetStage);
    return evaluatorProcess && evaluatorProcess.status === 'completed';
});

const hasStartedReview = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) return false;
    const targetStage = props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const evaluatorProcess = selectedUser.value.application.processes.find(p => p.stage === targetStage);
    return evaluatorProcess && !!evaluatorProcess.started_at;
});

const reviewStartTime = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) return null;
    const targetStage = props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const evaluatorProcess = selectedUser.value.application.processes.find(p => p.stage === targetStage);
    return evaluatorProcess?.started_at || null;
});

const isStartingReview = ref(false);
const isCancellingReview = ref(false);
const showCancelModal = ref(false);

const cancelReview = () => {
    showCancelModal.value = true;
};

const confirmCancelReview = async () => {

    const targetStage = props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const processes = selectedUser.value.application.processes;
    const processIndex = processes.findIndex(p => p.stage === targetStage);

    if (processIndex === -1) return;

    const evaluatorProcess = processes[processIndex];
    isCancellingReview.value = true;
    try {
        await axios.post(`/evaluator/cancel-review/${evaluatorProcess.id}`);
        // Rebuild selectedUser.value to clear started_at
        selectedUser.value = {
            ...selectedUser.value,
            application: {
                ...selectedUser.value.application,
                processes: processes.map((p, i) =>
                    i === processIndex ? { ...p, started_at: null, reviewed_by: null } : p
                ),
            },
        };
        showToast("Review cancelled.", "info");
        showCancelModal.value = false;
    } catch (error) {
        showToast(error.response?.data?.message || "Failed to cancel review.", "error");
    } finally {
        isCancellingReview.value = false;
    }
};

const startReview = async () => {
    const targetStage = props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const processes = selectedUser.value.application.processes;
    const processIndex = processes.findIndex(p => p.stage === targetStage);
    
    if (processIndex === -1) {
        showToast("Error finding application process.", "error");
        return;
    }

    const evaluatorProcess = processes[processIndex];
    isStartingReview.value = true;
    try {
        const response = await axios.post(`/evaluator/start-review/${evaluatorProcess.id}`);
        // Rebuild selectedUser.value to trigger Vue reactivity
        selectedUser.value = {
            ...selectedUser.value,
            application: {
                ...selectedUser.value.application,
                processes: processes.map((p, i) =>
                    i === processIndex ? { ...p, started_at: response.data.started_at } : p
                ),
            },
        };
        showToast("Review started successfully.");
    } catch (error) {
        if (error.response?.status === 409) {
            // Already started — re-fetch user to sync UI with DB state
            try {
                const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}?stage=${currentStage.value}`);
                const userData = refetch.data.user;
                selectedUser.value = {
                    ...selectedUser.value,
                    ...userData,
                    application: {
                        ...userData.application,
                        processes: userData.application?.processes || [],
                        program: userData.application?.program || null,
                        second_choice: userData.application?.second_choice || null,
                        third_choice: userData.application?.third_choice || null,
                    },
                    grades: userData.grades || null,
                };
                showToast("Review was already started.");
            } catch (refetchErr) {
                console.error("Refetch failed:", refetchErr);
            }
        } else {
            console.error("Error starting review:", error);
            showToast(error.response?.data?.message || "Failed to start review.", "error");
        }
    } finally {
        isStartingReview.value = false;
    }
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

const formatDate = (dateString) => {
    if (!dateString) return "—";
    return new Date(dateString).toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
};

const formatStage = (stage) => {
    if (!stage) return 'Unknown Stage';
    return stage
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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

const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");

const hasImagePreview = (file) =>
    Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ') : "";

// User selection and file fetching
const selectUser = async (user) => {
    try {
        const response = await axios.get(`/dashboard/user-files/${user.id}?stage=${currentStage.value}`);

        const userData = response.data.user;
        selectedUser.value = {
            ...user,
            ...userData,
            application: {
                ...userData.application,
                processes: userData.application?.processes || [],
                program: userData.application?.program || null,
                second_choice: userData.application?.second_choice || null,
                third_choice: userData.application?.third_choice || null,
            },
            grades: userData.grades || null,
            pipeline_status: user.pipeline_status,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        alert("An error occurred while loading the applicant profile. Please check the Developer Console (F12) for details. Error: " + error.message);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    isEvaluating.value = false;
    filesToReturn.value = {};
    returnNote.value = "";
    refreshDashboard();
};

// Image modal
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

const evaluationError = ref("");

const returnNoteCharCount = computed(() => {
    return returnNote.value.length;
});

// Evaluation controls
const startEvaluation = () => {
    isEvaluating.value = true;
    evaluationError.value = "";
    filesToReturn.value = {};
    returnNote.value = "";
};

const cancelEvaluation = () => {
    isEvaluating.value = false;
    filesToReturn.value = {};
    returnNote.value = "";
};

const isSubmitting = ref(false);

const promptReturn = () => {
    const selected = Object.keys(filesToReturn.value).filter((k) => filesToReturn.value[k]);
    if (props.user?.role_id !== 3 && !returnNote.value.trim()) {
        evaluationError.value = "Please provide a reject reason.";
        showToast("Please provide a reject reason.", "error");
        return;
    } else if (props.user?.role_id !== 8 && selected.length === 0 && !returnNote.value.trim()) {
        evaluationError.value = "Please select at least one file or provide a return reason.";
        showToast("Please select at least one file or provide a return reason.", "error");
        return;
    }
    showReturnModal.value = true;
};

const submitReturn = async () => {
    evaluationError.value = "";
    const note = returnNote.value.trim();

    if (returnNoteCharCount.value > 400) {
        evaluationError.value = "The reason cannot exceed 400 characters.";
        showReturnModal.value = false;
        return;
    }

    isSubmitting.value = true;
    try {
        const targetStageForApi = props.stage || (props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator');
        if (props.user?.role_id !== 3) {
            await axios.post(`/evaluator/flag-application/${selectedUser.value.id}?stage=${targetStageForApi}`, {
                note: returnNote.value,
                requires_promissory_note: requiresPromissoryNote.value,
                requires_admission_office: true
            });
            showToast("Applicant flagged for Admissions Office!");
        } else {
            await axios.post(`/evaluator/flag-application/${selectedUser.value.id}?stage=${targetStageForApi}`, {
                note: returnNote.value,
                requires_promissory_note: requiresPromissoryNote.value,
                requires_guidance_office: true
            });
            showToast("Applicant flagged for Guidance Office!");
        }

        showReturnModal.value = false;
        closeUserCard();
    } catch (error) {
        console.error(error);
        showReturnModal.value = false;
        const msg = error.response?.data?.message || error.response?.data?.errors?.note?.[0];
        evaluationError.value = msg || "Action failed. Please try again.";
    } finally {
        isSubmitting.value = false;
    }
};

const submitPass = async () => {
    evaluationError.value = "";
    isSubmitting.value = true;
    try {
        const targetStageForApi = props.stage || (props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator');
        await axios.post(
            `/evaluator/pass-application/${selectedUser.value.id}?stage=${targetStageForApi}`,
            {
                note: ""
            }
        );

        showPassModal.value = false;
        closeUserCard();
        showToast("Applicant passed successfully!");
    } catch (error) {
        console.error("Error passing application:", error);
        showPassModal.value = false;
        evaluationError.value = error.response?.data?.message || "Failed to pass application.";
    } finally {
        isSubmitting.value = false;
    }
};

const showPassModal = ref(false);
const showReturnModal = ref(false);

// Toast notification state
const toastMessage = ref('');
const toastType = ref('success');
const toastVisible = ref(false);
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toastMessage.value = message;
    toastType.value = type;
    toastVisible.value = true;
    toastTimeout = setTimeout(() => {
        toastVisible.value = false;
    }, 3000);
};
</script>

<template>
    <Head :title="currentStage === 'document_evaluator' ? 'Document Evaluator Dashboard' : 'Grade Evaluator Dashboard'" />
    <EvaluatorLayout>
        <!-- Success Toast Notification -->
        <transition enter-active-class="transition ease-out duration-300" enter-from-class="transform opacity-0 translate-y-[-1rem]" enter-to-class="transform opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-from-class="transform opacity-100 translate-y-0" leave-to-class="transform opacity-0 translate-y-[-1rem]">
            <div v-if="toastVisible" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md px-4">
                <div :class="['rounded-lg shadow-lg overflow-hidden border', toastType === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-500 dark:border-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-500 dark:border-red-400']">
                    <div class="p-4 flex items-start">
                        <svg v-if="toastType === 'success'" class="w-5 h-5 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p :class="['text-sm font-medium', toastType === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200']">{{ toastMessage }}</p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button @click="toastVisible = false" :class="['rounded-md inline-flex focus:outline-none', toastType === 'success' ? 'text-green-500 hover:text-green-600' : 'text-red-500 hover:text-red-600']">
                                <span class="sr-only">Close</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Header Section -->
        <div class="px-4 md:px-8 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <BlurText
                        :text="currentStage === 'document_evaluator' ? 'Document Evaluator Dashboard' : 'Grade Evaluator Dashboard'"
                        :delay="100"
                        animate-by="words"
                        direction="top"
                        class-name="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"
                    />
                    <BlurText
                        text="Review and evaluate application submissions."
                        :delay="60"
                        animate-by="words"
                        direction="top"
                        :step-duration="0.3"
                        class-name="text-gray-600 dark:text-gray-400 mt-2"
                    />
                </div>
                <div class="flex items-center space-x-3">
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
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Daily evaluation trends</p>
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
                class="absolute right-0 mt-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl z-10 w-72 origin-top-right transition-all"
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
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Pending Review</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Applications awaiting evaluation</p>
                        </div>
                        <Link href="/evaluator-applications" 
                              class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition dark:text-white dark:hover:text-white">
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
                                <span :class="getStatusClass(applicant.application?.status)" 
                                      class="px-3 py-1 rounded-full text-xs font-semibold shrink-0">
                                    {{ applicant.application?.status || "Pending" }}
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
                        <div v-if="displayedApplicants.length === 0" class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-3 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400">No pending applications</p>
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
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Application Review</h3>
                                    <p class="text-gray-600 dark:text-gray-400 text-sm">Application ID: {{ selectedUser.application?.id || 'N/A' }}</p>
                                </div>
                                <button @click="closeUserCard" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition min-h-[44px] min-w-[44px]">
                                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                                    <!-- Basic info row -->
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                                            <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.firstname }} {{ selectedUser.lastname }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Email Address</p>
                                            <p class="text-gray-900 dark:text-white">{{ selectedUser.email }}</p>
                                        </div>
                                        <div class="col-span-2">
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Current Status</p>
                                            <span :class="getEvaluationStatusClass(selectedUser.pipeline_status || selectedUser.application?.status)"
                                                  class="px-3 py-1 rounded-full text-sm font-semibold inline-block">
                                                {{ getEvaluationStatusText(selectedUser.pipeline_status || selectedUser.application?.status) }}
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

                                <!-- Quick Actions -->
                                <div v-if="!isEvaluationCompleted">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Actions</h4>
                                    
                                    <div v-if="!hasStartedReview" class="mb-4">
                                        <div class="space-y-3">
                                            <button
                                                @click="startReview"
                                                :disabled="isStartingReview"
                                                class="w-full px-4 py-2 bg-[#9E122C] hover:bg-[#800918] text-white rounded-lg transition font-medium min-h-[44px] flex justify-center items-center disabled:opacity-50"
                                            >
                                                <svg v-if="isStartingReview" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span>{{ isStartingReview ? 'Starting...' : 'Begin Review' }}</span>
                                            </button>
                                            <Link :href="`/applications/user/${selectedUser.id}?context=evaluator&stage=${currentStage}`"
                                                  :class="[getButtonClass('secondary'), 'w-full px-4 py-2 rounded-lg transition font-medium text-center block']">
                                                View Full Details
                                            </Link>
                                        </div>
                                    </div>
                                    
                                    <div v-else>
                                        <div class="mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-lg text-sm flex items-center justify-between border border-blue-200 dark:border-blue-800">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Review in progress since {{ new Date(reviewStartTime).toLocaleTimeString() }}
                                            </div>
                                            <button @click="cancelReview" :disabled="isCancellingReview" class="text-xs font-semibold hover:underline text-red-600 dark:text-red-400 disabled:opacity-50">
                                                {{ isCancellingReview ? 'Cancelling...' : 'Cancel' }}
                                            </button>
                                        </div>
                                        <div class="space-y-3">
                                            <button
                                                v-if="!isEvaluating"
                                                @click="startEvaluation"
                                                :class="[getButtonClass('danger'), 'w-full px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                            >
                                                {{ user?.role_id === 3 ? 'Go to Guidance Office' : 'Go to Admissions Office' }}
                                            </button>
                                            <button
                                                v-if="!isEvaluating"
                                                @click="showPassModal = true"
                                                :class="[getButtonClass('success'), 'w-full px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                            >
                                                Pass Application
                                            </button>
                                            <Link :href="`/applications/user/${selectedUser.id}?context=evaluator&stage=${currentStage}`"
                                                  :class="[getButtonClass('secondary'), 'w-full px-4 py-2 rounded-lg transition font-medium text-center block']">
                                                View Full Details
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Promissory Note Badge -->
                            <div v-if="selectedUser?.application?.requires_promissory_note"
                                class="flex items-start gap-3 p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl mb-5">
                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-orange-700 dark:text-orange-300">Requires Promissory Note</p>
                                    <p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">This applicant has been tagged to require a Promissory Note.</p>
                                </div>
                            </div>

                            <!-- Evaluation Completed Badge -->
                            <div v-if="isEvaluationCompleted"
                                class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl mb-5">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Evaluation Completed</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">You have already evaluated this application. Actions are no longer available.</p>
                                </div>
                            </div>

                            <!-- Evaluation Section -->
                            <div v-if="isEvaluating" class="mb-8 p-6 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-xl">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ user?.role_id === 3 ? 'Guidance Office Referral' : 'Admissions Office Referral' }}</h4>
                                <p class="text-sm text-amber-700 dark:text-amber-400 mb-4">{{ user?.role_id === 3 ? 'Provide a reason for sending this applicant to the Guidance Office.' : 'Provide a reason for sending this applicant to the Admissions Office.' }}</p>
                                
                                <div v-if="evaluationError" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded relative" role="alert">
                                    <span class="block sm:inline">{{ evaluationError }}</span>
                                </div>
                                
                                <!-- Return Note -->
                                <div class="mb-4">
                                    <label for="returnNote" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reason <span class="text-red-500 dark:text-red-300">*</span>
                                    </label>
                                    <textarea
                                        id="returnNote"
                                        name="returnNote"
                                        v-model="returnNote"
                                        rows="3"
                                        maxlength="400"
                                        :class="[
                                            'w-full border rounded-lg p-3 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent',
                                            returnNoteCharCount > 400 ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]'
                                        ]"
                                        placeholder="Explain what the applicant needs to do..."
                                    ></textarea>
                                    <div class="text-right mt-1 mb-2">
                                        <span :class="{'text-red-500': returnNoteCharCount > 400, 'text-gray-500': returnNoteCharCount <= 400}" class="text-xs">
                                            {{ returnNoteCharCount }} / 400 characters
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 mb-3 px-1">
                                        <input type="checkbox" id="promissoryNoteDashboard" v-model="requiresPromissoryNote" 
                                            class="w-4 h-4 text-[#9E122C] bg-white border-gray-300 rounded focus:ring-[#9E122C] dark:focus:ring-[#9E122C] dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                                        <label for="promissoryNoteDashboard" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Require Promissory Note
                                        </label>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex space-x-3">
                                    <button 
                                        @click="promptReturn"
                                        class="flex-1 px-4 py-2 bg-[#9E122C] hover:bg-[#800918] text-white text-sm font-semibold rounded-lg transition"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        @click="cancelEvaluation"
                                        :class="[getButtonClass('secondary'), 'px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                    >
                                        Cancel
                                    </button>
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
                                            <div class="relative">
                                                <img
                                                    v-if="hasImagePreview(file)"
                                                    :src="getFileUrl(file)"
                                                    :alt="formatFileKey(key)"
                                                    class="w-full h-32 object-cover cursor-pointer hover:opacity-90 transition"
                                                    @click="openImageModal(file)"
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
                                            <div class="bg-gray-50 dark:bg-gray-800 p-3 border-t border-gray-200 dark:border-gray-700">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate block">
                                                    {{ formatFileKey(key) }}
                                                </span>
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
                                            process.action === 'rejected' ? 'bg-red-500' :
                                            process.status === 'completed' ? 'bg-green-500' :
                                            process.status === 'in_progress' ? 'bg-yellow-500' :
                                            'bg-red-500'
                                        ]"></div>
                                        <div class="flex-1">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <p class="font-semibold text-gray-900 dark:text-white">
                                                        {{ formatStage(process.stage) }}
                                                    </p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                        {{ process.reviewer_notes || 'No notes provided' }}
                                                    </p>
                                                </div>
                                                <span :class="[
                                                    'px-2 py-1 rounded-full text-xs font-semibold',
                                                    process.action === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' :
                                                    process.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' :
                                                    process.status === 'in_progress' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' :
                                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                                ]">
                                                    {{ process.action === 'rejected' ? 'Rejected' : capitalize(process.status) }}
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
                        class="absolute top-4 right-4 p-2 bg-white/10 backdrop-blur-sm rounded-full hover:bg-white/20 transition dark:bg-gray-900/10 dark:hover:bg-gray-900/20 min-h-[44px] min-w-[44px]"
                    >
                        <svg class="w-6 h-6 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </transition>

        <ChangesConfirmationModal
            :show="showPassModal"
            title="Pass Application"
            subtitle="Pass this application to the next stage? This cannot be undone."
            confirmText="Confirm Pass"
            confirmButtonClass="bg-green-600 hover:bg-green-700 text-white"
            :hideTable="true"
            :loading="isSubmitting"
            @confirm="submitPass"
            @cancel="showPassModal = false"
        />

        <ChangesConfirmationModal
            :show="showReturnModal"
            title="Return Application"
            subtitle="Return this application to the applicant for corrections? This cannot be undone."
            confirmText="Confirm Return"
            confirmButtonClass="bg-red-600 hover:bg-red-700 text-white"
            :hideTable="true"
            :loading="isSubmitting"
            @confirm="submitReturn"
            @cancel="showReturnModal = false"
        />

        <ChangesConfirmationModal
            :show="showCancelModal"
            :loading="isCancellingReview"
            title="Cancel Review"
            subtitle="Are you sure you want to cancel your current review? Your progress will not be saved."
            confirm-text="Cancel Review"
            confirm-button-class="bg-red-600 hover:bg-red-700 text-white"
            :hide-table="true"
            @cancel="showCancelModal = false"
            @confirm="confirmCancelReview"
        />
    </EvaluatorLayout>
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