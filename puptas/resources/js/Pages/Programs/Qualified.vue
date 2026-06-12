<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Head } from "@inertiajs/vue3";
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";

const axios = window.axios;

const loading = ref(false);
const error = ref("");
const qualifiedPrograms = ref([]);
const disqualifiedPrograms = ref([]);
const lastUpdated = ref(null);
let pollInterval = null;

const fetchPrograms = async (silent = false) => {
  if (!silent) loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get("/applicant-dashboard/qualified-programs");
    qualifiedPrograms.value = data.qualified || [];
    disqualifiedPrograms.value = data.disqualified || [];
    lastUpdated.value = new Date();
  } catch (err) {
    if (!silent) {
      error.value = err.response?.data?.message || "Failed to load program eligibility data.";
    }
  } finally {
    if (!silent) loading.value = false;
  }
};

const timeAgo = computed(() => {
  if (!lastUpdated.value) return "";
  const seconds = Math.floor((new Date() - lastUpdated.value) / 1000);
  if (seconds < 5) return "Just now";
  if (seconds < 60) return `${seconds}s ago`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  return `${hours}h ago`;
});

const hasData = computed(() => qualifiedPrograms.value.length > 0 || disqualifiedPrograms.value.length > 0);
const isEmpty = computed(() => !loading.value && !error.value && qualifiedPrograms.value.length === 0 && disqualifiedPrograms.value.length === 0);

