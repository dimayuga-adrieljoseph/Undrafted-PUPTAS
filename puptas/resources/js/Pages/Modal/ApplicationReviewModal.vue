<script setup>
import { ref, watch, onMounted, computed } from "vue";

const props = defineProps({
    show: Boolean,
    userEmail: String,
});

const emit = defineEmits(["close", "refreshDashboard"]);

// State
const showModal = ref(props.show || false);
const loading = ref(false);
const error = ref("");
const applicationData = ref(null);
const eligiblePrograms = ref([]);
const previewImage = ref(null);
const showImageModal = ref(false);
const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");
const hasImagePreview = (file) => Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

// Submit state
const selectedProgramId = ref("");
const selectedSecondChoiceId = ref("");
const selectedThirdChoiceId = ref("");
const submitting = ref(false);
const submitError = ref("");
const submitSuccess = ref("");
const showSubmitConfirmation = ref(false);

// Computed: Check if application can be submitted (draft status)
const canSubmit = computed(() => {
    return (
        applicationData.value?.status === "draft" ||
        !applicationData.value?.status
    );
});

// Computed: Check if application can be resubmitted (returned or rejected status, no rejected/returned files)
const canResubmit = computed(() => {
    if (!["returned", "rejected"].includes(applicationData.value?.status)) return false;
    const files = applicationData.value?.uploadedFiles || {};
    const hasUnresolvedFiles = Object.values(files).some(f => f?.status === 'rejected' || f?.status === 'returned');
    return !hasUnresolvedFiles;
});

// Computed: Check if all required documents are uploaded
const allDocumentsUploaded = computed(() => {
    if (!applicationData.value?.uploadedFiles) return false;
    const files = applicationData.value.uploadedFiles;
    return Object.values(files).every((file) => file?.url);
});

// Computed: Cutoff data from application response
const cutoff = computed(() => applicationData.value?.cutoff ?? null);
const cutoffPassed = computed(() => cutoff.value?.is_passed === true);
const cutoffDisplay = computed(() => cutoff.value?.display ?? null);

// Computed: Check if application can be submitted (all conditions)
const canSubmitApplication = computed(() => {
    if (cutoffPassed.value) return false;
    return (
        canSubmit.value &&
        allDocumentsUploaded.value &&
        selectedProgramId.value &&
        selectedSecondChoiceId.value &&
        selectedThirdChoiceId.value &&
        eligiblePrograms.value.length > 0
    );
});

// Watch for prop changes
watch(
    () => props.show,
    async (visible) => {
        showModal.value = visible;
        if (visible) {
            submitError.value = "";
            submitSuccess.value = "";
            await fetchApplicationData();
            await fetchEligiblePrograms();
        }
    }
);



// Helper Functions
const formatStatus = (status) => {
    if (!status) return "Unknown";
    return status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, " ");
};

const getStatusClass = (status) => {
    switch ((status || "").toLowerCase()) {
        case "approved":
        case "completed":
            return "bg-green-600";
        case "pending":
        case "submitted":
            return "bg-blue-600";
        case "in_progress":
        case "processing":
            return "bg-yellow-600";
        case "rejected":
        case "returned":
            return "bg-red-600";
        case "draft":
            return "bg-gray-600";
        default:
            return "bg-gray-500";
    }
};

const formatDate = (date) => {
    if (!date) return "Not provided";
    return date;
};

const formatContact = (contact) => {
    if (!contact) return "Not provided";
    return contact;
};

const formatFileName = (key) => {
    if (!key) return "";
    if (key === "file10") return "Grade 10 Report Back";
    if (key === "file10Front") return "Grade 10 Report Front";
    if (key === "file11") return "Grade 11 Report Back";
    if (key === "file11Front") return "Grade 11 Report Front";
    if (key === "file12") return "Grade 12 Report Back";
    if (key === "file12Front") return "Grade 12 Report Front";
    return key
        .replace(/([A-Z])/g, " $1")
        .replace(/_/g, " ")
        .replace(/^./, (str) => str.toUpperCase());
};

const getProgramName = (programId) => {
    if (!programId || !eligiblePrograms.value.length) return null;
    // Use == (loose) to handle potential string/integer type mismatch
    const program = eligiblePrograms.value.find((p) => p.id == programId);
    return program ? program.name : null;
};

