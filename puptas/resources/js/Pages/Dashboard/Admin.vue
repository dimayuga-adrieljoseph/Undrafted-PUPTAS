<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { LineChart } from "vue-chart-3";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from "axios";
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
});

const selectedUser = ref(null);
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
      label: "Submitted", 
      data: props.chartData.submitted || [], 
      borderColor: "#2563EB",
      backgroundColor: "rgba(37, 99, 235, 0.1)",
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#2563EB",
      pointBorderColor: "#ffffff",
      pointBorderWidth: 2,
      pointRadius: 4,
    },
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
      label: "Returned", 
      data: props.chartData.returned || [], 
      borderColor: "#F59E0B",
      backgroundColor: "rgba(245, 158, 11, 0.1)",
      fill: true,
      tension: 0.4,
      pointBackgroundColor: "#F59E0B",
      pointBorderColor: "#ffffff",
      pointBorderWidth: 2,
      pointRadius: 4,
    },
  ],
}));

const getStatusClass = (status) => {
  const s = (status || "").toLowerCase();
  if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
  if (s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
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
  return new Date(dateString).toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
};

const selectUser = (user) => {
  selectedUser.value = user;
};

const closeUserCard = () => {
  selectedUser.value = null;
};

// ============================
// Special Cases Tab
// ============================
const activeTab = ref('dashboard');
const specialCaseApplicants = ref([]);
const specialCaseLoading = ref(false);
const approvalReason = ref('');
const processingId = ref(null);

const fetchSpecialCases = async () => {
  specialCaseLoading.value = true;
  try {
    const res = await axios.get('/admin/special-cases');
    specialCaseApplicants.value = res.data;
  } finally {
    specialCaseLoading.value = false;
  }
};

const approveApplicant = async (profileId) => {
  processingId.value = profileId;
  try {
    await axios.post(`/admin/special-cases/${profileId}/approve`, { reason: approvalReason.value || null });
    approvalReason.value = '';
    await fetchSpecialCases();
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to approve.');
  } finally {
    processingId.value = null;
  }
};

const rejectApplicant = async (profileId) => {
  if (!confirm('Are you sure you want to reject this applicant?')) return;
  processingId.value = profileId;
  try {
    await axios.post(`/admin/special-cases/${profileId}/reject`);
    await fetchSpecialCases();
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to reject.');
  } finally {
    processingId.value = null;
  }
};

onMounted(() => {
  fetchSpecialCases();
});
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout>
    <!-- Header Section -->
    <div class="px-4 md:px-8 mb-8">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Admissions Dashboard</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-2">Welcome back! Here's an overview of your application data.</p>
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="px-4 md:px-8 mb-6">
      <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl w-fit">
        <button
          @click="activeTab = 'dashboard'"
          :class="activeTab === 'dashboard' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'"
          class="px-5 py-2 rounded-lg text-sm font-medium transition-all duration-200"
        >
          Dashboard
        </button>
        <button
          @click="activeTab = 'special-cases'; fetchSpecialCases()"
          :class="activeTab === 'special-cases' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'"
          class="px-5 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center gap-2"
        >
          Special Cases
          <span v-if="specialCaseApplicants.length > 0" class="bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
            {{ specialCaseApplicants.length }}
          </span>
        </button>
      </div>
    </div>

    <!-- Dashboard Tab -->
    <template v-if="activeTab === 'dashboard'">
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
          <div class="mb-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">Applications Overview</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">Daily application trends (Last 30 days)</p>
          </div>
          
          <div class="flex flex-wrap gap-4 mb-6">
            <div class="flex items-center space-x-2">
              <div class="w-3 h-3 rounded-full bg-[#2563EB]"></div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Submitted</span>
            </div>
            <div class="flex items-center space-x-2">
              <div class="w-3 h-3 rounded-full bg-[#10B981]"></div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Accepted</span>
            </div>
            <div class="flex items-center space-x-2">
              <div class="w-3 h-3 rounded-full bg-[#F59E0B]"></div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Returned</span>
            </div>
          </div>
          
          <div class="h-80">
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
                  class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition">
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
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-[#9E122C] rounded-full flex items-center justify-center text-white font-semibold">
                    {{ user.firstname[0] }}{{ user.lastname[0] }}
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">
                      {{ user.firstname }} {{ user.lastname }}
                    </h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">{{ user.email }}</p>
                  </div>
                </div>
                <span :class="getStatusClass(user.application?.status)" 
                      class="px-3 py-1 rounded-full text-xs font-semibold">
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

    </template>
    <!-- End Dashboard Tab -->

    <!-- Special Cases Tab -->
    <div v-if="activeTab === 'special-cases'" class="px-4 md:px-8">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Special Case Review</h3>
          <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Applicants who logged in via IDP but did not pass PUPCET. Review and approve or reject each case manually.</p>
        </div>

        <div v-if="specialCaseLoading" class="p-12 text-center text-gray-500 dark:text-gray-400">
          Loading...
        </div>

        <div v-else-if="specialCaseApplicants.length === 0" class="p-12 text-center">
          <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-gray-500 dark:text-gray-400 font-medium">No pending special case applicants.</p>
        </div>

        <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
          <div
            v-for="applicant in specialCaseApplicants"
            :key="applicant.profile_id"
            class="p-6 flex flex-col md:flex-row md:items-center gap-4"
          >
            <!-- Applicant Info -->
            <div class="flex items-center gap-4 flex-1">
              <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/40 rounded-full flex items-center justify-center text-amber-700 dark:text-amber-300 font-bold text-lg">
                {{ (applicant.firstname?.[0] || '?') }}{{ (applicant.lastname?.[0] || '') }}
              </div>
              <div>
                <p class="font-semibold text-gray-900 dark:text-white">{{ applicant.firstname }} {{ applicant.lastname }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ applicant.email }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Registered via IDP &bull; {{ new Date(applicant.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</p>
              </div>
            </div>

            <!-- Status Badge -->
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 self-start md:self-center">
              {{ applicant.status }}
            </span>

            <!-- Reason input + Actions -->
            <div class="flex flex-col md:flex-row gap-2 items-start md:items-center">
              <input
                v-model="approvalReason"
                type="text"
                placeholder="Reason (optional)"
                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] w-48"
              />
              <button
                @click="approveApplicant(applicant.profile_id)"
                :disabled="processingId === applicant.profile_id"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition"
              >
                Approve
              </button>
              <button
                @click="rejectApplicant(applicant.profile_id)"
                :disabled="processingId === applicant.profile_id"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- End Special Cases Tab -->
    <transition name="fade">
      <div v-if="selectedUser" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/50" @click="closeUserCard"></div>
        
        <div class="relative min-h-screen flex items-center justify-center p-4">
          <div class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white">Applicant Details</h3>
                  <p class="text-gray-600 dark:text-gray-400">Application ID: {{ selectedUser.application?.id || 'N/A' }}</p>
                </div>
                <button @click="closeUserCard" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition">
                  <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Modal Content -->
            <div class="p-6 overflow-y-auto max-h-[60vh]">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Info -->
                <div class="space-y-4">
                  <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Personal Information</h4>
                  <div class="space-y-3">
                    <div>
                      <label class="text-sm text-gray-500 dark:text-gray-400">Full Name</label>
                      <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.firstname }} {{ selectedUser.lastname }}</p>
                    </div>
                    <div>
                      <label class="text-sm text-gray-500 dark:text-gray-400">Email Address</label>
                      <p class="text-gray-900 dark:text-white">{{ selectedUser.email }}</p>
                    </div>
                    <div>
                      <label class="text-sm text-gray-500 dark:text-gray-400">Phone Number</label>
                      <p class="text-gray-900 dark:text-white">{{ selectedUser.phone || "—" }}</p>
                    </div>
                  </div>
                </div>

                <!-- Application Info -->
                <div class="space-y-4">
                  <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Application Details</h4>
                  <div class="space-y-3">
                    <div>
                      <label class="text-sm text-gray-500 dark:text-gray-400">Course/Program</label>
                      <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.program?.name || "—" }}</p>
                      <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.program?.code || "" }}</p>
                    </div>
                    <div>
                      <label class="text-sm text-gray-500 dark:text-gray-400">Status</label>
                      <span :class="getStatusClass(selectedUser.application?.status)" 
                            class="px-3 py-1 rounded-full text-sm font-semibold">
                        {{ selectedUser.application?.status || "Unknown" }}
                      </span>
                    </div>
                    <div>
                      <label class="text-sm text-gray-500 dark:text-gray-400">Applied On</label>
                      <p class="text-gray-900 dark:text-white">{{ formatDate(selectedUser.application?.created_at) }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                <button @click="closeUserCard" 
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                  Close
                </button>
                <Link :href="`/applications/user/${selectedUser.id}`"
                      class="px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium">
                  View Full Application
                </Link>
              </div>
            </div>
          </div>
        </div>
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
</style>