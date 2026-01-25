<template>
  <ApplicantLayout title="Applicant Dashboard">
    <template #header>
      <h2 class="font-bold text-2xl text-gray-900 dark:text-gray-100">
        Applicant Dashboard
      </h2>
    </template>

    <div class="py-12 overflow-y-auto scrollbar-hide">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <!-- Status Card -->
        <div class="bg-white/30 dark:bg-gray-800 shadow-lg rounded-xl p-6 flex flex-col sm:flex-row sm:justify-between items-start sm:items-center gap-4">
          <div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
              Application Tracker
            </h3>
            <p class="text-gray-600 dark:text-gray-300 mt-1">
              Track your progress and uploaded documents.
            </p>
          </div>
          <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <div v-if="applicationStatus" class="flex items-center gap-2">
              <span class="font-medium text-gray-700 dark:text-gray-300">Application Status:</span>
              <span :class="`px-4 py-1 rounded-full text-white font-semibold ${getBadgeClass(applicationStatus)}`">
                {{ capitalize(applicationStatus) }}
              </span>
            </div>
            <div v-if="enrollmentStatus" class="flex items-center gap-2">
              <span class="font-medium text-gray-700 dark:text-gray-300">Enrollment Status:</span>
              <span :class="`px-4 py-2 rounded-full text-white font-semibold ${getEnrollmentBadgeClass(enrollmentStatus)}`">
                {{ capitalize(enrollmentStatus.replace(/_/g, " ")) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Application Timeline -->
        <div v-if="applicationProcesses.length" class="bg-white/20 dark:bg-gray-900 shadow-lg rounded-xl p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Application Timeline
          </h3>
          <div class="relative">
            <!-- Center Line -->
            <div class="absolute top-6 left-1/2 transform -translate-x-1/2 w-1 h-full bg-gray-300 dark:bg-gray-600"></div>
            <!-- Timeline Items -->
            <div class="space-y-12">
              <template v-for="(proc, idx) in applicationProcesses" :key="idx">
                <div class="flex items-start space-x-6 relative">
                  <div class="flex flex-col items-center">
                    <div :class="`w-8 h-8 rounded-full border-2 flex items-center justify-center text-white text-sm ${getBadgeClass(proc.status)}`">
                      {{ idx + 1 }}
                    </div>
                    <div class="mt-1 w-px h-full bg-gray-300 dark:bg-gray-600"></div>
                  </div>
                  <div class="flex-1">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                      {{ formatStage(proc.stage) }}
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                      {{ formatTimestamp(proc.created_at) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 italic">
                      {{ proc.notes || "No notes" }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                      By: 
                      <span v-if="proc.performed_by">
                        {{ proc.performed_by.firstname }} {{ proc.performed_by.lastname }}
                      </span>
                      <span v-else>System</span>
                    </p>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- Files Upload Grid -->
        <div v-if="stepKeys.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
          <template v-for="(key, idx) in stepKeys" :key="key">
            <div class="bg-white/30 dark:bg-gray-800 shadow-md rounded-xl p-4 flex flex-col items-center space-y-2 hover:scale-105 transition-transform">
              <div :class="`w-10 h-10 rounded-full flex items-center justify-center text-white text-sm ${getBadgeClass(fileStatuses[key]?.status)}`">
                {{ idx + 1 }}
              </div>
              <p class="text-xs text-center text-gray-700 dark:text-gray-300 font-medium">
                {{ formatKey(key) }}
              </p>
              <div class="mt-1">
                <img
                  v-if="fileStatuses[key]?.url"
                  :src="fileStatuses[key].url"
                  alt="Preview"
                  class="w-16 h-16 object-cover rounded-lg border cursor-pointer shadow-sm hover:shadow-lg transition-shadow"
                  @click="openImageModal(fileStatuses[key].url)"
                />
                <button
                  v-else
                  @click="triggerFileInput(key)"
                  class="text-blue-600 dark:text-blue-400 text-xs hover:underline"
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
          </template>
        </div>

        <!-- Loading / Empty / Error -->
        <div v-if="loading" class="text-center py-6 text-gray-600 dark:text-gray-300 animate-pulse">
          Loading…
        </div>
        <div v-else-if="!stepKeys.length" class="text-center py-6 text-gray-500 dark:text-gray-400">
          No files to display.
        </div>
        <div v-else-if="error" class="text-center text-red-500">
          {{ error }}
        </div>

        <!-- Modals -->
        <ApplicationReviewModal
          :show="showModal"
          :userEmail="props.user.email"
          @close="closeModal"
          @refreshDashboard="fetchData"
        />
      </div>
    </div>

    <!-- Image Preview Modal -->
    <div
      v-if="showImageModal"
      class="fixed inset-0 z-50 bg-black bg-opacity-80 flex items-center justify-center"
      @click="closeImageModal"
    >
      <img
        :src="previewSrc"
        alt="Preview"
        class="max-w-[90vw] max-h-[90vh] rounded shadow-lg"
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
const axios = window.axios;
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import ApplicationReviewModal from "@/Pages/Modal/ApplicationReviewModal.vue";

const props = defineProps({ user: Object });

const showModal = ref(false);
const loading = ref(false);
const error = ref("");
const fileStatuses = ref({});
const applicationStatus = ref("");
const enrollmentStatus = ref("");
const applicationProcesses = ref([]);
const fileInputRefs = ref({});
const showImageModal = ref(false);
const previewSrc = ref("");

const stepKeys = computed(() => Object.keys(fileStatuses.value));

const formatKey = (key) => key.replace(/([A-Z])/g, " $1").toUpperCase();
const formatStage = (stage) => stage.charAt(0).toUpperCase() + stage.slice(1).replace("_", " ");
const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1);
const formatTimestamp = (ts) => ts ? new Date(ts).toLocaleString(undefined, { year:"numeric", month:"short", day:"numeric", hour:"2-digit", minute:"2-digit" }) : "";

const getBadgeClass = (status) => {
  switch ((status || "").toLowerCase()) {
    case "draft": return "bg-gray-400 border-gray-400";
    case "submitted": return "bg-blue-500 border-blue-500";
    case "in_progress": return "bg-yellow-500 border-yellow-500";
    case "completed": return "bg-green-500 border-green-500";
    case "returned": return "bg-red-500 border-red-500";
    default: return "bg-gray-500 border-gray-500";
  }
};

const getEnrollmentBadgeClass = (status) => {
  switch ((status || "").toLowerCase()) {
    case "pending": return "bg-yellow-500 border-yellow-500";
    case "temporary": return "bg-yellow-500 border-yellow-500";
    case "officially_enrolled": return "bg-green-500 border-green-500";
    default: return "bg-gray-500 border-gray-500";
  }
};

const fetchData = async () => {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get("/user/application");
    fileStatuses.value = data.uploadedFiles || {};
    applicationStatus.value = data.status || "";
    enrollmentStatus.value = data.enrollment_status || "";
    applicationProcesses.value = data.processes || [];
  } catch {
    error.value = "Could not load application data.";
  } finally {
    loading.value = false;
  }
};

const triggerFileInput = (key) => fileInputRefs.value[key]?.click();
const reuploadFile = async (e, key) => {
  const file = e.target.files[0];
  if (!file) return;
  loading.value = true;
  const form = new FormData();
  form.append("file", file);
  form.append("field", key);
  try {
    const { data } = await axios.post("/user/application/reupload", form);
    fileStatuses.value[key] = { url: data.url, status: data.status };
  } catch {
    alert("Failed to reupload file.");
  } finally {
    loading.value = false;
  }
};

const openImageModal = (src) => { if(!src) return; previewSrc.value = src; showImageModal.value = true; };
const closeImageModal = () => { showImageModal.value = false; previewSrc.value = ""; };
const closeModal = () => (showModal.value = false);

onMounted(() => { fetchData(); showModal.value = true; });
</script>
