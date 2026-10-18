<script setup>
import { ref, onMounted } from 'vue';

// ── Reactive state ───────────────────────────────────────────────────────────
const kpiData  = ref(null);
const loading  = ref(true);
const error    = ref(null);

// ── Data fetching ────────────────────────────────────────────────────────────
const fetchKpiData = async () => {
  try {
    const response = await window.axios.get('/dashboard/kpi');
    kpiData.value  = response.data;
    loading.value  = false;
  } catch {
    error.value   = 'Failed to load KPI data. Please refresh the page.';
    loading.value = false;
  }
};

onMounted(() => {
  fetchKpiData();
});

// ── PDF export ───────────────────────────────────────────────────────────────
const downloadPdf = () => {
  window.open('/dashboard/kpi/export/pdf', '_blank');
};
</script>

<template>
  <!-- KPI Performance Dashboard Section -->
  <div>
    <!-- Section Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
      <div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">KPI Performance Dashboard</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">
          Key performance indicators for the current admissions cycle
        </p>
      </div>

      <!-- Download PDF button — always visible -->
      <button
        type="button"
        @click="downloadPdf"
        class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] text-white text-sm font-semibold rounded-lg hover:bg-[#b51834] transition-all shadow-md active:scale-95 shrink-0"
        aria-label="Download KPI Report as PDF"
      >
        <!-- Download icon -->
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
        Download KPI Report (PDF)
      </button>
    </div>

    <!-- Inline error banner (non-blocking — card shells remain below) -->
    <div
      v-if="error"
      role="alert"
      class="mb-4 flex items-start gap-3 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/60 rounded-lg text-sm text-red-700 dark:text-red-300"
    >
      <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ error }}</span>
    </div>

    <!-- KPI Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- ── Loading skeletons ── -->
      <template v-if="loading">
        <div
          v-for="n in 8"
          :key="n"
          class="animate-pulse bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700"
          aria-hidden="true"
        >
          <!-- Label placeholder -->
          <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-3"></div>
          <!-- Value placeholder -->
          <div class="h-7 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-2"></div>
          <!-- Target placeholder -->
          <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-2/3 mb-4"></div>
          <!-- Badge placeholder -->
          <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded-full w-1/3"></div>
        </div>
      </template>

      <!-- ── Error / neutral placeholder shells ── -->
      <template v-else-if="error">
        <div
          v-for="n in 8"
          :key="n"
          class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700"
        >
          <div class="h-3 bg-gray-100 dark:bg-gray-700 rounded w-3/4 mb-3"></div>
          <div class="h-7 bg-gray-100 dark:bg-gray-700 rounded w-1/2 mb-2"></div>
          <div class="h-3 bg-gray-100 dark:bg-gray-700 rounded w-2/3 mb-4"></div>
          <div class="h-5 bg-gray-100 dark:bg-gray-700 rounded-full w-1/3"></div>
        </div>
      </template>

      <!-- ── Loaded KPI cards ── -->
      <template v-else-if="kpiData && kpiData.kpis">
        <div
          v-for="kpi in kpiData.kpis"
          :key="kpi.id"
          class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 flex flex-col gap-1"
        >
          <!-- KPI Label -->
          <p class="text-xs font-medium text-gray-600 dark:text-gray-400 truncate" :title="kpi.label">
            {{ kpi.label }}
          </p>

          <!-- Computed value -->
          <p class="text-2xl font-bold text-gray-900 dark:text-white leading-none mt-1">
            {{ kpi.value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}{{ kpi.unit }}
          </p>

          <!-- Target value -->
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Target: {{ kpi.lowerIsBetter ? '≤' : '≥' }} {{ kpi.target }}{{ kpi.unit }}
          </p>

          <!-- Pass / Fail badge -->
          <div class="mt-2">
            <span
              v-if="kpi.met"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300"
            >
              <!-- Checkmark icon -->
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
              Target Met
            </span>
            <span
              v-else
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300"
            >
              <!-- X icon -->
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
              </svg>
              {{ kpi.lowerIsBetter ? 'Above Threshold' : 'Below Target' }}
            </span>
          </div>
        </div>
      </template>

    </div>

    <!-- Summary row (only shown when data is loaded) -->
    <div
      v-if="!loading && !error && kpiData && kpiData.summary"
      class="mt-3 flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400"
    >
      <span>
        Total KPIs: <strong class="text-gray-900 dark:text-white">{{ kpiData.summary.total_kpis }}</strong>
      </span>
      <span class="text-green-700 dark:text-green-400">
        Met: <strong>{{ kpiData.summary.kpis_met }}</strong>
      </span>
      <span class="text-red-700 dark:text-red-400">
        Below target: <strong>{{ kpiData.summary.kpis_failed }}</strong>
      </span>
      <span class="ml-auto text-xs text-gray-400 dark:text-gray-500" v-if="kpiData.generated_at">
        Generated {{ new Date(kpiData.generated_at).toLocaleString() }}
      </span>
    </div>

    <!-- Manual Baselines Reference Panel -->
    <div
      v-if="!loading && !error && kpiData && kpiData.manual_baselines"
      class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50 rounded-xl"
    >
      <p class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-2">
        Manual Baselines (Pre-PUPTAS Reference)
      </p>
      <p class="text-xs text-blue-700 dark:text-blue-300 mb-3">
        {{ kpiData.manual_baselines.source_note }}
      </p>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <!-- Applicant population -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-blue-100 dark:border-blue-800/40">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Students Processed (Manual)</p>
          <p class="text-lg font-bold text-gray-900 dark:text-white">{{ kpiData.manual_baselines.applicant_population.toLocaleString() }}</p>
        </div>
        <!-- Overall lead time -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-blue-100 dark:border-blue-800/40">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Avg. Admission Lead Time (Manual)</p>
          <p class="text-sm font-bold text-gray-900 dark:text-white">{{ kpiData.manual_baselines.overall_lead_time.display }}</p>
        </div>
        <!-- Step averages -->
        <div
          v-for="(stepData, stepKey) in kpiData.manual_baselines.step_averages"
          :key="stepKey"
          class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-blue-100 dark:border-blue-800/40"
        >
          <p class="text-gray-500 dark:text-gray-400 mb-1 capitalize">{{ stepKey.replace(/_/g, ' ') }} (Manual Avg.)</p>
          <p class="text-lg font-bold text-gray-900 dark:text-white">{{ stepData.avg_min }} min</p>
        </div>
      </div>

      <!-- PUPTAS live lead time comparison (loaded separately from the logbook KPI endpoint) -->
      <div
        v-if="kpiData.manual_baselines.puptas_lead_time"
        class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs"
      >
        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
          <p class="text-gray-500 dark:text-gray-400 mb-1">Manual Process Lead Time</p>
          <p class="text-sm font-bold text-gray-900 dark:text-white">{{ kpiData.manual_baselines.overall_lead_time.display }}</p>
          <p class="text-[11px] text-gray-400 mt-0.5">{{ kpiData.manual_baselines.source_note }}</p>
        </div>
        <div
          class="rounded-lg p-3 border"
          :class="kpiData.manual_baselines.puptas_lead_time.avg_min !== null
            ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800'
            : 'bg-gray-50 border-gray-200 dark:bg-gray-700/40 dark:border-gray-700'"
        >
          <p class="text-gray-500 dark:text-gray-400 mb-1">PUPTAS End-to-End Lead Time</p>
          <template v-if="kpiData.manual_baselines.puptas_lead_time.avg_min !== null">
            <p class="text-sm font-bold text-green-700 dark:text-green-300">{{ kpiData.manual_baselines.puptas_lead_time.avg_display }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">avg. from {{ kpiData.manual_baselines.puptas_lead_time.count.toLocaleString() }} applicants</p>
          </template>
          <p v-else class="text-[11px] text-gray-400">No data yet</p>
          <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 italic">{{ kpiData.manual_baselines.puptas_lead_time.caveat }}</p>
        </div>
      </div>

      <p class="text-[11px] text-blue-500 dark:text-blue-400 mt-3">
        Percentage-based KPI baselines (enrollment rate, slot utilization, etc.) are not yet established.
        Contact the Admission Office for historical slot counts and enrollment tallies to complete those baselines.
      </p>
    </div>
  </div>
</template>
