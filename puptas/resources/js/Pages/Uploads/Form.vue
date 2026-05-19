<template>
  <Head title="Upload Passers" />
  <AppLayout>
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Upload Excel File</h1>
        <p class="text-gray-600 dark:text-gray-300 mt-2">Upload test passers data in batches.</p>
      </div>

      <!-- Upload Form Card -->
      <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-8 space-y-6 border border-gray-200 dark:border-gray-700">
        
        <!-- Batch & Year Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Batch Number -->
          <div class="space-y-2">
            <label class="block text-gray-700 dark:text-gray-200 font-medium">Batch Number</label>
            <div class="flex gap-2">
              <select
                v-model="batch"
                @change="onBatchChange"
                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm"
              >
                <option value="Batch 1">Batch 1</option>
                <option value="Batch 2">Batch 2</option>
                <option value="Batch 3">Batch 3</option>
                <option value="Batch 4">Batch 4</option>
                <option value="--Custom--">--Custom--</option>
              </select>
              <input
                v-if="batch === '--Custom--'"
                v-model="customBatch"
                placeholder="Custom batch"
                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 shadow-sm"
              />
            </div>
          </div>

          <!-- School Year -->
          <div class="space-y-2">
            <label class="block text-gray-700 dark:text-gray-200 font-medium">School Year</label>
            <div class="flex gap-2">
              <select
                v-model="year"
                @change="onYearChange"
                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm"
              >
                <option v-for="yearOption in yearOptions" :key="yearOption" :value="yearOption">
                  {{ yearOption }}
                </option>
                <option value="--Custom--">--Custom--</option>
              </select>
              <input
                v-if="year === '--Custom--'"
                v-model="customYear"
                placeholder="Custom year"
                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 shadow-sm"
              />
            </div>
          </div>
        </div>

        <!-- Passer Status Selection (Status_Selector) -->
        <div class="space-y-2">
          <label class="block text-gray-700 dark:text-gray-200 font-medium">Passer Status</label>
          <select
            v-model="passerStatus"
            class="w-full rounded-md dark:bg-gray-700 dark:text-white shadow-sm"
            :class="[
              showStatusError
                ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                : 'border-gray-300 dark:border-gray-600 focus:border-[#9E122C] focus:ring-[#9E122C]'
            ]"
          >
            <option value="" disabled>Select Passer Status</option>
            <option value="1">Qualified</option>
            <option value="2">Waitlisted</option>
            <option value="3">Unqualified</option>
            <option value="4">Waitlisted Below Cut Off</option>
          </select>
          <!-- Validation Message Adjacent to Status_Selector -->
          <p v-if="showStatusError" class="text-sm text-red-600 font-medium mt-1">A status selection is required.</p>
        </div>

        <!-- File Upload -->
        <div class="space-y-2">
          <label class="block text-gray-700 dark:text-gray-200 font-medium">Excel File</label>
          <div
            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-6 text-center cursor-pointer hover:border-[#9E122C] transition"
            @click="$refs.fileInput.click()"
          >
            <p v-if="!file" class="text-gray-500 dark:text-gray-400">Click to upload</p>
            <p v-else class="text-gray-700 dark:text-gray-200 font-medium">{{ file.name }}</p>
            <input type="file" ref="fileInput" class="hidden" @change="onFileChange" />
          </div>
        </div>

        <!-- Submit Button -->
        <button
          @click="submitForm"
          :disabled="uploading"
          :class="[
            'w-full py-3 font-semibold rounded-xl transition',
            uploading
              ? 'bg-gray-400 text-white cursor-not-allowed'
              : 'bg-[#9E122C] text-white hover:bg-[#b51834] dark:bg-gray-900 dark:text-gray-900 dark:hover:bg-gray-800'
          ]"
        >
          <span v-if="uploading" class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Uploading...
          </span>
          <span v-else><i class="fa fa-upload mr-2"></i> Upload</span>
        </button>
      </div>

      <!-- Success Dialog Modal -->
      <transition name="fade">
        <div
          v-if="showDialog"
          class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
        >
          <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 text-center shadow-xl border border-gray-100 dark:border-gray-700">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Success!</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-4">Your records have been uploaded successfully.</p>
            <div class="text-left bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4 space-y-1">
              <p class="text-gray-700 dark:text-gray-200"><span class="font-medium">Imported:</span> {{ importedCount }} records</p>
              <p class="text-gray-700 dark:text-gray-200"><span class="font-medium">Skipped (duplicates):</span> {{ skippedCount }} records</p>
            </div>
            <button
              @click="redirectToEmails"
              class="px-6 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition dark:bg-gray-900 dark:text-gray-900 dark:hover:bg-gray-800"
            >
              OK
            </button>
          </div>
        </div>
      </transition>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";
