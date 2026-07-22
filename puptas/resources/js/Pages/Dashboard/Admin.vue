<script setup>
import { ref, computed, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { LineChart } from "vue-chart-3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BlurText from "@/Components/BlurText.vue";
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

// Simplified summary items
const summaryItems = computed(() => [
  { 
    label: "Total Applications", 
    value: props.summary.total, 
    icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>' },
    percentage: 100
  },
  { 
    label: "Accepted", 
    value: props.summary.accepted, 
    icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
    percentage: props.summary.total > 0 ? Math.round((props.summary.accepted / props.summary.total) * 100) : 0
  },
  { 
    label: "Pending", 
    value: props.summary.pending, 
    icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' },
    percentage: props.summary.total > 0 ? Math.round((props.summary.pending / props.summary.total) * 100) : 0
  },
  { 
    label: "Returned", 
    value: props.summary.returned, 
    icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>' },
    percentage: props.summary.total > 0 ? Math.round((props.summary.returned / props.summary.total) * 100) : 0
  },
]);

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

const displayedUsers = computed(() => {
  const users = props.allUsers || [];
  const query = searchQuery.value.trim().toLowerCase();
  
  if (!query) return users.slice(0, 5);
  
  return users.filter(user => 
    `${user.firstname} ${user.lastname}`.toLowerCase().includes(query) ||
    user.email.toLowerCase().includes(query)
  );
});

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
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout>
    <!-- Header Section -->
    <div class="px-4 md:px-8 mb-8">
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 px-4 md:px-8 mb-8">
      <div
        v-for="(item, index) in summaryItems"
        :key="index"
        class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">{{ item.label }}</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ item.value.toLocaleString() }}</p>
          </div>
          <div :class="[
            'p-3 rounded-lg',
            index === 0 ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
            index === 1 ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300' :
            index === 2 ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300' :
            'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300'
          ]">
            <component :is="item.icon" class="w-6 h-6" />
          </div>
        </div>
        <div class="mt-4">
          <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div 
              :class="[
                'h-full rounded-full',
                index === 0 ? 'bg-blue-500' :
                index === 1 ? 'bg-green-500' :
                index === 2 ? 'bg-yellow-500' :
                'bg-red-500'
              ]"
              :style="{ width: item.percentage + '%' }"
            ></div>
          </div>
          <p class="text-right text-xs text-gray-500 dark:text-gray-400 mt-2">{{ item.percentage }}% of total</p>
        </div>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8">
      <!-- Left Column: Chart -->
      <div class="lg:col-span-2">
        <!-- Chart Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
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
          
          <div class="h-64 md:h-80 w-full">
            <LineChart :chart-data="chartDataset" :options="chartOptions" class="w-full h-full" />
          </div>
        </div>
      </div>

      <!-- Right Column: Recent Applications -->
      <div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
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
          
          <div class="space-y-3">
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
          </div>
        </div>
      </div>
    </div>

    <!-- User Detail Modal -->
    <transition name="fade">
      <div v-if="selectedUser" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closeUserCard"></div>

        <div class="relative min-h-screen flex items-start justify-center p-4 sm:p-6 overflow-y-auto">
          <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-7xl my-4 sm:my-8 flex flex-col max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-4rem)] overflow-hidden">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-lg font-bold shrink-0">
                  {{ (selectedUser.firstname || selectedUser.email || '?').charAt(0).toUpperCase() }}{{ (selectedUser.lastname || '').charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">
                    {{ [selectedUser.firstname, selectedUser.middlename, selectedUser.lastname].filter(Boolean).join(' ') }}
                  </h2>
                  <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    App #{{ selectedUser.application?.id || 'N/A' }} · {{ selectedUser.reference_number || 'No ref' }} · {{ selectedUser.email }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <span :class="getStatusClass(selectedUser.application?.status)" class="hidden sm:inline px-3 py-1 rounded-full text-xs font-semibold">
                  {{ selectedUser.application?.status || 'Unknown' }}
                </span>
                <button @click="closeUserCard"
                  class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition min-h-[44px] min-w-[44px]" aria-label="Close">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Modal Body: 2-column layout -->
            <div class="flex-1 overflow-hidden px-4 sm:px-6 py-5 flex flex-col">
              <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column: Info & Grades -->
                <div class="lg:col-span-7 space-y-5 overflow-y-auto pr-2 pb-4 min-h-0">

                  <!-- Personal & Educational Info -->
                  <div>
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Personal Information</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Full Name</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ [selectedUser.firstname, selectedUser.lastname].filter(Boolean).join(' ') || '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ selectedUser.email || '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Phone</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedUser.phone || '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Sex</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ selectedUser.sex || '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">School</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ selectedUser.school || '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Strand</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedUser.strand || '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">G12 1st Sem</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.grades?.g12_first_sem ?? '—' }}</p>
                      </div>
                      <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">G12 2nd Sem</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.grades?.g12_second_sem ?? '—' }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- Program Choices -->
                  <div>
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Program Choices</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <div class="p-3 rounded-xl border border-[#9E122C]/30 bg-[#9E122C]/5 dark:bg-[#9E122C]/10">
                        <div class="flex items-center gap-2 mb-2">
                          <span class="w-5 h-5 rounded-full bg-[#9E122C] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                          <p class="text-xs font-semibold text-[#9E122C] dark:text-red-400 uppercase tracking-wide">1st Choice</p>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedUser.application?.program?.name || "—" }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application?.program?.code || "" }}</p>
                      </div>
                      <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !selectedUser.application?.second_choice }">
                        <div class="flex items-center gap-2 mb-2">
                          <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                          <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">2nd Choice</p>
                        </div>
                        <template v-if="selectedUser.application?.second_choice">
                          <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedUser.application.second_choice.name }}</p>
                          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application.second_choice.code }}</p>
                        </template>
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Not specified</p>
                      </div>
                      <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !selectedUser.application?.third_choice }">
                        <div class="flex items-center gap-2 mb-2">
                          <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                          <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">3rd Choice</p>
                        </div>
                        <template v-if="selectedUser.application?.third_choice">
                          <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedUser.application.third_choice.name }}</p>
                          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application.third_choice.code }}</p>
                        </template>
                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Not specified</p>
                      </div>
                    </div>
                  </div>

                  <!-- Academic Grades -->
                  <div v-if="selectedUser?.grades">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Academic Grades</h4>
                    <div class="grid grid-cols-3 gap-3">
                      <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.mathematics ?? '—' }}</p>
                      </div>
                      <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.science ?? '—' }}</p>
                      </div>
                      <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.english ?? '—' }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- Application History -->
                  <div v-if="selectedUser?.application?.processes?.length">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Application History</h4>
                    <div class="space-y-2">
                      <div v-for="(process, index) in selectedUser.application.processes" :key="index"
                        class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <div :class="[
                          'w-2.5 h-2.5 rounded-full mt-1.5 shrink-0',
                          process.action === 'rejected' ? 'bg-red-500' :
                          process.status === 'completed' ? 'bg-green-500' :
                          process.status === 'returned' ? 'bg-red-500' : 'bg-yellow-500'
                        ]"></div>
                        <div class="flex-1 min-w-0">
                          <div class="flex justify-between items-start">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatStage(process.stage) }}</p>
                            <span :class="[
                              'px-2 py-0.5 rounded-full text-xs font-medium',
                              process.action === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' :
                              process.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' :
                              process.status === 'returned' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' :
                              'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
                            ]">{{ process.action === 'rejected' ? 'Rejected' : capitalize(process.status) }}</span>
                          </div>
                          <p v-if="process.reviewer_notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ process.reviewer_notes }}</p>
                          <p v-else-if="process.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ process.notes }}</p>
                          <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ formatDate(process.created_at) }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Right Column: Details & Documents -->
                <div class="lg:col-span-5 space-y-5 overflow-y-auto pr-2 pb-4 min-h-0">

                  <!-- Application Details -->
                  <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Application Details</h4>
                    <div class="space-y-2 text-sm">
                      <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                        <span :class="getStatusClass(selectedUser.application?.status)" class="inline-block mt-0.5 px-2.5 py-1 rounded-full text-xs font-semibold">
                          {{ selectedUser.application?.status || 'Unknown' }}
                        </span>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Applied On</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(selectedUser.application?.created_at) }}</p>
                      </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                      <Link :href="`/applications/user/${selectedUser.id}`"
                        class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium text-sm">
                        View Full Application
                      </Link>
                    </div>
                  </div>

                  <!-- Uploaded Documents -->
                  <div v-if="Object.keys(selectedUserFiles).length">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-2 gap-3">
                      <div v-for="(file, key) in selectedUserFiles" :key="key"
                        class="p-2 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center gap-1.5 mb-1.5 min-w-0">
                          <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate" :title="formatFileKey(key)">{{ formatFileKey(key) }}</span>
                        </div>
                        <img v-if="hasImagePreview(file)" :src="getFileUrl(file)" alt="Document"
                          class="w-full aspect-[4/3] object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                          @click="openImageModal(file)" />
                        <div v-else class="w-full aspect-[4/3] flex items-center justify-center text-xs text-gray-400 dark:text-gray-500 bg-gray-200 dark:bg-gray-700 rounded-lg">
                          No file
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>

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
</style>

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