<template>
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"
    >
        <div
            class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl max-w-5xl w-full p-6 overflow-auto max-h-[90vh] relative"
            style="min-width: 700px"
        >
            <!-- Close Button -->
            <button
                @click="closeModal"
                class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white text-3xl font-bold"
                aria-label="Close modal"
            >
                &times;
            </button>

            <!-- Loading / Error / Content -->
            <template v-if="loading">
                <p class="text-gray-700">Loading application data...</p>
            </template>

            <template v-else-if="error">
                <p class="text-red-600 dark:text-red-400">{{ error }}</p>
            </template>

            <template v-else-if="applicationData">
                <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">
                    Review Your Application
                </h2>

                <div class="grid grid-cols-2 gap-8">
                    <!-- Left Column: Personal + High School -->
                    <div>
                        <section class="mb-6">
                            <h3 class="font-semibold mb-2 text-gray-800 dark:text-gray-300">Personal Details</h3>
                            <p><strong>Name:</strong> {{ applicationData.firstname }} {{ applicationData.middlename }} {{ applicationData.lastname }}</p>
                            <p><strong>Birthday:</strong> {{ applicationData.birthday }}</p>
                            <p><strong>Sex/Gender:</strong> {{ applicationData.sex }}</p>
                            <p><strong>Contact:</strong> +63{{ applicationData.contactnumber }}</p>
                            <p><strong>Address:</strong> {{ applicationData.address }}</p>
                            <p><strong>Email:</strong> {{ applicationData.email }}</p>
                        </section>

                        <section class="mb-6">
                            <h3 class="font-semibold mb-2 text-gray-800 dark:text-gray-300">High School Details</h3>
                            <p><strong>School:</strong> {{ applicationData.school }}</p>
                            <p><strong>School Address:</strong> {{ applicationData.schoolAdd }}</p>
                            <p><strong>School Year:</strong> {{ applicationData.schoolyear }}</p>
                            <p><strong>Date Graduated:</strong> {{ applicationData.dateGrad }}</p>
                            <p><strong>Strand:</strong> {{ applicationData.strand }}</p>
                            <p><strong>Track:</strong> {{ applicationData.track }}</p>
                        </section>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Choose Your Program</label>
                            <select
                                v-model="selectedProgramId"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-maroon-500 focus:border-maroon-500"
                            >
                                <option value="">-- Select Program --</option>
                                <option v-for="program in eligiblePrograms" :key="program.id" :value="program.id">
                                    {{ program.name }} ({{ program.code }})
                                </option>
                            </select>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Choose Your Second Program</label>
                            <select
                                v-model="secondChoiceId"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-maroon-500 focus:border-maroon-500"
                            >
                                <option value="">-- Select Program --</option>
                                <option
                                    v-for="program in eligiblePrograms"
                                    :key="program.id"
                                    :value="program.id"
                                    :disabled="program.id === selectedProgramId"
                                >
                                    {{ program.name }} ({{ program.code }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Right Column: Uploaded Documents -->
                    <section>
                        <h3 class="font-semibold mb-2 text-gray-800 dark:text-gray-300">Uploaded Documents</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <div v-for="(file, key) in applicationData.uploadedFiles" :key="key" class="image-wrapper">
                                <p class="text-sm font-medium text-gray-700 mb-1">{{ formatFileName(key) }}</p>
                                <img
                                    v-if="file"
                                    :src="file?.url"
                                    alt="Uploaded Document"
                                    class="max-h-20 object-cover rounded border border-gray-300 cursor-pointer hover:scale-105 transition-transform duration-200"
                                    @click="openImageModal(file)"
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <button
                                    class="mt-1 px-3 py-1 bg-gray-100 hover:bg-maroon-100 text-gray-800 rounded text-xs shadow-sm"
                                    @click="triggerFileInput(key)"
                                >
                                    Reupload
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end space-x-4 mt-6">
                    <button
                        @click="closeModal"
                        class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 text-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        :disabled="!canSubmit"
                        @click="submitApplication"
                        class="px-4 py-2 rounded-lg text-white"
                        :class="{
                            'bg-gray-400 cursor-not-allowed': !canSubmit,
                            'bg-maroon-700 hover:bg-maroon-900': canSubmit,
                        }"
                    >
                        Submit Application
                    </button>
                    <p v-if="applicationData?.status && !canSubmit" class="text-sm text-gray-600 mt-2">
                        Application already <strong>{{ applicationData.status }}</strong>.
                    </p>
                </div>
            </template>

            <template v-else>
                <p class="text-gray-700">No application data found.</p>
            </template>
        </div>
    </div>
    <button
        v-if="!showModal"
        @click="openModal"
        aria-label="Open Application Modal"
        class="fixed bottom-8 right-8 bg-maroon-700 hover:bg-maroon-900 text-white p-8 rounded-full shadow-lg focus:outline-none"
        title="Review Application"
    >
        <!-- You can replace this SVG with any icon you prefer -->
        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"
            />
        </svg>
    </button>
    <!-- End Reopen Modal Icon -->
    
    <!-- Image Preview Overlay -->
    <div
        v-if="showImageModal"
        class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-[60]"
        @click="closeImageModal"
    >
        <div class="relative max-w-[90vw] max-h-[90vh]">
            <img
                :src="previewImage"
                alt="Preview"
                class="max-w-full max-h-[90vh] object-contain rounded shadow-lg"
                @click.stop
                @error="(e) => console.error('Image failed to load:', previewImage)"
            />
        </div>
        <button
            @click.stop="closeImageModal"
            class="absolute top-5 right-5 text-white text-4xl font-bold hover:text-gray-300"
            aria-label="Close preview"
        >
            &times;
        </button>
    </div>
    
    <!-- Snackbar -->
    <transition name="fade">
        <div
            v-if="snackbar.visible"
            class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded shadow-lg z-50"
        >
            {{ snackbar.message }}
        </div>
    </transition>
</template>

<script setup>
/* Your existing script stays the same */
const formatFileName = (key) => {
    return key
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, str => str.toUpperCase())
        .replace(/_/g, ' ');
};

