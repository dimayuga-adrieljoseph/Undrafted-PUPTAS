<template>
  <Head title="Add Program" />
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 md:px-8 py-8">
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Add New Program</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Create a new academic program with its requirements and slot allocation</p>
      </div>

      <!-- Main Form Card -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Form Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-[#9E122C] to-[#b51834]">
          <h3 class="text-lg font-semibold text-white flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Program Details
          </h3>
        </div>

        <!-- Form Body -->
        <div class="p-6">
          <form @submit.prevent="addProgram">
            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Left Column - Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Program Code <span class="text-red-500">*</span>
                  </label>
                  <input 
                    v-model="newProgram.code" 
                    type="text"
                    placeholder="e.g., BSIT, BSCS"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Program Name <span class="text-red-500">*</span>
                  </label>
                  <input 
                    v-model="newProgram.name" 
                    type="text"
                    placeholder="e.g., Bachelor of Science in Information Technology"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    required
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Eligible Strands
                  </label>
                  <div class="relative">
                    <div class="min-h-[42px] w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 flex flex-wrap gap-2 cursor-pointer" @click="showStrandDropdown = !showStrandDropdown">
                      <span v-if="newProgram.strand_ids.length === 0" class="text-gray-400">Select strands...</span>
                      <span v-for="strandId in newProgram.strand_ids" :key="strandId" class="px-2 py-1 bg-[#9E122C]/10 text-[#9E122C] rounded text-sm flex items-center gap-1">
                        {{ getStrandName(strandId) }}
                        <button type="button" @click.stop="removeStrand(strandId)" class="hover:text-red-600">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </span>
                    </div>
                    <div v-if="showStrandDropdown" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-auto">
                      <div v-for="strand in availableStrands" :key="strand.id" class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer flex items-center gap-2" @click="toggleStrand(strand.id)">
                        <input type="checkbox" :checked="newProgram.strand_ids.includes(strand.id)" class="rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]" @click.stop>
                        <span>{{ strand.code }} - {{ strand.name }}</span>
                      </div>
                      <div v-if="availableStrands.length === 0" class="px-3 py-2 text-gray-500">
                        No strands available
                      </div>
                    </div>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Select one or more strands eligible for this program</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Available Slots <span class="text-red-500">*</span>
                  </label>
                  <input 
                    v-model.number="newProgram.slots" 
                    type="number" 
                    step="1" 
                    min="1"
                    placeholder="e.g., 50"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    required
                  />
                </div>
              </div>

              <!-- Right Column - Requirements -->
              <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                  <h4 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Minimum Grade Requirements
                  </h4>
                  
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Mathematics (0-100)</label>
                      <input 
                        v-model.number="newProgram.math" 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="100"
                        placeholder="0-100"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Science (0-100)</label>
                      <input 
                        v-model.number="newProgram.science" 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="100"
                        placeholder="0-100"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">English (0-100)</label>
                      <input 
                        v-model.number="newProgram.english" 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="100"
                        placeholder="0-100"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">GWA (1.00-100.00)</label>
                      <input 
                        v-model.number="newProgram.gwa" 
                        type="number" 
                        step="0.01" 
                        min="1" 
                        max="100"
                        placeholder="1.00-100.00"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                      />
                    </div>
                  </div>
                </div>

                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800">
                  <h4 class="text-sm font-semibold text-yellow-700 dark:text-yellow-300 mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Examination Requirements
                  </h4>
                  
                  <p class="text-xs text-gray-500 dark:text-gray-400">Passing PUPCET score is implied for all applicants.</p>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end space-x-3">
              <Link
                href="/programs"
                class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
              >
                Cancel
              </Link>
              <button 
                type="submit"
                :disabled="isSubmitting"
                class="px-6 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>{{ isSubmitting ? 'Adding...' : 'Add Program' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Help Section -->
      <div class="mt-6 bg-blue-50 dark:bg-blue-900/10 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
        <div class="flex items-start space-x-3">
          <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <h4 class="text-sm font-semibold text-blue-700 dark:text-blue-300">About Program Requirements</h4>
            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
              Fields marked with <span class="text-red-500">*</span> are required. Grade requirements should be entered as numeric values.
              Leave requirement fields empty if not applicable to the program.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
const axios = window.axios;
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, Link, router } from '@inertiajs/vue3';

const newProgram = ref({
  code: "",
  name: "",
  strand_ids: [],
  math: null,
  science: null,
  english: null,
  gwa: null,
  slots: 1,
});

const availableStrands = ref([]);
const showStrandDropdown = ref(false);
const errorMessage = ref("");
const isSubmitting = ref(false);

// Fetch available strands on mount
onMounted(async () => {
  try {
    const response = await axios.get("/programs/strands");
    availableStrands.value = response.data;
  } catch (error) {
    console.error("Error fetching strands:", error);
  }
});

// Get strand name by ID
const getStrandName = (strandId) => {
  const strand = availableStrands.value.find(s => s.id === strandId);
  return strand ? strand.code : strandId;
};

// Toggle strand selection
const toggleStrand = (strandId) => {
  const index = newProgram.value.strand_ids.indexOf(strandId);
  if (index === -1) {
    newProgram.value.strand_ids.push(strandId);
  } else {
    newProgram.value.strand_ids.splice(index, 1);
  }
};

// Remove strand from selection
const removeStrand = (strandId) => {
  const index = newProgram.value.strand_ids.indexOf(strandId);
  if (index !== -1) {
    newProgram.value.strand_ids.splice(index, 1);
  }
};

// Function to Add Program
const addProgram = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;
  
  try {
    errorMessage.value = "";
    
    const response = await axios.post("/programs", newProgram.value);
    
    // Reset form
    newProgram.value = { 
      code: "", 
      name: "", 
      strand_ids: [], 
      math: null, 
      science: null, 
      english: null, 
      gwa: null, 
      slots: 1 
    };
    
    router.get('/programs');

  } catch (error) {
    console.error("Error creating program:", error);
    
    // Show validation errors or generic error message
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      errorMessage.value = Object.values(errors).flat().join(", ");
    } else if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "Failed to create program. Please try again.";
    }
    
    alert(errorMessage.value);
  } finally {
    isSubmitting.value = false;
  }
};
</script>
