<script setup>
import { ref, computed, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { LineChart } from "vue-chart-3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BlurText from "@/Components/BlurText.vue";
import UserDetailsModal from "@/Pages/Applications/UserDetailsModal.vue";
import { 
  Chart as ChartJS, 
  LineController, 
  LineElement, 
  CategoryScale, 
  LinearScale, 
  PointElement, 
  Tooltip, 
  Legend, 
  Filler 
} from "chart.js";

ChartJS.register(
  LineController, 
  LineElement, 
  CategoryScale, 
  LinearScale, 
  PointElement, 
  Tooltip, 
  Legend, 
  Filler
);

const props = defineProps({
  allUsers: Array,
  summary: {
    type: Object,
    default: () => ({ total: 0, accepted: 0, pending: 0, returned: 0 }),
  },
  stageSummary: {
    type: Object,
    default: () => ({
      document_evaluator: 0,
      grade_evaluator: 0,
      interviewer: 0,
      medical: 0,
      records: 0,
      enrollment: 0,
    }),
  },
  chartData: {
    type: Object,
    default: () => ({ submitted: [], accepted: [], returned: [], labels: [] }),
  },
  filters: {
    type: Object,
    default: () => ({ start_date: '', end_date: '' })
  }
});

const startDateFilter = ref(props.filters?.start_date || '');
const endDateFilter = ref(props.filters?.end_date || '');
const showDateFilter = ref(false);

const applyFilters = () => {
  router.get(window.location.pathname, {
    start_date: startDateFilter.value,
    end_date: endDateFilter.value
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
};

const selectedUser = ref(null);
const selectedUserFiles = ref({});
const showImageModal = ref(false);
const previewImage = ref("");
const searchQuery = ref("");

// Simplified summary items: Total + one card per active pipeline stage
const summaryItems = computed(() => {
  const total = props.summary.total || 0;
  const stage = props.stageSummary || {};
  const pct = (value) => (total > 0 ? Math.round(((value || 0) / total) * 100) : 0);

  return [
    {
      label: "Total Applications",
      value: props.summary.total,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>' },
      percentage: 100,
      color: 'blue',
      stageKey: 'all',
    },
    {
      label: "Document Evaluation",
      value: stage.document_evaluator ?? 0,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>' },
      percentage: pct(stage.document_evaluator),
      color: 'orange',
      stageKey: 'document_evaluator',
    },
    {
      label: "Grade Evaluation",
      value: stage.grade_evaluator ?? 0,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>' },
      percentage: pct(stage.grade_evaluator),
      color: 'yellow',
      stageKey: 'grade_evaluator',
    },
    {
      label: "For Interview",
      value: stage.interviewer ?? 0,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8z" /></svg>' },
      percentage: pct(stage.interviewer),
      color: 'purple',
      stageKey: 'interviewer',
    },
    {
      label: "For Medical",
      value: stage.medical ?? 0,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>' },
      percentage: pct(stage.medical),
      color: 'cyan',
      stageKey: 'medical',
    },
    {
      label: "For Records",
      value: stage.records ?? 0,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V8a2 2 0 012-2h4l2 2h8a2 2 0 012 2v7a2 2 0 01-2 2H5z" /></svg>' },
      percentage: pct(stage.records),
      color: 'indigo',
      stageKey: 'records',
    },
    {
      label: "Enrolled",
      value: stage.enrollment ?? 0,
      icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
      percentage: pct(stage.enrollment),
      color: 'green',
      stageKey: 'enrollment',
    },
  ];
});

const colorMap = {
  blue: { icon: 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300', bar: 'bg-blue-500' },
  orange: { icon: 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-300', bar: 'bg-orange-500' },
  yellow: { icon: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300', bar: 'bg-yellow-500' },
  purple: { icon: 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300', bar: 'bg-purple-500' },
  cyan: { icon: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-300', bar: 'bg-cyan-500' },
  indigo: { icon: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300', bar: 'bg-indigo-500' },
  green: { icon: 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300', bar: 'bg-green-500' },
};

const iconClass = (color) => colorMap[color]?.icon || colorMap.blue.icon;
const barClass = (color) => colorMap[color]?.bar || colorMap.blue.bar;

// Chart configuration
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: 'rgba(255, 255, 255, 0.95)',
      titleColor: '#1f2937',
      bodyColor: '#374151',
      borderColor: '#e5e7eb',
      borderWidth: 1,
      cornerRadius: 8,
      padding: 12,
    }
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#6b7280' }
    },
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(107, 114, 128, 0.1)' },
      ticks: { 
        color: '#6b7280',
        callback: (value) => value.toLocaleString()
      }
    }
  }
};

const chartDataset = computed(() => ({
  labels: props.chartData.labels || [],
  datasets: [
    { 
      label: "Accepted", 
      data: props.chartData.accepted || [], 
      borderColor: "#10B981",
      backgroundColor: "rgba(16, 185, 129, 0.1)",
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#10B981",
      pointBorderColor: "#ffffff",
      pointBorderWidth: 2,
      pointRadius: 4,
    },
    { 
      label: "Pending", 
      data: props.chartData.submitted || [], 
      borderColor: "#EAB308",
      backgroundColor: "rgba(234, 179, 8, 0.1)",
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#EAB308",
      pointBorderColor: "#ffffff",
      pointBorderWidth: 2,
      pointRadius: 4,
    },
    { 
      label: "Returned", 
      data: props.chartData.returned || [], 
      borderColor: "#EF4444",
      backgroundColor: "rgba(239, 68, 68, 0.1)",
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#EF4444",
      pointBorderColor: "#ffffff",
      pointBorderWidth: 2,
      pointRadius: 4,
    },
  ],
}));

const getStatusClass = (status) => {
  const s = (status || "").toLowerCase();
  if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
  if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
  if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
  if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
  return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
};

const PAGE_SIZE = 2;
const currentPage = ref(1);

const filteredUsers = computed(() => {
  const users = props.allUsers || [];
  const query = searchQuery.value.trim().toLowerCase();
  if (!query) return users;
  return users.filter(user =>
    `${user.firstname} ${user.lastname}`.toLowerCase().includes(query) ||
    user.email.toLowerCase().includes(query)
  );
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / PAGE_SIZE)));

const displayedUsers = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE;
  return filteredUsers.value.slice(start, start + PAGE_SIZE);
});

watch(searchQuery, () => { currentPage.value = 1; });

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleString();
};

const formatDateOnly = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatStage = (stage) => {
  if (!stage) return 'Unknown Stage';
  return stage.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const capitalize = (str) =>
  typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatFileKey = (key) => {
  const map = {
    file10Front: 'Grade 10 Report Front',
    file10: 'Grade 10 Report Back',
    file11Front: "Grade 11 Report Front",
    file11: "Grade 11 Report Back",
    file12Front: "Grade 12 Report Front",
    file12: "Grade 12 Report Back",
    schoolId: "School ID",
    nonEnrollCert: "Certificate of Non-Enrollment",
    psa: "PSA Birth Certificate",
    goodMoral: "Good Moral Certificate",
    underOath: "Under Oath Document",
    photo2x2: "2x2 Photo",
    fileCorFront: "COR Front",
    fileCorBack: "COR Back",
  };
  return map[key] || key;
};

const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");
const hasImagePreview = (file) =>
  Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

const openImageModal = (file) => {
  const src = getFileUrl(file);
  if (!src || !hasImagePreview(file)) return;
  previewImage.value = src;
  showImageModal.value = true;
};

const closeImageModal = () => {
  showImageModal.value = false;
  previewImage.value = "";
};

const selectUser = async (user) => {
  try {
    const response = await window.axios.get(`/admin-dashboard/user-files/${user.id}`);
    selectedUser.value = {
      ...user,
      ...response.data.user,
      application: {
        ...response.data.user.application,
        processes: response.data.user.application?.processes || [],
      },
      grades: response.data.user.grades || null,
    };
    selectedUserFiles.value = response.data.uploadedFiles || {};
  } catch (error) {
    // Fall back to basic user data if fetch fails
    selectedUser.value = user;
    selectedUserFiles.value = {};
  }
};

const closeUserCard = () => {
  selectedUser.value = null;
  selectedUserFiles.value = {};
};

// Snackbar
const snackbar = ref({ show: false, message: '' });
let snackbarTimer = null;
const showSnackbar = (message) => {
  snackbar.value = { show: true, message };
  clearTimeout(snackbarTimer);
  snackbarTimer = setTimeout(() => { snackbar.value.show = false; }, 3500);
};

const tagApplication = async () => {
  try {
    const response = await window.axios.post(`/admin-dashboard/tag/${selectedUser.value.id}`);
    showSnackbar('Tagged as Enrolled.');
    if (selectedUser.value?.application) {
      selectedUser.value.application.enrollment_status = response.data.enrollment_status || 'officially_enrolled';
      selectedUser.value.application.status = response.data.application_status || 'accepted';
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to tag application.';
    showSnackbar(msg);
  }
};

const untagApplication = async () => {
  try {
    await window.axios.post(`/admin-dashboard/untag/${selectedUser.value.id}`);
    showSnackbar('Reverted to temporary enrolled.');
    if (selectedUser.value?.application) {
      selectedUser.value.application.enrollment_status = 'temporary';
      selectedUser.value.application.status = 'cleared_for_enrollment';
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to untag application.';
    showSnackbar(msg);
  }
};
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout>
    <div class="dash-shell">
    <!-- Header Section -->
    <div class="px-4 md:px-8 mb-8 shrink-0">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <BlurText
            text="Admissions Dashboard"
            :delay="100"
            animate-by="words"
            direction="top"
            class-name="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"
          />
          <BlurText
            text="Welcome back! Here's an overview of your application data."
            :delay="60"
            animate-by="words"
            direction="top"
            :step-duration="0.3"
            class-name="text-gray-600 dark:text-gray-400 mt-2"
          />
        </div>

      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3 px-4 md:px-8 mb-8 shrink-0">
      <component
        :is="item.stageKey ? 'a' : 'div'"
        v-for="(item, index) in summaryItems"
        :key="index"
        :href="item.stageKey ? (item.stageKey === 'all' ? '/applications' : `/applications?stage=${item.stageKey}`) : undefined"
        :class="[
          'bg-white dark:bg-gray-800 rounded-xl p-3.5 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 block',
          item.stageKey ? 'cursor-pointer hover:border-gray-400 dark:hover:border-gray-500' : ''
        ]"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium mb-1 truncate" :title="item.label">{{ item.label }}</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white leading-none">{{ item.value.toLocaleString() }}</p>
          </div>
          <div :class="['p-1.5 rounded-lg shrink-0', iconClass(item.color)]">
            <component :is="item.icon" class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-2.5">
          <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div 
              :class="['h-full rounded-full', barClass(item.color)]"
              :style="{ width: item.percentage + '%' }"
            ></div>
          </div>
          <p class="text-right text-[10px] text-gray-500 dark:text-gray-400 mt-1">{{ item.percentage }}%</p>
        </div>
      </component>
    </div>

    <!-- Main Content Grid -->
    <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8">
      <!-- Left Column: Chart -->
      <div class="lg:col-span-2 flex flex-col min-h-0">
        <!-- Chart Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 h-full flex flex-col">
          <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
              <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Applications Overview</h3>
              <p class="text-gray-600 dark:text-gray-400 text-sm">Daily application trends</p>
            </div>
            <div class="relative">
              <button 
                @click="showDateFilter = !showDateFilter"
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm font-medium text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50"
              >
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Date Filter
                <svg class="w-4 h-4 ml-1 text-gray-400 transition-transform duration-200" :class="{'rotate-180': showDateFilter}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div 
                v-if="showDateFilter" 
                class="absolute right-0 mt-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xl z-10 w-72 origin-top-right transition-all"
              >
                <div class="flex justify-between items-center mb-4">
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Custom Range</h4>
                  <button @click="showDateFilter = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                  </button>
                </div>
                <div class="space-y-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Start Date</label>
                    <input 
                      type="date" 
                      v-model="startDateFilter"
                      class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#9E122C] focus:ring-[#9E122C] rounded-lg shadow-sm transition-colors"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">End Date</label>
                    <input 
                      type="date" 
                      v-model="endDateFilter"
                      class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#9E122C] focus:ring-[#9E122C] rounded-lg shadow-sm transition-colors"
                    />
                  </div>
                  <div class="pt-2">
                    <button 
                      @click="applyFilters(); showDateFilter = false;"
                      class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2.5 bg-[#9E122C] text-white text-sm font-semibold rounded-lg hover:bg-[#b51834] transition-all shadow-md active:scale-95"
                    >
                      Apply Filter
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="flex flex-wrap gap-4 mb-6">
            <div class="flex items-center space-x-2">
              <div class="w-3 h-3 rounded-full bg-[#10B981]"></div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Accepted</span>
            </div>
            <div class="flex items-center space-x-2">
              <div class="w-3 h-3 rounded-full bg-[#EAB308]"></div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Pending</span>
            </div>
            <div class="flex items-center space-x-2">
              <div class="w-3 h-3 rounded-full bg-[#EF4444]"></div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Returned</span>
            </div>
          </div>
          
          <div class="flex-1 min-h-0 w-full">
            <LineChart :chart-data="chartDataset" :options="chartOptions" class="w-full h-full" />
          </div>
        </div>
      </div>

      <!-- Right Column: Recent Applications -->
      <div class="h-full min-h-0">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 h-full flex flex-col">
          <div class="flex justify-between items-center mb-6">
            <div>
              <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Recent Applications</h3>
              <p class="text-gray-600 dark:text-gray-400 text-sm">Latest applicant submissions</p>
            </div>
            <Link href="/applications" 
                  class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition dark:text-white dark:hover:text-white">
              View All
            </Link>
          </div>
          
          <div class="space-y-3 flex-1 overflow-y-auto min-h-0">
            <div
              v-for="user in displayedUsers"
              :key="user.id"
              @click="selectUser(user)"
              class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer"
            >
              <div class="flex items-center justify-between mb-3 gap-2">
                <div class="flex items-center space-x-3 min-w-0">
                  <div class="w-10 h-10 bg-[#9E122C] rounded-full flex items-center justify-center text-white font-semibold shrink-0">
                    {{ user.firstname?.charAt(0) || '' }}{{ user.lastname?.charAt(0) || '' }}
                  </div>
                  <div class="min-w-0">
                    <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                      {{ user.firstname || user.email || '—' }} {{ user.lastname || '' }}
                    </h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm truncate">{{ user.email }}</p>
                  </div>
                </div>
                <span :class="getStatusClass(user.application?.status)" 
                      class="px-3 py-1 rounded-full text-xs font-semibold shrink-0">
                  {{ user.application?.status || "Unknown" }}
                </span>
              </div>
              
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-gray-500 dark:text-gray-400">Course</p>
                  <p class="text-gray-900 dark:text-white font-medium">{{ user.application?.program?.code || "—" }}</p>
                </div>
                <div>
                  <p class="text-gray-500 dark:text-gray-400">Applied</p>
                  <p class="text-gray-900 dark:text-white font-medium">{{ formatDate(user.application?.created_at) }}</p>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="displayedUsers.length === 0" class="text-center py-8">
              <svg class="w-12 h-12 text-gray-400 mx-auto mb-3 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
              </svg>
              <p class="text-gray-500 dark:text-gray-400">No applications found</p>
            </div>
          </div>

          <!-- Pagination -->
          <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Page {{ currentPage }} of {{ totalPages }}
            </p>
            <div class="flex items-center gap-2">
              <button
                @click="currentPage--"
                :disabled="currentPage === 1"
                class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
                aria-label="Previous page"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                @click="currentPage++"
                :disabled="currentPage === totalPages"
                class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
                aria-label="Next page"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    </div>

    <!-- User Detail Modal -->
    <UserDetailsModal
      :selected-user="selectedUser"
      :selected-user-files="selectedUserFiles"
      :current-user="null"
      :available-programs="[]"
      :is-changing-course="false"
      :course-change-message="''"
      :change-course-selected-id="''"
      @close="closeUserCard"
      @open-image="openImageModal"
    >
      <template #top-actions>
        <!-- Application Details -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
          <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Application Details</h4>
          <div class="space-y-2 text-sm">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
              <span :class="getStatusClass(selectedUser?.application?.status)" class="inline-block mt-0.5 px-2.5 py-1 rounded-full text-xs font-semibold">
                {{ selectedUser?.application?.status || 'Unknown' }}
              </span>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Applied On</p>
              <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(selectedUser?.application?.created_at) }}</p>
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <Link :href="`/applications/user/${selectedUser?.id}`"
              class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium text-sm">
              View Full Application
            </Link>
          </div>
        </div>
      </template>
    </UserDetailsModal>

    <!-- Image Preview Modal -->
    <div v-if="showImageModal"
      class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[60] p-4"
      @click.self="closeImageModal">
      <div class="relative max-w-4xl w-full">
        <img :src="previewImage" alt="Preview" class="w-full h-auto rounded-lg shadow-2xl" />
        <button @click="closeImageModal"
          class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-70 transition">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Snackbar -->
    <transition name="snackbar">
      <div v-if="snackbar.show"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[70] px-5 py-3 bg-gray-900 dark:bg-gray-700 text-white text-sm font-medium rounded-xl shadow-xl flex items-center gap-3">
        <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        {{ snackbar.message }}
      </div>
    </transition>
  </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.snackbar-enter-active,
.snackbar-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.snackbar-enter-from,
.snackbar-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(1rem);
}
</style>