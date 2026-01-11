<template>
  <Head title="Dashboard" />
  <AppLayout>
    <!-- Summary Cards Section -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 px-4 md:px-8">
      <div
        v-for="(item, index) in summaryItems"
        :key="index"
        class="bg-white dark:bg-gray-800 border-l-4 border-[#9E122C] rounded-xl p-6 shadow hover:shadow-lg transition transform hover:-translate-y-1 cursor-pointer"
      >
        <div class="flex items-center mb-4">
          <div class="p-3 bg-[#9E122C] rounded-full text-white mr-3 flex items-center justify-center">
            <component :is="item.icon" class="w-6 h-6" />
          </div>
          <p class="text-gray-700 dark:text-gray-200 font-medium">{{ item.label }}</p>
        </div>
        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ item.value }}</p>
      </div>
    </div>

    <!-- Charts + Table Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8 px-4 md:px-8">
      <!-- Line Chart -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-red-300">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Applications Overview
        </h3>
        <LineChart :chart-data="chartDataset" class="h-60 w-full" />
      </div>

      <!-- Recent Applications -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-red-300 overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Applications</h3>
          <Link href="/applications" class="text-sm text-[#9E122C] hover:underline hover:text-[#b51834] transition">
            See all
          </Link>
        </div>
        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-200">Last Name</th>
              <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-200">First Name</th>
              <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-200">Course</th>
              <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-200">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="user in displayedUsers"
              :key="user.id"
              @click="selectUser(user)"
              class="cursor-pointer hover:bg-[#FBCB77]/40 dark:hover:bg-gray-700 transition"
            >
              <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ user.lastname }}</td>
              <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ user.firstname }}</td>
              <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ user.program?.code || "—" }}</td>
              <td class="px-4 py-2">
                <span :class="getStatusClass(user.status)" class="px-2 py-1 rounded text-xs font-semibold">
                  {{ user.status || "Unknown" }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- User Info Panel -->
    <transition name="slide-fade">
      <div
        v-if="selectedUser"
        class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl overflow-y-auto"
      >
        <button
          class="mt-6 px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
          @click="closeUserCard"
        >
          Close
        </button>

        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">User Information</h3>
        <div class="space-y-2">
          <p class="text-gray-800 dark:text-gray-200 font-medium">Name: {{ selectedUser.lastname }}, {{ selectedUser.firstname }}</p>
          <p class="text-gray-700 dark:text-gray-400">Email: {{ selectedUser.email }}</p>
          <p class="text-gray-700 dark:text-gray-400">Phone: {{ selectedUser.phone || "—" }}</p>
        </div>
      </div>
    </transition>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { LineChart } from "vue-chart-3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Chart as ChartJS, LineController, LineElement, CategoryScale, LinearScale, PointElement, Tooltip, Title, Legend } from "chart.js";
ChartJS.register(LineController, LineElement, CategoryScale, LinearScale, PointElement, Tooltip, Title, Legend);

const props = defineProps({
  allUsers: Array,
  summary: {
    type: Object,
    default: () => ({ total: 0, accepted: 0, pending: 0, returned: 0 }),
  },
  chartData: {
    type: Object,
    default: () => ({ submitted: [], accepted: [], returned: [], years: [] }),
  },
});

const users = ref(props.allUsers || []);
const selectedUser = ref(null);
const searchQuery = ref("");

const summaryItems = [
  { label: "Total Applications", value: props.summary.total, icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" /></svg>' } },
  { label: "Accepted", value: props.summary.accepted, icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>' } },
  { label: "Pending", value: props.summary.pending, icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" /></svg>' } },
  { label: "Returned", value: props.summary.returned, icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>' } },
];

const chartDataset = computed(() => ({
  labels: props.chartData.years || [],
  datasets: [
    { label: "Submitted", data: props.chartData.submitted, borderColor: "#2563EB", backgroundColor: "rgba(37, 99, 235, 0.2)", tension: 0.4 },
    { label: "Accepted", data: props.chartData.accepted, borderColor: "#10B981", backgroundColor: "rgba(16, 185, 129, 0.2)", tension: 0.4 },
    { label: "Returned", data: props.chartData.returned, borderColor: "#F59E0B", backgroundColor: "rgba(245, 158, 11, 0.2)", tension: 0.4 },
  ],
}));

const getStatusClass = (status) => {
  const s = (status || "").toLowerCase();
  if (s === "accepted") return "bg-green-100 text-green-700";
  if (s === "pending") return "bg-yellow-100 text-yellow-700";
  if (s === "returned") return "bg-red-100 text-red-700";
  return "bg-gray-100 text-gray-600";
};

const displayedUsers = computed(() => {
  // Filter users with role 'applicant'
  const applicants = users.value.filter(u => u.role?.toLowerCase() === 'applicant');

  if (!searchQuery.value.trim()) return applicants.slice(0, 4);

  return applicants.filter(u =>
    `${u.firstname} ${u.lastname}`.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

const selectUser = (user) => {
  selectedUser.value = user;
};
const closeUserCard = () => {
  selectedUser.value = null;
};
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
