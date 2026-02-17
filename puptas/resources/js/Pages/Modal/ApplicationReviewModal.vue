<template>
    <!-- Modal -->
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"
        @click.self="closeModal"
    >
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-auto relative">
            <!-- Header -->
            <div class="sticky top-0 bg-white dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    Applicant Information
                </h2>
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 text-2xl"
                >
                    &times;
                </button>
            </div>

            <!-- Body -->
            <div class="p-6">
                <!-- Loading State -->
                <div v-if="loading" class="text-center py-8">
                    <p class="text-gray-600 dark:text-gray-400">Loading...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="text-center py-8">
                    <p class="text-red-600 dark:text-red-400">{{ error }}</p>
                </div>

                <!-- Content -->
                <div v-else-if="applicationData" class="space-y-6">
                    <!-- Status Badge -->
                    <div class="flex justify-end">
                        <span :class="`px-3 py-1 rounded-full text-sm font-medium text-white ${getStatusClass(applicationData.status)}`">
                            {{ formatStatus(applicationData.status) }}
                        </span>
                    </div>

                    <!-- Personal Information -->
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Personal Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Full Name</p>
                                <p class="font-medium">{{ applicationData.firstname }} {{ applicationData.middlename }} {{ applicationData.lastname }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Email</p>
                                <p class="font-medium">{{ applicationData.email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Birthday</p>
                                <p class="font-medium">{{ formatDate(applicationData.birthday) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Sex/Gender</p>
                                <p class="font-medium">{{ applicationData.sex }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Contact</p>
                                <p class="font-medium">{{ formatContact(applicationData.contactnumber) }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Address</p>
                                <p class="font-medium">{{ applicationData.address }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Educational Background -->
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Educational Background</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">School</p>
                                <p class="font-medium">{{ applicationData.school }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">School Address</p>
                                <p class="font-medium">{{ applicationData.schoolAdd }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">School Year</p>
                                <p class="font-medium">{{ applicationData.schoolyear }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Date Graduated</p>
                                <p class="font-medium">{{ formatDate(applicationData.dateGrad) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Strand</p>
                                <p class="font-medium">{{ applicationData.strand }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Track</p>
                                <p class="font-medium">{{ applicationData.track }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Program Choices - Show selection if not submitted, display if submitted -->
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Program Choices</h3>
                        
                        <!-- If application is draft and not yet submitted, show selection -->
                        <div v-if="canSubmit" class="space-y-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">First Choice <span class="text-red-500">*</span></label>
                                <select
                                    v-model="selectedProgramId"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-maroon-500 focus:border-maroon-500"
                                    :disabled="submitting"
                                >
                                    <option value="">Select a program</option>
                                    <option v-for="program in eligiblePrograms" :key="program.id" :value="program.id">
                                        {{ program.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Second Choice (Optional)</label>
                                <select
                                    v-model="selectedSecondChoiceId"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-maroon-500 focus:border-maroon-500"
                                    :disabled="submitting"
                                >
                                    <option value="">Select a program</option>
                                    <option v-for="program in filteredSecondChoicePrograms" :key="program.id" :value="program.id">
                                        {{ program.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- If already submitted, display the choices -->
                        <div v-else-if="applicationData.program_id" class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">First Choice</p>
                                <p class="font-medium">{{ getProgramName(applicationData.program_id) || 'Not selected' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Second Choice</p>
                                <p class="font-medium">{{ getProgramName(applicationData.second_choice_id) || 'Not selected' }}</p>
                            </div>
                        </div>

                        <!-- No program selected yet -->
                        <div v-else class="text-gray-500 dark:text-gray-400 text-sm">
                            No program choices selected yet.
                        </div>
                    </div>

                    <!-- Documents -->
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Uploaded Documents</h3>
                        <div class="grid grid-cols-4 gap-3">
                            <div v-for="(file, key) in applicationData.uploadedFiles" :key="key" class="text-center">
                                <div class="mb-1">
                                    <img
                                        v-if="file?.url"
                                        :src="file.url"
                                        :alt="formatFileName(key)"
                                        class="w-full h-16 object-cover rounded border cursor-pointer hover:opacity-75"
                                        @click="openImageModal(file)"
                                    />
                                    <div v-else class="w-full h-16 bg-gray-100 dark:bg-gray-800 rounded border border-dashed flex items-center justify-center">
                                        <span class="text-xs text-gray-400">No file</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 truncate">{{ formatFileName(key) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Error -->
                    <div v-if="submitError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                        <p class="text-red-600 dark:text-red-400 text-sm">{{ submitError }}</p>
                    </div>

                    <!-- Submit Success -->
                    <div v-if="submitSuccess" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                        <p class="text-green-600 dark:text-green-400 text-sm">{{ submitSuccess }}</p>
                    </div>
                </div>

                <!-- No Data -->
                <div v-else class="text-center py-8">
                    <p class="text-gray-600 dark:text-gray-400">No application data found.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 bg-white dark:bg-gray-900 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <button
                    @click="closeModal"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg"
                >
                    Close
                </button>
                
                <!-- Submit Button - Only show if can submit -->
                <button
                    v-if="canSubmit"
                    @click="submitApplication"
                    :disabled="!canSubmitApplication || submitting"
                    :class="[
                        'px-6 py-2 rounded-lg font-medium transition-all',
                        canSubmitApplication && !submitting
                            ? 'bg-maroon-700 hover:bg-maroon-800 text-white shadow-md hover:shadow-lg'
                            : 'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed'
                    ]"
                >
                    <span v-if="submitting" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submitting...
                    </span>
                    <span v-else>Submit Application</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div
        v-if="showImageModal"
        class="fixed inset-0 z-[60] bg-black bg-opacity-90 flex items-center justify-center"
        @click="closeImageModal"
    >
        <div class="relative max-w-3xl max-h-[90vh]">
            <img
                :src="previewImage"
                alt="Preview"
                class="max-w-full max-h-[90vh] object-contain"
                @click.stop
            />
            <button
                @click.stop="closeImageModal"
                class="absolute top-2 right-2 text-white text-3xl hover:text-gray-300"
            >
                &times;
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from "vue";

const props = defineProps({
    show: Boolean,
    userEmail: String,
});

const emit = defineEmits(["close", "refreshDashboard"]);

// State
const showModal = ref(false);
const loading = ref(false);
const error = ref("");
const applicationData = ref(null);
const eligiblePrograms = ref([]);
const previewImage = ref(null);
const showImageModal = ref(false);

// Submit state
const selectedProgramId = ref("");
const selectedSecondChoiceId = ref("");
const submitting = ref(false);
const submitError = ref("");
const submitSuccess = ref("");

// Computed: Check if application can be submitted (draft status)
const canSubmit = computed(() => {
    return applicationData.value?.status === 'draft' || !applicationData.value?.status;
});

// Computed: Check if all required documents are uploaded
const allDocumentsUploaded = computed(() => {
    if (!applicationData.value?.uploadedFiles) return false;
    const files = applicationData.value.uploadedFiles;
    return Object.values(files).every(file => file?.url);
});

// Computed: Check if application can be submitted (all conditions)
const canSubmitApplication = computed(() => {
    return canSubmit.value && 
           allDocumentsUploaded.value && 
           selectedProgramId.value &&
           eligiblePrograms.value.length > 0;
});

// Computed: Filter out first choice from second choice options
const filteredSecondChoicePrograms = computed(() => {
    if (!selectedProgramId.value) return eligiblePrograms.value;
    return eligiblePrograms.value.filter(p => p.id !== selectedProgramId.value);
});

// Watch for prop changes
watch(() => props.show, async (visible) => {
    showModal.value = visible;
    if (visible) {
        submitError.value = "";
        submitSuccess.value = "";
        await fetchApplicationData();
        await fetchEligiblePrograms();
    }
});

// Helper Functions
const formatStatus = (status) => {
    if (!status) return 'Unknown';
    return status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ');
};

const getStatusClass = (status) => {
    switch ((status || "").toLowerCase()) {
        case "approved": case "completed": return "bg-green-600";
        case "pending": case "submitted": return "bg-blue-600";
        case "in_progress": case "processing": return "bg-yellow-600";
        case "rejected": case "returned": return "bg-red-600";
        case "draft": return "bg-gray-600";
        default: return "bg-gray-500";
    }
};

const formatDate = (date) => {
    if (!date) return 'Not provided';
    return date;
};

const formatContact = (contact) => {
    if (!contact) return 'Not provided';
    return contact;
};

const formatFileName = (key) => {
    if (!key) return '';
    return key.replace(/([A-Z])/g, ' $1').replace(/_/g, ' ').replace(/^./, str => str.toUpperCase());
};

const getProgramName = (programId) => {
    if (!programId || !eligiblePrograms.value.length) return null;
    const program = eligiblePrograms.value.find(p => p.id === programId);
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
const submitApplication = async () => {
    if (!selectedProgramId.value) {
        submitError.value = "Please select a first choice program.";
        return;
    }

    submitting.value = true;
    submitError.value = "";
    submitSuccess.value = "";

    try {
        const payload = {
            program_id: selectedProgramId.value,
            second_choice_id: selectedSecondChoiceId.value || null,
        };

        const response = await window.axios.post("/user/application/submit", payload);
        
        submitSuccess.value = response.data.message || "Application submitted successfully!";
        
        // Update local application data
        if (applicationData.value) {
            applicationData.value.status = response.data.status;
            applicationData.value.program_id = selectedProgramId.value;
            applicationData.value.second_choice_id = selectedSecondChoiceId.value || null;
        }

        // Emit refresh event to parent
        emit("refreshDashboard");

        // Close modal after a short delay
        setTimeout(() => {
            closeModal();
        }, 1500);

    } catch (e) {
        const message = e.response?.data?.message || "Failed to submit application. Please try again.";
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
    const src = typeof fileObj === 'string' ? fileObj : fileObj?.url;
    if (!src) return;
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

<style scoped>
/* Custom maroon colors for PUP */
.bg-maroon-700 { background-color: #800000; }
.hover\:bg-maroon-800:hover { background-color: #660000; }
.focus\:ring-maroon-500:focus { --tw-ring-color: #800000; }
.focus\:border-maroon-500:focus { border-color: #800000; }

/* Loading animation */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>