onMounted(() => {
  fetchPrograms();
  pollInterval = setInterval(() => fetchPrograms(true), 30000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <Head title="Qualified Programs" />
  <ApplicantLayout title="Qualified Programs">
    <template #header>
      <h2 class="font-bold text-2xl text-gray-900 dark:text-gray-100">
        Program Eligibility
      </h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Stats Bar -->
        <div v-if="!loading && hasData" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-6">
              <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                  {{ qualifiedPrograms.length }} Qualified
                </span>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                  {{ disqualifiedPrograms.length }} Not Qualified
                </span>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs text-gray-400 dark:text-gray-500">
                Updated {{ timeAgo }}
              </span>
              <button
                @click="fetchPrograms(true)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition min-h-[36px]"
              >
                <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': false }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
              </button>
            </div>
          </div>
        </div>

        <!-- Loading State (Skeleton) -->
        <div v-if="loading" class="space-y-6">
          <!-- Skeleton stats -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 animate-pulse">
            <div class="flex items-center gap-6">
              <div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
              <div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
          </div>
          <!-- Skeleton cards -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded mb-4 animate-pulse"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="n in 4" :key="n" class="p-5 bg-gray-50 dark:bg-gray-700/30 border border-gray-200 dark:border-gray-700 rounded-xl animate-pulse">
                <div class="h-5 w-24 bg-gray-200 dark:bg-gray-600 rounded mb-2"></div>
                <div class="h-4 w-40 bg-gray-200 dark:bg-gray-600 rounded mb-4"></div>
                <div class="h-2 w-full bg-gray-200 dark:bg-gray-600 rounded mb-3"></div>
                <div class="grid grid-cols-3 gap-2">
                  <div class="h-16 bg-gray-200 dark:bg-gray-600 rounded"></div>
                  <div class="h-16 bg-gray-200 dark:bg-gray-600 rounded"></div>
                  <div class="h-16 bg-gray-200 dark:bg-gray-600 rounded"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-8 text-center">
          <svg class="w-14 h-14 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <p class="text-red-600 dark:text-red-400 font-medium text-lg mb-2">Something went wrong</p>
          <p class="text-red-500 dark:text-red-400/80 text-sm mb-5">{{ error }}</p>
          <button
            @click="fetchPrograms()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg transition font-medium text-sm min-h-[44px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="isEmpty" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-16 text-center">
          <div class="w-20 h-20 mx-auto mb-5 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No Program Data Available</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
            Please make sure you have completed and submitted your grades to see which programs you qualify for.
          </p>
        </div>

        <!-- Content -->
        <template v-else>
          <!-- Qualified Programs -->
          <div v-if="qualifiedPrograms.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-green-200 dark:border-green-900/50 bg-green-50/50 dark:bg-green-900/10">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                  <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-base font-bold text-gray-900 dark:text-white">Qualified Programs</h3>
                  <p class="text-xs text-gray-500 dark:text-gray-400">You meet the requirements for {{ qualifiedPrograms.length }} program{{ qualifiedPrograms.length !== 1 ? 's' : '' }}</p>
                </div>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                  v-for="program in qualifiedPrograms"
                  :key="program.id"
                  class="group p-5 bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-800 hover:border-green-300 dark:hover:border-green-700 hover:shadow-md transition-all duration-200"
                >
                  <!-- Header -->
                  <div class="flex items-start justify-between mb-4">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ program.code }}</span>
                        <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">Qualified</span>
                      </div>
                      <p class="text-sm text-gray-600 dark:text-gray-400 leading-snug">{{ program.name }}</p>
                      <p class="text-xs text-blue-600 dark:text-blue-400 mt-1.5">
                        <span class="font-semibold">Strands:</span> {{ program.strand_names || 'Open to All' }}
                      </p>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0 ml-3">
                      <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                  </div>

                  <!-- Slots -->
                  <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                    <div class="flex items-center justify-between mb-1.5">
                      <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Available Slots</span>
                      <span class="text-xl font-bold text-green-600 dark:text-green-400">{{ program.slots }}</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 overflow-hidden">
                      <div
                        class="h-2 rounded-full transition-all duration-500 ease-out"
                        :class="program.slots > 10 ? 'bg-green-500' : program.slots > 3 ? 'bg-yellow-500' : 'bg-red-500'"
                        :style="{ width: Math.min(program.slots * 3, 100) + '%' }"
                      ></div>
                    </div>
                  </div>

                  <!-- Grade Comparison -->
                  <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Requirements vs Your Grades</p>
                    <div class="grid grid-cols-3 gap-2">
                      <div class="text-center p-2.5 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-900/20">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Math</p>
                        <p class="text-base font-bold text-green-600 dark:text-green-400">{{ program.your_grades.math }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">≥ {{ program.requirements.math }}</p>
                      </div>
                      <div class="text-center p-2.5 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-900/20">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Science</p>
                        <p class="text-base font-bold text-green-600 dark:text-green-400">{{ program.your_grades.science }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">≥ {{ program.requirements.science }}</p>
                      </div>
                      <div class="text-center p-2.5 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-100 dark:border-green-900/20">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">English</p>
                        <p class="text-base font-bold text-green-600 dark:text-green-400">{{ program.your_grades.english }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">≥ {{ program.requirements.english }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Disqualified Programs -->
          <div v-if="disqualifiedPrograms.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-900/10">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                  <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-base font-bold text-gray-900 dark:text-white">Not Qualified</h3>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ disqualifiedPrograms.length }} program{{ disqualifiedPrograms.length !== 1 ? 's' : '' }} didn't match your qualifications</p>
                </div>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                  v-for="program in disqualifiedPrograms"
                  :key="program.id"
                  class="group p-5 bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-800/60 hover:border-red-300 dark:hover:border-red-700 hover:shadow-md transition-all duration-200 opacity-90 hover:opacity-100"
                >
                  <!-- Header -->
                  <div class="flex items-start justify-between mb-4">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg font-bold text-gray-700 dark:text-gray-200">{{ program.code }}</span>
                        <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">Not Qualified</span>
                      </div>
                      <p class="text-sm text-gray-500 dark:text-gray-400 leading-snug">{{ program.name }}</p>
                      <p class="text-xs text-blue-600 dark:text-blue-400 mt-1.5">
                        <span class="font-semibold">Strands:</span> {{ program.strand_names || 'Open to All' }}
                      </p>
                      <!-- Reason badge -->
                      <div class="mt-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                          <span v-if="!program.meets_strand && !program.meets_grades">Strand mismatch & grades too low</span>
                          <span v-else-if="!program.meets_strand">Strand mismatch</span>
                          <span v-else-if="!program.meets_grades">Did not meet grade requirements</span>
                          <span v-else>Not qualified</span>
                        </span>
                      </div>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0 ml-3">
                      <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </div>
                  </div>

                  <!-- Slots -->
                  <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                    <div class="flex items-center justify-between">
                      <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Available Slots</span>
                      <span class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ program.slots }}</span>
                    </div>
                  </div>

                  <!-- Grade Comparison -->
                  <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Requirements vs Your Grades</p>
                    <div class="grid grid-cols-3 gap-2">
                      <div
                        class="text-center p-2.5 rounded-lg border"
                        :class="program.your_grades.math >= program.requirements.math
                          ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20'
                          : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/20'"
                      >
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Math</p>
                        <p class="text-base font-bold" :class="program.your_grades.math >= program.requirements.math ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ program.your_grades.math }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">≥ {{ program.requirements.math }}</p>
                      </div>
                      <div
                        class="text-center p-2.5 rounded-lg border"
                        :class="program.your_grades.science >= program.requirements.science
                          ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20'
                          : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/20'"
                      >
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Science</p>
                        <p class="text-base font-bold" :class="program.your_grades.science >= program.requirements.science ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ program.your_grades.science }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">≥ {{ program.requirements.science }}</p>
                      </div>
                      <div
                        class="text-center p-2.5 rounded-lg border"
                        :class="program.your_grades.english >= program.requirements.english
                          ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20'
                          : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/20'"
                      >
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">English</p>
                        <p class="text-base font-bold" :class="program.your_grades.english >= program.requirements.english ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ program.your_grades.english }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">≥ {{ program.requirements.english }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

      </div>
    </div>
  </ApplicantLayout>
</template>

<style scoped>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1s linear infinite;
}
</style>