// Data fetching
const fetchApplicationData = async () => {
    loading.value = true;
    error.value = "";
    try {
        const response = await window.axios.get("/user/application");
        applicationData.value = response.data;

        // Pre-populate program selections if they exist
        if (response.data.program_id) {
            selectedProgramId.value = response.data.program_id;
        }
        if (response.data.second_choice_id) {
            selectedSecondChoiceId.value = response.data.second_choice_id;
        }
        if (response.data.third_choice_id) {
            selectedThirdChoiceId.value = response.data.third_choice_id;
        }
    } catch (e) {
        error.value = "Failed to load application data.";
    } finally {
        loading.value = false;
    }
};

const fetchEligiblePrograms = async () => {
    try {
        const response = await window.axios.get("/user/eligible-programs");
        eligiblePrograms.value = response.data.programs || [];
    } catch (e) {
        console.error("Failed to load programs:", e);
    }
};

// Submit application
const openSubmitConfirmation = () => {
    if (!selectedProgramId.value) {
        submitError.value = "Please select a first choice program.";
        return;
    }
    if (!selectedSecondChoiceId.value) {
        submitError.value = "Please select a second choice program.";
        return;
    }
    if (!selectedThirdChoiceId.value) {
        submitError.value = "Please select a third choice program.";
        return;
    }
    submitError.value = "";
    showSubmitConfirmation.value = true;
};

const confirmAndSubmit = async () => {
    showSubmitConfirmation.value = false;
    await submitApplication();
};

const submitApplication = async () => {
    if (!selectedProgramId.value) {
        submitError.value = "Please select a first choice program.";
        return;
    }
    
    if (!selectedSecondChoiceId.value) {
        submitError.value = "Please select a second choice program.";
        return;
    }
    
    if (!selectedThirdChoiceId.value) {
        submitError.value = "Please select a third choice program.";
        return;
    }

    // Capture names NOW from all programs (not just filtered lists) before any state changes
    const allPrograms = eligiblePrograms.value;
    const findName = (id) => allPrograms.find((p) => p.id == id)?.name ?? null;
    const capturedFirstName  = findName(selectedProgramId.value);
    const capturedSecondName = findName(selectedSecondChoiceId.value);
    const capturedThirdName  = findName(selectedThirdChoiceId.value);

    submitting.value = true;
    submitError.value = "";
    submitSuccess.value = "";

    try {
        const payload = {
            program_id: selectedProgramId.value,
            second_choice_id: selectedSecondChoiceId.value,
            third_choice_id: selectedThirdChoiceId.value,
        };

        const response = await window.axios.post(
            "/user/application/submit",
            payload
        );

        submitSuccess.value =
            response.data.message || "Application submitted successfully!";

        // Update local application data using pre-captured names
        if (applicationData.value) {
            applicationData.value.status = response.data.status;
            applicationData.value.program_id = selectedProgramId.value ? Number(selectedProgramId.value) : null;
            applicationData.value.second_choice_id = selectedSecondChoiceId.value ? Number(selectedSecondChoiceId.value) : null;
            applicationData.value.third_choice_id = selectedThirdChoiceId.value ? Number(selectedThirdChoiceId.value) : null;
            applicationData.value.program_name       = capturedFirstName;
            applicationData.value.second_choice_name = capturedSecondName;
            applicationData.value.third_choice_name  = capturedThirdName;
        }

        // Emit refresh event to parent
        emit("refreshDashboard");

        // Close modal after a short delay
        setTimeout(() => {
            closeModal();
        }, 1500);
    } catch (e) {
        const message =
            e.response?.data?.message ||
            "Failed to submit application. Please try again.";
        submitError.value = message;
    } finally {
        submitting.value = false;
    }
};

// Resubmit application
const resubmitApplication = async () => {
    submitting.value = true;
    submitError.value = "";
    submitSuccess.value = "";

    try {
        const response = await window.axios.post("/user/application/resubmit");

        submitSuccess.value =
            response.data.message || "Application resubmitted for evaluation!";

        // Update local application data
        if (applicationData.value) {
            applicationData.value.status = response.data.status || "submitted";
        }

        // Emit refresh event to parent
        emit("refreshDashboard");

        // Close modal after a short delay
        setTimeout(() => {
            closeModal();
        }, 1500);
    } catch (e) {
        const message =
            e.response?.data?.message ||
            "Failed to resubmit application. Please try again.";
        submitError.value = message;
    } finally {
        submitting.value = false;
    }
};

// Modal functions
const closeModal = () => {
    showModal.value = false;
    emit("close");
};

const openImageModal = (fileObj) => {
    const src = getFileUrl(fileObj);
    if (!src || !hasImagePreview(fileObj)) return;
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
    previewImage.value = null;
};

// Make sure modal opens when prop is true on mount
onMounted(() => {
    if (props.show) {
        showModal.value = true;
        fetchApplicationData();
        fetchEligiblePrograms();
    }
});
</script>

