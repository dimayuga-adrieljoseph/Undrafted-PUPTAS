<!-- resources/js/Components/ApplicationReviewModal.vue -->
<template>
    <!-- Paste the modal HTML here (everything inside v-if="showModal") -->
    <!-- Modal -->
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
    >
        <div
            class="bg-gradient-to-br from-orange-200 to-red-400 dark:bg-gray-900 rounded-lg shadow-lg max-w-5xl w-full p-6 overflow-auto max-h-[90vh] relative"
            style="min-width: 700px"
        >
            <button
                @click="closeModal"
                class="absolute top-3 right-3 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-3xl font-bold"
                aria-label="Close modal"
            >
                &times;
            </button>

            <template v-if="loading">
                <p>Loading application data...</p>
            </template>

            <template v-else-if="error">
                <p class="text-red-600 dark:text-red-400">
                    {{ error }}
                </p>
            </template>

            <template v-else-if="applicationData">
                <h2
                    class="text-xl font-bold mb-4 text-gray-900 dark:text-white"
                >
                    Review Your Application
                </h2>

                <div class="grid grid-cols-2 gap-8">
                    <!-- Left Column: Personal + High School Details -->
                    <div>
                        <section>
                            <h3
                                class="font-semibold mb-2 text-gray-800 dark:text-gray-300"
                            >
                                Personal Details
                            </h3>
                            <p>
                                <strong>Name:</strong>
                                {{ applicationData.firstname }}
                                {{ applicationData.middlename }}
                                {{ applicationData.lastname }}
                            </p>
                            <p>
                                <strong>Birthday:</strong>
                                {{ applicationData.birthday }}
                            </p>
                            <p>
                                <strong>Sex/Gender:</strong>
                                {{ applicationData.sex }}
                            </p>
                            <p>
                                <strong>Contact Number:</strong>
                                +63{{ applicationData.contactnumber }}
                            </p>
                            <p>
                                <strong>Address:</strong>
                                {{ applicationData.address }}
                            </p>
                            <p>
                                <strong>Email:</strong>
                                {{ applicationData.email }}
                            </p>
                        </section>

                        <section class="mt-6">
                            <h3
                                class="font-semibold mb-2 text-gray-800 dark:text-gray-300"
                            >
                                High School Details
                            </h3>
                            <p>
                                <strong>School:</strong>
                                {{ applicationData.school }}
                            </p>
                            <p>
                                <strong>School Address:</strong>
                                {{ applicationData.schoolAdd }}
                            </p>
                            <p>
                                <strong>School Year:</strong>
                                {{ applicationData.schoolyear }}
                            </p>
                            <p>
                                <strong>Date Graduated:</strong>
                                {{ applicationData.dateGrad }}
                            </p>
                            <p>
                                <strong>Strand:</strong>
                                {{ applicationData.strand }}
                            </p>
                            <p>
                                <strong>Track:</strong>
                                {{ applicationData.track }}
                            </p>
                        </section>
                        <div class="mt-4">
                            <label
                                for="programSelect"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Choose Your Program
                            </label>
                            <select
                                id="programSelect"
                                v-model="selectedProgramId"
                                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                            >
                                <option value="">-- Select Program --</option>
                                <option
                                    v-for="program in eligiblePrograms"
                                    :key="program.id"
                                    :value="program.id"
                                >
                                    {{ program.name }} ({{ program.code }})
                                </option>
                            </select>
                        </div>
                        <div class="mt-4">
                            <label
                                for="secondProgramSelect"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Choose Your Second Program
                            </label>
                            <select
                                id="secondProgramSelect"
                                v-model="secondChoiceId"
                                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
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
                        <h3
                            class="font-semibold mb-2 text-gray-800 dark:text-gray-300"
                        >
                            Uploaded Documents
                        </h3>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="image-wrapper">
                                <p>Grade 10 Front:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles
                                            .file10Front
                                    "
                                    :src="
                                        applicationData.uploadedFiles
                                            .file10Front?.url
                                    "
                                    alt="Grade 10 Front"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .file10Front
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>

                                <!-- Hidden File Input -->
                                <input
                                    type="file"
                                    ref="file10FrontInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="
                                        (e) => reuploadFile(e, 'file10Front')
                                    "
                                />

                                <!-- Reupload Button -->
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('file10Front')"
                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>Grade 10 Back:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles
                                            .file10_back
                                    "
                                    :src="
                                        applicationData.uploadedFiles
                                            .file10_back?.url
                                    "
                                    alt="Grade 10 Front"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .file10_back
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>

                                <!-- Hidden File Input -->
                                <input
                                    type="file"
                                    ref="fileInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="(e) => reuploadFile(e, 'file')"
                                />

                                <!-- Reupload Button -->
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('file')"
                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>Grade 11 Report:</p>
                                <img
                                    v-if="applicationData.uploadedFiles.file11"
                                    :src="
                                        applicationData.uploadedFiles.file11
                                            ?.url
                                    "
                                    alt="Grade 11 Report"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles.file11
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="file11Input"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="(e) => reuploadFile(e, 'file11')"
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('file11')"
                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>Grade 12 Report:</p>
                                <img
                                    v-if="applicationData.uploadedFiles.file12"
                                    :src="
                                        applicationData.uploadedFiles.file12
                                            ?.url
                                    "
                                    alt="Grade 12 Report"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles.file12
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="file12Input"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="(e) => reuploadFile(e, 'file12')"
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('file12')"
                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>School ID:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles.schoolId
                                    "
                                    :src="
                                        applicationData.uploadedFiles.schoolId
                                            ?.url
                                    "
                                    alt="School ID"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .schoolId
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="schoolIdInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="(e) => reuploadFile(e, 'schoolId')"
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('schoolId')"
                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>Certificate of Non-Enrollment:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles
                                            .nonEnrollCert
                                    "
                                    :src="
                                        applicationData.uploadedFiles
                                            .nonEnrollCert?.url
                                    "
                                    alt="Non-Enrollment Certificate"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .nonEnrollCert
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="nonEnrollCertInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="
                                        (e) => reuploadFile(e, 'nonEnrollCert')
                                    "
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('nonEnrollCert')"
                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>PSA Birth Certificate:</p>
                                <img
                                    v-if="applicationData.uploadedFiles.psa"
                                    :src="
                                        applicationData.uploadedFiles.psa?.url
                                    "
                                    alt="PSA"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles.psa
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="psaInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="(e) => reuploadFile(e, 'psa')"
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('psa')"

                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>Good Moral Certificate:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles.goodMoral
                                    "
                                    :src="
                                        applicationData.uploadedFiles.goodMoral
                                            ?.url
                                    "
                                    alt="Good Moral"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .goodMoral
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="goodMoralInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="
                                        (e) => reuploadFile(e, 'goodMoral')
                                    "
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('goodMoral')"

                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>Under Oath Document:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles.underOath
                                    "
                                    :src="
                                        applicationData.uploadedFiles.underOath
                                            ?.url
                                    "
                                    alt="Under Oath"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .underOath
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="underOathInput"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="
                                        (e) => reuploadFile(e, 'underOath')
                                    "
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('underOath')"

                                >
                                    Reupload
                                </button>
                            </div>

                            <div class="image-wrapper">
                                <p>2x2 Photo:</p>
                                <img
                                    v-if="
                                        applicationData.uploadedFiles.photo2x2
                                    "
                                    :src="
                                        applicationData.uploadedFiles.photo2x2
                                            ?.url
                                    "
                                    alt="2x2 Photo"
                                    class="max-h-20 object-contain border rounded"
                                    @click="
                                        openImageModal(
                                            applicationData.uploadedFiles
                                                .photo2x2
                                        )
                                    "
                                />
                                <div v-else class="placeholder">
                                    No Image Uploaded
                                </div>
                                <input
                                    type="file"
                                    ref="photo2x2Input"
                                    style="display: none"
                                    accept="image/*,.pdf"
                                    @change="(e) => reuploadFile(e, 'photo2x2')"
                                />
                                <button
                                    class="mt-1 px-2 py-1 bg-white/50 text-black text-xs rounded hover:bg-red-400"
                                    @click="triggerFileInput('photo2x2')"
                                >
                                    Reupload
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button
                        @click="closeModal"
                        class="px-4 py-2 bg-gray-400 rounded hover:bg-gray-500 text-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        :disabled="!canSubmit"
                        @click="submitApplication"
                        class="px-4 py-2 rounded text-white"
                        :class="{
                            'bg-gray-400 cursor-not-allowed': !canSubmit,
                            'bg-maroon-700 hover:bg-maroon-900': canSubmit,
                        }"
                    >
                        Submit Application
                    </button>
                    <p
                        v-if="applicationData?.status && !canSubmit"
                        class="text-sm text-gray-600 mt-2"
                    >
                        Application already
                        <strong>{{ applicationData.status }}</strong
                        >.
                    </p>
                </div>
            </template>

            <template v-else>
                <p>No application data found.</p>
            </template>
        </div>
    </div>
    <!-- Reopen Modal Icon -->
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
    <div
        v-if="showImageModal"
        class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50"
    >
        <img
            :src="previewImage"
            alt="Preview"
            class="max-w-full max-h-full rounded shadow-lg"
            @click="closeImageModal"
        />
        <button
            @click="closeImageModal"
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
        showSnackbar(
            "Failed to submit application. Choose programs before submitting."
        );
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

const openImageModal = (src) => {
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
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
.bg-maroon-700 {
    background-color: #800000;
}
.bg-maroon-900 {
    background-color: #660000;
}

/* Smaller placeholder styling */
.placeholder {
    background-color: #f3f4f6;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #d1d5db;
    color: #9ca3af;
    font-style: italic;
    font-size: 0.75rem;
    border-radius: 0.375rem;
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
    object-fit: cover; /* Crop and center image nicely */
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
    background-color: #fff;
}

.grid.grid-cols-2 {
    gap: 1rem;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Optional: cap modal scroll on smaller screens */
@media (max-width: 768px) {
    .image-wrapper img {
        width: 80px;
        height: 80px;
    }
}
</style>