const axios = window.axios;
const { start, finish } = useGlobalLoading();

const batch = ref("Batch 1");
const customBatch = ref("");
const year = ref("");
const customYear = ref("");
const file = ref(null);
const showDialog = ref(false);
const yearOptions = ref([]);
const passerStatus = ref("");
const showStatusError = ref(false);
const importedCount = ref(0);
const skippedCount = ref(0);
const uploading = ref(false);

onMounted(() => {
  const currentYear = new Date().getFullYear();
  yearOptions.value = [
    `${currentYear}-${currentYear + 1}`,
    `${currentYear - 1}-${currentYear}`,
    `${currentYear - 2}-${currentYear - 1}`,
    `${currentYear - 3}-${currentYear - 2}`,
  ];
  year.value = `${currentYear}-${currentYear + 1}`;
});

const onBatchChange = () => {
  if (batch.value !== "--Custom--") customBatch.value = "";
};
const onYearChange = () => {
  if (year.value !== "--Custom--") customYear.value = "";
};
const onFileChange = (e) => {
  file.value = e.target.files[0];
};

const submitForm = async () => {
  const resolvedYear = year.value === "--Custom--" ? customYear.value : String(year.value);
  const resolvedBatch = batch.value === "--Custom--" ? customBatch.value : batch.value;

  showStatusError.value = false;

  // Validate status selector presence
  if (!passerStatus.value) {
    showStatusError.value = true;
    return;
  }
  if (!resolvedBatch) return alert("Please enter a batch number.");
  if (!resolvedYear) return alert("Please select a school year.");
  if (!file.value) return alert("Please select a file to upload.");

  const formData = new FormData();
  formData.append("batch_number", resolvedBatch);
  formData.append("school_year", resolvedYear);
  formData.append("passer_status_id", passerStatus.value);
  formData.append("file", file.value);

  uploading.value = true;
  start();
  try {
    const response = await axios.post("/test-passers/upload", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    importedCount.value = response.data?.imported_count ?? 0;
    skippedCount.value = response.data?.skipped_count ?? 0;
    showDialog.value = true;
  } catch (error) {
    handleUploadError(error);
  } finally {
    uploading.value = false;
    finish();
  }
};

const handleUploadError = (error) => {
  console.error(error);
  const status = error.response?.status;
  const data = error.response?.data;
  const message = data?.message || data?.error || null;
  if (status === 403) {
    alert("Upload failed: You do not have permission to upload passers.");
  } else if (status === 422) {
    if (data?.imported_count !== undefined && data?.skipped_count !== undefined) {
      alert(`Upload complete but no new records were added.\n\nSkipped: ${data.skipped_count} duplicate(s).\n\nAll entries in this file already exist in the system.`);
    } else {
      const errors = data?.errors;
      const detail = errors ? Object.values(errors).flat().join("\n") : message;
      alert("Upload failed: " + (detail || "Validation error."));
    }
  } else {
    alert("Upload failed." + (message ? " " + message : ""));
  }
};

const redirectToEmails = () => {
  window.location.href = "/test-passers";
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