<template>
    <!-- Modal Backdrop -->
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="closeModal"
    >
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-[#9E122C] text-white flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Application</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Review your submitted information</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        v-if="applicationData"
                        :class="`px-3 py-1 rounded-full text-xs font-semibold text-white ${getStatusClass(applicationData.status)}`"
                    >
                        {{ formatStatus(applicationData.status) }}
                    </span>
                    <button
                        @click="closeModal"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto px-6 py-5">

                <!-- Loading -->
                <div v-if="loading" class="flex items-center justify-center py-16">
                    <svg class="animate-spin h-8 w-8 text-[#9E122C]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <!-- Error -->
                <div v-else-if="error" class="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                </div>

                <!-- Content -->
                <div v-else-if="applicationData" class="space-y-6">

                    <!-- Returned Alert -->
                    <div v-if="applicationData.status === 'returned'" class="flex items-start gap-3 p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl">
                        <svg class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-orange-800 dark:text-orange-200">Application Returned</p>
                            <p class="text-xs text-orange-700 dark:text-orange-300 mt-1">
                                Please ensure you have addressed all the evaluator's remarks before resubmitting your application. If your grades were rejected, please double-check and correct them.
                            </p>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Personal Information</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Full Name</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ applicationData.firstname }} {{ applicationData.middlename }} {{ applicationData.lastname }}
                                </p>
                            </div>
                            <div class="col-span-2 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ applicationData.email }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Educational Background -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Educational Background</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">School Year</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ applicationData.schoolyear }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Date Graduated</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(applicationData.dateGrad) }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Strand</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ applicationData.strand }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Track</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ applicationData.track }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Program Choices -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Program Choices</h3>

                        <!-- Draft: show selects -->
                        <div v-if="canSubmit" class="space-y-3">
                            <!-- Program finality reminder -->
                            <div class="flex items-start gap-3 rounded-xl border-2 border-red-400 bg-red-50 p-3 dark:border-red-700 dark:bg-red-900/20">
                                <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                                    Your selected programs should already be final. Once submitted, changes may only be accommodated during the interview schedule.
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    First Choice <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="selectedProgramId"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C]"
                                    :disabled="submitting"
                                >
                                    <option value="">Select a program</option>
                                    <option
                                        v-for="program in eligiblePrograms"
                                        :key="program.id"
                                        :value="program.id"
                                        :disabled="program.id == selectedSecondChoiceId || program.id == selectedThirdChoiceId"
                                    >
                                        {{ program.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Second Choice <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="selectedSecondChoiceId"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C]"
                                    :disabled="submitting"
                                >
                                    <option value="">Select a program</option>
                                    <option
                                        v-for="program in eligiblePrograms"
                                        :key="program.id"
                                        :value="program.id"
                                        :disabled="program.id == selectedProgramId || program.id == selectedThirdChoiceId"
                                    >
                                        {{ program.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Third Choice <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="selectedThirdChoiceId"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C]"
                                    :disabled="submitting"
                                >
                                    <option value="">Select a program</option>
                                    <option
                                        v-for="program in eligiblePrograms"
                                        :key="program.id"
                                        :value="program.id"
                                        :disabled="program.id == selectedProgramId || program.id == selectedSecondChoiceId"
                                    >
                                        {{ program.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Submitted: display choices -->
                        <div v-else-if="applicationData.program_id" class="grid grid-cols-3 gap-3">
                            <!-- 1st Choice -->
                            <div class="p-3 rounded-xl border border-[#9E122C]/30 bg-[#9E122C]/5 dark:bg-[#9E122C]/10">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-5 h-5 rounded-full bg-[#9E122C] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                                    <p class="text-xs font-semibold text-[#9E122C] dark:text-red-400 uppercase tracking-wide">1st Choice</p>
                                </div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ applicationData.program_name || getProgramName(applicationData.program_id) || 'Not selected' }}</p>
                            </div>
                            <!-- 2nd Choice -->
                            <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !applicationData.second_choice_id }">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">2nd Choice</p>
                                </div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ applicationData.second_choice_name || getProgramName(applicationData.second_choice_id) || 'Not selected' }}</p>
                            </div>
                            <!-- 3rd Choice -->
                            <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !applicationData.third_choice_id }">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">3rd Choice</p>
                                </div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white leading-snug">{{ applicationData.third_choice_name || getProgramName(applicationData.third_choice_id) || 'Not selected' }}</p>
                            </div>
                        </div>

                        <p v-else class="text-sm text-gray-400 dark:text-gray-500 italic">No program choices selected yet.</p>
                    </div>

                    <!-- Documents -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Uploaded Documents</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <div
                                v-for="(file, key) in applicationData.uploadedFiles"
                                :key="key"
                                class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700"
                            >
                                <img
                                    v-if="hasImagePreview(file)"
                                    :src="getFileUrl(file)"
                                    :alt="formatFileName(key)"
                                    class="w-full h-20 object-cover rounded-lg cursor-pointer hover:opacity-80 transition mb-2"
                                    @click="openImageModal(file)"
                                />
                                <div
                                    v-else
                                    class="w-full h-20 flex items-center justify-center bg-gray-200 dark:bg-gray-700 rounded-lg mb-2"
                                >
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 text-center truncate">{{ formatFileName(key) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cutoff: closed banner (Requirement 4.2) -->
                    <div
                        v-if="cutoffPassed"
                        class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl"
                    >
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm font-semibold text-red-700 dark:text-red-300">The submission period has closed.</p>
                    </div>

                    <!-- Cutoff: deadline indicator (Requirement 4.1) -->
                    <div
                        v-else-if="cutoffDisplay"
                        class="flex items-start gap-3 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-300 dark:border-yellow-700 rounded-xl"
                    >
                        <svg class="w-5 h-5 text-yellow-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-300 uppercase tracking-wide mb-0.5">Submission Deadline:</p>
                            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ cutoffDisplay }}</p>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <div v-if="submitError" class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl">
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-red-600 dark:text-red-400">{{ submitError }}</p>
                    </div>
                    <div v-if="submitSuccess" class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl">
                        <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-green-600 dark:text-green-400">{{ submitSuccess }}</p>
                    </div>

                </div>

                <!-- No Data -->
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No application data found.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shrink-0">
                <button
                    @click="closeModal"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-lg transition"
                >
                    Close
                </button>

                <!-- Resubmit button (when application was returned or rejected) -->
                <div v-if="['returned', 'rejected'].includes(applicationData?.status)" class="flex items-center gap-3">
                    <span v-if="!canResubmit" class="text-xs text-red-500 dark:text-red-400 font-medium hidden sm:inline-block">
                        Please re-upload returned documents first
                    </span>
                    <button
                        @click="resubmitApplication"
                        :disabled="!canResubmit || submitting"
                        :title="!canResubmit ? 'Please re-upload all returned or rejected documents first.' : ''"
                        :class="[
                            'px-5 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2',
                            canResubmit && !submitting
                                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed',
                        ]"
                    >
                        <svg v-if="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        {{ submitting ? 'Resubmitting…' : 'Resubmit Application' }}
                    </button>
                </div>

                <!-- Submit button (when application is draft) -->
                <button
                    v-if="canSubmit"
                    @click="openSubmitConfirmation"
                    :disabled="!canSubmitApplication || submitting"
                    :class="[
                        'px-5 py-2 rounded-lg text-sm font-semibold transition',
                        canSubmitApplication && !submitting
                            ? 'bg-[#9E122C] hover:bg-[#7a0e22] text-white shadow-sm'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed',
                    ]"
                >
                    <span v-if="submitting" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting…
                    </span>
                    <span v-else>Submit Application</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div
        v-if="showImageModal"
        class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4"
        @click="closeImageModal"
    >
        <div class="relative max-w-3xl w-full">
            <img
                :src="previewImage"
                alt="Preview"
                class="w-full h-auto max-h-[85vh] object-contain rounded-xl shadow-2xl"
                @click.stop
            />
            <button
                @click.stop="closeImageModal"
                class="absolute top-3 right-3 p-2 bg-black/50 hover:bg-black/70 text-white rounded-full transition"
                aria-label="Close preview"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Submit Confirmation Modal -->
    <div
        v-if="showSubmitConfirmation"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        @click.self="showSubmitConfirmation = false"
    >
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-[#9E122C] dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Confirm Application Submission</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Please read carefully before proceeding</p>
                </div>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl">
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2">Submitted information is considered final.</p>
                    <p class="text-sm text-red-600 dark:text-red-400">
                        Changes after submission may only be accommodated during the interview schedule. Once submitted, your selected programs and personal information cannot be modified.
                    </p>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to submit your application? This action cannot be undone.
                </p>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-3 justify-end">
                <button
                    @click="showSubmitConfirmation = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-lg transition"
                >
                    Go Back
                </button>
                <button
                    @click="confirmAndSubmit"
                    :disabled="submitting"
                    class="px-5 py-2 text-sm font-semibold text-white bg-[#9E122C] hover:bg-[#7a0e22] rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <svg v-if="submitting" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ submitting ? 'Submitting…' : 'Yes, Submit Application' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Loading animation */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