import { defineProps, defineEmits, ref, watch, onMounted, computed } from "vue";
const axios = window.axios;
import { nextTick } from "vue";

const props = defineProps({
    show: Boolean,
    userEmail: String,
});

const emit = defineEmits(["close", "reupload"]);

watch(
    () => props.show,
    async (visible) => {
        if (visible) await fetchApplicationData();
    }
);

const selectedProgramId = ref("");
const secondChoiceId = ref("");

const showModal = ref(false);
const loading = ref(false);
const error = ref("");
const applicationData = ref(null);
const snackbar = ref({
    visible: false,
    message: "",
});

const showSnackbar = (msg, duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.visible = true;
    setTimeout(() => {
        snackbar.value.visible = false;
    }, duration);
};

const canSubmit = computed(() => {
    const status = applicationData.value?.status;
    return !status || ["draft", "returned"].includes(status);
});

const fetchApplicationData = async () => {
    loading.value = true;
    error.value = "";
    try {
        const response = await axios.get("/user/application");
        applicationData.value = response.data;

        console.log("Application status:", applicationData.value?.status);
        await fetchEligiblePrograms();

        console.log("Setting selected:", response.data.program_id);

        nextTick(() => {
            selectedProgramId.value = response.data.program_id ?? "";
            secondChoiceId.value = response.data.second_choice_id ?? "";
        });
    } catch (e) {
        error.value = "Failed to load application data.";
    } finally {
        loading.value = false;
    }
};

const openModal = () => {
    showModal.value = true;
    fetchApplicationData();
};

const closeModal = () => {
    showModal.value = false;
};

const submitApplication = async () => {
    try {
        await axios.post("/user/application/submit", {
            program_id: selectedProgramId.value,
            second_choice_id: secondChoiceId.value || null,
        });
        await fetchApplicationData(); // Refresh local modal data
        emit("refreshDashboard"); // 🔥 Tell parent to update dashboard
        showSnackbar("Application submitted!");

        // Optional delay to keep modal open briefly before closing
        setTimeout(() => {
            closeModal();
        }, 1000);
    } catch (e) {
        const errorMsg = e.response?.data?.message || e.response?.data?.errors?.program_id?.[0] || "Failed to submit application. Choose programs before submitting.";
        showSnackbar(errorMsg);
        console.error('Submit error:', e.response?.data);
    }
};

// Open modal on page load
onMounted(() => {
    openModal();
    fileInputRefs.value = {
        file10Front: null,
        file11: null,
        file12: null,
        schoolId: null,
        nonEnrollCert: null,
        psa: null,
        goodMoral: null,
        underOath: null,
        photo2x2: null,
    };
});

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (fileObj) => {
    // Handle both string URLs and file objects { url, status }
    const src = typeof fileObj === 'string' ? fileObj : fileObj?.url;
    
    console.log('openImageModal called with:', fileObj);
    console.log('Extracted URL:', src);
    
    if (!src) {
        console.warn('No valid URL found');
        return;
    }
    
    // Show image in overlay
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
    previewImage.value = null;
};

const fileInputRefs = ref({});

const file10FrontInput = ref(null);
const fileInput = ref(null);
const file11Input = ref(null);
const file12Input = ref(null);
const schoolIdInput = ref(null);
const nonEnrollCertInput = ref(null);
const psaInput = ref(null);
const goodMoralInput = ref(null);
const underOathInput = ref(null);
const photo2x2Input = ref(null);

const triggerFileInput = (key) => {
    const inputRefs = {
        file10Front: file10FrontInput,
        file: fileInput,
        file11: file11Input,
        file12: file12Input,
        schoolId: schoolIdInput,
        nonEnrollCert: nonEnrollCertInput,
        psa: psaInput,
        goodMoral: goodMoralInput,
        underOath: underOathInput,
        photo2x2: photo2x2Input,
    };

    const inputRef = inputRefs[key];
    if (inputRef && inputRef.value) {
        inputRef.value.click();
    }
};

const reuploadFile = async (event, fieldName) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("file", file);
    formData.append("field", fieldName);

    try {
        const response = await axios.post("/user/application/reupload", formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        showSnackbar("File reuploaded!");

        await fetchApplicationData(); // refresh view with new file
    } catch (err) {
        console.error("Reupload error:", err.response?.data || err);
        showSnackbar(err.response?.data?.message || "Failed to reupload file.");
    }
};

const eligiblePrograms = ref([]);

const fetchEligiblePrograms = async () => {
    try {
        const response = await axios.get("/user/eligible-programs");
        eligiblePrograms.value = response.data.programs;
    } catch (e) {
        console.error("Failed to load eligible programs");
    }
};
</script>

<style scoped>
/* Modern minor redesign styles */
.bg-maroon-700 { background-color: #800000; }
.bg-maroon-900 { background-color: #660000; }

.placeholder {
    background-color: #f9fafb;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed #d1d5db;
    color: #9ca3af;
    font-style: italic;
    font-size: 0.75rem;
    border-radius: 0.5rem;
    text-align: center;
    padding: 0 4px;
}

.image-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.image-wrapper img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    background-color: #fff;
    cursor: pointer;
    transition: transform 0.2s;
}

.image-wrapper img:hover {
    transform: scale(1.05);
}

.grid.grid-cols-2 {
    gap: 1.25rem;
}
</style>

