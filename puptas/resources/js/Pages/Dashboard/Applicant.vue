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
const showImageModal = ref(false);
const previewSrc = ref("");
const uploadingKeys = ref([]);
const fileUploadProgress = ref({});
const uploadErrors = ref({});

// File statuses come directly from the backend
const stepKeys = computed(() => Object.keys(fileStatuses.value));

const uploadedCount = computed(() => {
  return Object.values(fileStatuses.value).filter(f => f?.url).length;
});

const uploadProgressPercentage = computed(() => {
  if (!stepKeys.value.length) return 0;
  return (uploadedCount.value / stepKeys.value.length) * 100;
});

const formatKey = (key) => key.replace(/([A-Z])/g, " $1").toUpperCase();
const formatStage = (stage) => stage.charAt(0).toUpperCase() + stage.slice(1).replace("_", " ");
const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1);
const formatTimestamp = (ts) => ts ? new Date(ts).toLocaleString(undefined, { year:"numeric", month:"short", day:"numeric", hour:"2-digit", minute:"2-digit" }) : "";

const getStatusShort = (status) => {
  switch ((status || "").toLowerCase()) {
    case "approved": case "completed": return "OK";
    case "pending": case "submitted": return "PD";
    case "in_progress": case "processing": return "IP";
    case "rejected": case "returned": return "RJ";
    case "draft": return "DR";
    default: return "PD";
  }
};

const getStatusIconBg = (status) => {
  switch ((status || "").toLowerCase()) {
    case "approved": case "completed": return "bg-green-600";
    case "pending": case "submitted": return "bg-blue-600";
    case "in_progress": case "processing": return "bg-yellow-600";
    case "rejected": case "returned": return "bg-red-600";
    case "draft": return "bg-gray-600";
    default: return "bg-gray-600";
  }
};

const getEnrollmentIconBg = (status) => {
  switch ((status || "").toLowerCase()) {
    case "pending": return "bg-yellow-600";
    case "temporary": return "bg-blue-600";
    case "officially_enrolled": return "bg-green-600";
    default: return "bg-gray-600";
  }
};

const getBadgeClass = (status) => {
  switch ((status || "").toLowerCase()) {
    case "approved": case "completed": return "bg-green-500";
    case "pending": case "submitted": return "bg-blue-500";
    case "in_progress": case "processing": return "bg-yellow-500";
    case "rejected": case "returned": return "bg-red-500";
    case "draft": return "bg-gray-400";
    default: return "bg-gray-500";
  }
};

const getEnrollmentBadgeClass = (status) => {
  switch ((status || "").toLowerCase()) {
    case "pending": return "bg-yellow-500";
    case "temporary": return "bg-yellow-500";
    case "officially_enrolled": return "bg-green-500";
    default: return "bg-gray-500";
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

const triggerFileInput = (key) => {
  const input = document.getElementById('file-input-' + key);
  if (input) {
    input.value = '';
    input.click();
  }
};

const reuploadFile = async (e, key) => {
  const file = e.target.files[0];
  if (!file) return;

  const maxSize = 5 * 1024 * 1024;
  if (file.size > maxSize) {
    uploadErrors.value[key] = "File size must be less than 5MB";
    setTimeout(() => {
      delete uploadErrors.value[key];
    }, 5000);
    return;
  }

  const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
  if (!validTypes.includes(file.type)) {
    uploadErrors.value[key] = "Please upload an image or PDF file";
    setTimeout(() => {
      delete uploadErrors.value[key];
    }, 5000);
    return;
  }

  uploadingKeys.value.push(key);
  fileUploadProgress.value[key] = 0;
  delete uploadErrors.value[key];

  const form = new FormData();
  form.append("file", file);
  form.append("field", key);

  try {
    const { data } = await axios.post("/user/application/reupload", form, {
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          fileUploadProgress.value[key] = percentCompleted;
        }
      }
    });
    
    fileStatuses.value[key] = { url: data.url, status: data.status };
    await fetchData();
  } catch (error) {
    uploadErrors.value[key] = error.response?.data?.message || "Failed to upload file.";
    setTimeout(() => {
      delete uploadErrors.value[key];
    }, 5000);
  } finally {
    uploadingKeys.value = uploadingKeys.value.filter(k => k !== key);
    delete fileUploadProgress.value[key];
  }
};

