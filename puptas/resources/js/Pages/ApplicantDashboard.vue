<template>
    <ApplicantLayout title="Applicant Dashboard">
        <template #header>
            <h2
                class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight"
            >
                Applicant Dashboard
            </h2>
        </template>

        <!-- outer container: scrollable but scrollbar hidden -->
        <div class="py-12 overflow-y-auto scrollbar-hide">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white/25 dark:bg-gray-800 shadow-xl sm:rounded-lg p-6"
                >
                    <!-- Header & Status -->
                    <h1
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        YOUR APPLICATION TRACKER
                    </h1>
                    <div v-if="applicationStatus" class="mb-6">
                        <span class="font-semibold">Current Status:</span>
                        <span
                            :class="`ml-2 px-3 py-1 rounded-full text-white ${getBadgeClass(
                                applicationStatus
                            )}`"
                        >
                            {{ capitalize(applicationStatus) }}
                        </span>
                    </div>

                    <!-- ── Application Timeline ── -->
                    <div
                        v-if="applicationProcesses.length"
                        class="overflow-x-auto scrollbar-hide mb-8"
                    >
                        <div class="relative inline-block min-w-full py-4">
                            <!-- center line -->
                            <div
                                class="absolute inset-x-0 top-6 h-px bg-gray-300 dark:bg-gray-600"
                            ></div>
                            <!-- dots -->
                            <div
                                class="flex justify-between items-center relative"
                            >
                                <template
                                    v-for="(proc, idx) in applicationProcesses"
                                    :key="idx"
                                >
                                    <div
                                        class="flex flex-col items-center px-2"
                                    >
                                        <!-- colored dot -->
                                        <div
                                            :class="[
                                                'w-6 h-6 rounded-full border-2 flex items-center justify-center text-white text-xs',
                                                getBadgeClass(proc.status),
                                            ]"
                                        >
                                            {{ idx + 1 }}
                                        </div>
                                        <!-- details -->
                                        <div
                                            class="mt-2 text-xs text-center text-gray-700 dark:text-gray-300"
                                        >
                                            {{ formatStage(proc.stage) }}
                                        </div>
                                        <div
                                            class="text-xs text-center text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                formatTimestamp(proc.created_at)
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-center text-gray-500 dark:text-gray-400"
                                        >
                                            {{ proc.notes }}
                                        </div>
                                        <div
                                            class="text-xs text-center text-gray-500 dark:text-gray-400"
                                        >
                                            By:
                                            <span v-if="proc.performed_by">
                                                {{
                                                    proc.performed_by.firstname
                                                }}
                                                {{ proc.performed_by.lastname }}
                                            </span>
                                            <span v-else>System</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- ── Files Tracker ── -->
                    <div
                        v-if="stepKeys.length"
                        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 mb-8"
                    >
                        <div
                            v-for="(key, idx) in stepKeys"
                            :key="key"
                            class="flex flex-col items-center space-y-2"
                        >
                            <div
                                :class="[
                                    'w-6 h-6 rounded-full flex items-center justify-center text-white text-xs',
                                    getBadgeClass(fileStatuses[key]?.status),
                                ]"
                            >
                                {{ idx + 1 }}
                            </div>
                            <div
                                class="text-xs text-center text-gray-700 dark:text-gray-300"
                            >
                                {{ formatKey(key) }}
                            </div>
                            <div>
                                <img
                                    v-if="fileStatuses[key]?.url"
                                    :src="fileStatuses[key].url"
                                    alt="Preview"
                                    class="w-12 h-12 object-cover rounded cursor-pointer border"
                                    @click="
                                        openImageModal(fileStatuses[key].url)
                                    "
                                />
                                <button
                                    v-else
                                    @click="triggerFileInput(key)"
                                    class="text-blue-600 text-xs"
                                >
                                    Upload
                                </button>
                                <input
                                    type="file"
                                    :ref="(el) => (fileInputRefs[key] = el)"
                                    class="hidden"
                                    @change="reuploadFile($event, key)"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Loading / Empty / Error -->
                    <div v-if="loading" class="text-center py-6">
                        <span
                            class="animate-pulse text-gray-600 dark:text-gray-300"
                        >
                            Loading…
                        </span>
                    </div>
                    <div
                        v-else-if="!stepKeys.length"
                        class="text-center py-6 text-gray-500"
                    >
                        No files to display.
                    </div>
                    <div v-else-if="error" class="text-red-500">
                        {{ error }}
                    </div>
                </div>

                <ApplicationReviewModal
                    :show="showModal"
                    :userEmail="props.user.email"
                    @close="closeModal"
                    @refreshDashboard="fetchData"
                />
            </div>
        </div>

        <!-- Preview overlay for images -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 z-50 bg-black bg-opacity-80 flex items-center justify-center"
            @click="closeImageModal"
        >
            <img
                :src="previewSrc"
                alt="Preview"
                class="max-w-[90vw] max-h-[90vh] rounded shadow"
            />
            <button
                class="absolute top-5 right-5 text-white text-3xl font-bold"
                aria-label="Close preview"
                @click.stop="closeImageModal"
            >
                &times;
            </button>
        </div>
    </ApplicantLayout>
</template>

<script setup>
import { defineProps, ref, computed, onMounted } from "vue";
import axios from "axios";
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import ApplicationReviewModal from "@/Pages/Modal/ApplicationReviewModal.vue";

const props = defineProps({ user: Object });

// reactive state
const showModal = ref(false);
const loading = ref(false);
const error = ref("");
const fileStatuses = ref({});
const applicationStatus = ref("");
const applicationProcesses = ref([]);
const fileInputRefs = ref({});

// derived keys for file grid
const stepKeys = computed(() => Object.keys(fileStatuses.value));

// helpers
const formatKey = (key) => key.replace(/([A-Z])/g, " $1").toUpperCase();
const formatStage = (stage) =>
    stage.charAt(0).toUpperCase() + stage.slice(1).replace("_", " ");
const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1);
const formatTimestamp = (ts) =>
    ts
        ? new Date(ts).toLocaleString(undefined, {
              year: "numeric",
              month: "short",
              day: "numeric",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "";

const getBadgeClass = (status) => {
    switch ((status || "").toLowerCase()) {
        case "draft":
            return "bg-gray-400 border-gray-400";
        case "submitted":
            return "bg-blue-500 border-blue-500";
        case "in_progress":
            return "bg-yellow-500 border-yellow-500";
        case "completed":
            return "bg-green-500 border-green-500";
        case "returned":
            return "bg-red-500 border-red-500";
        default:
            return "bg-gray-500 border-gray-500";
    }
};

// initial data load
async function fetchData() {
    loading.value = true;
    error.value = "";
    try {
        const { data } = await axios.get("/user/application");
        fileStatuses.value = data.uploadedFiles || {};
        applicationStatus.value = data.status || "";
        applicationProcesses.value = data.processes || [];
    } catch {
        error.value = "Could not load application data.";
    } finally {
        loading.value = false;
    }
}

// trigger hidden file input
const triggerFileInput = (key) => fileInputRefs.value[key]?.click();

// reupload handler: update only the one file’s state immediately
const reuploadFile = async (e, key) => {
    const file = e.target.files[0];
    if (!file) return;

    loading.value = true;
    const form = new FormData();
    form.append("file", file);
    form.append("field", key);

    try {
        // your backend returns { message, url, status }
        const { data } = await axios.post("/user/application/reupload", form);

        // patch only that one entry
        fileStatuses.value[key] = {
            url: data.url,
            status: data.status,
        };
    } catch {
        alert("Failed to reupload file.");
    } finally {
        loading.value = false;
    }
};

// Preview state
const showImageModal = ref(false);
const previewSrc = ref("");

const openImageModal = (src) => {
    if (!src) return;
    previewSrc.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
    previewSrc.value = "";
};
const closeModal = () => (showModal.value = false);

onMounted(() => {
    fetchData();
    showModal.value = true;
});
</script>

<style scoped>
/* The hidden class is already applied to dashboard file inputs, so no global rule needed */
</style>
