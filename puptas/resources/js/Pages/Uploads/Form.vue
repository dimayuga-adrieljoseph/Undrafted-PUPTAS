<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Upload Excel File</h1>
        <p class="text-gray-600 dark:text-gray-300 mt-2">Upload test passers data in batches.</p>
      </div>

      <!-- Upload Form Card -->
      <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-8 space-y-6">
        <!-- Batch & Year Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Batch -->
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
          class="w-full py-3 bg-[#9E122C] text-white font-semibold rounded-xl hover:bg-[#b51834] transition"
        >
          <i class="fa fa-upload mr-2"></i> Upload
        </button>
      </div>

      <!-- Success Modal -->
      <transition name="fade">
        <div
          v-if="showDialog"
          class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
        >
          <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-96 text-center shadow-xl">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Success!</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-4">Your records have been uploaded successfully.</p>
            <button
              @click="redirectToEmails"
              class="px-6 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition"
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
const axios = window.axios;

const batch = ref("Batch 1");
const customBatch = ref("");
const year = ref("");
const customYear = ref("");
const file = ref(null);
const showDialog = ref(false);
const yearOptions = ref([]);

onMounted(() => {
  const currentYear = new Date().getFullYear();
  yearOptions.value = [currentYear, currentYear - 1, currentYear - 2, currentYear - 3];
  year.value = currentYear;
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
  if (!file.value) return alert("Please select a file to upload.");
  const formData = new FormData();
  formData.append("batch_number", batch.value === "--Custom--" ? customBatch.value : batch.value);
  formData.append("school_year", year.value === "--Custom--" ? customYear.value : year.value);
  formData.append("file", file.value);

  try {
    await axios.post("/test-passers/upload", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    showDialog.value = true;
  } catch (error) {
    console.error(error);
    alert("Upload failed.");
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