const openImageModal = (src) => { 
  if(!src) return; 
  previewSrc.value = src; 
  showImageModal.value = true; 
  document.body.style.overflow = 'hidden';
};

const closeImageModal = () => { 
  showImageModal.value = false; 
  previewSrc.value = ""; 
  document.body.style.overflow = '';
};

const closeModal = () => (showModal.value = false);

onMounted(() => { 
  fetchData(); 
});
</script>

<template>
  <ApplicantLayout title="Applicant Dashboard">
    <template #header>
      <h2 class="font-bold text-2xl text-gray-900 dark:text-gray-100">
        Applicant Dashboard
      </h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Welcome Header -->
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Welcome back, {{ props.user?.firstname || 'Applicant' }}!</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage your application and track your progress</p>
          </div>
          
          <!-- Review Application Button -->
          <button
            @click="showModal = true"
            class="flex items-center gap-2 bg-maroon-700 hover:bg-maroon-800 text-white px-5 py-2.5 rounded-lg shadow-md transition-all hover:shadow-lg"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">Review Application</span>
          </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <!-- Application Status Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Application Status</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {{ applicationStatus ? capitalize(applicationStatus) : 'Not Started' }}
                </p>
              </div>
              <div :class="`w-12 h-12 rounded-full flex items-center justify-center ${getStatusIconBg(applicationStatus)}`">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Enrollment Status Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Enrollment Status</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {{ enrollmentStatus ? capitalize(enrollmentStatus.replace(/_/g, " ")) : 'Pending' }}
                </p>
              </div>
              <div :class="`w-12 h-12 rounded-full flex items-center justify-center ${getEnrollmentIconBg(enrollmentStatus)}`">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Documents Progress Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Documents Uploaded</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {{ uploadedCount }}/{{ stepKeys.length }}
                </p>
              </div>
              <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
              </div>
            </div>
            <!-- Progress Bar -->
            <div class="mt-3">
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" :style="{ width: uploadProgressPercentage + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left Column - Timeline -->
          <div class="lg:col-span-1">
            <div v-if="applicationProcesses.length" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Application Timeline
              </h3>
              <div class="space-y-4">
                <div v-for="(proc, idx) in applicationProcesses" :key="idx" class="flex gap-3">
                  <div class="relative">
                    <div :class="`w-8 h-8 rounded-full flex items-center justify-center text-white text-xs ${getBadgeClass(proc.status)}`">
                      {{ idx + 1 }}
                    </div>
                    <div v-if="idx < applicationProcesses.length - 1" class="absolute top-8 left-1/2 w-0.5 h-8 bg-gray-200 dark:bg-gray-700 -translate-x-1/2"></div>
                  </div>
                  <div class="flex-1 pb-4">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                      {{ formatStage(proc.stage) }}
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatTimestamp(proc.created_at) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                      {{ proc.notes || "No notes" }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column - Documents Grid -->
          <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Required Documents
              </h3>
              
              <div v-if="loading && !stepKeys.length" class="text-center py-8">
                <div class="flex justify-center items-center space-x-2">
                  <svg class="animate-spin h-5 w-5 text-maroon-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400">Loading documents...</span>
                </div>
              </div>

              <div v-else-if="stepKeys.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="(key, idx) in stepKeys" :key="key" class="group">
                  <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 hover:border-maroon-500 transition-all">
                    <!-- Document Icon/Preview -->
                    <div class="relative mb-2">
                      <div v-if="fileStatuses[key]?.url" class="relative">
                        <img
                          :src="fileStatuses[key].url"
                          :alt="formatKey(key)"
                          class="w-full h-20 object-cover rounded-md cursor-pointer"
                          @click="openImageModal(fileStatuses[key].url)"
                        />
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-md transition-all"></div>
                      </div>
                      <div v-else class="w-full h-20 bg-gray-200 dark:bg-gray-600 rounded-md flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                      </div>

                      <!-- Status Badge -->
                      <div class="absolute -top-1 -right-1">
                        <span :class="`px-1.5 py-0.5 rounded-full text-xs text-white ${getBadgeClass(fileStatuses[key]?.status)}`">
                          {{ getStatusShort(fileStatuses[key]?.status) }}
                        </span>
                      </div>
                    </div>

                    <!-- Document Name -->
                    <p class="text-xs text-center text-gray-700 dark:text-gray-300 font-medium mb-2 truncate" :title="formatKey(key)">
                      {{ formatKey(key) }}
                    </p>

                    <!-- Action Button -->
                    <button
                      v-if="!fileStatuses[key]?.url"
                      @click="triggerFileInput(key)"
                      class="w-full py-1 text-xs bg-maroon-600 hover:bg-maroon-700 text-white rounded transition-colors"
                      :disabled="uploadingKeys.includes(key)"
                    >
                      {{ uploadingKeys.includes(key) ? 'Uploading...' : 'Upload' }}
                    </button>
                    <button
                      v-else
                      @click="triggerFileInput(key)"
                      class="w-full py-1 text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded transition-colors"
                      :disabled="uploadingKeys.includes(key)"
                    >
                      Replace
                    </button>

                    <!-- Hidden File Input -->
                    <input
                      type="file"
                      :id="'file-input-' + key"
                      class="hidden"
                      accept="image/*,.pdf"
                      @change="reuploadFile($event, key)"
                    />

                    <!-- Upload Progress -->
                    <div v-if="fileUploadProgress && fileUploadProgress[key] !== undefined" class="mt-2">
                      <div class="w-full bg-gray-200 rounded-full h-1">
                        <div class="bg-maroon-600 h-1 rounded-full" :style="{ width: fileUploadProgress[key] + '%' }"></div>
                      </div>
                    </div>

                    <!-- Error Message -->
                    <p v-if="uploadErrors && uploadErrors[key]" class="text-xs text-red-500 text-center mt-1">
                      {{ uploadErrors[key] }}
                    </p>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-8">
                <p class="text-gray-500 dark:text-gray-400">No documents to display.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p class="text-red-600 dark:text-red-400 text-center">{{ error }}</p>
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
      class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center p-4"
      @click="closeImageModal"
    >
      <div class="relative max-w-[90vw] max-h-[90vh]">
        <img
          :src="previewSrc"
          alt="Preview"
          class="max-w-full max-h-full rounded-lg shadow-2xl"
          @click.stop
        />
        <button
          class="absolute top-2 right-2 text-white text-4xl hover:text-gray-300 transition-colors w-10 h-10 flex items-center justify-center rounded-full bg-black/50 hover:bg-black/70"
          @click.stop="closeImageModal"
        >
          &times;
        </button>
      </div>
    </div>
  </ApplicantLayout>
</template>

<style scoped>
/* Custom colors */
.bg-maroon-600 { background-color: #800000; }
.bg-maroon-700 { background-color: #991b1b; }
.bg-maroon-800 { background-color: #660000; }
.hover\:bg-maroon-700:hover { background-color: #991b1b; }
.hover\:bg-maroon-800:hover { background-color: #660000; }
.hover\:border-maroon-500:hover { border-color: #800000; }

/* Transitions */
.transition-all {
  transition: all 0.2s ease-in-out;
}

/* Scrollbar hiding */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Loading animation */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1s linear infinite;
}
</style>